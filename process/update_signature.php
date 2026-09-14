<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login");
    exit;
}

require_once '../Connection/db.php';

$userId = $_SESSION['user_id'];
$uploadDir = __DIR__ . '/../uploads/signatures/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

require_once __DIR__ . '/../classes/SecureUploadHandler.php';

$signaturePath = null;
$uploadError = null;

try {
    // Case 1: Base64 Canvas Drawing Data (5-Layer Defense)
    if (!empty($_POST['signature_data'])) {
        $signaturePath = SecureUploadHandler::validateAndSaveBase64Image(
            $_POST['signature_data'],
            'signatures',
            'user_sig_' . (int)$userId
        );
    } 
    // Case 2: File Upload (PNG/JPEG - 5-Layer Defense)
    elseif (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
        $signaturePath = SecureUploadHandler::validateAndSaveImageUpload(
            $_FILES['signature_file'],
            'signatures',
            'user_sig_' . (int)$userId
        );
    }
} catch (Exception $e) {
    $uploadError = $e->getMessage();
}

if ($signaturePath) {
    // Optionally remove old signature file if exists
    try {
        $oldStmt = $pdo->prepare("SELECT signature_path FROM users WHERE id = ?");
        $oldStmt->execute([$userId]);
        $oldPath = $oldStmt->fetchColumn();
        if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
            @unlink(__DIR__ . '/../' . $oldPath);
        }
    } catch (Exception $e) {}

    $stmt = $pdo->prepare("UPDATE users SET signature_path = ? WHERE id = ?");
    $stmt->execute([$signaturePath, $userId]);

    // Sync signature to purchase orders prepared or approved by this user
    try {
        $poPrepStmt = $pdo->prepare("UPDATE purchase_orders SET prepared_signature = ? WHERE prepared_by = ?");
        $poPrepStmt->execute([$signaturePath, $userId]);

        $poAppStmt = $pdo->prepare("UPDATE purchase_orders SET approved_signature = ? WHERE approved_by = ? OR (approved_by IS NULL AND approved_signature LIKE ?)");
        $poAppStmt->execute([$signaturePath, $userId, '%user_sig_' . $userId . '_%']);
    } catch (Exception $e) {}

    $_SESSION['message'] = "Official E-Signature updated successfully!";
    $_SESSION['msg_type'] = "success";
} else {
    $_SESSION['message'] = $uploadError ?: "Failed to update E-Signature. Please draw or upload a valid image (PNG/JPEG).";
    $_SESSION['msg_type'] = "danger";
}

header("Location: ../profile");
exit;
