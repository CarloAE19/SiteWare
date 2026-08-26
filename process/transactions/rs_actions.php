<?php
// ==========================================
// REQUISITIONS (RS) ACTIONS
// ==========================================

// --- AJAX: FETCH RS DATA VIA QR SCANNER ---
if ($action === 'fetch_rs_data') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
        exit;
    }

    $input_raw = trim($_POST['rs_no'] ?? '');
    $clean = trim(urldecode($input_raw));
    $clean = str_ireplace('REQ-DATA:', '', $clean);
    $clean = trim($clean);
    
    // Normalize alphanumeric version (e.g. RS20267324 or 20267324)
    $alphanumeric = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($clean));
    $digitsOnly = preg_replace('/[^0-9]/', '', $clean);

    $stmt = $pdo->prepare("
        SELECT id, rs_no, requestor_name, project_name, status, type 
        FROM requisitions 
        WHERE rs_no = ? 
           OR UPPER(rs_no) = ?
           OR REPLACE(UPPER(rs_no), '-', '') = ?
           OR REPLACE(REPLACE(UPPER(rs_no), '-', ''), 'RS', '') = ?
           OR rs_no LIKE ?
        ORDER BY (rs_no = ?) DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([
        $clean,
        strtoupper($clean),
        $alphanumeric,
        $digitsOnly ?: $alphanumeric,
        '%' . $clean . '%',
        $clean
    ]);
    $rs = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rs) {
        echo json_encode(['status' => 'error', 'message' => 'Requisition Slip not found.']);
        exit;
    }

    if ($rs['type'] === 'restock' || $rs['project_name'] === 'Warehouse Restock') {
        echo json_encode(['status' => 'error', 'message' => 'RS Number not found.']);
        exit;
    }

    if ($rs['status'] === 'Released') {
        echo json_encode(['status' => 'error', 'message' => 'This Requisition Slip has already been released and is expired.']);
        exit;
    }

    if (!in_array($rs['status'], ['Approved', 'PO Created', 'Staged (Ready for Pickup)'])) {
        echo json_encode(['status' => 'error', 'message' => 'This Requisition Slip has not been Approved or Staged yet. Current status: ' . $rs['status']]);
        exit;
    }

    $itemStmt = $pdo->prepare("
        SELECT ri.item_code, ri.quantity, COALESCE(i.item_name, ri.new_item_name) as item_name, ri.is_new_item, ri.new_category, ri.new_unit 
        FROM requisition_items ri 
        LEFT JOIN inventory i ON ri.item_code = i.item_code 
        WHERE ri.requisition_id = ?
    ");
    $itemStmt->execute([$rs['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'rs_no' => $rs['rs_no'],
        'requestor_name' => $rs['requestor_name'] ?? 'N/A',
        'project_name' => $rs['project_name'],
        'rs_status' => $rs['status'],
        'items' => $items
    ]);
    exit;
}

// --- AJAX: FETCH RS ITEMS WITH SUPPLIER HISTORY ---
elseif ($action === 'fetch_rs_with_history') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
        exit;
    }

    $rs_id = $_POST['rs_id'] ?? 0;

    // Only return items that are Approved (excludes Rejected items from Partially Approved RSes)
    $stmt = $pdo->prepare("
        SELECT ri.item_code, ri.quantity, COALESCE(i.item_name, ri.new_item_name) as item_name, ri.is_new_item, ri.new_category, ri.new_unit 
        FROM requisition_items ri 
        LEFT JOIN inventory i ON ri.item_code = i.item_code 
        WHERE ri.requisition_id = ? AND ri.item_status = 'Approved'
    ");
    $stmt->execute([$rs_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($items)) {
        $histStmt = $pdo->prepare("
            SELECT s.company_name, po.created_at 
            FROM po_items pi 
            JOIN purchase_orders po ON pi.po_id = po.id 
            JOIN suppliers s ON po.supplier_id = s.id 
            WHERE pi.item_code = ? AND po.status != 'Generated'
            ORDER BY po.created_at DESC 
            LIMIT 1
        ");

        foreach ($items as &$item) {
            $histStmt->execute([$item['item_code']]);
            $history = $histStmt->fetch(PDO::FETCH_ASSOC);

            if ($history) {
                $item['last_supplier'] = $history['company_name'];
                $item['last_purchased'] = date('M d, Y', strtotime($history['created_at']));
            } else {
                $item['last_supplier'] = '<span class="text-muted fst-italic">No History</span>';
                $item['last_purchased'] = '';
            }
        }
    }

    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

// --- CREATE REQUISITION ---
elseif ($action === 'create_rs') {
    $type = $_POST['type'] ?? 'project';
    $projectName = ($type === 'restock') ? 'Warehouse Restock' : $_POST['project_name'];
    $reqId = $_SESSION['user_id'] ?? $_POST['requestor_id'];
    $reqName = $_SESSION['user_name'] ?? $_POST['requestor_name'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO requisitions (rs_no, requestor_id, requestor_name, project_name, urgency, remarks, status, type) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending Approval', ?)
        ");
        $stmt->execute([
            $_POST['rs_no'], 
            $reqId, 
            $reqName, 
            $projectName, 
            $_POST['urgency'], 
            $_POST['remarks'], 
            $type
        ]);
        $requisition_id = $pdo->lastInsertId();

        $items = $_POST['items'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $isNewItems = $_POST['is_new_items'] ?? [];
        $newItemNames = $_POST['new_item_names'] ?? [];
        $newCategories = $_POST['new_categories'] ?? [];
        $newUnits = $_POST['new_units'] ?? [];
        $itemNotes = $_POST['item_notes'] ?? [];

        $itemStmt = $pdo->prepare("
            INSERT INTO requisition_items (requisition_id, item_code, quantity, is_new_item, new_item_name, new_category, new_unit, item_notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        for ($i = 0; $i < count($items); $i++) {
            $isNew = !empty($isNewItems[$i]) ? 1 : 0;
            $code = trim($items[$i] ?? '');
            $qty = (int)($quantities[$i] ?? 0);
            $name = $isNew ? trim($newItemNames[$i] ?? '') : null;
            $cat = $isNew ? trim($newCategories[$i] ?? 'Materials') : null;
            $unit = $isNew ? trim($newUnits[$i] ?? 'pcs') : null;
            $note = trim($itemNotes[$i] ?? '') ?: null;

            if ($isNew && empty($code)) {
                $code = 'ITM-' . rand(1000, 9999);
            }

            if (!empty($code) && $qty > 0) {
                $itemStmt->execute([$requisition_id, $code, $qty, $isNew, $name, $cat, $unit, $note]);
            }
        }

        // Conflict Check Engine
        $conflicts = [];
        $conflictItems = [];
        if ($type !== 'restock') {
            $conflictStmt = $pdo->prepare("
                SELECT ri.item_code, i.item_name, i.quantity as current_stock,
                       COALESCE(p.total_pending, 0) as total_pending
                FROM requisition_items ri
                LEFT JOIN inventory i ON ri.item_code = i.item_code
                LEFT JOIN (
                    SELECT ri2.item_code, SUM(ri2.quantity) as total_pending
                    FROM requisition_items ri2
                    JOIN requisitions r2 ON ri2.requisition_id = r2.id
                    WHERE r2.status = 'Pending Approval'
                    GROUP BY ri2.item_code
                ) p ON ri.item_code = p.item_code
                WHERE ri.requisition_id = ? AND COALESCE(p.total_pending, 0) > i.quantity
            ");
            $conflictStmt->execute([$requisition_id]);
            $conflicts = $conflictStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($conflicts as $c) {
                $conflictItems[] = $c['item_name'];
            }
        }

        $conflictWarning = "";
        if (!empty($conflictItems)) {
            $conflictWarning = " ⚠️ Conflict alert: Stock deficit for " . implode(', ', $conflictItems) . ".";
        }

        $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'New Requisition Pending', ?)");
        $notifText = ($type === 'restock')
            ? "{$_POST['requestor_name']} submitted a Warehouse Restock request ({$_POST['rs_no']})."
            : "{$_POST['requestor_name']} submitted {$_POST['rs_no']} for {$projectName}." . $conflictWarning;

        $notif->execute([$notifText]);
        sendPushNotification($pdo, 'New Requisition Pending', $notifText, 'management', null);

        // If there is a conflict, notify the submitting requestor and other affected requestors
        if (!empty($conflicts)) {
            // 1. Notify self
            $msgToSelf = "Requisition submitted, but warning: stock conflicts detected for " . implode(', ', $conflictItems) . ".";
            $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Conflict Warning', ?)")
                ->execute([$_POST['requestor_id'], $msgToSelf]);
            sendPushNotification($pdo, 'Requisition Conflict Warning', $msgToSelf, null, (int)$_POST['requestor_id']);

            // 2. Notify other requestors who also have pending requests for the same items
            foreach ($conflicts as $c) {
                $otherReqStmt = $pdo->prepare("
                    SELECT DISTINCT r.requestor_id, r.requestor_name, r.rs_no, r.project_name
                    FROM requisition_items ri
                    JOIN requisitions r ON ri.requisition_id = r.id
                    WHERE ri.item_code = ? AND r.status = 'Pending Approval' AND r.id != ?
                ");

            $otherReqStmt->execute([$c['item_code'], $requisition_id]);
            $otherRequestors = $otherReqStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($otherRequestors as $other) {
                $msgToOther = "Conflict Alert: {$_POST['requestor_name']} requested {$c['item_name']} for {$projectName}, which conflicts with your pending request {$other['rs_no']} for {$other['project_name']}.";
                $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Conflict Alert', ?)")
                    ->execute([$other['requestor_id'], $msgToOther]);
                sendPushNotification($pdo, 'Requisition Conflict Alert', $msgToOther, null, (int)$other['requestor_id']);
            }
        }

        // 3. Notify Admin of the conflict
        $msgToAdmin = "Conflict Alert: Requisition {$_POST['rs_no']} submitted by {$_POST['requestor_name']} for {$projectName} has stock conflicts: " . implode(', ', $conflictItems) . ".";
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'Requisition Conflict Alert', ?)")
            ->execute([$msgToAdmin]);
        sendPushNotification($pdo, 'Requisition Conflict Alert', $msgToAdmin, 'admin', null);
    }

        $pdo->commit();

        $successMsg = ($type === 'restock')
            ? "Restock request created successfully and sent to Management for approval."
            : "Requisition created successfully and sent to Management for approval.";

        if (!empty($is_ajax)) {
            echo json_encode([
                'status' => 'success',
                'success' => true,
                'message' => $successMsg
            ]);
            exit;
        }

        $_SESSION['message'] = $successMsg;
        $_SESSION['msg_type'] = "success";
        header("Location: ../requisitions");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// --- APPROVE REQUISITION (Per-Item) ---
elseif ($action === 'approve_rs') {
    if (!in_array($_SESSION['user_role'], ['management', 'admin'])) {
        throw new Exception("Only Management or Admins can approve requisitions.");
    }

    $rs_id      = (int)$_POST['rs_id'];
    $itemStatuses = $_POST['item_statuses'] ?? [];  // [item_id => 'Approved'|'Rejected']
    $itemRemarks  = $_POST['item_remarks']  ?? [];  // [item_id => 'reason text']

    if (empty($itemStatuses)) {
        $_SESSION['message'] = "No item statuses were submitted.";
        $_SESSION['msg_type'] = "danger";
        header("Location: ../requisitions");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update each item row (including any typo corrections made by management for unlisted items)
        $itemUpdateStmt = $pdo->prepare(
            "UPDATE requisition_items 
             SET item_status = ?, 
                 item_remarks = ?,
                 new_item_name = CASE WHEN ? IS NOT NULL AND ? != '' THEN ? ELSE new_item_name END
             WHERE id = ? AND requisition_id = ?"
        );
        $itemNames = $_POST['item_names'] ?? [];
        foreach ($itemStatuses as $itemId => $status) {
            $remark = trim($itemRemarks[$itemId] ?? '');
            $cleanStatus = in_array($status, ['Approved', 'Rejected']) ? $status : 'Approved';
            $correctedName = isset($itemNames[$itemId]) ? trim($itemNames[$itemId]) : null;
            $itemUpdateStmt->execute([$cleanStatus, $remark ?: null, $correctedName, $correctedName, $correctedName, (int)$itemId, $rs_id]);
        }

    // Derive RS-level status
    $countStmt = $pdo->prepare(
        "SELECT
            SUM(item_status = 'Approved')  AS approved_count,
            SUM(item_status = 'Rejected')  AS rejected_count,
            COUNT(*)                        AS total_count
         FROM requisition_items WHERE requisition_id = ?"
    );
    $countStmt->execute([$rs_id]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

    $approvedCount = (int)$counts['approved_count'];
    $rejectedCount = (int)$counts['rejected_count'];
    $totalCount    = (int)$counts['total_count'];

    if ($approvedCount === 0) {
        $rsStatus = 'Rejected';
    } elseif ($rejectedCount === 0) {
        $rsStatus = 'Approved';
    } else {
        $rsStatus = 'Partially Approved';
    }

    $pdo->prepare("UPDATE requisitions SET status = ?, approved_by = ? WHERE id = ?")
        ->execute([$rsStatus, $_SESSION['user_id'], $rs_id]);

    // Fetch RS info for notifications
    $rsData = $pdo->prepare("SELECT rs_no, requestor_id, type FROM requisitions WHERE id = ?");
    $rsData->execute([$rs_id]);
    $rs = $rsData->fetch();

    // Build a detailed list of rejected items for the notification
    $rejectedItemDetails = '';
    if ($rejectedCount > 0) {
        $rejStmt = $pdo->prepare("
            SELECT COALESCE(i.item_name, ri.new_item_name, ri.item_code) as item_name, ri.item_remarks
            FROM requisition_items ri
            LEFT JOIN inventory i ON ri.item_code = i.item_code
            WHERE ri.requisition_id = ? AND ri.item_status = 'Rejected'
        ");
        $rejStmt->execute([$rs_id]);
        $rejectedItems = $rejStmt->fetchAll(PDO::FETCH_ASSOC);
        $lines = [];
        foreach ($rejectedItems as $ri) {
            $line = '• ' . $ri['item_name'];
            if (!empty($ri['item_remarks'])) {
                $line .= ' — Reason: ' . $ri['item_remarks'];
            }
            $lines[] = $line;
        }
        $rejectedItemDetails = "\n" . implode("\n", $lines);
    }

    // Notify requestor
    $notifMsg = match($rsStatus) {
        'Approved'           => "Your request {$rs['rs_no']} has been fully approved. All items were approved by management.",
        'Partially Approved' => "Your request {$rs['rs_no']} was partially approved — {$approvedCount} of {$totalCount} items approved, {$rejectedCount} rejected.{$rejectedItemDetails}",
        'Rejected'           => "Your request {$rs['rs_no']} was rejected. All items were declined by management.{$rejectedItemDetails}",
        default              => "Your request {$rs['rs_no']} status has been updated to: {$rsStatus}."
    };
    $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, ?, ?)")
        ->execute([$rs['requestor_id'], "Requisition {$rsStatus}", $notifMsg]);
    sendPushNotification($pdo, "Requisition {$rsStatus}", $notifMsg, null, (int)$rs['requestor_id']);

    // Notify purchasing only if something was approved
    if ($approvedCount > 0 && $rs['type'] !== 'restock') {
        $purchasingMsg = "{$rs['rs_no']} was {$rsStatus}. Please generate a PO for the approved items.";
        $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'Ready for PO', ?)")
            ->execute([$purchasingMsg]);
        sendPushNotification($pdo, 'Ready for PO', $purchasingMsg, 'purchasing', null);
    }

    $msgType = $rsStatus === 'Rejected' ? 'danger' : ($rsStatus === 'Partially Approved' ? 'warning' : 'success');

    $pdo->commit();

    $_SESSION['message'] = "Requisition {$rsStatus}. ({$approvedCount} approved, {$rejectedCount} rejected out of {$totalCount} items)";
    $_SESSION['msg_type'] = $msgType;
    header("Location: ../requisitions");
    exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// --- STAGE RS MATERIALS ---
elseif ($action === 'stage_rs_materials') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
        throw new Exception("Unauthorized.");
    }

    $rs_id = $_POST['rs_id'];
    $rsData = $pdo->prepare("SELECT rs_no, requestor_id, type, project_name FROM requisitions WHERE id = ?");
    $rsData->execute([$rs_id]);
    $rs = $rsData->fetch();

    if ($rs && (($rs['type'] ?? '') === 'restock' || ($rs['project_name'] ?? '') === 'Warehouse Restock')) {
        throw new Exception("Warehouse Restock requisitions cannot be staged for pickup. Please generate a Purchase Order instead.");
    }

    $stmt = $pdo->prepare("UPDATE requisitions SET status = 'Staged (Ready for Pickup)' WHERE id = ?");
    $stmt->execute([$rs_id]);

    if ($rs && !empty($rs['requestor_id'])) {
        $msg = "Your requested materials for {$rs['rs_no']} have been pre-picked & staged by the Warehouse In-Charge. Ready for express pickup!";
        $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Materials Staged (Ready for Pickup)', ?)")
            ->execute([$rs['requestor_id'], $msg]);
        sendPushNotification($pdo, 'Materials Staged (Ready for Pickup)', $msg, null, (int)$rs['requestor_id']);
    }

    if (!empty($is_ajax)) {
        echo json_encode(['status' => 'success', 'message' => "Requisition {$rs['rs_no']} marked as Staged & Ready for Pickup."]);
        exit;
    }

    $_SESSION['message'] = "Requisition {$rs['rs_no']} marked as Staged & Ready for Express Pickup.";
    $_SESSION['msg_type'] = "info";
    header("Location: ../requisitions");
    exit;
}

// --- REJECT REQUISITION ---
elseif ($action === 'reject_rs') {
    if (!in_array($_SESSION['user_role'], ['management', 'admin'])) {
        throw new Exception("Only Management or Admins can reject requisitions.");
    }

    $reason = trim($_POST['reject_reason']);
    $appendRemark = "\n\n[MANAGEMENT REJECTED]: " . $reason;

    $stmt = $pdo->prepare("UPDATE requisitions SET status = 'Rejected', remarks = CONCAT(IFNULL(remarks,''), ?) WHERE id = ?");
    $stmt->execute([$appendRemark, $_POST['rs_id']]);

    $rsData = $pdo->prepare("SELECT rs_no, requestor_id FROM requisitions WHERE id = ?");
    $rsData->execute([$_POST['rs_id']]);
    $rs = $rsData->fetch();

    $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Rejected', ?)")
        ->execute([$rs['requestor_id'], "Your request {$rs['rs_no']} was rejected. Reason: {$reason}"]);
    sendPushNotification($pdo, 'Requisition Rejected', "Your request {$rs['rs_no']} was rejected. Reason: {$reason}", null, (int)$rs['requestor_id']);

    $_SESSION['message'] = "Requisition Rejected successfully.";
    $_SESSION['msg_type'] = "danger";
    header("Location: ../requisitions");
    exit;
}

// --- EDIT & RESUBMIT REQUISITION ---
elseif ($action === 'edit_rs') {
    $rs_id = (int)($_POST['rs_id'] ?? 0);
    if (!$rs_id) {
        throw new Exception("Invalid requisition ID.");
    }

    $rsStmt = $pdo->prepare("SELECT * FROM requisitions WHERE id = ?");
    $rsStmt->execute([$rs_id]);
    $rs = $rsStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rs) {
        throw new Exception("Requisition not found.");
    }

    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $userRole = $_SESSION['user_role'] ?? '';

    // Authorization: Owner or Management/Admin
    $isOwner = ((int)$rs['requestor_id'] === $currentUserId);
    $isManagement = in_array($userRole, ['management', 'admin']);

    if (!$isOwner && !$isManagement) {
        throw new Exception("Unauthorized to edit this requisition.");
    }

    // Only allow editing pending or rejected requisitions
    if (!in_array($rs['status'], ['Pending Approval', 'Rejected'])) {
        throw new Exception("Only Pending or Rejected requisitions can be edited.");
    }

    $projectName = trim($_POST['project_name'] ?? $rs['project_name']);
    $urgency = trim($_POST['urgency'] ?? $rs['urgency']);
    $remarks = trim($_POST['remarks'] ?? '');

    $wasRejected = ($rs['status'] === 'Rejected');
    $newStatus = 'Pending Approval'; // Resets to Pending Approval upon resubmission

    try {
        $pdo->beginTransaction();

        $updateStmt = $pdo->prepare("
            UPDATE requisitions 
            SET project_name = ?, urgency = ?, remarks = ?, status = ? 
            WHERE id = ?
        ");
        $updateStmt->execute([$projectName, $urgency, $remarks, $newStatus, $rs_id]);

        // Delete existing items and re-insert updated items
        $pdo->prepare("DELETE FROM requisition_items WHERE requisition_id = ?")->execute([$rs_id]);

        $items = $_POST['items'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $isNewItems = $_POST['is_new_items'] ?? [];
        $newItemNames = $_POST['new_item_names'] ?? [];
        $newCategories = $_POST['new_categories'] ?? [];
        $newUnits = $_POST['new_units'] ?? [];
        $itemNotes = $_POST['item_notes'] ?? [];

        $itemStmt = $pdo->prepare("
            INSERT INTO requisition_items (requisition_id, item_code, quantity, is_new_item, new_item_name, new_category, new_unit, item_notes, item_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");

        for ($i = 0; $i < count($items); $i++) {
            $isNew = !empty($isNewItems[$i]) ? 1 : 0;
            $code = trim($items[$i] ?? '');
            $qty = (int)($quantities[$i] ?? 0);
            $name = $isNew ? trim($newItemNames[$i] ?? '') : null;
            $cat = $isNew ? trim($newCategories[$i] ?? 'Materials') : null;
            $unit = $isNew ? trim($newUnits[$i] ?? 'pcs') : null;
            $note = trim($itemNotes[$i] ?? '') ?: null;

            if ($isNew && empty($code)) {
                $code = 'ITM-' . rand(1000, 9999);
            }

            if ($qty > 0 && (!empty($code) || !empty($name))) {
                $itemStmt->execute([
                    $rs_id,
                    $code,
                    $qty,
                    $isNew,
                    $name,
                    $cat,
                    $unit,
                    $note
                ]);
            }
        }

        // Notify management if resubmitted from rejected
        if ($wasRejected) {
            $notifMsg = "Requisition {$rs['rs_no']} was updated and resubmitted by {$rs['requestor_name']} for approval.";
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'Requisition Resubmitted', ?)")
                ->execute([$notifMsg]);
            sendPushNotification($pdo, 'Requisition Resubmitted', $notifMsg, 'management');
        }

        $pdo->commit();

        if (!empty($is_ajax)) {
            echo json_encode([
                'status' => 'success', 
                'success' => true, 
                'message' => "Requisition {$rs['rs_no']} updated and resubmitted successfully."
            ]);
            exit;
        }

        $_SESSION['message'] = "Requisition {$rs['rs_no']} updated successfully.";
        $_SESSION['msg_type'] = "success";
        header("Location: ../requisitions");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
