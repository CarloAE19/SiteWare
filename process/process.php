<?php
session_start();

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

// === 2. LOAD DEPENDENCIES ===
require_once __DIR__ . '/../Connection/db.php';

// FCM HTTP v1 Push Notification Helper (JWT + OAuth2, no Composer required)
require_once __DIR__ . '/../Connection/fcm_helper.php';

// === 3. MODULE ROUTER ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Check if the action is expected to return JSON (AJAX / Fetch request)
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
               (strpos($action, 'fetch_') === 0) ||
               (in_array($action, ['live_sync', 'stock_in_scanned']));

    if ($is_ajax) {
        header('Content-Type: application/json');
    }

    try {
        // Route the request to the specific module file based on the action
        if (in_array($action, ['live_sync', 'stock_in_scanned', 'add', 'edit', 'delete'])) {
            require __DIR__ . '/module_inventory.php';
        } elseif (in_array($action, ['add_supplier', 'edit_supplier', 'delete_supplier'])) {
            require __DIR__ . '/module_suppliers.php';
        } elseif (in_array($action, ['add_user', 'edit_user', 'delete_user', 'update_profile'])) {
            require __DIR__ . '/module_users.php';
        }
        elseif (in_array($action, ['create_rs', 'approve_rs', 'reject_rs', 'stage_rs_materials', 'create_po', 'update_po_eta', 'mark_po_delivered', 'send_po_sms', 'log_po_delay', 'create_withdrawal', 'fetch_rs_data', 'fetch_rs_with_history', 'fetch_po_items', 'fetch_supplier_delivery_history', 'fetch_po_sms_preview', 'fetch_sms_threads', 'fetch_sms_messages', 'send_supplier_reply_sms', 'mark_sms_thread_read', 'fetch_combined_alerts'])) {
            require __DIR__ . '/module_transactions.php';
        } elseif ($action === 'submit_audit') {
            require __DIR__ . '/module_audit.php';
        } elseif (in_array($action, ['add_unit', 'edit_unit', 'delete_unit', 'add_category', 'edit_category', 'delete_category', 'add_project', 'edit_project', 'delete_project', 'update_login_bg', 'reset_login_bg'])) {
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
