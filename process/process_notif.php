<?php
session_start();

// Ensure the user is logged in before allowing notification updates
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once '../Connection/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Mark a single notification as read
    if ($action === 'read_notif') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$_POST['notif_id']]);
        echo json_encode(['status' => 'success']);
        exit;
        
    // Mark ALL notifications as read for this specific user
    } elseif ($action === 'read_all_notifs') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE target_user_id = ? OR target_role = ?")
            ->execute([$_SESSION['user_id'], $_SESSION['user_role']]);
        echo json_encode(['status' => 'success']);
        exit;
    }
}