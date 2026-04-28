<?php
// ==========================================
// AUDIT (WEEKLY RECOUNT) LOGIC
// ==========================================

if ($action === 'submit_audit') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) throw new Exception("Unauthorized.");

    // Generate a clear weekly period label, e.g. "Week 11 — Mar 10–16, 2026"
    $weekNum   = date('W');                          // ISO week number
    $weekStart = new DateTime();                     // today
    $weekStart->setISODate((int)date('o'), (int)$weekNum, 1); // Monday of this week
    $weekEnd   = clone $weekStart;
    $weekEnd->modify('+6 days');                     // Sunday of this week
    if ($weekStart->format('Y') !== $weekEnd->format('Y')) {
        $audit_week = 'Week ' . (int)$weekNum . ' — ' . $weekStart->format('M j, Y') . '–' . $weekEnd->format('M j, Y');
    } elseif ($weekStart->format('m') !== $weekEnd->format('m')) {
        $audit_week = 'Week ' . (int)$weekNum . ' — ' . $weekStart->format('M j') . '–' . $weekEnd->format('M j, Y');
    } else {
        $audit_week = 'Week ' . (int)$weekNum . ' — ' . $weekStart->format('M j') . '–' . $weekEnd->format('j, Y');
    }

    $conducted_by = $_SESSION['user_id']; $remarks = $_POST['remarks'] ?? '';
    $item_codes = $_POST['item_code']; $system_qtys = $_POST['system_qty']; $physical_qtys = $_POST['physical_qty'];

    $stmt = $pdo->prepare("INSERT INTO inventory_audits (audit_month, conducted_by, remarks) VALUES (?, ?, ?)");
    $stmt->execute([$audit_week, $conducted_by, $remarks]);
    $audit_id = $pdo->lastInsertId();

    $auditItemStmt = $pdo->prepare("INSERT INTO audit_items (audit_id, item_code, system_qty, physical_qty, discrepancy) VALUES (?, ?, ?, ?, ?)");
    $updateInvStmt = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE item_code = ?");
    $updateStatusStmt = $pdo->prepare("UPDATE inventory SET status = CASE WHEN quantity <= 0 THEN 'Out of Stock' WHEN quantity <= 10 THEN 'Low Stock' ELSE 'In Stock' END WHERE item_code = ?");
    
    $discrepancyCount = 0;
    for ($i = 0; $i < count($item_codes); $i++) {
        $sys_qty = (int)$system_qtys[$i]; $phys_qty = (int)$physical_qtys[$i]; $diff = $phys_qty - $sys_qty;
        $auditItemStmt->execute([$audit_id, $item_codes[$i], $sys_qty, $phys_qty, $diff]);
        
        if ($diff !== 0) {
            $discrepancyCount++;
            $updateInvStmt->execute([$phys_qty, $item_codes[$i]]);
            $updateStatusStmt->execute([$item_codes[$i]]);
        }
    }

    $pdo->prepare("UPDATE inventory_audits SET total_discrepancy_items = ? WHERE id = ?")->execute([$discrepancyCount, $audit_id]);
    
    // SMART PUSH NOTIFICATIONS LOGIC
    if ($discrepancyCount > 0) {
        $alertMsg = "The $audit_week weekly recount is complete. Found $discrepancyCount item(s) with discrepancies. Inventory has been adjusted.";
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'Weekly Audit Discrepancy Alert', ?)")->execute([$alertMsg]);
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'Weekly Audit Discrepancy Alert', ?)")->execute([$alertMsg]);
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('warehouse', 'Weekly Audit Discrepancy Alert', ?)")->execute([$alertMsg]);
        sendPushNotification($pdo, 'Weekly Audit Discrepancy Alert', $alertMsg, 'admin', null);
        sendPushNotification($pdo, 'Weekly Audit Discrepancy Alert', $alertMsg, 'management', null);
        sendPushNotification($pdo, 'Weekly Audit Discrepancy Alert', $alertMsg, 'warehouse', null);
    } else {
        $successMsg = "The $audit_week weekly recount was completed successfully. All physical stocks match the system records exactly.";
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'Weekly Audit Completed', ?)")->execute([$successMsg]);
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'Weekly Audit Completed', ?)")->execute([$successMsg]);
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('warehouse', 'Weekly Audit Completed', ?)")->execute([$successMsg]);
        sendPushNotification($pdo, 'Weekly Audit Completed', $successMsg, 'admin', null);
        sendPushNotification($pdo, 'Weekly Audit Completed', $successMsg, 'management', null);
        sendPushNotification($pdo, 'Weekly Audit Completed', $successMsg, 'warehouse', null);
    }

    $_SESSION['message'] = "Weekly recount submitted successfully. Inventory has been adjusted.";
    $_SESSION['msg_type'] = "success";
    header("Location: ../audit"); 
    exit;
}
?>