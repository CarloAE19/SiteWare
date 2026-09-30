<?php
/**
 * SiteWare — Construction Inventory Management System (CIMS)
 * Copyright (c) GB Construction & Enterprises Inc. & The MedYas.
 * All Rights Reserved.
 *
 * PROPRIETARY AND CONFIDENTIAL.
 * Endpoint: Multi-Factor Authentication (TOTP) Management
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Connection/db.php';
require_once __DIR__ . '/../helpers/totp_helper.php';

init_secure_session();

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = trim($_POST['action'] ?? '');
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

// 2. CSRF Validation
if (!validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Security token invalid or expired. Please refresh the page.']);
    exit;
}

try {
    // Fetch active user
    $userStmt = $pdo->prepare("SELECT id, name, username, password, mfa_enabled, mfa_secret, mfa_backup_codes FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User record not found.');
    }

    $clientIp = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

    switch ($action) {
        case 'setup':
            // Generate a fresh 32-character Base32 secret key
            $secret = TotpHelper::generateSecret(32);
            $backupCodes = TotpHelper::generateBackupCodes(8);

            // Store temporarily in session until confirmed with a valid 6-digit code
            $_SESSION['pending_totp'] = [
                'secret' => $secret,
                'backup_hashed' => $backupCodes['hashed'],
                'backup_plain' => $backupCodes['plain'],
                'created_at' => time()
            ];

            $otpAuthUri = TotpHelper::getOtpAuthUri($user['username'], $secret, 'SiteWare-CIMS');
            // Format secret into 4-character chunks for human-friendly typing
            $secretFormatted = implode(' ', str_split($secret, 4));

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'secret' => $secret,
                'secret_formatted' => $secretFormatted,
                'otpauth_url' => $otpAuthUri,
                'backup_codes' => $backupCodes['plain'],
                'message' => 'Setup initiated. Please scan the QR code or enter the key into your authenticator app.'
            ]);
            break;

        case 'verify_and_enable':
            if (empty($_SESSION['pending_totp']) || empty($_SESSION['pending_totp']['secret'])) {
                throw new Exception('No pending 2FA setup found. Please restart the setup process.');
            }

            $submittedCode = trim($_POST['code'] ?? '');
            if (!preg_match('/^[0-9]{6}$/', $submittedCode)) {
                throw new Exception('Please enter a valid 6-digit verification code.');
            }

            $pendingSecret = $_SESSION['pending_totp']['secret'];
            $isValid = TotpHelper::verifyCode($pendingSecret, $submittedCode, 1);

            if (!$isValid) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'The code you entered does not match. Please ensure your authenticator app time is synchronized and try again.'
                ]);
                exit;
            }

            // Code is valid! Commit to database inside transaction
            $pdo->beginTransaction();
            try {
                $backupHashed = $_SESSION['pending_totp']['backup_hashed'];
                $updateStmt = $pdo->prepare("UPDATE users SET mfa_enabled = 1, mfa_secret = ?, mfa_backup_codes = ? WHERE id = ?");
                $updateStmt->execute([$pendingSecret, json_encode($backupHashed), $userId]);

                // ISO 9001 Audit Trail
                $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $auditStmt->execute([
                    $userId,
                    'MFA_ENABLED',
                    'users',
                    $userId,
                    'MFA Disabled',
                    'MFA Enabled (TOTP Authenticator)',
                    $clientIp
                ]);

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

            unset($_SESSION['pending_totp']);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Two-Factor Authentication has been successfully enabled! Your account is now secured.'
            ]);
            break;

        case 'disable':
            $confirmPassword = $_POST['password'] ?? '';
            if (empty($confirmPassword)) {
                throw new Exception('Please provide your current password to disable Two-Factor Authentication.');
            }

            if (!password_verify($confirmPassword, $user['password'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Incorrect password. Two-Factor Authentication was not disabled.'
                ]);
                exit;
            }

            $pdo->beginTransaction();
            try {
                $updateStmt = $pdo->prepare("UPDATE users SET mfa_enabled = 0, mfa_secret = NULL, mfa_backup_codes = NULL WHERE id = ?");
                $updateStmt->execute([$userId]);

                // ISO 9001 Audit Trail
                $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $auditStmt->execute([
                    $userId,
                    'MFA_DISABLED',
                    'users',
                    $userId,
                    'MFA Enabled (TOTP Authenticator)',
                    'MFA Disabled',
                    $clientIp
                ]);

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Two-Factor Authentication has been disabled.'
            ]);
            break;

        case 'regenerate_backup_codes':
            if (empty($user['mfa_enabled'])) {
                throw new Exception('Two-Factor Authentication is not enabled for your account.');
            }

            $confirmPassword = $_POST['password'] ?? '';
            if (empty($confirmPassword)) {
                throw new Exception('Please enter your password to regenerate backup codes.');
            }

            if (!password_verify($confirmPassword, $user['password'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Incorrect password. Could not regenerate backup codes.'
                ]);
                exit;
            }

            $newBackupCodes = TotpHelper::generateBackupCodes(8);

            $pdo->beginTransaction();
            try {
                $updateStmt = $pdo->prepare("UPDATE users SET mfa_backup_codes = ? WHERE id = ?");
                $updateStmt->execute([json_encode($newBackupCodes['hashed']), $userId]);

                $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $auditStmt->execute([
                    $userId,
                    'MFA_BACKUP_REGENERATED',
                    'users',
                    $userId,
                    'Old Backup Codes',
                    '8 Fresh Backup Codes Generated',
                    $clientIp
                ]);

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'backup_codes' => $newBackupCodes['plain'],
                'message' => 'New recovery backup codes generated successfully. Previous codes are now invalid.'
            ]);
            break;

        default:
            throw new Exception('Invalid action requested.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("MFA Error [User ID {$userId}]: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit;
