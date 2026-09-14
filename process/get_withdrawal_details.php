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

$withdrawalNo = trim($_GET['withdrawal_no'] ?? '');
if (empty($withdrawalNo)) {
    echo json_encode(['success' => false, 'error' => 'Withdrawal Slip number is required.']);
    exit;
}

try {
    // 1. Fetch Withdrawal details
    $wStmt = $pdo->prepare("
        SELECT w.*, u.name as releaser_name, u.signature_path as releaser_signature_path, r.requestor_name
        FROM withdrawals w
        LEFT JOIN users u ON w.released_by = u.id
        LEFT JOIN requisitions r ON (w.remarks LIKE CONCAT('%', r.rs_no, '%') AND r.rs_no != '')
        WHERE w.withdrawal_no = ?
    ");
    $wStmt->execute([$withdrawalNo]);
    $withdrawal = $wStmt->fetch(PDO::FETCH_ASSOC);

    if (!$withdrawal) {
        echo json_encode(['success' => false, 'error' => 'Withdrawal Slip ' . htmlspecialchars($withdrawalNo) . ' not found.']);
        exit;
    }

    if (empty($withdrawal['releaser_signature_path'])) {
        $whStmt = $pdo->query("SELECT signature_path, name FROM users WHERE role IN ('warehouse', 'admin') AND signature_path IS NOT NULL AND signature_path != '' ORDER BY role ASC LIMIT 1");
        $whUser = $whStmt->fetch(PDO::FETCH_ASSOC);
        if ($whUser) {
            $withdrawal['releaser_signature_path'] = $whUser['signature_path'];
            if (empty($withdrawal['releaser_name']) || $withdrawal['releaser_name'] === 'Warehouse Officer') {
                $withdrawal['releaser_name'] = $whUser['name'];
            }
        }
    }

    // 2. Fetch items for this Withdrawal
    $itemsStmt = $pdo->prepare("
        SELECT wi.item_code, wi.quantity, COALESCE(i.item_name, wi.item_code) as item_name, COALESCE(i.unit, 'pcs') as unit
        FROM withdrawal_items wi
        LEFT JOIN inventory i ON wi.item_code = i.item_code
        WHERE wi.withdrawal_id = ?
    ");
    $itemsStmt->execute([$withdrawal['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedDate = date('M d, Y g:i A', strtotime($withdrawal['date_withdrawn']));

    echo json_encode([
        'success' => true,
        'withdrawal' => [
            'id' => (int)$withdrawal['id'],
            'withdrawal_no' => $withdrawal['withdrawal_no'],
            'project_name' => $withdrawal['project_name'] ?: 'N/A',
            'releaser_name' => $withdrawal['releaser_name'] ?: 'Warehouse Officer',
            'releaser_signature_path' => $withdrawal['releaser_signature_path'] ?: null,
            'received_by' => $withdrawal['received_by'] ?: 'N/A',
            'requestor_name' => $withdrawal['requestor_name'] ?: 'N/A',
            'remarks' => $withdrawal['remarks'] ?: 'No remarks provided.',
            'signature_path' => $withdrawal['signature_path'] ?: null,
            'photo_proof_path' => $withdrawal['photo_proof_path'] ?: null,
            'date_withdrawn' => $formattedDate
        ],
        'items' => $items
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
