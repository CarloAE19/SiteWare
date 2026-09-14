<?php
/* ==========================================================
 * CIMS - FCM HTTP v1 PUSH NOTIFICATION HELPER
 * Uses Service Account + JWT to get OAuth2 tokens
 * No Composer required — pure PHP + OpenSSL
 * HTTP requests use file_get_contents (no cURL needed)
 * ========================================================== */

define('FCM_SA_PATH',      __DIR__ . '/firebase-service-account.json');
define('FCM_TOKEN_CACHE',  sys_get_temp_dir() . '/cims_fcm_token.json');
define('FCM_PROJECT_ID',   'siteware-9fb2f');
define('FCM_SEND_URL',     'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send');


// ----------------------------------------------------------
// Internal HTTP helper (no cURL dependency)
// ----------------------------------------------------------
function _fcm_http_post(string $url, array $headers, string $body): ?string {
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $body,
            'ignore_errors' => true,   // read response body even on 4xx/5xx
            'timeout'       => 3,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    return ($response === false) ? null : $response;
}


// ----------------------------------------------------------
// 1. JWT Builder (RS256) — No external library needed
// ----------------------------------------------------------
function _fcm_build_jwt(array $sa): string {
    $now = time();
    $header  = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    // URL-safe base64
    $header  = rtrim(strtr($header,  '+/', '-_'), '=');
    $payload = rtrim(strtr($payload, '+/', '-_'), '=');

    $sigInput = "{$header}.{$payload}";
    $privKey  = openssl_pkey_get_private($sa['private_key']);
    openssl_sign($sigInput, $sig, $privKey, OPENSSL_ALGO_SHA256);
    $sig = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

    return "{$sigInput}.{$sig}";
}


// ----------------------------------------------------------
// 2. Access Token Fetcher (with file-based cache)
// ----------------------------------------------------------
function _fcm_get_access_token(): ?string {
    // Return cached token if still valid (5 min buffer)
    if (file_exists(FCM_TOKEN_CACHE)) {
        $cache = json_decode(file_get_contents(FCM_TOKEN_CACHE), true);
        if (isset($cache['token'], $cache['expires_at']) && time() < ($cache['expires_at'] - 300)) {
            return $cache['token'];
        }
    }

    if (!file_exists(FCM_SA_PATH)) {
        return null;
    }

    $sa  = json_decode(file_get_contents(FCM_SA_PATH), true);
    $jwt = _fcm_build_jwt($sa);

    $response = _fcm_http_post(
        'https://oauth2.googleapis.com/token',
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ])
    );

    if ($response === null) {
        return null;
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        return null;
    }

    // Cache the token
    file_put_contents(FCM_TOKEN_CACHE, json_encode([
        'token'      => $data['access_token'],
        'expires_at' => time() + ($data['expires_in'] ?? 3600),
    ]));

    return $data['access_token'];
}


// ----------------------------------------------------------
// 3. Single FCM Message Sender
// ----------------------------------------------------------
function _fcm_send_one(string $deviceToken, string $title, string $body, string $accessToken): void {
    $payload = json_encode([
        'message' => [
            'token' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body'  => $body,
            ],
            'webpush' => [
                'notification' => [
                    'icon'         => '/CIMS/assets/LogoGB.png',
                    'badge'        => '/CIMS/assets/favicon.ico',
                    'click_action' => '/CIMS/',
                ],
            ],
        ],
    ]);

    _fcm_http_post(
        FCM_SEND_URL,
        [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        $payload
    );
}


// ----------------------------------------------------------
// 4. PUBLIC API
//    sendPushNotification($pdo, $title, $body, $role, $userId)
//    Pass $target_role for role-wide broadcasts,
//    or $target_user_id for a specific user.
// ----------------------------------------------------------
function sendPushNotification(PDO $pdo, string $title, string $body, ?string $target_role, ?int $target_user_id): void {
    $accessToken = _fcm_get_access_token();
    if (!$accessToken) {
        return;
    }

    if ($target_user_id !== null) {
        $stmt = $pdo->prepare("SELECT fcm_token FROM users WHERE id = ? AND fcm_token IS NOT NULL AND fcm_token != ''");
        $stmt->execute([$target_user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT fcm_token FROM users WHERE role = ? AND fcm_token IS NOT NULL AND fcm_token != ''");
        $stmt->execute([$target_role]);
    }

    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tokens as $token) {
        _fcm_send_one($token, $title, $body, $accessToken);
    }
}
