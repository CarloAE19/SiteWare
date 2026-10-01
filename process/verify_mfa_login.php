<?php
/**
 * SiteWare — Construction Inventory Management System (CIMS)
 * Copyright (c) GB Construction & Enterprises Inc. & The MedYas.
 * All Rights Reserved.
 *
 * PROPRIETARY AND CONFIDENTIAL.
 * Endpoint: Multi-Factor Authentication (TOTP / Backup Code) Login Verification
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Connection/db.php';
require_once __DIR__ . '/../helpers/totp_helper.php';

init_secure_session();

$clientIp = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

try {
    // 1. Verify Active Pre-Auth Pending Session
    if (empty($_SESSION['mfa_pending_user_id'])) {
        http_response_code(401);
        throw new Exception('No pending authentication session found. Please sign in again.');
    }

    // 2. Pre-Auth TTL Check (5-minute maximum lifetime)
    $pendingTime = $_SESSION['mfa_pending_time'] ?? 0;
    if (time() - $pendingTime > 300) {
        unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_user_name'], $_SESSION['mfa_pending_time'], $_SESSION['mfa_pending_attempts']);
        http_response_code(408);
        throw new Exception('Your verification session has timed out. Please sign in again.');
    }

    // 3. Rate-Limit / Brute Force Prevention on MFA Verification
    $_SESSION['mfa_pending_attempts'] = ($_SESSION['mfa_pending_attempts'] ?? 0) + 1;
    if ($_SESSION['mfa_pending_attempts'] > 5) {
        $failedUserId = (int)$_SESSION['mfa_pending_user_id'];
        unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_user_name'], $_SESSION['mfa_pending_time'], $_SESSION['mfa_pending_attempts']);

        // Log security warning
        $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $auditStmt->execute([
            $failedUserId,
            'MFA_LOCKOUT_ATTEMPTS',
            'users',
            $failedUserId,
            null,
            'Exceeded 5 consecutive failed MFA attempts',
            $clientIp
        ]);

        http_response_code(429);
        throw new Exception('Too many incorrect verification attempts. Verification session terminated for security.');
    }

    // 4. CSRF Token Validation
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validate_csrf_token($csrfToken)) {
        http_response_code(403);
        throw new Exception('Security token invalid or expired. Please refresh the page and try again.');
    }

    $userId = (int)$_SESSION['mfa_pending_user_id'];
    $submittedCode = trim($_POST['code'] ?? '');

    if (empty($submittedCode)) {
        throw new Exception('Please enter your 6-digit verification code or backup code.');
    }

    // Fetch user security profile
    $stmt = $pdo->prepare("SELECT id, name, username, role, status, mfa_enabled, mfa_secret, mfa_backup_codes FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['mfa_enabled']) || empty($user['mfa_secret'])) {
        throw new Exception('Two-Factor Authentication is not properly configured for this account.');
    }

    if (isset($user['status']) && strtolower($user['status']) === 'inactive') {
        throw new Exception('Your account has been deactivated. Please contact an administrator.');
    }

    $isVerified = false;
    $usedBackupCode = false;

    // Check 1: 6-Digit TOTP Code
    if (preg_match('/^[0-9]{6}$/', $submittedCode)) {
        $isVerified = TotpHelper::verifyCode($user['mfa_secret'], $submittedCode, 1);
    }

    // Check 2: Single-Use Backup Recovery Code
    if (!$isVerified && !empty($user['mfa_backup_codes'])) {
        $hashedCodes = json_decode($user['mfa_backup_codes'], true) ?: [];
        $consumedIdx = TotpHelper::verifyAndConsumeBackupCode($submittedCode, $hashedCodes);

        if ($consumedIdx !== null) {
            $isVerified = true;
            $usedBackupCode = true;

            // Remove the used code and update DB atomically
            array_splice($hashedCodes, $consumedIdx, 1);
            $updCodesStmt = $pdo->prepare("UPDATE users SET mfa_backup_codes = ? WHERE id = ?");
            $updCodesStmt->execute([json_encode($hashedCodes), $userId]);

            $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $auditStmt->execute([
                $userId,
                'MFA_BACKUP_CODE_USED',
                'users',
                $userId,
                null,
                'Single-use backup recovery code was used for authentication',
                $clientIp
            ]);
        }
    }

    if (!$isVerified) {
        $remainingAttempts = 5 - $_SESSION['mfa_pending_attempts'];
        if ($remainingAttempts > 0) {
            throw new Exception("Invalid code. Please verify your authenticator app or backup code. ({$remainingAttempts} attempt(s) remaining)");
        } else {
            throw new Exception("Invalid code. Pre-authentication session terminated.");
        }
    }

    // Verification Succeeded!
    unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_user_name'], $_SESSION['mfa_pending_time'], $_SESSION['mfa_pending_attempts']);

    // Session Hardening
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']); // Rotate CSRF token

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['screen_locked']);
    $_SESSION['fresh_login'] = true;

    // 🛡️ Track live active device session
    if (function_exists('record_user_active_session')) {
        record_user_active_session($pdo, (int)$user['id']);
    }

    // ISO 9001 Audit Trail
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $auditStmt->execute([
        $user['id'],
        'LOGIN_MFA_SUCCESS',
        'users',
        $user['id'],
        null,
        $usedBackupCode ? 'Authenticated via One-Time Backup Code' : 'Authenticated via TOTP 6-Digit Code',
        $clientIp
    ]);

    $newCsrfToken = function_exists('generate_csrf_token') ? generate_csrf_token() : '';

    echo json_encode([
        'success' => true,
        'status' => 'success',
        'redirect' => 'dashboard',
        'csrf_token' => $newCsrfToken,
        'message' => 'Verification successful! Redirecting to dashboard...'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit;
