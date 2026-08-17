<?php
require_once __DIR__ . '/../Connection/db.php';
init_secure_session();

// === 1. GLOBAL AUTHENTICATION CHECK ===
if (!isset($_SESSION['user_id'])) {
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
               (isset($_POST['action']) && strpos($_POST['action'], 'fetch_') === 0);

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in again.']);
        exit;
    }

    header("Location: ../login");
    exit;
}

// FCM HTTP v1 Push Notification Helper (JWT + OAuth2, no Composer required)
require_once __DIR__ . '/../Connection/fcm_helper.php';

// === 3. MODULE ROUTER ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Check if the action is expected to return JSON (AJAX / Fetch request)
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
               (strpos($action, 'fetch_') === 0) ||
               (in_array($action, ['live_sync', 'stock_in_scanned', 'verify_current_password', 'change_password_modal']));

    if ($is_ajax) {
        header('Content-Type: application/json');
    }

    // Anti-Double Submit / Rapid Spam Throttling on Mutating Actions
    $mutatingActions = ['create_rs', 'create_withdrawal', 'create_po', 'mark_po_delivered', 'submit_audit', 'add', 'edit', 'delete', 'add_user', 'add_supplier'];
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
        } elseif (in_array($action, ['add_user', 'edit_user', 'delete_user', 'toggle_user_status', 'update_profile', 'verify_current_password', 'change_password_modal'])) {
            require __DIR__ . '/module_users.php';
        }
        elseif (in_array($action, ['create_rs', 'approve_rs', 'reject_rs', 'stage_rs_materials', 'create_po', 'update_po_eta', 'mark_po_delivered', 'log_viber_order_sent', 'log_po_delay', 'create_withdrawal', 'fetch_rs_data', 'fetch_rs_with_history', 'fetch_po_items', 'fetch_po_details', 'fetch_supplier_delivery_history', 'fetch_po_viber_preview', 'fetch_combined_alerts'])) {
            require __DIR__ . '/module_transactions.php';
        } elseif ($action === 'submit_audit') {
            require __DIR__ . '/module_audit.php';
        } elseif (in_array($action, ['add_unit', 'edit_unit', 'delete_unit', 'add_category', 'edit_category', 'delete_category', 'add_project', 'edit_project', 'delete_project', 'update_login_bg', 'reset_login_bg', 'update_login_blur'])) {
            require __DIR__ . '/module_settings.php';
        } else {
            throw new Exception("Invalid system action requested: " . htmlspecialchars($action));
        }
    } catch (Throwable $e) {
        if ($is_ajax) {
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
