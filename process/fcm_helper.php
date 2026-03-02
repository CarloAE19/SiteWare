<?php
/* ==========================================================
 * GB INVENTORY - FIREBASE PUSH NOTIFICATION ENGINE
 * Enterprise-grade Google OAuth2 JWT Generator (No Composer required!)
 * ========================================================== */

function sendPushNotification($pdo, $title, $body, $targetRole = null, $targetUserId = null) {
    // 1. Load the Private Key you downloaded from Firebase
    $keyFile = __DIR__ . '/firebase-key.json';
    if (!file_exists($keyFile)) {
        error_log("FCM ERROR: firebase-key.json is missing in the process folder!");
        return false;
    }

    $keyData = json_decode(file_get_contents($keyFile), true);
    $projectId = $keyData['project_id'];

    // 2. Generate a Secure JWT Token to authenticate with Google
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

    // 3. Exchange JWT for a Google Access Token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $accessToken = $response['access_token'] ?? null;
    if (!$accessToken) {
        error_log("FCM ERROR: Failed to get Google Access Token.");
        return false;
    }

    // 4. Find exactly who needs to receive this notification from the DB
    $tokens = [];
    if ($targetUserId) {
        $stmt = $pdo->prepare("SELECT fcm_token FROM users WHERE id = ? AND fcm_token IS NOT NULL");
        $stmt->execute([$targetUserId]);
        if ($res = $stmt->fetch()) $tokens[] = $res['fcm_token'];
    } elseif ($targetRole) {
        $stmt = $pdo->prepare("SELECT fcm_token FROM users WHERE role = ? AND fcm_token IS NOT NULL");
        $stmt->execute([$targetRole]);
        foreach ($stmt->fetchAll() as $row) $tokens[] = $row['fcm_token'];
    }

    // 5. Blast the notification to every targeted device!
    foreach ($tokens as $token) {
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
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
        curl_exec($ch);
        curl_close($ch);
    }
    return true;
}
?>