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
        echo json_encode(['success' => true, 'status' => 'success', 'message' => 'All notifications marked as read.']);
        exit;

    // Clear / Dismiss ALL notifications for the current user (Role-Isolated)
    } elseif ($action === 'clear_all_notifs') {
        $userId = (int)$_SESSION['user_id'];
        
        // 1. Set user's cleared timestamp to now
        $pdo->prepare("UPDATE users SET notifications_cleared_at = NOW() WHERE id = ?")->execute([$userId]);
        
        // 2. Mark user-targeted notifications as read
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE target_user_id = ?")->execute([$userId]);

        echo json_encode(['success' => true, 'status' => 'success', 'message' => 'All notifications cleared.']);
        exit;

    // Clear / Dismiss a SINGLE notification for the current user
    } elseif ($action === 'clear_single_notif') {
        $notifId = (int)($_POST['notif_id'] ?? 0);
        $userId = (int)$_SESSION['user_id'];

        if ($notifId > 0) {
            // If targeted directly to this user, delete the row
            $del = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND target_user_id = ?");
            $del->execute([$notifId, $userId]);
            
            // Otherwise ensure it is marked as read
            if ($del->rowCount() === 0) {
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notifId]);
            }
        }
        echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Notification dismissed.']);
        exit;
        
    // --- Save Firebase Device Token ---
    } elseif ($action === 'save_fcm_token') {
        $token = trim($_POST['fcm_token'] ?? '');
        if (!empty($token)) {
            $stmt = $pdo->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
            $stmt->execute([$token, $_SESSION['user_id']]);
            echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Token saved successfully.']);
        } else {
            echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Empty token provided.']);
        }
        exit;
    }
}

// GET endpoint: Get unread notification count for live dashboard polling
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_unread_count') {
    $role = $_SESSION['user_role'];
    $userId = (int)$_SESSION['user_id'];

    // Get user's cleared_at timestamp
    $uStmt = $pdo->prepare("SELECT notifications_cleared_at FROM users WHERE id = ?");
    $uStmt->execute([$userId]);
    $clearedAt = $uStmt->fetchColumn();

    if ($role === 'requestor') {
        $sql = "
            SELECT COUNT(*) FROM notifications 
            WHERE (target_user_id = ? OR target_role = 'requestor') 
              AND is_read = 0
              AND title NOT LIKE '%PO%' 
              AND title NOT LIKE '%Purchase Order%'
              AND message NOT LIKE '%PO-%' 
              AND message NOT LIKE '%Purchase Order%'
              AND title NOT LIKE '%Audit%'
              AND message NOT LIKE '%Audit%'
        ";
        $params = [$userId];
        if (!empty($clearedAt)) {
            $sql .= " AND created_at > ?";
            $params[] = $clearedAt;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $sql = "
            SELECT COUNT(*) FROM notifications 
            WHERE (target_user_id = ? OR target_role = ? OR target_role = 'all')
              AND is_read = 0
        ";
        $params = [$userId, $role];
        if (!empty($clearedAt)) {
            $sql .= " AND created_at > ?";
            $params[] = $clearedAt;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    $unreadCount = (int)$stmt->fetchColumn();
    echo json_encode(['success' => true, 'status' => 'success', 'unread_count' => $unreadCount]);
    exit;
}