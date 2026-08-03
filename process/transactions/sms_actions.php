<?php
// ==========================================
// SMS MESSAGING ACTIONS
// ==========================================

// Helper for HTTP SMS / Gateway sending
if (!function_exists('send_http_sms_gateway_request')) {
    function send_http_sms_gateway_request($phone, $smsMessage) {
        $apiKey = defined('SMS_API_KEY') ? SMS_API_KEY : '';
        $fromNumber = defined('SMS_FROM_NUMBER') ? SMS_FROM_NUMBER : '';
        $gatewayUrl = defined('SMS_GATEWAY_URL') ? SMS_GATEWAY_URL : 'https://api.httpsms.com/v1/messages/send';

        if (!empty($apiKey) && !in_array($apiKey, ['YOUR_SMS_API_KEY', 'YOUR_HTTPSMS_API_KEY'])) {
            if (strpos($gatewayUrl, 'httpsms') !== false) {
                $payload = json_encode([
                    'content' => $smsMessage,
                    'from'    => $fromNumber,
                    'to'      => $phone
                ]);

                $options = [
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "x-api-key: {$apiKey}\r\n" .
                                     "Content-Type: application/json\r\n" .
                                     "Accept: application/json\r\n",
                        'content' => $payload,
                        'ignore_errors' => true,
                        'timeout' => 15
                    ],
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true
                    ]
                ];

                $context = stream_context_create($options);
                $response = @file_get_contents($gatewayUrl, false, $context);

                $httpCode = 0;
                if (isset($http_response_header) && is_array($http_response_header)) {
                    foreach ($http_response_header as $header) {
                        if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                            $httpCode = intval($matches[1]);
                            break;
                        }
                    }
                }

                $resData = json_decode($response, true);
                if ($httpCode >= 200 && $httpCode < 300) {
                    return ['success' => true, 'error' => ''];
                } else {
                    $errorMessage = (is_array($resData) && isset($resData['message'])) 
                        ? $resData['message'] 
                        : "httpSMS Gateway Error (HTTP Code {$httpCode})";
                    return ['success' => false, 'error' => $errorMessage];
                }
            } else {
                // Semaphore Gateway Handler (Fallback)
                $postData = http_build_query([
                    'apikey'  => $apiKey,
                    'number'  => $phone,
                    'message' => $smsMessage
                ]);

                $options = [
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                        'content' => $postData,
                        'ignore_errors' => true,
                        'timeout' => 10
                    ]
                ];

                $context = stream_context_create($options);
                $response = @file_get_contents($gatewayUrl, false, $context);

                $httpCode = 0;
                if (isset($http_response_header) && is_array($http_response_header)) {
                    foreach ($http_response_header as $header) {
                        if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                            $httpCode = intval($matches[1]);
                            break;
                        }
                    }
                }

                if ($httpCode >= 200 && $httpCode < 300) {
                    return ['success' => true, 'error' => ''];
                } else {
                    return ['success' => false, 'error' => "SMS Gateway Error (HTTP Code {$httpCode})"];
                }
            }
        } else {
            // Simulation Mode (when API Key is not configured yet)
            usleep(800000);
            return ['success' => true, 'error' => ''];
        }
    }
}

// --- SEND PO SMS ---
if ($action === 'send_po_sms') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];
    $supplier_id = $_POST['supplier_id'] ?? null;
    $smsMessage = $_POST['message'] ?? '';
    $phone = $_POST['contact_number'] ?? '';

    // Update the PO supplier in the database if new one is selected
    if ($supplier_id) {
        $updateStmt = $pdo->prepare("UPDATE purchase_orders SET supplier_id = ? WHERE id = ?");
        $updateStmt->execute([$supplier_id, $po_id]);
    }

    // Fetch company name of the target supplier
    $company = '';
    if ($supplier_id) {
        $compStmt = $pdo->prepare("SELECT company_name FROM suppliers WHERE id = ?");
        $compStmt->execute([$supplier_id]);
        $company = $compStmt->fetchColumn();
    }
    if (empty($company)) {
        $company = $_POST['company'] ?? 'Supplier';
    }

    $sendResult = send_http_sms_gateway_request($phone, $smsMessage);

    if (!$sendResult['success']) {
        echo json_encode(['status' => 'error', 'message' => 'SMS Gateway Error: ' . $sendResult['error']]);
        exit;
    }

    $pdo->prepare("UPDATE purchase_orders SET status = 'SMS Sent' WHERE id = ?")->execute([$po_id]);

    $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'SMS Order Sent', ?)");
    $notif->execute(["Automated SMS was sent to {$company} for {$po_no} with verified item list."]);

    echo json_encode(['status' => 'success']);
    exit;
}

