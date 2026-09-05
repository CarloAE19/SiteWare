<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../Connection/db.php';

// Check CSRF token on POST requests if provided or available
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($csrfToken) && function_exists('validate_csrf_token') && !validate_csrf_token($csrfToken)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired CSRF token.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Mark a single notification as read
    if ($action === 'read_notif') {
        $notifId = (int)($_POST['notif_id'] ?? 0);
        if ($notifId > 0) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notifId]);
        }
        echo json_encode(['status' => 'success', 'message' => 'Notification marked as read.']);
        exit;
        
    // Mark ALL notifications as read
    } elseif ($action === 'read_all_notifs') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE target_user_id = ? OR target_role = ? OR target_role = 'all'")
            ->execute([$_SESSION['user_id'], $_SESSION['user_role']]);
        echo json_encode(['status' => 'success', 'message' => 'All notifications marked as read.']);
        exit;
        
    // --- Save Firebase Device Token ---
    } elseif ($action === 'save_fcm_token') {
        $token = trim($_POST['fcm_token'] ?? '');
        if (!empty($token)) {
            $stmt = $pdo->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
            $stmt->execute([$token, $_SESSION['user_id']]);
            echo json_encode(['status' => 'success', 'message' => 'Token saved successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Empty token provided.']);
        }
        exit;
    }
}

// GET endpoint: Get unread notification count for live dashboard polling
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_unread_count') {
    $role = $_SESSION['user_role'];
    $userId = (int)$_SESSION['user_id'];

    if ($role === 'requestor') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE (target_user_id = ? OR target_role = 'requestor') 
              AND is_read = 0
              AND title NOT LIKE '%PO%' 
              AND title NOT LIKE '%Purchase Order%'
              AND message NOT LIKE '%PO-%' 
              AND message NOT LIKE '%Purchase Order%'
        ");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE (target_user_id = ? OR target_role = ? OR target_role = 'all')
              AND is_read = 0
        ");
        $stmt->execute([$userId, $role]);
    }
    $unreadCount = (int)$stmt->fetchColumn();
    echo json_encode(['status' => 'success', 'unread_count' => $unreadCount]);
    exit;
}