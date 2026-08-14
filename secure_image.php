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

// 5. Determine Content-Type MIME
$mimeType = mime_content_type($filePath);
if (!$mimeType || !in_array($mimeType, ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])) {
    // Default fallback based on extension
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeType = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : 'image/jpeg');
}

// 6. Send Headers and Stream Binary File Content
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-cache, no-store, must-revalidate');
readfile($filePath);
exit;
