<?php
// ==========================================
// REQUISITIONS, POs, AND WITHDRAWALS LOGIC
// ==========================================

// --- AJAX: FETCH RS DATA VIA QR SCANNER (PHASE 2 WORKFLOW) ---
if ($action === 'fetch_rs_data') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']); exit;
    }

    $rs_no = str_replace('REQ-DATA:', '', $_POST['rs_no']); 
    
    $stmt = $pdo->prepare("SELECT id, project_name FROM requisitions WHERE rs_no = ? AND status IN ('Approved', 'PO Created')");
    $stmt->execute([$rs_no]);
    $rs = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rs) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid QR Code, or this RS has not been Approved yet.']);
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
    $stmt = $pdo->prepare("INSERT INTO requisitions (rs_no, requestor_id, requestor_name, project_name, urgency, remarks, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending Approval')");
    $stmt->execute([$_POST['rs_no'], $_POST['requestor_id'], $_POST['requestor_name'], $_POST['project_name'], $_POST['urgency'], $_POST['remarks']]);
    $requisition_id = $pdo->lastInsertId();

    $items = $_POST['items'];
    $quantities = $_POST['quantities'];
    $itemStmt = $pdo->prepare("INSERT INTO requisition_items (requisition_id, item_code, quantity) VALUES (?, ?, ?)");
    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i]) && !empty($quantities[$i])) {
            $itemStmt->execute([$requisition_id, $items[$i], $quantities[$i]]);
        }
    }
    
    $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'New Requisition Pending', ?)");
    $notif->execute(["{$_POST['requestor_name']} submitted {$_POST['rs_no']} for {$_POST['project_name']}."]);

    $_SESSION['message'] = "Requisition created successfully and sent to Management for approval.";
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

    $_SESSION['message'] = "Requisition Approved. Ready for Purchasing.";
    $_SESSION['msg_type'] = "success";
    header("Location: ../requisitions");
    exit;

} elseif ($action === 'reject_rs') {
    if (!in_array($_SESSION['user_role'], ['management', 'admin'])) throw new Exception("Only Management or Admins can reject requisitions.");
    
    // FIXED: Capture the reason and append it to the remarks so the user can read it!
    $reason = trim($_POST['reject_reason']);
    $appendRemark = "\n\n[MANAGEMENT REJECTED]: " . $reason;

    $stmt = $pdo->prepare("UPDATE requisitions SET status = 'Rejected', remarks = CONCAT(IFNULL(remarks,''), ?) WHERE id = ?");
    $stmt->execute([$appendRemark, $_POST['rs_id']]);
    
    $rsData = $pdo->prepare("SELECT rs_no, requestor_id FROM requisitions WHERE id = ?");
    $rsData->execute([$_POST['rs_id']]);
    $rs = $rsData->fetch();

    $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Rejected', ?)")->execute([$rs['requestor_id'], "Your request {$rs['rs_no']} was rejected. Reason: {$reason}"]);

    $_SESSION['message'] = "Requisition Rejected successfully.";
    $_SESSION['msg_type'] = "danger";
    header("Location: ../requisitions");
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

    $_SESSION['message'] = "Purchase Order generated and sent to Supplier successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../po");
    exit;

} elseif ($action === 'mark_po_delivered') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) throw new Exception("Unauthorized.");
    
    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];
    
    $pdo->prepare("UPDATE purchase_orders SET status = 'Delivered' WHERE id = ?")->execute([$po_id]);
    
    $alertMsg = "Order {$po_no} has arrived and was received at the warehouse.";
    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'PO Delivered', ?)")->execute([$alertMsg]);
    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'PO Delivered', ?)")->execute([$alertMsg]);

    $_SESSION['message'] = "Purchase Order marked as successfully Delivered!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../po");
    exit;

} elseif ($action === 'send_po_sms') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit;
    }
    
    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];
    $company = $_POST['company'];
    
    usleep(1500000); // Simulate SMS
    $pdo->prepare("UPDATE purchase_orders SET status = 'SMS Sent' WHERE id = ?")->execute([$po_id]);
    
    $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'SMS Order Sent', ?)");
    $notif->execute(["Automated SMS was sent to {$company} for {$po_no}."]);

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
    $updateStatusStmt = $pdo->prepare("UPDATE inventory SET status = CASE WHEN quantity <= 0 THEN 'Out of Stock' WHEN quantity <= 10 THEN 'Low Stock' ELSE 'In Stock' END WHERE item_code = ?");
    
    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i]) && !empty($quantities[$i])) {
            $wdItemStmt->execute([$withdrawal_id, $items[$i], $quantities[$i]]);
            $deductStmt->execute([$quantities[$i], $items[$i]]);
            $updateStatusStmt->execute([$items[$i]]); 
        }
    }

    $_SESSION['message'] = "Materials successfully withdrawn and deducted from inventory.";
    $_SESSION['msg_type'] = "success";
    header("Location: ../withdrawals");
    exit;
}
?>