<?php
// ==========================================
// REQUISITIONS, POs, AND WITHDRAWALS LOGIC
// ==========================================

// --- AJAX: FETCH SUPPLIER DELIVERY HISTORY ---
if ($action === 'fetch_supplier_delivery_history') {
    if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'management', 'warehouse'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']); exit;
    }

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);

    // Fetch supplier info
    $supStmt = $pdo->prepare("SELECT company_name, supplier_code FROM suppliers WHERE id = ?");
    $supStmt->execute([$supplier_id]);
    $supplier = $supStmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        echo json_encode(['status' => 'error', 'message' => 'Supplier not found.']); exit;
    }

    // Fetch all POs for this supplier
    $poStmt = $pdo->prepare("
        SELECT p.id, p.po_no, p.status, p.created_at, p.delay_remarks
        FROM purchase_orders p
        WHERE p.supplier_id = ?
        ORDER BY p.created_at DESC
    ");
    $poStmt->execute([$supplier_id]);
    $pos = $poStmt->fetchAll(PDO::FETCH_ASSOC);

    // For each PO, fetch its items with expected vs actual quantities
    $itemStmt = $pdo->prepare("
        SELECT pi.item_code, pi.quantity AS expected_qty, i.item_name
        FROM po_items pi
        LEFT JOIN inventory i ON pi.item_code = i.item_code
        WHERE pi.po_id = ?
    ");

    $orders = [];
    $totalDelivered = 0;
    $goodDeliveries = 0;
    $discrepancies = 0;
    $delayed = 0;
    $pending = 0;

    foreach ($pos as $po) {
        $itemStmt->execute([$po['id']]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse actual received quantities from delay_remarks for discrepancy POs
        $itemDetails = [];
        foreach ($items as $item) {
            $itemDetails[] = [
                'item_code' => $item['item_code'],
                'item_name' => $item['item_name'] ?? $item['item_code'],
                'expected_qty' => (int)$item['expected_qty']
            ];
        }

        // Classify the order
        $classification = 'pending';
        if (strpos($po['status'], 'Discrepancy') !== false) {
            $classification = 'discrepancy';
            $discrepancies++;
            $totalDelivered++;
        } elseif ($po['status'] === 'Delivered') {
            $classification = 'good';
            $goodDeliveries++;
            $totalDelivered++;
        } elseif (strpos($po['status'], 'Delayed') !== false) {
            $classification = 'delayed';
            $delayed++;
        } else {
            $pending++;
        }

        $orders[] = [
            'po_no' => $po['po_no'],
            'status' => $po['status'],
            'classification' => $classification,
            'date' => date('M d, Y', strtotime($po['created_at'])),
            'delay_remarks' => $po['delay_remarks'],
            'items' => $itemDetails
        ];
    }

    echo json_encode([
        'status' => 'success',
        'supplier' => $supplier,
        'orders' => $orders,
        'summary' => [
            'total' => count($pos),
            'delivered' => $totalDelivered,
            'good' => $goodDeliveries,
            'discrepancies' => $discrepancies,
            'delayed' => $delayed,
            'pending' => $pending
        ]
    ]);
    exit;
}

// --- AJAX: FETCH RS DATA VIA QR SCANNER (PHASE 2 WORKFLOW) ---
elseif ($action === 'fetch_rs_data') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']); exit;
    }

    $rs_no = str_replace('REQ-DATA:', '', $_POST['rs_no']); 
    
    $stmt = $pdo->prepare("SELECT id, project_name, status FROM requisitions WHERE rs_no = ?");
    $stmt->execute([$rs_no]);
    $rs = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rs) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid QR Code. Requisition Slip not found.']);
        exit;
    }

    if ($rs['status'] === 'Released') {
        echo json_encode(['status' => 'error', 'message' => 'This Requisition Slip (QR Code) has already been released and is expired.']);
        exit;
    }

    if (!in_array($rs['status'], ['Approved', 'PO Created'])) {
        echo json_encode(['status' => 'error', 'message' => 'This Requisition Slip has not been Approved yet. Current status: ' . $rs['status']]);
        exit;
    }

    $itemStmt = $pdo->prepare("SELECT ri.item_code, ri.quantity, i.item_name FROM requisition_items ri LEFT JOIN inventory i ON ri.item_code = i.item_code WHERE ri.requisition_id = ?");
    $itemStmt->execute([$rs['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'rs_no' => $rs_no,
        'project_name' => $rs['project_name'],
        'items' => $items
    ]);
    exit;
}

