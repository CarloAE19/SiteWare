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

    $input_raw = trim($_POST['rs_no']);
    $rs_no_clean = str_replace(['REQ-DATA:', ' ', '-'], '', strtoupper($input_raw));
    if (!str_starts_with($rs_no_clean, 'RS') && !empty($rs_no_clean)) {
        $rs_no_clean = 'RS' . $rs_no_clean;
    }
    
    $stmt = $pdo->prepare("SELECT id, rs_no, project_name, status, type FROM requisitions WHERE REPLACE(REPLACE(UPPER(rs_no), '-', ''), ' ', '') = ? OR UPPER(rs_no) = ?");
    $stmt->execute([$rs_no_clean, strtoupper($input_raw)]);
    $rs = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rs) {
        echo json_encode(['status' => 'error', 'message' => 'Requisition Slip not found.']);
        exit;
    }

    if ($rs['type'] === 'restock' || $rs['project_name'] === 'Warehouse Restock') {
        echo json_encode(['status' => 'error', 'message' => 'RS Number not found.']);
        exit;
    }

    if ($rs['status'] === 'Released') {
        echo json_encode(['status' => 'error', 'message' => 'This Requisition Slip has already been released and is expired.']);
        exit;
    }

    if (!in_array($rs['status'], ['Approved', 'PO Created', 'Staged (Ready for Pickup)'])) {
        echo json_encode(['status' => 'error', 'message' => 'This Requisition Slip has not been Approved or Staged yet. Current status: ' . $rs['status']]);
        exit;
    }

    $itemStmt = $pdo->prepare("SELECT ri.item_code, ri.quantity, i.item_name FROM requisition_items ri LEFT JOIN inventory i ON ri.item_code = i.item_code WHERE ri.requisition_id = ?");
    $itemStmt->execute([$rs['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'rs_no' => $rs['rs_no'],
        'project_name' => $rs['project_name'],
        'rs_status' => $rs['status'],
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

} elseif ($action === 'stage_rs_materials') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) throw new Exception("Unauthorized.");
    
    $rs_id = $_POST['rs_id'];
    $stmt = $pdo->prepare("UPDATE requisitions SET status = 'Staged (Ready for Pickup)' WHERE id = ?");
    $stmt->execute([$rs_id]);
    
    $rsData = $pdo->prepare("SELECT rs_no, requestor_id FROM requisitions WHERE id = ?");
    $rsData->execute([$rs_id]);
    $rs = $rsData->fetch();

    if ($rs && !empty($rs['requestor_id'])) {
        $msg = "Your requested materials for {$rs['rs_no']} have been pre-picked & staged by the Warehouse In-Charge. Ready for express pickup!";
        $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Materials Staged (Ready for Pickup)', ?)")->execute([$rs['requestor_id'], $msg]);
        sendPushNotification($pdo, 'Materials Staged (Ready for Pickup)', $msg, null, (int)$rs['requestor_id']);
    }

    if (!empty($is_ajax)) {
        echo json_encode(['status' => 'success', 'message' => "Requisition {$rs['rs_no']} marked as Staged & Ready for Pickup."]);
        exit;
    }

    $_SESSION['message'] = "Requisition {$rs['rs_no']} marked as Staged & Ready for Express Pickup.";
    $_SESSION['msg_type'] = "info";
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
    $expected_delivery_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;

    $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_no, rs_id, supplier_id, prepared_by, expected_delivery_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$po_no, $rs_id, $supplier_id, $prepared_by, $expected_delivery_date]);
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

    $etaMsg = $expected_delivery_date ? " Target Warehouse ETA: " . date('M d, Y', strtotime($expected_delivery_date)) . "." : "";
    $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('warehouse', 'Incoming Delivery Expected', ?)");
    $notif->execute(["PO {$po_no} generated.{$etaMsg} Prepare space to receive materials."]);
    sendPushNotification($pdo, 'Incoming Delivery Expected', "PO {$po_no} generated.{$etaMsg} Prepare space to receive materials.", 'warehouse', null);

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
    $supplier_id = $_POST['supplier_id'] ?? null;
    $smsMessage = $_POST['message'] ?? '';
    $phone = $_POST['contact_number'] ?? '';
    
    // Update the PO supplier in the database if new one is selected
    if ($supplier_id) {
        $updateStmt = $pdo->prepare("UPDATE purchase_orders SET supplier_id = ? WHERE id = ?");
        $updateStmt->execute([$supplier_id, $po_id]);
    }
    
    // Fetch company name of the target supplier
    $company = '';
    if ($supplier_id) {
        $compStmt = $pdo->prepare("SELECT company_name FROM suppliers WHERE id = ?");
        $compStmt->execute([$supplier_id]);
        $company = $compStmt->fetchColumn();
    }
    if (empty($company)) {
        $company = $_POST['company'] ?? 'Supplier';
    }

    $smsSent = false;
    $errorMessage = "";

    // 1. Check SMS API Key (httpSMS integration)
    $apiKey = defined('SMS_API_KEY') ? SMS_API_KEY : '';
    $fromNumber = defined('SMS_FROM_NUMBER') ? SMS_FROM_NUMBER : '';
    $gatewayUrl = defined('SMS_GATEWAY_URL') ? SMS_GATEWAY_URL : 'https://api.httpsms.com/v1/messages/send';

    if (!empty($apiKey) && !in_array($apiKey, ['YOUR_SMS_API_KEY', 'YOUR_HTTPSMS_API_KEY'])) {
        if (strpos($gatewayUrl, 'httpsms') !== false) {
            // httpSMS Gateway Handler
            $payload = json_encode([
                'content' => $smsMessage,
                'from'    => $fromNumber,
                'to'      => $phone
            ]);

            $options = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "x-api-key: {$apiKey}\r\n" .
                                 "Content-Type: application/json\r\n" .
                                 "Accept: application/json\r\n",
                    'content' => $payload,
                    'ignore_errors' => true,
                    'timeout' => 15
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ];

            $context = stream_context_create($options);
            $response = @file_get_contents($gatewayUrl, false, $context);

            $httpCode = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                        $httpCode = intval($matches[1]);
                        break;
                    }
                }
            }

            $resData = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300) {
                $smsSent = true;
            } else {
                if (is_array($resData) && isset($resData['message'])) {
                    $errorMessage = $resData['message'];
                } else {
                    $errorMessage = "httpSMS Server Error (HTTP Code {$httpCode})";
                }
            }
        } else {
            // Semaphore Gateway Handler (Fallback)
            $postData = http_build_query([
                'apikey' => $apiKey,
                'number' => $phone,
                'message' => $smsMessage
            ]);

            $options = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $postData,
                    'ignore_errors' => true,
                    'timeout' => 10
                ]
            ];

            $context = stream_context_create($options);
            $response = @file_get_contents($gatewayUrl, false, $context);
            
            $httpCode = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                        $httpCode = intval($matches[1]);
                        break;
                    }
                }
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                $smsSent = true;
            } else {
                $errorMessage = "SMS Gateway Error (HTTP Code {$httpCode})";
            }
        }
    } else {
        // Simulation Mode (when API Key is not configured yet)
        usleep(1500000); // Simulate SMS processing delay
        $smsSent = true;
    }

    if (!$smsSent) {
        echo json_encode(['status' => 'error', 'message' => 'SMS Gateway Error: ' . $errorMessage]);
        exit;
    }
    
    $pdo->prepare("UPDATE purchase_orders SET status = 'SMS Sent' WHERE id = ?")->execute([$po_id]);
    
    $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'SMS Order Sent', ?)");
    $notif->execute(["Automated SMS was sent to {$company} for {$po_no} with verified item list."]);

    echo json_encode(['status' => 'success']);
    exit;

} elseif ($action === 'fetch_po_sms_preview') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
    }
    
    $po_id = $_POST['po_id'] ?? 0;
    
    // Fetch items
    $stmt = $pdo->prepare("SELECT pi.quantity, i.item_name FROM po_items pi JOIN inventory i ON pi.item_code = i.item_code WHERE pi.po_id = ?");
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $itemList = "";
    foreach ($items as $item) {
        $itemList .= "- {$item['quantity']}x {$item['item_name']}\n";
    }
    
    echo json_encode([
        'status' => 'success',
        'items' => $items,
        'item_list' => $itemList
    ]);
    exit;

} elseif ($action === 'log_po_delay') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) throw new Exception("Unauthorized.");
    
    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];
    $newEta = !empty($_POST['new_eta']) ? $_POST['new_eta'] : null;

    $delayReason = $_POST['delay_type'];
    if (!empty($_POST['remarks'])) {
        $delayReason .= " - " . $_POST['remarks'];
    }

    if ($newEta) {
        $pdo->prepare("UPDATE purchase_orders SET status = 'Delayed (Weather)', delay_remarks = ?, expected_delivery_date = ? WHERE id = ?")->execute([$delayReason, $newEta, $po_id]);
    } else {
        $pdo->prepare("UPDATE purchase_orders SET status = 'Delayed (Weather)', delay_remarks = ? WHERE id = ?")->execute([$delayReason, $po_id]);
    }
    
    $alertMsg = "ALERT: {$po_no} is delayed. Reason: {$delayReason}";
    if ($newEta) {
        $alertMsg .= ". New ETA: " . date('M d, Y', strtotime($newEta));
    }

    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'Supply Chain Delay', ?)")->execute([$alertMsg]);
    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('warehouse', 'Expected Delivery Delayed', ?)")->execute([$alertMsg]);
    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'Supply Chain Delay', ?)")->execute([$alertMsg]);

    sendPushNotification($pdo, 'Supply Chain Delay', $alertMsg, 'management', null);
    sendPushNotification($pdo, 'Expected Delivery Delayed', $alertMsg, 'warehouse', null);
    sendPushNotification($pdo, 'Supply Chain Delay', $alertMsg, 'admin', null);

    $_SESSION['message'] = "Logistics delay & revised ETA successfully logged and alerts sent.";
    $_SESSION['msg_type'] = "warning";
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
} elseif ($action === 'fetch_sms_threads') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'] ?? '', ['purchasing', 'admin', 'management'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
    }

    try {
        // Consolidated thread query grouping by supplier or normalized phone number
        $stmt = $pdo->query("
            SELECT 
                sub.thread_key,
                sub.supplier_id,
                sub.other_phone AS sender_number,
                COALESCE(s.company_name, sub.auto_company_name, sub.other_phone) AS company_name,
                s.contact_person,
                COALESCE(s.contact_number, sub.other_phone) AS contact_number,
                MAX(sub.created_at) AS last_message_at,
                SUM(CASE WHEN sub.direction = 'inbound' AND sub.is_read = 0 THEN 1 ELSE 0 END) AS unread_count,
                (
                    SELECT r2.message_text 
                    FROM supplier_sms_replies r2 
                    WHERE 
                        (sub.supplier_id IS NOT NULL AND r2.supplier_id = sub.supplier_id)
                        OR RIGHT(REGEXP_REPLACE(r2.sender_number, '[^0-9]', ''), 9) = sub.phone_digits
                        OR RIGHT(REGEXP_REPLACE(r2.receiver_number, '[^0-9]', ''), 9) = sub.phone_digits
                    ORDER BY r2.id DESC LIMIT 1
                ) AS last_message
            FROM (
                SELECT 
                    r.id, r.po_id, r.direction, r.sender_number, r.receiver_number, r.message_text, r.is_read, r.created_at,
                    CASE WHEN r.direction = 'inbound' THEN r.sender_number ELSE r.receiver_number END AS other_phone,
                    RIGHT(REGEXP_REPLACE(CASE WHEN r.direction = 'inbound' THEN r.sender_number ELSE r.receiver_number END, '[^0-9]', ''), 9) AS phone_digits,
                    s_auto.id AS auto_supplier_id,
                    s_auto.company_name AS auto_company_name,
                    COALESCE(r.supplier_id, s_auto.id) AS supplier_id,
                    COALESCE(
                        CONCAT('SUP_', COALESCE(r.supplier_id, s_auto.id)),
                        CONCAT('PHONE_', RIGHT(REGEXP_REPLACE(CASE WHEN r.direction = 'inbound' THEN r.sender_number ELSE r.receiver_number END, '[^0-9]', ''), 9))
                    ) AS thread_key
                FROM supplier_sms_replies r
                LEFT JOIN suppliers s_auto 
                    ON REPLACE(REPLACE(REPLACE(s_auto.contact_number, ' ', ''), '-', ''), '+', '') LIKE CONCAT('%', RIGHT(REGEXP_REPLACE(CASE WHEN r.direction = 'inbound' THEN r.sender_number ELSE r.receiver_number END, '[^0-9]', ''), 9))
            ) sub
            LEFT JOIN suppliers s ON sub.supplier_id = s.id
            GROUP BY sub.thread_key
            ORDER BY last_message_at DESC
        ");
        $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalUnreadStmt = $pdo->query("SELECT COUNT(*) FROM supplier_sms_replies WHERE direction = 'inbound' AND is_read = 0");
        $totalUnread = (int)$totalUnreadStmt->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'threads' => $threads,
            'total_unread' => $totalUnread
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;

} elseif ($action === 'fetch_sms_messages') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'] ?? '', ['purchasing', 'admin', 'management'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
    }

    $phone = $_POST['sender_number'] ?? '';
    $supplier_id = $_POST['supplier_id'] ?? null;
    $po_id = $_POST['po_id'] ?? null;

    $cleanDigits = !empty($phone) ? substr(preg_replace('/[^0-9]/', '', $phone), -9) : '';

    try {
        if ($po_id) {
            $stmt = $pdo->prepare("
                SELECT r.*, COALESCE(s.company_name, r.sender_number) AS company_name 
                FROM supplier_sms_replies r
                LEFT JOIN suppliers s ON r.supplier_id = s.id
                WHERE 
                    r.po_id = :po_id
                    OR (:sup_id IS NOT NULL AND r.supplier_id = :sup_id)
                    OR RIGHT(REGEXP_REPLACE(r.sender_number, '[^0-9]', ''), 9) = :digits
                    OR RIGHT(REGEXP_REPLACE(r.receiver_number, '[^0-9]', ''), 9) = :digits
                ORDER BY r.id ASC
            ");
            $stmt->execute([':po_id' => $po_id, ':sup_id' => $supplier_id, ':digits' => $cleanDigits]);
        } else {
            $stmt = $pdo->prepare("
                SELECT r.*, COALESCE(s.company_name, r.sender_number) AS company_name 
                FROM supplier_sms_replies r
                LEFT JOIN suppliers s ON r.supplier_id = s.id
                WHERE 
                    (:sup_id IS NOT NULL AND r.supplier_id = :sup_id)
                    OR (LENGTH(:digits) >= 7 AND RIGHT(REGEXP_REPLACE(r.sender_number, '[^0-9]', ''), 9) = :digits)
                    OR (LENGTH(:digits) >= 7 AND RIGHT(REGEXP_REPLACE(r.receiver_number, '[^0-9]', ''), 9) = :digits)
                ORDER BY r.id ASC
            ");
            $stmt->execute([':sup_id' => $supplier_id, ':digits' => $cleanDigits]);
        }

        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark inbound messages in this thread as read
        if (!empty($supplier_id)) {
            $markReadStmt = $pdo->prepare("UPDATE supplier_sms_replies SET is_read = 1 WHERE (supplier_id = ? OR RIGHT(REGEXP_REPLACE(sender_number, '[^0-9]', ''), 9) = ?) AND direction = 'inbound'");
            $markReadStmt->execute([$supplier_id, $cleanDigits]);
        } elseif (!empty($cleanDigits)) {
            $markReadStmt = $pdo->prepare("UPDATE supplier_sms_replies SET is_read = 1 WHERE RIGHT(REGEXP_REPLACE(sender_number, '[^0-9]', ''), 9) = ? AND direction = 'inbound'");
            $markReadStmt->execute([$cleanDigits]);
        }

        echo json_encode([
            'status' => 'success',
            'messages' => $messages
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;

} elseif ($action === 'send_supplier_reply_sms') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'] ?? '', ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
    }

    $phone = trim($_POST['phone'] ?? '');
    $supplier_id = $_POST['supplier_id'] ?? null;
    $po_id = $_POST['po_id'] ?? null;
    $smsMessage = trim($_POST['message'] ?? '');

    if (empty($phone) || empty($smsMessage)) {
        echo json_encode(['status' => 'error', 'message' => 'Phone number and message text are required.']);
        exit;
    }

    $apiKey = defined('SMS_API_KEY') ? SMS_API_KEY : '';
    $fromNumber = defined('SMS_FROM_NUMBER') ? SMS_FROM_NUMBER : '';
    $gatewayUrl = defined('SMS_GATEWAY_URL') ? SMS_GATEWAY_URL : 'https://api.httpsms.com/v1/messages/send';

    $smsSent = false;
    $errorMessage = '';

    if (!empty($apiKey) && !in_array($apiKey, ['YOUR_SMS_API_KEY', 'YOUR_HTTPSMS_API_KEY'])) {
        $payload = json_encode([
            'content' => $smsMessage,
            'from'    => $fromNumber,
            'to'      => $phone
        ]);

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "x-api-key: {$apiKey}\r\n" .
                             "Content-Type: application/json\r\n" .
                             "Accept: application/json\r\n",
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 15
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($gatewayUrl, false, $context);

        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                    $httpCode = intval($matches[1]);
                    break;
                }
            }
        }

        $resData = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $smsSent = true;
        } else {
            $errorMessage = $resData['message'] ?? "httpSMS Gateway Error (HTTP {$httpCode})";
        }
    } else {
        // Simulation mode
        usleep(800000);
        $smsSent = true;
    }

    if ($smsSent) {
        // Log outbound message in DB
        $logStmt = $pdo->prepare("
            INSERT INTO supplier_sms_replies (supplier_id, po_id, direction, sender_number, receiver_number, message_text, is_read)
            VALUES (?, ?, 'outbound', ?, ?, ?, 1)
        ");
        $logStmt->execute([$supplier_id, $po_id, $fromNumber ?: 'SYSTEM', $phone, $smsMessage]);

        echo json_encode(['status' => 'success', 'message' => 'SMS reply sent successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $errorMessage]);
    }
    exit;

} elseif ($action === 'mark_sms_thread_read') {
    header('Content-Type: application/json');
    $phone = $_POST['phone'] ?? '';
    if (!empty($phone)) {
        $pdo->prepare("UPDATE supplier_sms_replies SET is_read = 1 WHERE sender_number = ? AND direction = 'inbound'")->execute([$phone]);
    }
    echo json_encode(['status' => 'success']);
    exit;

} elseif ($action === 'update_po_eta') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Only Purchasing Officers can update the ETA.']);
        exit;
    }

    $po_id = $_POST['po_id'] ?? null;
    $eta_date = $_POST['expected_delivery_date'] ?? null;

    if (!$po_id || !$eta_date) {
        echo json_encode(['status' => 'error', 'message' => 'PO ID and ETA date are required.']);
        exit;
    }

    // Fetch PO details
    $poStmt = $pdo->prepare("SELECT p.po_no, s.company_name FROM purchase_orders p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ?");
    $poStmt->execute([$po_id]);
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        echo json_encode(['status' => 'error', 'message' => 'Purchase Order not found.']);
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE purchase_orders SET expected_delivery_date = ? WHERE id = ?");
    $updateStmt->execute([$eta_date, $po_id]);

    $formattedEta = date('M d, Y', strtotime($eta_date));
    $notifTitle = "🚚 Supply ETA Updated: " . $po['po_no'];
    $notifBody = "Delivery from {$po['company_name']} is now estimated to arrive at warehouse on {$formattedEta}.";

    // Insert system notifications for warehouse and management
    foreach (['warehouse', 'management'] as $targetRole) {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES (?, ?, ?)");
        $notifStmt->execute([$targetRole, $notifTitle, $notifBody]);
        sendPushNotification($pdo, $notifTitle, $notifBody, $targetRole, null);
    }

    echo json_encode([
        'status' => 'success',
        'message' => "ETA for {$po['po_no']} updated to {$formattedEta} successfully!",
        'eta_formatted' => $formattedEta
    ]);
    exit;

} elseif ($action === 'fetch_combined_alerts') {
    header('Content-Type: application/json');
    
    $userRole = $_SESSION['user_role'] ?? 'warehouse';
    $combinedAlerts = [];
    $totalUnread = 0;
    $today = date('Y-m-d');

    // 1. Fetch Supply ETA Alerts from Purchase Orders
    $poQuery = "
        SELECT p.id, p.po_no, p.status, p.expected_delivery_date, p.created_at, s.company_name
        FROM purchase_orders p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.status NOT IN ('Delivered', 'Cancelled')
        ORDER BY 
            CASE 
                WHEN p.expected_delivery_date = ? THEN 1
                WHEN p.expected_delivery_date < ? THEN 2
                ELSE 3 
            END,
            p.expected_delivery_date ASC
        LIMIT 10
    ";
    $poStmt = $pdo->prepare($poQuery);
    $poStmt->execute([$today, $today]);
    $pos = $poStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pos as $po) {
        $eta = $po['expected_delivery_date'];
        $type = 'supply_eta';
        $badgeClass = 'bg-secondary';
        $icon = 'bi-truck';
        $category = 'on_track';
        $timeAgo = 'Scheduled';

        if (!empty($eta)) {
            $daysDiff = (int) (strtotime($eta) - strtotime($today)) / 86400;
            if ($daysDiff == 0) {
                $category = 'arriving_today';
                $badgeClass = 'bg-warning text-dark';
                $icon = 'bi-truck-flatbed';
                $title = "🚚 Arriving Today: PO {$po['po_no']}";
                $message = "Supplies from {$po['company_name']} scheduled to arrive at warehouse today (" . date('M d', strtotime($eta)) . ").";
                $timeAgo = "TODAY";
                $totalUnread++;
            } elseif ($daysDiff < 0) {
                $category = 'overdue';
                $badgeClass = 'bg-danger';
                $icon = 'bi-exclamation-triangle-fill';
                $daysOverdue = abs((int)$daysDiff);
                $title = "⚠️ Overdue Delivery: PO {$po['po_no']}";
                $message = "Supply delivery from {$po['company_name']} is overdue by {$daysOverdue} day(s). Target was " . date('M d', strtotime($eta)) . ".";
                $timeAgo = "Overdue";
                $totalUnread++;
            } else {
                $category = 'on_track';
                $badgeClass = 'bg-info text-dark';
                $icon = 'bi-box-seam';
                $title = "📦 Scheduled Supply: PO {$po['po_no']}";
                $message = "Supplies from {$po['company_name']} expected on " . date('M d, Y', strtotime($eta)) . " (in {$daysDiff} days).";
                $timeAgo = "In {$daysDiff}d";
            }
        } else {
            $title = "📦 Pending Delivery: PO {$po['po_no']}";
            $message = "Supplies from {$po['company_name']} pending delivery. Target ETA not set yet.";
        }

        $combinedAlerts[] = [
            'id' => 'po_' . $po['id'],
            'po_id' => $po['id'],
            'po_no' => $po['po_no'],
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'time_ago' => $timeAgo,
            'is_read' => ($category === 'on_track') ? 1 : 0,
            'badge_class' => $badgeClass,
            'icon' => $icon,
            'created_at' => $po['created_at']
        ];
    }

    // 2. Fetch Supplier SMS Replies
    if (in_array($userRole, ['admin', 'purchasing', 'management'])) {
        $smsStmt = $pdo->prepare("
            SELECT r.*, s.company_name, p.po_no
            FROM supplier_sms_replies r
            LEFT JOIN suppliers s ON r.supplier_id = s.id
            LEFT JOIN purchase_orders p ON r.po_id = p.id
            WHERE r.direction = 'inbound'
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $smsStmt->execute();
        $smsReplies = $smsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($smsReplies as $sms) {
            if ($sms['is_read'] == 0) $totalUnread++;
            
            // Check if message text mentions a date
            $hasDate = preg_match('/(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|\d{1,2}\/\d{1,2}|\d{4}-\d{2}-\d{2}|today|tomorrow|monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $sms['message_text']);

            $combinedAlerts[] = [
                'id' => 'sms_' . $sms['id'],
                'type' => 'sms_reply',
                'category' => 'sms',
                'supplier_name' => $sms['company_name'] ?: 'Supplier',
                'sender_number' => $sms['sender_number'],
                'po_id' => $sms['po_id'],
                'po_no' => $sms['po_no'],
                'title' => "💬 SMS: " . ($sms['company_name'] ?: $sms['sender_number']),
                'message' => "\"" . mb_strimwidth($sms['message_text'], 0, 85, '...') . "\"",
                'time_ago' => time_elapsed_string($sms['created_at']),
                'is_read' => (int) $sms['is_read'],
                'badge_class' => 'bg-purple',
                'icon' => 'bi-chat-left-text-fill',
                'has_date_mention' => $hasDate ? 1 : 0,
                'created_at' => $sms['created_at']
            ];
        }
    }

    // 3. Fetch System Notifications
    $notifStmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE (target_role = ? OR target_role = 'all')
        ORDER BY created_at DESC 
        LIMIT 6
    ");
    $notifStmt->execute([$userRole]);
    $systemNotifs = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($systemNotifs as $sys) {
        if ($sys['is_read'] == 0) $totalUnread++;
        $combinedAlerts[] = [
            'id' => 'sys_' . $sys['id'],
            'type' => 'system_alert',
            'category' => 'system',
            'title' => $sys['title'],
            'message' => $sys['message'],
            'time_ago' => time_elapsed_string($sys['created_at']),
            'is_read' => (int) $sys['is_read'],
            'badge_class' => ($sys['is_read'] == 0) ? 'bg-primary' : 'bg-secondary',
            'icon' => 'bi-bell-fill',
            'created_at' => $sys['created_at']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'total_unread' => $totalUnread,
        'alerts' => $combinedAlerts
    ]);
    exit;
}
?>