<?php
// ==========================================
// PURCHASE ORDERS (PO) ACTIONS
// ==========================================

// --- AJAX: FETCH SUPPLIER DELIVERY HISTORY ---
if ($action === 'fetch_supplier_delivery_history') {
    if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'management', 'warehouse'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
        exit;
    }

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);

    // Fetch supplier info
    $supStmt = $pdo->prepare("SELECT company_name, supplier_code FROM suppliers WHERE id = ?");
    $supStmt->execute([$supplier_id]);
    $supplier = $supStmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        echo json_encode(['status' => 'error', 'message' => 'Supplier not found.']);
        exit;
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

// --- AJAX: FETCH PO ITEMS FOR RECEIVING MODAL ---
elseif ($action === 'fetch_po_items') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
        exit;
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

// --- CREATE PURCHASE ORDER ---
elseif ($action === 'create_po') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        throw new Exception("Unauthorized action.");
    }

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
}

// --- MARK PO DELIVERED / STOCK IN ---
elseif ($action === 'mark_po_delivered') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
        throw new Exception("Unauthorized.");
    }

    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];

    $item_codes = $_POST['item_codes'] ?? [];
    $actual_qtys = $_POST['actual_qtys'] ?? [];
    $expected_qtys = $_POST['expected_qtys'] ?? [];

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

        if ($actual > 0) {
            $updateInv->execute([$actual, $actual, $actual, $code]);
        }
    }

    if ($hasDiscrepancy) {
        $cleanDesc = trim($discrepancyLog);

        $pdo->prepare("UPDATE purchase_orders SET status = 'Delivered (Discrepancy)', delay_remarks = CONCAT(IFNULL(delay_remarks,''), '\n\n[DELIVERY DISCREPANCY]:\n', ?) WHERE id = ?")
            ->execute([$cleanDesc, $po_id]);

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
}

// --- LOG PO DELAY ---
elseif ($action === 'log_po_delay') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        throw new Exception("Unauthorized.");
    }

    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];
    $newEta = !empty($_POST['new_eta']) ? $_POST['new_eta'] : null;

    $delayReason = $_POST['delay_type'];
    if (!empty($_POST['remarks'])) {
        $delayReason .= " - " . $_POST['remarks'];
    }

    if ($newEta) {
        $pdo->prepare("UPDATE purchase_orders SET status = 'Delayed (Weather)', delay_remarks = ?, expected_delivery_date = ? WHERE id = ?")
            ->execute([$delayReason, $newEta, $po_id]);
    } else {
        $pdo->prepare("UPDATE purchase_orders SET status = 'Delayed (Weather)', delay_remarks = ? WHERE id = ?")
            ->execute([$delayReason, $po_id]);
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

// --- UPDATE PO ETA ---
elseif ($action === 'update_po_eta') {
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
}
