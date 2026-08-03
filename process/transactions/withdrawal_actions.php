<?php
// ==========================================
// WITHDRAWAL ACTIONS
// ==========================================

if ($action === 'create_withdrawal') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
        throw new Exception("Only the Warehouse In-Charge can release materials.");
    }

    $withdrawal_no = $_POST['withdrawal_no'];
    $project_name = $_POST['project_name'];
    $remarks = $_POST['remarks'] ?? '';
    $released_by = $_SESSION['user_id'];
    $items = $_POST['items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $received_by = trim($_POST['received_by'] ?? '');

    // Validate inventory stock first
    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i]) && !empty($quantities[$i])) {
            $checkStmt = $pdo->prepare("SELECT quantity, item_name FROM inventory WHERE item_code = ?");
            $checkStmt->execute([$items[$i]]);
            $invItem = $checkStmt->fetch();

            if (!$invItem || $invItem['quantity'] < $quantities[$i]) {
                throw new Exception("Insufficient stock for " . ($invItem['item_name'] ?? $items[$i]) . ". Available: " . ($invItem['quantity'] ?? 0));
            }
        }
    }
    
    // 1. Process Digital Signature Image (Base64 -> PNG)
    $signature_path = null;
    if (!empty($_POST['signature_data'])) {
        $sigData = $_POST['signature_data'];
        if (strpos($sigData, 'base64,') !== false) {
            $sigData = explode('base64,', $sigData)[1];
        }
        $sigBinary = base64_decode($sigData);
        if ($sigBinary) {
            $sigDir = __DIR__ . '/../../uploads/signatures';
            if (!file_exists($sigDir)) {
                mkdir($sigDir, 0777, true);
            }
            $sigFilename = 'sig_' . preg_replace('/[^A-Za-z0-9_-]/', '', $withdrawal_no) . '_' . time() . '.png';
            file_put_contents($sigDir . '/' . $sigFilename, $sigBinary);
            $signature_path = 'uploads/signatures/' . $sigFilename;
        }
    }

    // 2. Process Photo Proof File Upload
    $photo_proof_path = null;
    if (isset($_FILES['photo_proof']) && $_FILES['photo_proof']['error'] === UPLOAD_ERR_OK) {
        $photoDir = __DIR__ . '/../../uploads/proofs';
        if (!file_exists($photoDir)) {
            mkdir($photoDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['photo_proof']['name'], PATHINFO_EXTENSION));
        if (empty($ext)) $ext = 'jpg';
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $photoFilename = 'proof_' . preg_replace('/[^A-Za-z0-9_-]/', '', $withdrawal_no) . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo_proof']['tmp_name'], $photoDir . '/' . $photoFilename)) {
                $photo_proof_path = 'uploads/proofs/' . $photoFilename;
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO withdrawals (withdrawal_no, project_name, released_by, remarks, received_by, signature_path, photo_proof_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$withdrawal_no, $project_name, $released_by, $remarks, $received_by, $signature_path, $photo_proof_path]);
    $withdrawal_id = $pdo->lastInsertId();

    $wdItemStmt = $pdo->prepare("INSERT INTO withdrawal_items (withdrawal_id, item_code, quantity) VALUES (?, ?, ?)");
    $deductStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE item_code = ?");
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

    for ($i = 0; $i < count($items); $i++) {
        if (!empty($items[$i]) && !empty($quantities[$i])) {
            $wdItemStmt->execute([$withdrawal_id, $items[$i], $quantities[$i]]);
            $deductStmt->execute([$quantities[$i], $items[$i]]);
            $updateStatusStmt->execute([$items[$i]]);
        }
    }

    // If this withdrawal was created via QR scan or manual RS lookup, expire it
    if (!empty($_POST['rs_no'])) {
        $rs_no_input = trim($_POST['rs_no']);
        $rs_no_clean = str_replace(['REQ-DATA:', ' ', '-'], '', strtoupper($rs_no_input));
        if (!str_starts_with($rs_no_clean, 'RS') && !empty($rs_no_clean)) {
            $rs_no_clean = 'RS' . $rs_no_clean;
        }

        // Fetch requisition info to notify the requestor
        $rsStmt = $pdo->prepare("SELECT id, rs_no, requestor_id FROM requisitions WHERE REPLACE(REPLACE(UPPER(rs_no), '-', ''), ' ', '') = ? OR UPPER(rs_no) = ?");
        $rsStmt->execute([$rs_no_clean, strtoupper($rs_no_input)]);
        $rsData = $rsStmt->fetch(PDO::FETCH_ASSOC);

        if ($rsData) {
            // Update status of requisition to 'Released'
            $updateRsStmt = $pdo->prepare("UPDATE requisitions SET status = 'Released' WHERE id = ?");
            $updateRsStmt->execute([$rsData['id']]);

            // Notify the requestor
            $notifMsg = "Materials for your requisition {$rsData['rs_no']} have been released from the warehouse.";
            $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Released', ?)")
                ->execute([$rsData['requestor_id'], $notifMsg]);
            sendPushNotification($pdo, 'Requisition Released', $notifMsg, null, (int)$rsData['requestor_id']);
        }
    }

    $_SESSION['message'] = "Materials successfully withdrawn and deducted from inventory.";
    $_SESSION['msg_type'] = "success";
    header("Location: ../withdrawals");
    exit;
}
