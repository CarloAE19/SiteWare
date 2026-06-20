<?php
session_start();

// === 1. GLOBAL AUTHENTICATION CHECK ===
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login");
    exit;
}

// === 2. LOAD DEPENDENCIES ===
require_once '../Connection/db.php';

// FCM HTTP v1 Push Notification Helper (JWT + OAuth2, no Composer required)
require_once '../Connection/fcm_helper.php';

// === 3. MODULE ROUTER ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // Route the request to the specific module file based on the action
        if (in_array($action, ['live_sync', 'stock_in_scanned', 'add', 'edit', 'delete'])) {
            require 'module_inventory.php';
        } elseif (in_array($action, ['add_supplier', 'edit_supplier', 'delete_supplier'])) {
            require 'module_suppliers.php';
        } elseif (in_array($action, ['add_user', 'edit_user', 'delete_user', 'update_profile'])) {
            require 'module_users.php';
        }
        // FIXED: Added 'fetch_rs_data' right here so the Scanner can communicate with the backend!
        elseif (in_array($action, ['create_rs', 'approve_rs', 'reject_rs', 'create_po', 'mark_po_delivered', 'send_po_sms', 'log_po_delay', 'create_withdrawal', 'fetch_rs_data', 'fetch_rs_with_history', 'fetch_po_items', 'fetch_supplier_delivery_history'])) {
            require 'module_transactions.php';
        } elseif ($action === 'submit_audit') {
            require 'module_audit.php';
        } elseif (in_array($action, ['add_unit', 'edit_unit', 'delete_unit', 'add_category', 'edit_category', 'delete_category', 'add_project', 'edit_project', 'delete_project', 'update_login_bg', 'reset_login_bg'])) {
            require 'module_settings.php';
        } else {
            throw new Exception("Invalid system action requested: " . htmlspecialchars($action));
        }
    } catch (Exception $e) {
        // Global Error Handler: Catches errors from ANY module seamlessly
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