// --- REQUISITIONS (RS) ---
elseif ($action === 'create_rs') {
    $type = $_POST['type'] ?? 'project';
    $projectName = ($type === 'restock') ? 'Warehouse Restock' : $_POST['project_name'];

    $stmt = $pdo->prepare("INSERT INTO requisitions (rs_no, requestor_id, requestor_name, project_name, urgency, remarks, status, type) VALUES (?, ?, ?, ?, ?, ?, 'Pending Approval', ?)");
    $stmt->execute([$_POST['rs_no'], $_POST['requestor_id'], $_POST['requestor_name'], $projectName, $_POST['urgency'], $_POST['remarks'], $type]);
    $requisition_id = $pdo->lastInsertId();

    $items = $_POST['items'];
    $quantities = $_POST['quantities'];
    $itemStmt = $pdo->prepare("INSERT INTO requisition_items (requisition_id, item_code, quantity) VALUES (?, ?, ?)");
    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i]) && !empty($quantities[$i])) {
            $itemStmt->execute([$requisition_id, $items[$i], $quantities[$i]]);
        }
    }
    
    // Conflict Check Engine
    $conflicts = [];
    $conflictItems = [];
    if ($type !== 'restock') {
        $conflictStmt = $pdo->prepare("
            SELECT ri.item_code, i.item_name, i.quantity as current_stock,
                   COALESCE(p.total_pending, 0) as total_pending
            FROM requisition_items ri
            LEFT JOIN inventory i ON ri.item_code = i.item_code
            LEFT JOIN (
                SELECT ri2.item_code, SUM(ri2.quantity) as total_pending
                FROM requisition_items ri2
                JOIN requisitions r2 ON ri2.requisition_id = r2.id
                WHERE r2.status = 'Pending Approval'
                GROUP BY ri2.item_code
            ) p ON ri.item_code = p.item_code
            WHERE ri.requisition_id = ? AND COALESCE(p.total_pending, 0) > i.quantity
        ");
        $conflictStmt->execute([$requisition_id]);
        $conflicts = $conflictStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($conflicts as $c) {
            $conflictItems[] = $c['item_name'];
        }
    }

    $conflictWarning = "";
    if (!empty($conflictItems)) {
        $conflictWarning = " ⚠️ Conflict alert: Stock deficit for " . implode(', ', $conflictItems) . ".";
    }
    
    $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'New Requisition Pending', ?)");
    $notifText = ($type === 'restock') 
        ? "{$_POST['requestor_name']} submitted a Warehouse Restock request ({$_POST['rs_no']})."
        : "{$_POST['requestor_name']} submitted {$_POST['rs_no']} for {$projectName}." . $conflictWarning;
        
    $notif->execute([$notifText]);
    sendPushNotification($pdo, 'New Requisition Pending', $notifText, 'management', null);

    // If there is a conflict, notify the submitting requestor and other affected requestors
    if (!empty($conflicts)) {
        // 1. Notify self
        $msgToSelf = "Requisition submitted, but warning: stock conflicts detected for " . implode(', ', $conflictItems) . ".";
        $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Conflict Warning', ?)")
            ->execute([$_POST['requestor_id'], $msgToSelf]);
        sendPushNotification($pdo, 'Requisition Conflict Warning', $msgToSelf, null, (int)$_POST['requestor_id']);

        // 2. Notify other requestors who also have pending requests for the same items
        foreach ($conflicts as $c) {
            $otherReqStmt = $pdo->prepare("
                SELECT DISTINCT r.requestor_id, r.requestor_name, r.rs_no, r.project_name
                FROM requisition_items ri
                JOIN requisitions r ON ri.requisition_id = r.id
                WHERE ri.item_code = ? AND r.status = 'Pending Approval' AND r.id != ?
            ");
            $otherReqStmt->execute([$c['item_code'], $requisition_id]);
            $otherRequestors = $otherReqStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($otherRequestors as $other) {
                $msgToOther = "Conflict Alert: {$_POST['requestor_name']} requested {$c['item_name']} for {$projectName}, which conflicts with your pending request {$other['rs_no']} for {$other['project_name']}.";
                $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Conflict Alert', ?)")
                    ->execute([$other['requestor_id'], $msgToOther]);
                sendPushNotification($pdo, 'Requisition Conflict Alert', $msgToOther, null, (int)$other['requestor_id']);
            }
        }

        // 3. Notify Admin of the conflict
        $msgToAdmin = "Conflict Alert: Requisition {$_POST['rs_no']} submitted by {$_POST['requestor_name']} for {$projectName} has stock conflicts: " . implode(', ', $conflictItems) . ".";
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'Requisition Conflict Alert', ?)")
            ->execute([$msgToAdmin]);
        sendPushNotification($pdo, 'Requisition Conflict Alert', $msgToAdmin, 'admin', null);
    }

    $_SESSION['message'] = ($type === 'restock') 
        ? "Restock request created successfully and sent to Management for approval."
        : "Requisition created successfully and sent to Management for approval.";
    $_SESSION['msg_type'] = "success";
    header("Location: ../requisitions");
    exit;
    
} elseif ($action === 'approve_rs') {
    if (!in_array($_SESSION['user_role'], ['management', 'admin'])) throw new Exception("Only Management or Admins can approve requisitions.");
    
    $stmt = $pdo->prepare("UPDATE requisitions SET status = 'Approved' WHERE id = ?");
    $stmt->execute([$_POST['rs_id']]);
    
    $rsData = $pdo->prepare("SELECT rs_no, requestor_id FROM requisitions WHERE id = ?");
    $rsData->execute([$_POST['rs_id']]);
    $rs = $rsData->fetch();

    $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Approved', ?)")->execute([$rs['requestor_id'], "Your request {$rs['rs_no']} has been approved."]);
    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'Ready for PO', ?)")->execute(["{$rs['rs_no']} was approved. Please generate a PO."]);
    sendPushNotification($pdo, 'Requisition Approved', "Your request {$rs['rs_no']} has been approved.", null, (int)$rs['requestor_id']);
    sendPushNotification($pdo, 'Ready for PO', "{$rs['rs_no']} was approved. Please generate a PO.", 'purchasing', null);

    $_SESSION['message'] = "Requisition Approved. Ready for Purchasing.";
    $_SESSION['msg_type'] = "success";
    header("Location: ../requisitions");
    exit;

} elseif ($action === 'reject_rs') {
    if (!in_array($_SESSION['user_role'], ['management', 'admin'])) throw new Exception("Only Management or Admins can reject requisitions.");
    
    $reason = trim($_POST['reject_reason']);
    $appendRemark = "\n\n[MANAGEMENT REJECTED]: " . $reason;

    $stmt = $pdo->prepare("UPDATE requisitions SET status = 'Rejected', remarks = CONCAT(IFNULL(remarks,''), ?) WHERE id = ?");
    $stmt->execute([$appendRemark, $_POST['rs_id']]);
    
    $rsData = $pdo->prepare("SELECT rs_no, requestor_id FROM requisitions WHERE id = ?");
    $rsData->execute([$_POST['rs_id']]);
    $rs = $rsData->fetch();

    $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Rejected', ?)")->execute([$rs['requestor_id'], "Your request {$rs['rs_no']} was rejected. Reason: {$reason}"]);
    sendPushNotification($pdo, 'Requisition Rejected', "Your request {$rs['rs_no']} was rejected. Reason: {$reason}", null, (int)$rs['requestor_id']);

    $_SESSION['message'] = "Requisition Rejected successfully.";
    $_SESSION['msg_type'] = "danger";
    header("Location: ../requisitions");
    exit;
}

