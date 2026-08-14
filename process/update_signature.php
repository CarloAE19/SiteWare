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

$signaturePath = null;

// Case 1: Base64 Canvas Drawing Data
if (!empty($_POST['signature_data'])) {
    $sigData = $_POST['signature_data'];
    if (preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $sigData)) {
        $sigData = substr($sigData, strpos($sigData, ',') + 1);
        $sigData = base64_decode($sigData);

        if ($sigData !== false) {
            $filename = 'user_sig_' . $userId . '_' . time() . '.png';
            $fullPath = $uploadDir . $filename;
            if (file_put_contents($fullPath, $sigData)) {
                $signaturePath = 'uploads/signatures/' . $filename;
            }
        }
    }
} 
// Case 2: File Upload (PNG/JPEG)
elseif (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['signature_file']['tmp_name'];
    $fileName = $_FILES['signature_file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['png', 'jpg', 'jpeg'];
    if (in_array($fileExtension, $allowedExtensions)) {
        $filename = 'user_sig_' . $userId . '_' . time() . '.' . $fileExtension;
        $fullPath = $uploadDir . $filename;

        if (move_uploaded_file($fileTmpPath, $fullPath)) {
            $signaturePath = 'uploads/signatures/' . $filename;
        }
    }
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
    $_SESSION['message'] = "Failed to update E-Signature. Please draw or upload a valid image (PNG/JPEG).";
    $_SESSION['msg_type'] = "danger";
}

header("Location: ../profile");
exit;