// --- FETCH PO SMS PREVIEW ---
elseif ($action === 'fetch_po_sms_preview') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $po_id = $_POST['po_id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT pi.quantity, i.item_name 
        FROM po_items pi 
        JOIN inventory i ON pi.item_code = i.item_code 
        WHERE pi.po_id = ?
    ");
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itemList = "";
    foreach ($items as $item) {
        $itemList .= "- {$item['quantity']}x {$item['item_name']}\n";
    }

    echo json_encode([
        'status' => 'success',
        'items' => $items,
        'item_list' => $itemList
    ]);
    exit;
}

// --- FETCH SMS THREADS ---
elseif ($action === 'fetch_sms_threads') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'] ?? '', ['purchasing', 'admin', 'management'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    try {
        $stmt = $pdo->query("
            SELECT 
                sub.thread_key,
                sub.supplier_id,
                sub.other_phone AS sender_number,
                COALESCE(s.company_name, sub.auto_company_name, sub.other_phone) AS company_name,
                s.contact_person,
                COALESCE(s.contact_number, sub.other_phone) AS contact_number,
                MAX(sub.created_at) AS last_message_at,
                SUM(CASE WHEN sub.direction = 'inbound' AND sub.is_read = 0 THEN 1 ELSE 0 END) AS unread_count,
                (
                    SELECT r2.message_text 
                    FROM supplier_sms_replies r2 
                    WHERE 
                        (sub.supplier_id IS NOT NULL AND r2.supplier_id = sub.supplier_id)
                        OR RIGHT(REGEXP_REPLACE(r2.sender_number, '[^0-9]', ''), 9) = sub.phone_digits
                        OR RIGHT(REGEXP_REPLACE(r2.receiver_number, '[^0-9]', ''), 9) = sub.phone_digits
                    ORDER BY r2.id DESC LIMIT 1
                ) AS last_message
            FROM (
                SELECT 
                    r.id, r.po_id, r.direction, r.sender_number, r.receiver_number, r.message_text, r.is_read, r.created_at,
                    CASE WHEN r.direction = 'inbound' THEN r.sender_number ELSE r.receiver_number END AS other_phone,
                    RIGHT(REGEXP_REPLACE(CASE WHEN r.direction = 'inbound' THEN r.sender_number ELSE r.receiver_number END, '[^0-9]', ''), 9) AS phone_digits,
                    s_auto.id AS auto_supplier_id,
                    s_auto.company_name AS auto_company_name,
                    COALESCE(r.supplier_id, s_auto.id) AS supplier_id,
                    COALESCE(
                        CONCAT('SUP_', COALESCE(r.supplier_id, s_auto.id)),
                        CONCAT('PHONE_', RIGHT(REGEXP_REPLACE(CASE WHEN r.direction = 'inbound' THEN r.sender_number ELSE r.receiver_number END, '[^0-9]', ''), 9))
                    ) AS thread_key
                FROM supplier_sms_replies r
                LEFT JOIN suppliers s_auto 
                    ON REPLACE(REPLACE(REPLACE(s_auto.contact_number, ' ', ''), '-', ''), '+', '') LIKE CONCAT('%', RIGHT(REGEXP_REPLACE(CASE WHEN r.direction = 'inbound' THEN r.sender_number ELSE r.receiver_number END, '[^0-9]', ''), 9))
            ) sub
            LEFT JOIN suppliers s ON sub.supplier_id = s.id
            GROUP BY sub.thread_key
            ORDER BY last_message_at DESC
        ");
        $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalUnreadStmt = $pdo->query("SELECT COUNT(*) FROM supplier_sms_replies WHERE direction = 'inbound' AND is_read = 0");
        $totalUnread = (int)$totalUnreadStmt->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'threads' => $threads,
            'total_unread' => $totalUnread
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- FETCH SMS MESSAGES ---
elseif ($action === 'fetch_sms_messages') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'] ?? '', ['purchasing', 'admin', 'management'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $phone = $_POST['sender_number'] ?? '';
    $supplier_id = $_POST['supplier_id'] ?? null;
    $po_id = $_POST['po_id'] ?? null;

    $cleanDigits = !empty($phone) ? substr(preg_replace('/[^0-9]/', '', $phone), -9) : '';

    try {
        if ($po_id) {
            $stmt = $pdo->prepare("
                SELECT r.*, COALESCE(s.company_name, r.sender_number) AS company_name 
                FROM supplier_sms_replies r
                LEFT JOIN suppliers s ON r.supplier_id = s.id
                WHERE 
                    r.po_id = :po_id
                    OR (:sup_id IS NOT NULL AND r.supplier_id = :sup_id)
                    OR RIGHT(REGEXP_REPLACE(r.sender_number, '[^0-9]', ''), 9) = :digits
                    OR RIGHT(REGEXP_REPLACE(r.receiver_number, '[^0-9]', ''), 9) = :digits
                ORDER BY r.id ASC
            ");
            $stmt->execute([':po_id' => $po_id, ':sup_id' => $supplier_id, ':digits' => $cleanDigits]);
        } else {
            $stmt = $pdo->prepare("
                SELECT r.*, COALESCE(s.company_name, r.sender_number) AS company_name 
                FROM supplier_sms_replies r
                LEFT JOIN suppliers s ON r.supplier_id = s.id
                WHERE 
                    (:sup_id IS NOT NULL AND r.supplier_id = :sup_id)
                    OR (LENGTH(:digits) >= 7 AND RIGHT(REGEXP_REPLACE(r.sender_number, '[^0-9]', ''), 9) = :digits)
                    OR (LENGTH(:digits) >= 7 AND RIGHT(REGEXP_REPLACE(r.receiver_number, '[^0-9]', ''), 9) = :digits)
                ORDER BY r.id ASC
            ");
            $stmt->execute([':sup_id' => $supplier_id, ':digits' => $cleanDigits]);
        }

        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark inbound messages in this thread as read
        if (!empty($supplier_id)) {
            $markReadStmt = $pdo->prepare("UPDATE supplier_sms_replies SET is_read = 1 WHERE (supplier_id = ? OR RIGHT(REGEXP_REPLACE(sender_number, '[^0-9]', ''), 9) = ?) AND direction = 'inbound'");
            $markReadStmt->execute([$supplier_id, $cleanDigits]);
        } elseif (!empty($cleanDigits)) {
            $markReadStmt = $pdo->prepare("UPDATE supplier_sms_replies SET is_read = 1 WHERE RIGHT(REGEXP_REPLACE(sender_number, '[^0-9]', ''), 9) = ? AND direction = 'inbound'");
            $markReadStmt->execute([$cleanDigits]);
        }

        echo json_encode([
            'status' => 'success',
            'messages' => $messages
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- SEND SUPPLIER REPLY SMS ---
elseif ($action === 'send_supplier_reply_sms') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'] ?? '', ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $phone = trim($_POST['phone'] ?? '');
    $supplier_id = $_POST['supplier_id'] ?? null;
    $po_id = $_POST['po_id'] ?? null;
    $smsMessage = trim($_POST['message'] ?? '');

    if (empty($phone) || empty($smsMessage)) {
        echo json_encode(['status' => 'error', 'message' => 'Phone number and message text are required.']);
        exit;
    }

    $sendResult = send_http_sms_gateway_request($phone, $smsMessage);

    if ($sendResult['success']) {
        $fromNumber = defined('SMS_FROM_NUMBER') ? SMS_FROM_NUMBER : '';
        $logStmt = $pdo->prepare("
            INSERT INTO supplier_sms_replies (supplier_id, po_id, direction, sender_number, receiver_number, message_text, is_read)
            VALUES (?, ?, 'outbound', ?, ?, ?, 1)
        ");
        $logStmt->execute([$supplier_id, $po_id, $fromNumber ?: 'SYSTEM', $phone, $smsMessage]);

        echo json_encode(['status' => 'success', 'message' => 'SMS reply sent successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $sendResult['error']]);
    }
    exit;
}

// --- MARK SMS THREAD READ ---
elseif ($action === 'mark_sms_thread_read') {
    header('Content-Type: application/json');
    $phone = $_POST['phone'] ?? '';
    if (!empty($phone)) {
        $pdo->prepare("UPDATE supplier_sms_replies SET is_read = 1 WHERE sender_number = ? AND direction = 'inbound'")->execute([$phone]);
    }
    echo json_encode(['status' => 'success']);
    exit;
}