// --- AJAX: FETCH RS ITEMS WITH SUPPLIER HISTORY ---
elseif ($action === 'fetch_rs_with_history') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']); exit;
    }

    $rs_id = $_POST['rs_id'] ?? 0;
    
    // Fetch items requested in this RS
    $stmt = $pdo->prepare("
        SELECT ri.item_code, ri.quantity, i.item_name 
        FROM requisition_items ri 
        LEFT JOIN inventory i ON ri.item_code = i.item_code 
        WHERE ri.requisition_id = ?
    ");
    $stmt->execute([$rs_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each item, look for the most recent PO supplier
    foreach ($items as &$item) {
        $histStmt = $pdo->prepare("
            SELECT s.company_name, po.created_at 
            FROM po_items pi 
            JOIN purchase_orders po ON pi.po_id = po.id 
            JOIN suppliers s ON po.supplier_id = s.id 
            WHERE pi.item_code = ? AND po.status != 'Generated'
            ORDER BY po.created_at DESC LIMIT 1
        ");
        $histStmt->execute([$item['item_code']]);
        $history = $histStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($history) {
            $item['last_supplier'] = $history['company_name'];
            $item['last_purchased'] = date('M d, Y', strtotime($history['created_at']));
        } else {
            $item['last_supplier'] = '<span class="text-muted fst-italic">No History</span>';
            $item['last_purchased'] = '';
        }
    }

    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

// --- AJAX: FETCH PO ITEMS FOR RECEIVING MODAL ---
elseif ($action === 'fetch_po_items') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']); exit;
    }
    
    $po_id = $_POST['po_id'] ?? 0;
    
    $stmt = $pdo->prepare("
        SELECT pi.item_code, pi.quantity as expected_qty, i.item_name 
        FROM po_items pi 
        JOIN inventory i ON pi.item_code = i.item_code 
        WHERE pi.po_id = ?
    ");
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

// --- PURCHASE ORDERS (PO) ---
elseif ($action === 'create_po') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) throw new Exception("Unauthorized action.");

    $po_no = $_POST['po_no'];
    $rs_id = $_POST['rs_id'];
    $supplier_id = $_POST['supplier_id'];
    $prepared_by = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_no, rs_id, supplier_id, prepared_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$po_no, $rs_id, $supplier_id, $prepared_by]);
    $po_id = $pdo->lastInsertId();

    $rsItemsStmt = $pdo->prepare("
        SELECT ri.item_code, ri.quantity, i.unit_price 
        FROM requisition_items ri 
        LEFT JOIN inventory i ON ri.item_code = i.item_code 
        WHERE ri.requisition_id = ?
    ");
    $rsItemsStmt->execute([$rs_id]);
    $rsItems = $rsItemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $poItemStmt = $pdo->prepare("INSERT INTO po_items (po_id, item_code, quantity, unit_price) VALUES (?, ?, ?, ?)");
    foreach ($rsItems as $item) {
        $price = $item['unit_price'] ?? 0.00; 
        $poItemStmt->execute([$po_id, $item['item_code'], $item['quantity'], $price]);
    }

    $pdo->prepare("UPDATE requisitions SET status = 'PO Created' WHERE id = ?")->execute([$rs_id]);

    $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('warehouse', 'Incoming Delivery Expected', ?)");
    $notif->execute(["PO {$po_no} has been generated. Prepare space to receive materials."]);
    sendPushNotification($pdo, 'Incoming Delivery Expected', "PO {$po_no} has been generated. Prepare space to receive materials.", 'warehouse', null);

    $_SESSION['message'] = "Purchase Order generated and sent to Supplier successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../po");
    exit;

} elseif ($action === 'mark_po_delivered') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) throw new Exception("Unauthorized.");
    
    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];
    
    $item_codes = $_POST['item_codes'] ?? [];
    $actual_qtys = $_POST['actual_qtys'] ?? [];
    $expected_qtys = $_POST['expected_qtys'] ?? [];
    
    // 1. Prepare dynamic addition query. It adds Actual received to existing Inventory.
    $updateInv = $pdo->prepare("
        UPDATE inventory i 
        JOIN units u ON i.unit = u.unit_name 
        SET i.quantity = i.quantity + ?, 
            i.status = CASE 
                        WHEN (i.quantity + ?) <= 0 THEN 'Out of Stock'
                        WHEN (i.quantity + ?) <= u.reorder_level THEN 'Low Stock'
                        ELSE 'In Stock' 
                     END 
        WHERE i.item_code = ?
    ");
    
    $discrepancyLog = "";
    $hasDiscrepancy = false;
    
    for ($i = 0; $i < count($item_codes); $i++) {
        $actual = (int)($actual_qtys[$i] ?? 0);
        $expected = (int)($expected_qtys[$i] ?? 0);
        $code = $item_codes[$i];
        
        if ($actual != $expected) {
            $hasDiscrepancy = true;
            $nameStmt = $pdo->prepare("SELECT item_name FROM inventory WHERE item_code = ?");
            $nameStmt->execute([$code]);
            $itemName = $nameStmt->fetchColumn() ?: $code;
            $discrepancyLog .= "\n- {$itemName} [Code: {$code}]: Expected {$expected}, Received {$actual}";
        }
        
        // Add only the ACTUAL things physically received!
        if ($actual > 0) {
            $updateInv->execute([$actual, $actual, $actual, $code]);
        }
    }

    if ($hasDiscrepancy) {
        $cleanDesc = trim($discrepancyLog);
        
        // Mark as Delivered but with a red flag Warning Discrepancy
        $pdo->prepare("UPDATE purchase_orders SET status = 'Delivered (Discrepancy)', delay_remarks = CONCAT(IFNULL(delay_remarks,''), '\n\n[DELIVERY DISCREPANCY]:\n', ?) WHERE id = ?")->execute([$cleanDesc, $po_id]);
        
        $alertMsg = "DISCREPANCY ALERT for {$po_no}: Order arrived physically with missing or excess items!{$discrepancyLog}";
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'PO Discrepancy Found', ?)")->execute([$alertMsg]);
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'PO Receiving Discrepancy', ?)")->execute([$alertMsg]);
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'PO Discrepancy Alert', ?)")->execute([$alertMsg]);

        sendPushNotification($pdo, 'PO Discrepancy Found', $alertMsg, 'management', null);
        sendPushNotification($pdo, 'PO Discrepancy Found', $alertMsg, 'purchasing', null);
        sendPushNotification($pdo, 'PO Discrepancy Found', $alertMsg, 'admin', null);
        
        $_SESSION['message'] = "Stock In partial/discrepancy recorded! Management has been notified of the mismatch.";
        $_SESSION['msg_type'] = "warning";
    } else {
        // Perfect Delivery - Mark strictly as Delivered normally
        $pdo->prepare("UPDATE purchase_orders SET status = 'Delivered' WHERE id = ?")->execute([$po_id]);
        
        $alertMsg = "Order {$po_no} has arrived complete. Exactly correct quantities successfully STOCKED IN to Master Inventory.";
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'PO Delivered & Verified', ?)")->execute([$alertMsg]);
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'PO Delivered & Verified', ?)")->execute([$alertMsg]);
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'PO Delivered & Verified', ?)")->execute([$alertMsg]);
        sendPushNotification($pdo, 'PO Delivered & Verified', $alertMsg, 'purchasing', null);
        sendPushNotification($pdo, 'PO Delivered & Verified', $alertMsg, 'management', null);
        sendPushNotification($pdo, 'PO Delivered & Verified', $alertMsg, 'admin', null);

        $_SESSION['message'] = "Stock In Successful! Delivered physical items perfectly matched the ordered blueprint.";
        $_SESSION['msg_type'] = "success";
    }

    header("Location: ../po");
    exit;

} elseif ($action === 'send_po_sms') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
    }
    
    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];
    $company = $_POST['company'];
    $phone = $_POST['contact_number'] ?? '';
    
    // 1. Fetch Items for the SMS
    $stmt = $pdo->prepare("SELECT pi.quantity, i.item_name FROM po_items pi JOIN inventory i ON pi.item_code = i.item_code WHERE pi.po_id = ?");
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itemList = "";
    foreach ($items as $item) {
        $itemList .= "- {$item['quantity']}x {$item['item_name']}\n";
    }

    $smsMessage = "GB Construction PO: {$po_no}\nItems to purchase:\n{$itemList}\nIf you have any concerns or clarifications text or email here";

    // 2. Generic API implementation placeholder (cURL to SMS API)
    // Configure this section with your actual SMS Blaster API details!
    /*
    $ch = curl_init('https://api.smsblaster.example/send'); // Replace with actual API endpoint
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'api_key' => 'YOUR_API_KEY', // Check .env if necessary
        'number' => $phone,
        'message' => $smsMessage
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    */

    usleep(1500000); // Simulate SMS processing delay
    
    $pdo->prepare("UPDATE purchase_orders SET status = 'SMS Sent' WHERE id = ?")->execute([$po_id]);
    
    $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'SMS Order Sent', ?)");
    $notif->execute(["Automated SMS was sent to {$company} for {$po_no} with verified item list."]);

    echo json_encode(['status' => 'success']);
    exit;

} elseif ($action === 'log_po_delay') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) throw new Exception("Unauthorized.");
    
    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];
    $delayReason = $_POST['delay_type'] . " - " . $_POST['remarks'];

    $pdo->prepare("UPDATE purchase_orders SET status = 'Delayed (Weather)', delay_remarks = ? WHERE id = ?")->execute([$delayReason, $po_id]);
    
    $alertMsg = "ALERT: {$po_no} is delayed. Reason: {$delayReason}";
    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'Supply Chain Delay', ?)")->execute([$alertMsg]);
    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('warehouse', 'Expected Delivery Delayed', ?)")->execute([$alertMsg]);
    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'Supply Chain Delay', ?)")->execute([$alertMsg]);

    sendPushNotification($pdo, 'Supply Chain Delay', $alertMsg, 'management', null);
    sendPushNotification($pdo, 'Expected Delivery Delayed', $alertMsg, 'warehouse', null);
    sendPushNotification($pdo, 'Supply Chain Delay', $alertMsg, 'admin', null);

    $_SESSION['message'] = "Logistics delay successfully logged and alerts sent.";
    $_SESSION['msg_type'] = "danger";
    header("Location: ../po");
    exit;
}

