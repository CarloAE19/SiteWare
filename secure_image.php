<?php
// ==========================================================
// GB INVENTORY - AUTHENTICATED SECURE IMAGE PROXY
// Verifies user session before serving sensitive signatures & photo proofs
// ==========================================================

if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 1. Check Authentication: User must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. You must be logged in to view sensitive proof documents.']);
    exit;
}

// 2. Validate Type Parameter
$type = $_GET['type'] ?? 'signatures';
if (!in_array($type, ['signatures', 'proofs', 'receipts'])) {
    http_response_code(400);
    exit('Invalid image type requested.');
}

// 3. Role-Based Authorization Control
$userRole = $_SESSION['user_role'] ?? '';

// Purchase Order Receipts are strictly restricted to Admin, Management, Purchasing, and Warehouse roles
if ($type === 'receipts' && !in_array($userRole, ['admin', 'management', 'purchasing', 'warehouse'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Access Denied. Your user role does not have permission to view Purchase Order receipts.']);
    exit;
}

// 3. Sanitize File Parameter (Prevent Directory Traversal Attacks)
$rawFile = $_GET['file'] ?? '';
$filename = basename($rawFile); // Restricts path manipulation like ../../

if (empty($filename)) {
    http_response_code(400);
    exit('No file specified.');
}

$filePath = __DIR__ . '/uploads/' . $type . '/' . $filename;

// 4. Verify File Existence
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('Image file not found.');
}

// 5. Determine Content-Type MIME with strict validation
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf'
];

$realMime = null;
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $filePath);
    finfo_close($finfo);
}
if (!$realMime && function_exists('mime_content_type')) {
    $realMime = mime_content_type($filePath);
}

if (!array_key_exists($realMime, $allowedMimes)) {
    // Strict fallback check by extension
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        $realMime = 'application/pdf';
    } elseif ($ext === 'png') {
        $realMime = 'image/png';
    } elseif ($ext === 'webp') {
        $realMime = 'image/webp';
    } elseif (in_array($ext, ['jpg', 'jpeg'])) {
        $realMime = 'image/jpeg';
    } else {
        http_response_code(415);
        exit('Unsupported Media Type.');
    }
}

// 6. Send Hardened Headers and Stream Binary Content (Layer 5)
header('Content-Type: ' . $realMime);
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
if ($realMime === 'application/pdf') {
    header("Content-Security-Policy: default-src 'none'");
}

readfile($filePath);
exit;
