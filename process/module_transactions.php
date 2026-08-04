<?php
// ============================================================================
// MODULE TRANSACTIONS ROUTER
// Routes transaction requests (Requisitions, POs, Withdrawals, SMS, Alerts)
// to their respective modular handlers under process/transactions/
// ============================================================================

if (!isset($action)) {
    $action = $_POST['action'] ?? '';
}

// 1. Requisition Actions
if (in_array($action, ['fetch_rs_data', 'fetch_rs_with_history', 'create_rs', 'approve_rs', 'stage_rs_materials', 'reject_rs'])) {
    require __DIR__ . '/transactions/rs_actions.php';
}
// 2. Purchase Order Actions
elseif (in_array($action, ['fetch_supplier_delivery_history', 'fetch_po_items', 'fetch_po_details', 'create_po', 'mark_po_delivered', 'log_po_delay', 'update_po_eta'])) {
    require __DIR__ . '/transactions/po_actions.php';
}
// 3. Withdrawal Actions
elseif ($action === 'create_withdrawal') {
    require __DIR__ . '/transactions/withdrawal_actions.php';
}
// 4. SMS Messaging Actions
elseif (in_array($action, ['send_po_sms', 'fetch_po_sms_preview', 'fetch_sms_threads', 'fetch_sms_messages', 'send_supplier_reply_sms', 'mark_sms_thread_read'])) {
    require __DIR__ . '/transactions/sms_actions.php';
}
// 5. Alert Actions
elseif ($action === 'fetch_combined_alerts') {
    require __DIR__ . '/transactions/alert_actions.php';
}
else {
    throw new Exception("Unknown transaction action: " . htmlspecialchars($action));
}