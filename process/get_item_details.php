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

$itemCode = trim($_GET['item_code'] ?? '');
if (empty($itemCode)) {
    echo json_encode(['success' => false, 'error' => 'Item Code is required.']);
    exit;
}

try {
    // 1. Fetch Item details
    $itemStmt = $pdo->prepare("SELECT * FROM inventory WHERE item_code = ?");
    $itemStmt->execute([$itemCode]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Inventory item ' . htmlspecialchars($itemCode) . ' not found.']);
        exit;
    }

    // 2. Fetch total consumed in last 30 days
    $consumedStmt = $pdo->prepare("
        SELECT SUM(wi.quantity) as total_consumed 
        FROM withdrawal_items wi 
        JOIN withdrawals w ON wi.withdrawal_id = w.id 
        WHERE wi.item_code = ? AND w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $consumedStmt->execute([$itemCode]);
    $consumed30d = (int)($consumedStmt->fetchColumn() ?: 0);

    // 3. Fetch recent withdrawals for this item
    $recentStmt = $pdo->prepare("
        SELECT w.withdrawal_no, w.project_name, wi.quantity, w.date_withdrawn
        FROM withdrawal_items wi
        JOIN withdrawals w ON wi.withdrawal_id = w.id
        WHERE wi.item_code = ?
        ORDER BY w.date_withdrawn DESC
        LIMIT 5
    ");
    $recentStmt->execute([$itemCode]);
    $recentWithdrawals = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($recentWithdrawals as &$rw) {
        $rw['formatted_date'] = date('M d, Y', strtotime($rw['date_withdrawn']));
    }

    // Determine Status Badge
    $status = $item['status'] ?: 'In Stock';
    $qty = (int)($item['quantity'] ?? 0);
    if ($qty == 0) {
        $status = 'Out of Stock';
    } elseif ($qty < 15) {
        $status = 'Low Stock';
    }

    echo json_encode([
        'success' => true,
        'item' => [
            'id' => (int)$item['id'],
            'item_code' => $item['item_code'],
            'item_name' => $item['item_name'],
            'category' => $item['category'] ?: 'General',
            'quantity' => $qty,
            'unit' => $item['unit'] ?: 'pcs',
            'price' => number_format((float)($item['unit_price'] ?? 0), 2),
            'status' => $status,
            'consumed_30d' => $consumed30d,
            'created_at' => date('M d, Y', strtotime($item['created_at']))
        ],
        'recent_withdrawals' => $recentWithdrawals
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
