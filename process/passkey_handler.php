<?php
/**
 * SiteWare — Construction Inventory Management System (CIMS)
 * Copyright (c) GB Construction & Enterprises Inc. & The MedYas.
 * All Rights Reserved.
 *
 * PROPRIETARY AND CONFIDENTIAL.
 * Endpoint: WebAuthn / FIDO2 Passkeys Handler (Registration & Biometric Login)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Connection/db.php';
require_once __DIR__ . '/../helpers/webauthn_helper.php';

init_secure_session();

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
$clientIp = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

try {
    switch ($action) {
        // =========================================================================
        // 1. REGISTRATION OPTIONS (Requires Authenticated Session)
        // =========================================================================
        case 'get_registration_options':
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                throw new Exception('Unauthorized. Please log in first.');
            }

            $userId = (int)$_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT id, name, username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('User not found.');
            }

            $challenge = WebAuthnHelper::generateChallenge();
            $_SESSION['passkey_reg_challenge'] = $challenge;

            // Fetch existing registered credentials to exclude duplicate registrations
            $credStmt = $pdo->prepare("SELECT credential_id FROM user_passkeys WHERE user_id = ?");
            $credStmt->execute([$userId]);
            $existingCreds = $credStmt->fetchAll(PDO::FETCH_COLUMN);

            $excludeCredentials = [];
            foreach ($existingCreds as $cId) {
                $excludeCredentials[] = [
                    'id' => $cId,
                    'type' => 'public-key',
                    'transports' => ['internal', 'hybrid', 'usb', 'nfc', 'ble']
                ];
            }

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'options' => [
                    'challenge' => $challenge,
                    'rp' => [
                        'name' => 'SiteWare CIMS',
                        'id' => WebAuthnHelper::getRpId()
                    ],
                    'user' => [
                        'id' => WebAuthnHelper::base64UrlEncode((string)$userId),
                        'name' => $user['username'],
                        'displayName' => $user['name']
                    ],
                    'pubKeyCredParams' => [
                        ['alg' => -7, 'type' => 'public-key'],   // ES256 (P-256)
                        ['alg' => -257, 'type' => 'public-key']  // RS256 (RSA)
                    ],
                    'authenticatorSelection' => [
                        'userVerification' => 'preferred',
                        'residentKey' => 'preferred'
                    ],
                    'timeout' => 60000,
                    'attestation' => 'none',
                    'excludeCredentials' => $excludeCredentials
                ]
            ]);
            break;

        // =========================================================================
        // 2. SAVE REGISTRATION (Requires Authenticated Session & CSRF)
        // =========================================================================
        case 'save_registration':
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                throw new Exception('Unauthorized.');
            }

            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!validate_csrf_token($csrfToken)) {
                http_response_code(403);
                throw new Exception('Security token invalid. Please refresh.');
            }

            if (empty($_SESSION['passkey_reg_challenge'])) {
                throw new Exception('Registration challenge expired. Please try again.');
            }

            $userId = (int)$_SESSION['user_id'];
            $clientDataJSON_b64 = $_POST['clientDataJSON'] ?? '';
            $attestationObject_b64 = $_POST['attestationObject'] ?? '';
            $deviceName = trim($_POST['device_name'] ?? '');

            if (empty($deviceName)) {
                $deviceName = 'Biometric Device (' . date('M d, Y') . ')';
            }

            $clientDataRaw = WebAuthnHelper::base64UrlDecode($clientDataJSON_b64);
            $attestationRaw = WebAuthnHelper::base64UrlDecode($attestationObject_b64);

            $clientData = json_decode($clientDataRaw, true);
            if (!$clientData || ($clientData['type'] ?? '') !== 'webauthn.create') {
                throw new Exception('Invalid clientDataJSON type.');
            }

            // Verify challenge
            if (($clientData['challenge'] ?? '') !== $_SESSION['passkey_reg_challenge']) {
                throw new Exception('Passkey challenge mismatch.');
            }

            // Decode attestation object
            $offset = 0;
            $attestation = WebAuthnHelper::decodeCbor($attestationRaw, $offset);
            $authDataBin = $attestation['authData'] ?? '';

            if (empty($authDataBin)) {
                throw new Exception('Missing authData in attestation.');
            }

            $parsed = WebAuthnHelper::parseAuthData($authDataBin);

            if (empty($parsed['credentialId']) || empty($parsed['publicKeyPem'])) {
                throw new Exception('Failed to extract credential public key.');
            }

            // Store in database
            $pdo->beginTransaction();
            try {
                $insStmt = $pdo->prepare("INSERT INTO user_passkeys (user_id, credential_id, public_key, device_name, sign_count) VALUES (?, ?, ?, ?, ?)");
                $insStmt->execute([
                    $userId,
                    $parsed['credentialId'],
                    $parsed['publicKeyPem'],
                    htmlspecialchars($deviceName, ENT_QUOTES, 'UTF-8'),
                    $parsed['signCount']
                ]);

                $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $auditStmt->execute([
                    $userId,
                    'PASSKEY_REGISTERED',
                    'user_passkeys',
                    $pdo->lastInsertId(),
                    null,
                    "Device registered: {$deviceName}",
                    $clientIp
                ]);

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

            unset($_SESSION['passkey_reg_challenge']);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Passkey registered successfully! You can now sign in with this device.'
            ]);
            break;

        // =========================================================================
        // 3. DELETE PASSKEY (Requires Authenticated Session & CSRF)
        // =========================================================================
        case 'delete_passkey':
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                throw new Exception('Unauthorized.');
            }

            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!validate_csrf_token($csrfToken)) {
                http_response_code(403);
                throw new Exception('Security token invalid.');
            }

            $passkeyId = (int)($_POST['passkey_id'] ?? 0);
            $userId = (int)$_SESSION['user_id'];

            $delStmt = $pdo->prepare("DELETE FROM user_passkeys WHERE id = ? AND user_id = ?");
            $delStmt->execute([$passkeyId, $userId]);

            if ($delStmt->rowCount() > 0) {
                $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $auditStmt->execute([
                    $userId,
                    'PASSKEY_REMOVED',
                    'user_passkeys',
                    $passkeyId,
                    "Passkey ID {$passkeyId}",
                    'Removed',
                    $clientIp
                ]);
            }

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Passkey removed successfully.'
            ]);
            break;

        // =========================================================================
        // 4. LOGIN OPTIONS (Public / Pre-Auth / Inactivity Unlock)
        // =========================================================================
        case 'get_login_options':
            $challenge = WebAuthnHelper::generateChallenge();
            $_SESSION['passkey_auth_challenge'] = $challenge;

            $loginOptions = [
                'challenge' => $challenge,
                'rpId' => WebAuthnHelper::getRpId(),
                'timeout' => 60000,
                'userVerification' => 'preferred'
            ];

            // If user is locked/authenticated, restrict assertion options to their registered credentials
            if (!empty($_SESSION['user_id'])) {
                $uid = (int)$_SESSION['user_id'];
                $cStmt = $pdo->prepare("SELECT credential_id FROM user_passkeys WHERE user_id = ?");
                $cStmt->execute([$uid]);
                $creds = $cStmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($creds)) {
                    $loginOptions['allowCredentials'] = array_map(function($cid) {
                        return [
                            'type' => 'public-key',
                            'id' => $cid
                        ];
                    }, $creds);
                }
            }

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'options' => $loginOptions
            ]);
            break;

        // =========================================================================
        // 5. VERIFY LOGIN / SCREEN UNLOCK (Public / Pre-Auth / Inactivity Unlock)
        // =========================================================================
        case 'verify_login':
            if (empty($_SESSION['passkey_auth_challenge'])) {
                throw new Exception('Authentication challenge expired. Please try again.');
            }

            $expectedChallenge = $_SESSION['passkey_auth_challenge'];
            $credentialId = trim($_POST['id'] ?? '');
            $clientDataJSON_b64 = $_POST['clientDataJSON'] ?? '';
            $authenticatorData_b64 = $_POST['authenticatorData'] ?? '';
            $signature_b64 = $_POST['signature'] ?? '';

            if (empty($credentialId) || empty($clientDataJSON_b64) || empty($authenticatorData_b64) || empty($signature_b64)) {
                throw new Exception('Incomplete authentication assertion payload.');
            }

            $clientDataRaw = WebAuthnHelper::base64UrlDecode($clientDataJSON_b64);
            $authDataRaw = WebAuthnHelper::base64UrlDecode($authenticatorData_b64);
            $signatureRaw = WebAuthnHelper::base64UrlDecode($signature_b64);

            $clientData = json_decode($clientDataRaw, true);
            if (!$clientData || ($clientData['type'] ?? '') !== 'webauthn.get') {
                throw new Exception('Invalid clientData type.');
            }

            if (($clientData['challenge'] ?? '') !== $expectedChallenge) {
                throw new Exception('Challenge verification failed.');
            }

            // Look up stored credential and user details
            $stmt = $pdo->prepare("
                SELECT p.id AS passkey_id, p.user_id, p.public_key, p.sign_count, p.device_name,
                       u.name, u.username, u.role, u.status
                FROM user_passkeys p
                JOIN users u ON p.user_id = u.id
                WHERE p.credential_id = ?
            ");
            $stmt->execute([$credentialId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                throw new Exception('Unrecognized Passkey. Please register this device in your account settings first.');
            }

            if (isset($record['status']) && strtolower($record['status']) === 'inactive') {
                throw new Exception('Your account has been deactivated. Please contact an administrator.');
            }

            // Anti-IDOR / Identity lock check: If the screen is locked, ensure the passkey belongs to the locked account
            if (!empty($_SESSION['screen_locked']) && !empty($_SESSION['user_id'])) {
                if ((int)$record['user_id'] !== (int)$_SESSION['user_id']) {
                    throw new Exception('This passkey belongs to a different account. Please use your own passkey or password.');
                }
            }

            // Cryptographic verification
            $isValid = WebAuthnHelper::verifyAssertionSignature(
                $record['public_key'],
                $authDataRaw,
                $clientDataRaw,
                $signatureRaw
            );

            if (!$isValid) {
                throw new Exception('Biometric cryptographic signature validation failed.');
            }

            // Update sign count
            $newSignCount = unpack('N', substr($authDataRaw, 33, 4))[1];
            $updStmt = $pdo->prepare("UPDATE user_passkeys SET sign_count = ? WHERE id = ?");
            $updStmt->execute([$newSignCount, $record['passkey_id']]);

            // Authentication Successful!
            $isUnlock = !empty($_SESSION['screen_locked']);
            unset($_SESSION['passkey_auth_challenge']);
            unset($_SESSION['mfa_pending_user_id']);
            unset($_SESSION['mfa_pending_user_name']);

            unset($_SESSION['screen_locked']);
            $_SESSION['last_activity'] = time();

            if (!$isUnlock) {
                // Session Hardening for fresh login from login.php
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);

                $_SESSION['user_id'] = $record['user_id'];
                $_SESSION['user_name'] = $record['name'];
                $_SESSION['user_role'] = $record['role'];
                $_SESSION['fresh_login'] = true;
            }

            // Audit Log (ISO 9001 Traceability)
            $auditAction = $isUnlock ? 'SCREEN_UNLOCK_PASSKEY_SUCCESS' : 'LOGIN_PASSKEY_SUCCESS';
            $auditRemarks = $isUnlock
                ? "Screen unlocked via Passkey ({$record['device_name']})"
                : "Passkey login via {$record['device_name']}";

            $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $auditStmt->execute([
                $record['user_id'],
                $auditAction,
                'users',
                $record['user_id'],
                null,
                $auditRemarks,
                $clientIp
            ]);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'unlocked' => true,
                'redirect' => 'dashboard',
                'message' => $isUnlock ? 'Screen unlocked successfully.' : 'Identity verified! Redirecting to dashboard...'
            ]);
            break;

        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Passkey Handler Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit;
