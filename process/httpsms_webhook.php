<?php
// ==========================================
// httpSMS Webhook Endpoint for Incoming SMS
// ==========================================
header('Content-Type: application/json');

require_once __DIR__ . '/../Connection/db.php';

// Log raw request for debugging
$rawInput = file_get_contents('php://input');
file_put_contents(__DIR__ . '/webhook.log', date('[Y-m-d H:i:s] ') . "Received: " . $rawInput . PHP_EOL, FILE_APPEND);

if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty request body']);
    exit;
}

$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

// Support both httpSMS wrapper format { event_type, data } and raw format { content, from, to }
$eventType = $payload['event_type'] ?? 'message.received';
$msgData = $payload['data'] ?? $payload;

$senderNumber = trim($msgData['contact'] ?? ($msgData['from'] ?? ''));
$receiverNumber = trim($msgData['owner'] ?? ($msgData['to'] ?? ''));
$messageText = trim($msgData['content'] ?? '');

if (empty($senderNumber) || empty($messageText)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Missing sender number or message content']);
    exit;
}

try {
    // 1. Clean phone number for matching (get last 9-10 digits)
    $cleanSenderDigits = preg_replace('/[^0-9]/', '', $senderNumber);
    $searchPattern = '%' . substr($cleanSenderDigits, -9);

    // 2. Find matching supplier
    $supplierStmt = $pdo->prepare("SELECT id, company_name FROM suppliers WHERE REPLACE(REPLACE(REPLACE(contact_number, ' ', ''), '-', ''), '+', '') LIKE ? LIMIT 1");
    $supplierStmt->execute([$searchPattern]);
    $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);

    $supplierId = $supplier['id'] ?? null;
    $companyName = $supplier['company_name'] ?? 'Unknown Supplier';

    // 3. Match PO ID if mentioned in text (e.g. PO-20260724-123) or fetch latest PO for this supplier
    $poId = null;
    if (preg_match('/PO-\d{8}-\d+/i', $messageText, $poMatches)) {
        $poNo = strtoupper($poMatches[0]);
        $poStmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE po_no = ? LIMIT 1");
        $poStmt->execute([$poNo]);
        $poId = $poStmt->fetchColumn() ?: null;
    }

    if (!$poId && $supplierId) {
        $poStmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE supplier_id = ? ORDER BY id DESC LIMIT 1");
        $poStmt->execute([$supplierId]);
        $poId = $poStmt->fetchColumn() ?: null;
    }

    // 4. Save into supplier_sms_replies table
    $insertStmt = $pdo->prepare("
        INSERT INTO supplier_sms_replies (supplier_id, po_id, direction, sender_number, receiver_number, message_text, is_read)
        VALUES (?, ?, 'inbound', ?, ?, ?, 0)
    ");
    $insertStmt->execute([$supplierId, $poId, $senderNumber, $receiverNumber, $messageText]);

    // 5. Create System Notification for Purchasing & Admin
    $notifTitle = "📩 SMS Reply: " . $companyName;
    $snippet = mb_strimwidth($messageText, 0, 90, '...');
    $notifMsg = "Supplier ({$senderNumber}) sent: \"{$snippet}\"";

    $notifStmt = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', ?, ?)");
    $notifStmt->execute([$notifTitle, $notifMsg]);

    $notifStmtAdmin = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', ?, ?)");
    $notifStmtAdmin->execute([$notifTitle, $notifMsg]);

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Incoming SMS logged successfully',
        'supplier' => $companyName,
        'supplier_id' => $supplierId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
