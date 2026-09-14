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

$rsNo = trim($_GET['rs_no'] ?? '');
if (empty($rsNo)) {
    echo json_encode(['success' => false, 'error' => 'Requisition Slip number is required.']);
    exit;
}

try {
    // 1. Fetch Requisition details
    $stmt = $pdo->prepare("SELECT r.*, u.name as requestor_user_name FROM requisitions r LEFT JOIN users u ON r.requestor_id = u.id WHERE r.rs_no = ?");
    $stmt->execute([$rsNo]);
    $rs = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rs) {
        echo json_encode(['success' => false, 'error' => 'Requisition Slip ' . htmlspecialchars($rsNo) . ' not found.']);
        exit;
    }

    // 2. Fetch items for this requisition
    $itemsStmt = $pdo->prepare("
        SELECT ri.id as item_id, ri.requisition_id, ri.quantity, ri.item_code, 
               COALESCE(i.item_name, ri.new_item_name, ri.item_code) as item_name, 
               COALESCE(i.unit, ri.new_unit, 'pcs') as unit, 
               ri.is_new_item, ri.new_category, 
               i.quantity as current_stock,
               COALESCE(p.total_pending, 0) as total_pending,
               p.pending_details,
               ri.item_status,
               ri.item_remarks,
               ri.item_notes
        FROM requisition_items ri 
        LEFT JOIN inventory i ON ri.item_code = i.item_code
        LEFT JOIN (
            SELECT ri2.item_code, SUM(ri2.quantity) as total_pending,
                   GROUP_CONCAT(CONCAT(r2.project_name, ' [', ri2.quantity, 'x by ', r2.requestor_name, ']') SEPARATOR '; ') as pending_details
            FROM requisition_items ri2
            JOIN requisitions r2 ON ri2.requisition_id = r2.id
            WHERE r2.status = 'Pending Approval'
            GROUP BY ri2.item_code
        ) p ON ri.item_code = p.item_code
        WHERE ri.requisition_id = ?
    ");
    $itemsStmt->execute([$rs['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);


    // 3. Format Date
    $createdTime = strtotime($rs['created_at']);
    $formattedDate = date('M d, Y g:i A', $createdTime);

    echo json_encode([
        'success' => true,
        'requisition' => [
            'id' => (int)$rs['id'],
            'rs_no' => $rs['rs_no'],
            'project_name' => $rs['project_name'],
            'requestor_name' => $rs['requestor_name'] ?: ($rs['requestor_user_name'] ?: 'User'),
            'status' => $rs['status'],
            'urgency' => $rs['urgency'],
            'type' => $rs['type'] ?? 'project',
            'remarks' => $rs['remarks'],
            'created_at' => $rs['created_at'],
            'formatted_date' => $formattedDate
        ],
        'items' => $items
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
