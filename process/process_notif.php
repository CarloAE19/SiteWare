<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../Connection/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Mark a single notification as read
    if ($action === 'read_notif') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$_POST['notif_id']]);
        echo json_encode(['status' => 'success']);
        exit;
        
    // Mark ALL notifications as read
    } elseif ($action === 'read_all_notifs') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE target_user_id = ? OR target_role = ?")
            ->execute([$_SESSION['user_id'], $_SESSION['user_role']]);
        echo json_encode(['status' => 'success']);
        exit;
        
    // --- NEW: Save Firebase Device Token ---
    } elseif ($action === 'save_fcm_token') {
        $token = trim($_POST['fcm_token']);
        if (!empty($token)) {
            // Update the user's record with their device ID
            $stmt = $pdo->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
            $stmt->execute([$token, $_SESSION['user_id']]);
            echo json_encode(['status' => 'success', 'message' => 'Token saved.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Empty token.']);
        }
        exit;
    }
}