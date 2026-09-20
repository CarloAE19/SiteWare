<?php
// ==========================================
// WITHDRAWAL ACTIONS
// ==========================================

if ($action === 'create_withdrawal') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
        throw new Exception("Only the Warehouse In-Charge can release materials.");
    }

    $clientCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (function_exists('validate_csrf_token') && !validate_csrf_token($clientCsrf)) {
        throw new Exception("Security validation failed: Invalid or expired CSRF token. Please refresh and try again.");
    }

    $withdrawal_no = $_POST['withdrawal_no'];
    $project_name = $_POST['project_name'];
    $remarks = $_POST['remarks'] ?? '';
    $released_by = $_SESSION['user_id'];
    $items = $_POST['items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $received_by = trim($_POST['received_by'] ?? '');

    try {
        $pdo->beginTransaction();

        // Validate inventory stock first with row locking (FOR UPDATE)
        for ($i = 0; $i < count($items); $i++) {
            if (!empty($items[$i]) && !empty($quantities[$i])) {
                $checkStmt = $pdo->prepare("SELECT quantity, item_name FROM inventory WHERE item_code = ? FOR UPDATE");
                $checkStmt->execute([$items[$i]]);
                $invItem = $checkStmt->fetch();

                if (!$invItem || $invItem['quantity'] < $quantities[$i]) {
                    throw new Exception("Insufficient stock for " . ($invItem['item_name'] ?? $items[$i]) . ". Available: " . ($invItem['quantity'] ?? 0));
                }
            }
        }
        
        // 1. Process Digital Signature Image (5-Layer Defense: Base64 -> PNG)
        require_once __DIR__ . '/../../classes/SecureUploadHandler.php';
        $signature_path = null;
        if (!empty($_POST['signature_data'])) {
            $signature_path = SecureUploadHandler::validateAndSaveBase64Image(
                $_POST['signature_data'],
                'signatures',
                'sig_' . preg_replace('/[^A-Za-z0-9_-]/', '', $withdrawal_no)
            );
        }

        // 2. Process Photo Proof File Upload (5-Layer Defense: Re-encoded Image)
        $photo_proof_path = null;
        if (isset($_FILES['photo_proof']) && $_FILES['photo_proof']['error'] === UPLOAD_ERR_OK) {
            $photo_proof_path = SecureUploadHandler::validateAndSaveImageUpload(
                $_FILES['photo_proof'],
                'proofs',
                'proof_' . preg_replace('/[^A-Za-z0-9_-]/', '', $withdrawal_no)
            );
        }

        if (empty($photo_proof_path)) {
            throw new Exception("Photo proof of handed-over materials is required before releasing.");
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

        // Cryptographically Seal Material Withdrawal with RSA-2048 PKI Signature
        try {
            require_once __DIR__ . '/../../helpers/crypto_helper.php';
            signWithdrawal($pdo, $withdrawal_id);
        } catch (Exception $cryptoEx) {
            error_log("WD Crypto Signing Notice: " . $cryptoEx->getMessage());
        }

        // If this withdrawal was created via QR scan or manual RS lookup, expire it
        if (!empty($_POST['rs_no'])) {
            $rs_no_input = trim($_POST['rs_no']);
            $rs_no_clean = str_replace(['REQ-DATA:', ' ', '-'], '', strtoupper($rs_no_input));
            if (!str_starts_with($rs_no_clean, 'RS') && !empty($rs_no_clean)) {
                $rs_no_clean = 'RS' . $rs_no_clean;
            }

            // Fetch requisition info using indexed column lookup
            $rsStmt = $pdo->prepare("SELECT id, rs_no, requestor_id FROM requisitions WHERE rs_no = ? OR rs_no = ? OR rs_no = ?");
            $rsStmt->execute([$rs_no_input, $rs_no_clean, strtoupper($rs_no_input)]);
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

        // ISO 9001 Clause 8.5.2 & 7.5: Complete Audit Trail Traceability
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $auditStmt->execute([
                $released_by,
                'MATERIAL_WITHDRAWN',
                'withdrawal',
                $withdrawal_id,
                null,
                json_encode([
                    'withdrawal_no'  => $withdrawal_no,
                    'project_name'   => $project_name,
                    'received_by'    => $received_by,
                    'rs_no'          => $_POST['rs_no'] ?? null,
                    'items_released' => count($items)
                ]),
                $ip
            ]);
        } catch (Exception $auditEx) {
            error_log("Withdrawal Audit Log Notice: " . $auditEx->getMessage());
        }

        $pdo->commit();

        if (!empty($is_ajax)) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'status'  => 'success',
                'message' => "Materials successfully withdrawn and deducted from inventory.",
                'data'    => [
                    'withdrawal_id' => $withdrawal_id,
                    'withdrawal_no' => $withdrawal_no,
                    'project_name'  => $project_name
                ]
            ]);
            exit;
        }

        $_SESSION['message'] = "Materials successfully withdrawn and deducted from inventory.";
        $_SESSION['msg_type'] = "success";
        header("Location: ../withdrawals");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
