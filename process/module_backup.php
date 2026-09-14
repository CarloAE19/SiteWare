<?php
// =========================================================================
// SiteWare Backup & Disaster Recovery Backend Controller
// Conforms to Quality Standards Rule 5 (RBAC/CSRF) and CIMS Modal AJAX Skill
// =========================================================================

require_once __DIR__ . '/../Connection/db.php';
require_once __DIR__ . '/../classes/BackupService.php';

if (session_status() === PHP_SESSION_NONE) {
    init_secure_session();
}

// 1. Strict Server-Side Authentication & RBAC Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Access denied: Administrator privileges required for backup operations.']);
    exit;
}

$backupService = new BackupService($pdo);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 2. Direct File Download Action (Streams binary/text file)
if ($action === 'download_backup') {
    $filename = $_GET['file'] ?? $_POST['file'] ?? '';
    if (empty($filename)) {
        header("Location: ../settings?tab=backup");
        exit;
    }

    try {
        $backupService->downloadBackup($filename);
    } catch (Exception $e) {
        $_SESSION['message'] = "Download failed: " . $e->getMessage();
        $_SESSION['msg_type'] = "danger";
        header("Location: ../settings?tab=backup");
        exit;
    }
    exit;
}

// 3. For all AJAX endpoints, set JSON response headers
header('Content-Type: application/json; charset=utf-8');

// 4. Validate Anti-CSRF Token for State-Altering Actions
$mutatingActions = ['create_backup', 'restore_backup', 'delete_backup'];
if (in_array($action, $mutatingActions)) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Security validation failed: Invalid or expired CSRF token. Please refresh the page.'
        ]);
        exit;
    }
}

try {
    switch ($action) {
        case 'fetch_backups':
            $backups = $backupService->listBackups();
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'count' => count($backups),
                'data' => $backups
            ]);
            break;

        case 'create_backup':
            $result = $backupService->generateDatabaseBackup(true, 'siteware_backup_');
            $backupsList = $backupService->listBackups();

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => "SiteWare database backup '{$result['filename']}' generated successfully!",
                'data' => [
                    'backup' => $result,
                    'backups' => $backupsList,
                    'count' => count($backupsList)
                ]
            ]);
            break;

        case 'delete_backup':
            $filename = trim($_POST['filename'] ?? '');
            if (empty($filename)) {
                throw new InvalidArgumentException("Missing filename for backup deletion.");
            }

            $backupService->deleteBackup($filename);
            $backupsList = $backupService->listBackups();

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => "Backup file '{$filename}' was deleted successfully.",
                'data' => [
                    'backups' => $backupsList,
                    'count' => count($backupsList)
                ]
            ]);
            break;

        case 'restore_backup':
            // Two restoration sources: uploaded .sql file OR existing server backup
            $sourceType = $_POST['source_type'] ?? 'server';

            if ($sourceType === 'upload') {
                if (empty($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                    $errorCode = $_FILES['backup_file']['error'] ?? 'UNKNOWN';
                    throw new RuntimeException("Upload failed. File error code: {$errorCode}. Ensure the file is not larger than your server upload limit.");
                }

                $uploadedFile = $_FILES['backup_file'];
                $originalName = basename($uploadedFile['name']);

                // Validate extension
                if (!str_ends_with(strtolower($originalName), '.sql')) {
                    throw new InvalidArgumentException("Invalid file format. Please upload a genuine .sql database backup file.");
                }

                // Check size limit (e.g. 100MB)
                $maxBytes = 100 * 1024 * 1024;
                if ($uploadedFile['size'] > $maxBytes) {
                    throw new RuntimeException("Uploaded file exceeds the maximum 100MB size limit.");
                }

                $restoreResult = $backupService->restoreFromBackup($uploadedFile['tmp_name'], $originalName);
            } else {
                // Existing server file
                $filename = trim($_POST['filename'] ?? '');
                if (empty($filename)) {
                    throw new InvalidArgumentException("Please select a valid backup file to restore.");
                }

                $safeName = basename($filename);
                $filePath = $backupService->getBackupDir() . DIRECTORY_SEPARATOR . $safeName;
                $restoreResult = $backupService->restoreFromBackup($filePath, $safeName);
            }

            $backupsList = $backupService->listBackups();

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => "SiteWare database restored successfully! ({$restoreResult['queries_executed']} queries applied). A safety pre-restore snapshot was automatically created: {$restoreResult['safety_snapshot']}.",
                'data' => [
                    'restore_summary' => $restoreResult,
                    'backups' => $backupsList,
                    'count' => count($backupsList)
                ]
            ]);
            break;

        default:
            throw new InvalidArgumentException("Invalid backup action requested: " . htmlspecialchars($action));
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit;
