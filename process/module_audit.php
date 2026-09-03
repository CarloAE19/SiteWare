<?php
// ==========================================
// AUDIT (WEEKLY RECOUNT) LOGIC
// ==========================================

if ($action === 'submit_audit') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) throw new Exception("Unauthorized.");

    // Validate inputs
    if (empty($_POST['item_code']) || !is_array($_POST['item_code'])) {
        throw new Exception("No items provided for recount.");
    }
    $item_codes = $_POST['item_code'];
    $system_qtys = $_POST['system_qty'] ?? [];
    $physical_qtys = $_POST['physical_qty'] ?? [];

    $item_count = count($item_codes);
    if ($item_count === 0 || count($system_qtys) !== $item_count || count($physical_qtys) !== $item_count) {
        throw new Exception("Invalid recount payload: item count mismatch.");
    }

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

    $conducted_by = $_SESSION['user_id'];
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO inventory_audits (audit_month, conducted_by, remarks) VALUES (?, ?, ?)");
        $stmt->execute([$audit_week, $conducted_by, $remarks]);
        $audit_id = $pdo->lastInsertId();

        $auditItemStmt = $pdo->prepare("INSERT INTO audit_items (audit_id, item_code, system_qty, physical_qty, discrepancy) VALUES (?, ?, ?, ?, ?)");
        $updateInvStmt = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE item_code = ?");
        $updateStatusStmt = $pdo->prepare("
            UPDATE inventory i 
            JOIN units u ON i.unit = u.unit_name 
            SET i.status = CASE 
                WHEN i.quantity <= 0 THEN 'Out of Stock' 
                WHEN i.quantity <= u.reorder_level THEN 'Low Stock' 
                ELSE 'In Stock' 
            END 
            WHERE i.item_code = ?
        ");

        $discrepancyCount = 0;
        for ($i = 0; $i < $item_count; $i++) {
            $item_code = trim($item_codes[$i]);
            $sys_qty   = max(0, (int)$system_qtys[$i]);
            $phys_qty  = max(0, (int)$physical_qtys[$i]);
            $diff      = $phys_qty - $sys_qty;

            $auditItemStmt->execute([$audit_id, $item_code, $sys_qty, $phys_qty, $diff]);

            if ($diff !== 0) {
                $discrepancyCount++;
                $updateInvStmt->execute([$phys_qty, $item_code]);
                $updateStatusStmt->execute([$item_code]);
            }
        }

        $pdo->prepare("UPDATE inventory_audits SET total_discrepancy_items = ? WHERE id = ?")->execute([$discrepancyCount, $audit_id]);

        // In-app notifications stored in database
        if ($discrepancyCount > 0) {
            $alertMsg = "The $audit_week weekly recount is complete. Found $discrepancyCount item(s) with discrepancies. Inventory has been adjusted.";
            $notifStmt = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES (?, 'Weekly Audit Discrepancy Alert', ?)");
            $notifStmt->execute(['admin', $alertMsg]);
            $notifStmt->execute(['management', $alertMsg]);
            $notifStmt->execute(['warehouse', $alertMsg]);
        } else {
            $successMsg = "The $audit_week weekly recount was completed successfully. All physical stocks match the system records exactly.";
            $notifStmt = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES (?, 'Weekly Audit Completed', ?)");
            $notifStmt->execute(['admin', $successMsg]);
            $notifStmt->execute(['management', $successMsg]);
            $notifStmt->execute(['warehouse', $successMsg]);
        }

        // Database transaction committed immediately
        $pdo->commit();

        $feedbackMsg = "Weekly recount submitted successfully. Inventory has been adjusted.";

        if (!empty($is_ajax)) {
            echo json_encode([
                'status' => 'success',
                'success' => true,
                'message' => $feedbackMsg,
                'audit_id' => $audit_id,
                'audit_week' => $audit_week,
                'discrepancies' => $discrepancyCount
            ]);

            // If FastCGI is available, flush buffer to client immediately
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }

        // SMART PUSH NOTIFICATIONS LOGIC (Non-blocking and safe)
        try {
            if ($discrepancyCount > 0) {
                $alertMsg = "The $audit_week weekly recount is complete. Found $discrepancyCount item(s) with discrepancies. Inventory has been adjusted.";
                sendPushNotification($pdo, 'Weekly Audit Discrepancy Alert', $alertMsg, 'admin', null);
                sendPushNotification($pdo, 'Weekly Audit Discrepancy Alert', $alertMsg, 'management', null);
                sendPushNotification($pdo, 'Weekly Audit Discrepancy Alert', $alertMsg, 'warehouse', null);
            } else {
                $successMsg = "The $audit_week weekly recount was completed successfully. All physical stocks match the system records exactly.";
                sendPushNotification($pdo, 'Weekly Audit Completed', $successMsg, 'admin', null);
                sendPushNotification($pdo, 'Weekly Audit Completed', $successMsg, 'management', null);
                sendPushNotification($pdo, 'Weekly Audit Completed', $successMsg, 'warehouse', null);
            }
        } catch (Throwable $pushErr) {
            error_log("Push notification notice in submit_audit: " . $pushErr->getMessage());
        }

        if (empty($is_ajax)) {
            $_SESSION['message'] = $feedbackMsg;
            $_SESSION['msg_type'] = "success";
            header("Location: ../audit"); 
            exit;
        }
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
?>