<?php
// ============================================================================
// MODULE TRANSACTIONS ROUTER
// Routes transaction requests (Requisitions, POs, Withdrawals, Viber, Alerts)
// to their respective modular handlers under process/transactions/
// ============================================================================

if (!isset($action)) {
    $action = $_POST['action'] ?? '';
}

// 1. Requisition Actions
if (in_array($action, ['fetch_rs_data', 'fetch_rs_with_history', 'create_rs', 'edit_rs', 'approve_rs', 'stage_rs_materials', 'reject_rs'])) {
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
// 4. Viber Messaging Actions
elseif (in_array($action, ['log_viber_order_sent', 'fetch_po_viber_preview'])) {
    require __DIR__ . '/transactions/viber_actions.php';
}
// 5. Alert Actions
elseif ($action === 'fetch_combined_alerts') {
    require __DIR__ . '/transactions/alert_actions.php';
} else {
    throw new Exception("Unknown transaction action: " . htmlspecialchars($action));
}