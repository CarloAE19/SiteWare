<?php
/* ==========================================================
 * GB INVENTORY - FIREBASE PUSH NOTIFICATION ENGINE
 * Enterprise-grade Google OAuth2 JWT Generator (No Composer required!)
 *  Localhost XAMPP SSL Bypass & Native Background Delivery
 * ========================================================== */

function sendPushNotification($pdo, $title, $body, $targetRole = null, $targetUserId = null) {
    $keyFile = __DIR__ . '/firebase-key.json';
    if (!file_exists($keyFile)) return false;

    $keyData = json_decode(file_get_contents($keyFile), true);
    $projectId = $keyData['project_id'];

    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $payload = json_encode([
        'iss' => $keyData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signatureInput = $base64UrlHeader . '.' . $base64UrlPayload;

    $signature = '';
    openssl_sign($signatureInput, $signature, $keyData['private_key'], OPENSSL_ALGO_SHA256);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $signatureInput . '.' . $base64UrlSignature;

    // 1. Authenticate with Google (Bypass local SSL)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // FIX: Bypass XAMPP SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // FIX: Bypass XAMPP SSL
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $accessToken = $response['access_token'] ?? null;
    if (!$accessToken) return false;

    // 2. Gather Tokens
    $tokens = [];
    if ($targetUserId) {
        $stmt = $pdo->prepare("SELECT fcm_token FROM users WHERE id = ? AND fcm_token IS NOT NULL AND fcm_token != ''");
        $stmt->execute([$targetUserId]);
        if ($res = $stmt->fetch()) $tokens[] = $res['fcm_token'];
    } elseif ($targetRole) {
        $stmt = $pdo->prepare("SELECT fcm_token FROM users WHERE role = ? AND fcm_token IS NOT NULL AND fcm_token != ''");
        $stmt->execute([$targetRole]);
        foreach ($stmt->fetchAll() as $row) $tokens[] = $row['fcm_token'];
    }

    // 3. Send Notification payload to Google
    foreach ($tokens as $token) {
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'webpush' => [
                    'headers' => [ 'Urgency' => 'high' ], // FIX: Apple iOS Wakeup
                    'notification' => [ 'icon' => '/CIMS/assets/LogoGB.png' ],
                    'fcm_options' => [ 'link' => '/CIMS/' ] // FIX: Click to open App
                ]
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // FIX: Bypass XAMPP SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // FIX: Bypass XAMPP SSL
        curl_exec($ch);
        curl_close($ch);
    }
    return true;
}
?>