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
    // Point-in-Time Snapshot Pattern (ISO 9001 Clause 7.5 & Legal Non-Repudiation):
    // 1. Do NOT delete old signature files; past Purchase Orders and Material Withdrawals must preserve their historical signatures.
    // 2. Do NOT retroactively update past documents; profile signature changes only affect future transactions.
    $stmt = $pdo->prepare("UPDATE users SET signature_path = ? WHERE id = ?");
    $stmt->execute([$signaturePath, $userId]);

    $_SESSION['message'] = "Official E-Signature updated successfully!";
    $_SESSION['msg_type'] = "success";
} else {
    $_SESSION['message'] = $uploadError ?: "Failed to update E-Signature. Please draw or upload a valid image (PNG/JPEG).";
    $_SESSION['msg_type'] = "danger";
}

header("Location: ../profile");
exit;
