<?php
require_once __DIR__ . '/../Connection/db.php';
init_secure_session();

// Initialize action & request method immediately
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Check if the action is expected to return JSON (AJAX / Fetch request)
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
           (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
           (strpos($action, 'fetch_') === 0) ||
           (in_array($action, ['live_sync', 'stock_in_scanned', 'verify_current_password', 'change_password_modal', 'unlock_screen', 'lock_screen', 'ping_session', 'update_idle_settings', 'submit_audit', 'create_backup', 'restore_backup', 'delete_backup', 'fetch_backups', 'create_rs', 'edit_rs', 'approve_rs', 'reject_rs', 'stage_rs_materials']));

if ($is_ajax) {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
}

// === 1. GLOBAL AUTHENTICATION CHECK ===
if (!isset($_SESSION['user_id'])) {
    if ($is_ajax) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in again.']);
        exit;
    }

    header("Location: ../login");
    exit;
}

// === 2. IMMEDIATE ACCOUNT STATUS REVOCATION CHECK (Enterprise RBAC Standard) ===
if (!defined('DB_OFFLINE') && isset($pdo) && $pdo !== null) {
    $statusCheck = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $statusCheck->execute([$_SESSION['user_id']]);
    $userActiveStatus = $statusCheck->fetchColumn();

    if ($userActiveStatus !== false && strtolower($userActiveStatus) === 'inactive') {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        if ($is_ajax) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Your account has been deactivated. Access revoked.']);
            exit;
        }

        header("Location: ../login?deactivated=1");
        exit;
    }
}

// === 2.5 SERVER-SIDE SCREEN LOCK GUARD (Zero-Trust Anti-Tamper) ===
if (!empty($_SESSION['screen_locked']) && !in_array($action, ['unlock_screen', 'lock_screen', 'logout', 'ping_session', 'fetch_combined_alerts'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(423); // 423 Locked
    echo json_encode([
        'success' => false,
        'status' => 'locked',
        'message' => 'Screen is locked due to inactivity. Enter your password to unlock before making requests.'
    ]);
    exit;
}

// FCM HTTP v1 Push Notification Helper (JWT + OAuth2, no Composer required)
require_once __DIR__ . '/../Connection/fcm_helper.php';

// === 3. MODULE ROUTER ===
if ($requestMethod === 'POST' || (strpos($action, 'fetch_') === 0 && !empty($action))) {

    // Anti-Double Submit / Rapid Spam Throttling on Mutating Actions
    $mutatingActions = ['create_rs', 'create_withdrawal', 'create_po', 'mark_po_delivered', 'cancel_po', 'submit_audit', 'add', 'edit', 'delete', 'add_user', 'add_supplier', 'create_backup', 'restore_backup', 'delete_backup', 'update_idle_settings'];
    if (in_array($action, $mutatingActions)) {
        $throttleKey = 'post_' . $action . '_user_' . $_SESSION['user_id'];
        $throttle = check_rate_limit($throttleKey, 1, 2);
        if (!$throttle['allowed']) {
            throw new Exception("Please wait a moment before submitting again (anti-duplicate protection).");
        }
        record_rate_limit_attempt($throttleKey);
    }

    try {
        // Route the request to the specific module file based on the action
        if (in_array($action, ['live_sync', 'stock_in_scanned', 'add', 'edit', 'delete'])) {
            require __DIR__ . '/module_inventory.php';
        } elseif (in_array($action, ['add_supplier', 'edit_supplier', 'delete_supplier'])) {
            require __DIR__ . '/module_suppliers.php';
        } elseif (in_array($action, ['add_user', 'edit_user', 'delete_user', 'toggle_user_status', 'update_profile', 'verify_current_password', 'change_password_modal', 'unlock_screen', 'lock_screen', 'ping_session'])) {
            require __DIR__ . '/module_users.php';
        }
        elseif (in_array($action, ['create_rs', 'edit_rs', 'approve_rs', 'reject_rs', 'stage_rs_materials', 'create_po', 'update_po_eta', 'mark_po_out_for_delivery', 'upload_po_receipt', 'mark_po_delivered', 'cancel_po', 'log_viber_order_sent', 'log_po_delay', 'create_withdrawal', 'fetch_rs_data', 'fetch_rs_with_history', 'fetch_po_items', 'fetch_po_details', 'fetch_supplier_delivery_history', 'fetch_po_viber_preview', 'fetch_combined_alerts'])) {
            require __DIR__ . '/module_transactions.php';
        } elseif ($action === 'submit_audit') {
            require __DIR__ . '/module_audit.php';
        } elseif (in_array($action, ['create_backup', 'restore_backup', 'delete_backup', 'fetch_backups', 'download_backup'])) {
            require __DIR__ . '/module_backup.php';
        } elseif (in_array($action, ['add_unit', 'edit_unit', 'delete_unit', 'add_category', 'edit_category', 'delete_category', 'add_project', 'edit_project', 'delete_project', 'toggle_project_status', 'fetch_project_details', 'update_login_bg', 'reset_login_bg', 'update_login_blur', 'update_idle_settings'])) {
            require __DIR__ . '/module_settings.php';
        } else {
            throw new Exception("Invalid system action requested: " . htmlspecialchars($action));
        }
    } catch (Throwable $e) {
        if ($is_ajax) {
            if (ob_get_length()) {
                ob_clean();
            }
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }

        // Global Error Handler for standard forms: Catches errors from ANY module seamlessly
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['msg_type'] = "danger";
        $redirect = $_SERVER['HTTP_REFERER'] ?? '../index';
        header("Location: " . str_replace('.php', '', $redirect));
        exit;
    }
} else {
    header("Location: ../index");
    exit;
}
