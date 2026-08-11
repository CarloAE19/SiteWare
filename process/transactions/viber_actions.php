<?php
// ==========================================
// VIBER MESSAGING & ORDER DISPATCH ACTIONS
// ==========================================

// --- LOG PO VIBER DISPATCH ---
if ($action === 'log_viber_order_sent') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $po_id = $_POST['po_id'] ?? null;
    $po_no = $_POST['po_no'] ?? '';
    $supplier_id = $_POST['supplier_id'] ?? null;
    $phone = $_POST['contact_number'] ?? '';
    $viberMessage = $_POST['message'] ?? '';

    if ($supplier_id && $po_id) {
        $updateStmt = $pdo->prepare("UPDATE purchase_orders SET supplier_id = ? WHERE id = ?");
        $updateStmt->execute([$supplier_id, $po_id]);
    }

    $company = 'Supplier';
    if ($supplier_id) {
        $compStmt = $pdo->prepare("SELECT company_name FROM suppliers WHERE id = ?");
        $compStmt->execute([$supplier_id]);
        $company = $compStmt->fetchColumn() ?: 'Supplier';
    }

    if ($po_id) {
        $pdo->prepare("UPDATE purchase_orders SET status = 'Viber Order Sent' WHERE id = ?")->execute([$po_id]);
    }

    // Record audit log
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO supplier_viber_logs (supplier_id, po_id, direction, sender_number, receiver_number, message_text, is_read)
            VALUES (?, ?, 'outbound', 'VIBER', ?, ?, 1)
        ");
        $logStmt->execute([$supplier_id, $po_id, $phone ?: 'SYSTEM', "[Viber Dispatched]\n" . $viberMessage]);
    } catch (PDOException $e) { }

    $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'Viber Order Dispatched', ?)");
    $notif->execute(["Viber Order message was dispatched to {$company} for {$po_no}."]);

    echo json_encode(['status' => 'success', 'message' => 'Viber order logged successfully']);
    exit;
}

// --- FETCH PO VIBER PREVIEW ---
elseif ($action === 'fetch_po_viber_preview') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $po_id = $_POST['po_id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT pi.quantity, COALESCE(i.item_name, pi.custom_item_name, pi.item_code) as item_name 
        FROM po_items pi 
        LEFT JOIN inventory i ON pi.item_code = i.item_code 
        WHERE pi.po_id = ?
    ");
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
}