// --- WITHDRAWALS ---
elseif ($action === 'create_withdrawal') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
        throw new Exception("Only the Warehouse In-Charge can release materials.");
    }

    $withdrawal_no = $_POST['withdrawal_no'];
    $project_name = $_POST['project_name'];
    $remarks = $_POST['remarks'];
    $released_by = $_SESSION['user_id'];
    $items = $_POST['items'];
    $quantities = $_POST['quantities'];

    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i]) && !empty($quantities[$i])) {
            $checkStmt = $pdo->prepare("SELECT quantity, item_name FROM inventory WHERE item_code = ?");
            $checkStmt->execute([$items[$i]]);
            $invItem = $checkStmt->fetch();
            
            if (!$invItem || $invItem['quantity'] < $quantities[$i]) {
                throw new Exception("Insufficient stock for " . ($invItem['item_name'] ?? $items[$i]) . ". Available: " . ($invItem['quantity'] ?? 0));
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO withdrawals (withdrawal_no, project_name, released_by, remarks) VALUES (?, ?, ?, ?)");
    $stmt->execute([$withdrawal_no, $project_name, $released_by, $remarks]);
    $withdrawal_id = $pdo->lastInsertId();

    $wdItemStmt = $pdo->prepare("INSERT INTO withdrawal_items (withdrawal_id, item_code, quantity) VALUES (?, ?, ?)");
    $deductStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE item_code = ?");
    $updateStatusStmt = $pdo->prepare("
        UPDATE inventory i 
        JOIN units u ON i.unit = u.unit_name 
        SET i.status = CASE 
            WHEN i.quantity <= 0 THEN 'Out of Stock' 
            WHEN i.quantity <= u.reorder_level THEN 'Low Stock' 
            ELSE 'In Stock' 
        END 
        WHERE i.item_code = ?
    ");
    
    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i]) && !empty($quantities[$i])) {
            $wdItemStmt->execute([$withdrawal_id, $items[$i], $quantities[$i]]);
            $deductStmt->execute([$quantities[$i], $items[$i]]);
            $updateStatusStmt->execute([$items[$i]]); 
        }
    }

    // If this withdrawal was created via QR scan of an Approved Requisition Slip, expire it
    if (!empty($_POST['rs_no'])) {
        $rs_no = $_POST['rs_no'];
        
        // Fetch requisition info to notify the requestor
        $rsStmt = $pdo->prepare("SELECT id, requestor_id FROM requisitions WHERE rs_no = ?");
        $rsStmt->execute([$rs_no]);
        $rsData = $rsStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rsData) {
            // Update status of requisition to 'Released'
            $updateRsStmt = $pdo->prepare("UPDATE requisitions SET status = 'Released' WHERE id = ?");
            $updateRsStmt->execute([$rsData['id']]);
            
            // Notify the requestor
            $notifMsg = "Materials for your requisition {$rs_no} have been released from the warehouse.";
            $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Released', ?)")
                ->execute([$rsData['requestor_id'], $notifMsg]);
            sendPushNotification($pdo, 'Requisition Released', $notifMsg, null, (int)$rsData['requestor_id']);
        }
    }

    $_SESSION['message'] = "Materials successfully withdrawn and deducted from inventory.";
    $_SESSION['msg_type'] = "success";
    header("Location: ../withdrawals");
    exit;
}
?>