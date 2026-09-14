<?php
ini_set('display_errors', 0);
error_reporting(0);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized session. Please log in.']);
    exit;
}

require_once __DIR__ . '/../Connection/db.php';

$poNo = trim($_GET['po_no'] ?? '');
if (empty($poNo)) {
    echo json_encode(['success' => false, 'error' => 'Purchase Order number is required.']);
    exit;
}

try {
    // 1. Fetch Purchase Order details
    $poStmt = $pdo->prepare("
        SELECT p.*, 
               s.company_name, s.contact_person, s.contact_number, s.email AS supplier_email, s.address AS supplier_address, 
               r.rs_no, r.project_name, 
               u.name AS prepared_by_name, u.signature_path AS prepared_user_sig,
               app_u.name AS approved_by_name, app_u.signature_path AS approved_user_sig,
               u_rec.name AS received_by_name
        FROM purchase_orders p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN requisitions r ON p.rs_id = r.id
        LEFT JOIN users u ON p.prepared_by = u.id
        LEFT JOIN users app_u ON COALESCE(p.approved_by, r.approved_by) = app_u.id
        LEFT JOIN users u_rec ON p.received_by = u_rec.id
        WHERE p.po_no = ?
    ");
    $poStmt->execute([$poNo]);
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        echo json_encode(['success' => false, 'error' => 'Purchase Order ' . htmlspecialchars($poNo) . ' not found.']);
        exit;
    }

    $prepSig = $po['prepared_signature'] ?: ($po['prepared_user_sig'] ?: null);
    $appSig = $po['approved_signature'] ?: ($po['approved_user_sig'] ?: null);
    $appBy = $po['approved_by_name'];

    if (empty($appBy)) {
        $mgrStmt = $pdo->query("SELECT name, signature_path FROM users WHERE role IN ('management', 'admin') ORDER BY role DESC LIMIT 1");
        $mgr = $mgrStmt->fetch(PDO::FETCH_ASSOC);
        if ($mgr) {
            $appBy = $mgr['name'];
            if (empty($appSig)) {
                $appSig = $mgr['signature_path'];
            }
        } else {
            $appBy = 'Management / Approver';
        }
    }

    // 2. Fetch items for this PO
    $itemsStmt = $pdo->prepare("
        SELECT pi.item_code, pi.quantity, 
               COALESCE(pi.unit_price, i.unit_price, 0) as unit_price, 
               COALESCE(i.item_name, pi.custom_item_name, pi.item_code) as item_name, 
               COALESCE(i.unit, pi.unit, 'pcs') as unit,
               pi.is_new_item, pi.category
        FROM po_items pi 
        LEFT JOIN inventory i ON pi.item_code = i.item_code 
        WHERE pi.po_id = ?
    ");
    $itemsStmt->execute([$po['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total order value
    $totalVal = 0;
    foreach ($items as &$item) {
        $qty = (float)($item['quantity'] ?? 0);
        $price = (float)($item['unit_price'] ?? 0);
        $item['total_price'] = $qty * $price;
        $totalVal += $item['total_price'];
    }

    $formattedCreated = date('M d, Y g:i A', strtotime($po['created_at']));
    $formattedETA = !empty($po['expected_delivery_date']) ? date('M d, Y', strtotime($po['expected_delivery_date'])) : 'N/A';

    echo json_encode([
        'success' => true,
        'po' => [
            'id' => (int)$po['id'],
            'po_no' => $po['po_no'],
            'status' => $po['status'] ?: 'Generated',
            'supplier_name' => $po['company_name'] ?: 'N/A',
            'supplier_contact' => $po['contact_number'] ?: 'N/A',
            'supplier_email' => $po['supplier_email'] ?: 'N/A',
            'rs_no' => $po['rs_no'] ?: 'N/A',
            'project_name' => $po['project_name'] ?: 'Warehouse Restock',
            'prepared_by' => $po['prepared_by_name'] ?: 'Purchasing Officer',
            'prepared_signature' => $prepSig,
            'approved_by' => $appBy,
            'approved_signature' => $appSig,
            'received_by' => $po['received_by_name'] ?: 'N/A',
            'expected_delivery' => $formattedETA,
            'delay_remarks' => $po['delay_remarks'] ?: null,
            'proof_of_receipt' => $po['proof_of_receipt'] ?: null,
            'created_at' => $formattedCreated,
            'total_value' => $totalVal
        ],
        'items' => $items
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
