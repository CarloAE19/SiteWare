<?php
/**
 * SiteWare — Construction Inventory Management System (CIMS)
 * Copyright (c) GB Construction & Enterprises Inc. & The MedYas.
 * All Rights Reserved.
 *
 * PROPRIETARY AND CONFIDENTIAL.
 * Endpoint: Trusted Device Management (Remember This Device for 30 Days)
 * Standards: NIST SP 800-63B, ISO/IEC 25010, & CIMS Security Rules
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Connection/db.php';

init_secure_session();

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$clientIp = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

// 2. CSRF Validation for mutating POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Security token invalid or expired. Please refresh the page.']);
        exit;
    }
}

function get_friendly_device_name(?string $userAgent): string
{
    if (empty($userAgent)) {
        return 'Web Browser';
    }

    $os = 'Unknown OS';
    if (preg_match('/windows nt 10/i', $userAgent)) $os = 'Windows 10/11';
    elseif (preg_match('/windows nt 6\.3/i', $userAgent)) $os = 'Windows 8.1';
    elseif (preg_match('/windows nt/i', $userAgent)) $os = 'Windows PC';
    elseif (preg_match('/android/i', $userAgent)) {
        if (preg_match('/(tecno[^\;]+)/i', $userAgent, $m)) $os = trim($m[1]);
        elseif (preg_match('/(sm-[a-z0-9]+)/i', $userAgent, $m)) $os = 'Samsung (' . trim($m[1]) . ')';
        elseif (preg_match('/(pixel[^\;]+)/i', $userAgent, $m)) $os = trim($m[1]);
        else $os = 'Android Device';
    }
    elseif (preg_match('/iphone/i', $userAgent)) $os = 'iPhone';
    elseif (preg_match('/ipad/i', $userAgent)) $os = 'iPad';
    elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $os = 'macOS';
    elseif (preg_match('/linux/i', $userAgent)) $os = 'Linux';

    $browser = 'Browser';
    if (preg_match('/edg/i', $userAgent)) $browser = 'Edge';
    elseif (preg_match('/chrome/i', $userAgent) && !preg_match('/edg/i', $userAgent)) $browser = 'Chrome';
    elseif (preg_match('/safari/i', $userAgent) && !preg_match('/chrome/i', $userAgent)) $browser = 'Safari';
    elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/opera|opr/i', $userAgent)) $browser = 'Opera';

    return "{$browser} on {$os}";
}

try {
    switch ($action) {
        // =========================================================================
        // 1. TRUST THIS DEVICE (30 Days)
        // =========================================================================
        case 'trust_device':
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $deviceName = function_exists('get_friendly_device_name') ? get_friendly_device_name($userAgent) : 'Web Browser';

            // Clean up any stale expired tokens for this user first
            $cleanStmt = $pdo->prepare("DELETE FROM user_trusted_devices WHERE user_id = ? AND expires_at < NOW()");
            $cleanStmt->execute([$userId]);

            // Insert new trusted device token (expires in 30 days)
            $stmt = $pdo->prepare("
                INSERT INTO user_trusted_devices (user_id, device_token_hash, device_name, ip_address, expires_at)
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
            ");
            $stmt->execute([$userId, $tokenHash, $deviceName, $clientIp]);
            $newDeviceId = (int)$pdo->lastInsertId();

            // Link with active session
            if (function_exists('record_user_active_session')) {
                record_user_active_session($pdo, $userId, $tokenHash, $newDeviceId);
            }

            // Set secure HTTP-only cookie for 30 days
            $is_secure_conn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            setcookie('cims_trusted_device', $userId . ':' . $rawToken, [
                'expires' => time() + (86400 * 30), // 30 days
                'path' => '/',
                'secure' => $is_secure_conn,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            // ISO 9001 Audit Trail
            $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $auditStmt->execute([
                $userId,
                'DEVICE_TRUSTED',
                'users',
                $userId,
                null,
                "Device remembered for 30 days: {$deviceName}",
                $clientIp
            ]);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Device trusted for 30 days. You will not be asked for 2FA on this browser.',
                'device_name' => $deviceName
            ]);
            break;

        // =========================================================================
        // 2. GET ACTIVE SESSIONS & RECOGNIZED DEVICES
        // =========================================================================
        case 'get_sessions':
        case 'get_devices':
            $currentSessionId = session_id();
            $stmt = $pdo->prepare("
                SELECT uas.id, uas.session_id, uas.device_name, uas.ip_address, uas.is_remembered, 
                       uas.created_at, uas.last_activity, uas.status,
                       utd.expires_at,
                       (uas.session_id = ?) AS is_current_device
                FROM user_active_sessions uas
                LEFT JOIN user_trusted_devices utd ON (uas.trusted_device_id = utd.id OR uas.trusted_token_hash = utd.device_token_hash)
                WHERE uas.user_id = ? AND uas.status = 'active'
                ORDER BY is_current_device DESC, uas.last_activity DESC
            ");
            $stmt->execute([$currentSessionId, $userId]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'sessions' => $sessions
            ]);
            break;

        // =========================================================================
        // 3. REVOKE REMOTE SESSION & TRUSTED DEVICE (Instant Remote Logout)
        // =========================================================================
        case 'revoke_session_and_device':
        case 'revoke_device':
            $sessionId = (int)($_POST['session_id'] ?? $_POST['device_id'] ?? 0);
            $revokeAllOthers = !empty($_POST['revoke_all_others']) || !empty($_POST['revoke_all']);
            $currentSessionId = session_id();

            if ($revokeAllOthers) {
                // 1. Fetch token hashes of all other sessions to clean user_trusted_devices
                $otherTokensStmt = $pdo->prepare("
                    SELECT trusted_token_hash, trusted_device_id FROM user_active_sessions 
                    WHERE user_id = ? AND session_id != ? AND status = 'active'
                ");
                $otherTokensStmt->execute([$userId, $currentSessionId]);
                $otherRows = $otherTokensStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($otherRows as $row) {
                    if (!empty($row['trusted_device_id'])) {
                        $pdo->prepare("DELETE FROM user_trusted_devices WHERE id = ? AND user_id = ?")->execute([$row['trusted_device_id'], $userId]);
                    } elseif (!empty($row['trusted_token_hash'])) {
                        $pdo->prepare("DELETE FROM user_trusted_devices WHERE device_token_hash = ? AND user_id = ?")->execute([$row['trusted_token_hash'], $userId]);
                    }
                }

                // 2. Mark all other sessions as revoked
                $revStmt = $pdo->prepare("UPDATE user_active_sessions SET status = 'revoked' WHERE user_id = ? AND session_id != ?");
                $revStmt->execute([$userId, $currentSessionId]);
                $affectedCount = $revStmt->rowCount();

                $msg = "All other sessions have been logged out and their 2FA bypass revoked.";

                // ISO 9001 Audit Trail
                $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $auditStmt->execute([
                    $userId,
                    'ALL_REMOTE_SESSIONS_REVOKED',
                    'users',
                    $userId,
                    null,
                    "Revoked {$affectedCount} remote sessions and trusted devices",
                    $clientIp
                ]);

            } else {
                if ($sessionId <= 0) {
                    throw new Exception('Invalid session or device identifier.');
                }

                // Fetch session record
                $findStmt = $pdo->prepare("SELECT * FROM user_active_sessions WHERE id = ? AND user_id = ? LIMIT 1");
                $findStmt->execute([$sessionId, $userId]);
                $targetSession = $findStmt->fetch(PDO::FETCH_ASSOC);

                if (!$targetSession) {
                    // Fallback: check if it's a legacy trusted_device id
                    $delTrust = $pdo->prepare("DELETE FROM user_trusted_devices WHERE id = ? AND user_id = ?");
                    $delTrust->execute([$sessionId, $userId]);
                    $msg = 'Device trust revoked successfully.';
                } else {
                    // Mark session as revoked
                    $updStmt = $pdo->prepare("UPDATE user_active_sessions SET status = 'revoked' WHERE id = ? AND user_id = ?");
                    $updStmt->execute([$sessionId, $userId]);

                    // Remove linked trusted device record
                    if (!empty($targetSession['trusted_device_id'])) {
                        $pdo->prepare("DELETE FROM user_trusted_devices WHERE id = ? AND user_id = ?")->execute([$targetSession['trusted_device_id'], $userId]);
                    } elseif (!empty($targetSession['trusted_token_hash'])) {
                        $pdo->prepare("DELETE FROM user_trusted_devices WHERE device_token_hash = ? AND user_id = ?")->execute([$targetSession['trusted_token_hash'], $userId]);
                    }

                    $devName = htmlspecialchars($targetSession['device_name']);
                    $msg = "\"{$devName}\" has been signed out and its 2FA bypass revoked.";

                    // If user revoked their OWN current session
                    if ($targetSession['session_id'] === $currentSessionId) {
                        setcookie('cims_trusted_device', '', time() - 42000, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
                    }
                }

                // ISO 9001 Audit Trail
                $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $auditStmt->execute([
                    $userId,
                    'SESSION_REMOTE_LOGOUT_REVOKED',
                    'users',
                    $userId,
                    null,
                    $msg,
                    $clientIp
                ]);
            }

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => $msg
            ]);
            break;

        default:
            throw new Exception('Invalid action requested.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit;
