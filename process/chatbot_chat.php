<?php
ini_set('display_errors', 0);
error_reporting(0);
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Unauthorized session. Please log in.',
        'error' => 'Unauthorized session. Please log in.'
    ]);
    exit;
}

require_once __DIR__ . '/../Connection/db.php';

// 2. CSRF Token Validation
if (function_exists('getallheaders')) {
    $requestHeaders = getallheaders();
    $csrfToken = $requestHeaders['X-CSRF-Token'] ?? ($requestHeaders['x-csrf-token'] ?? '');
    if (!empty($_SESSION['csrf_token']) && !empty($csrfToken) && !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'CSRF validation failed. Please refresh your page.',
            'error' => 'CSRF validation failed. Please refresh your page.'
        ]);
        exit;
    }
}

// 3. Rate Limiting: Max 10 messages per 60 seconds per user
$aiRateLimit = check_rate_limit('ai_chat_user_' . $_SESSION['user_id'], 10, 60);
if (!$aiRateLimit['allowed']) {
    http_response_code(429);
    $rateLimitMsg = "You are sending messages too quickly. Please wait {$aiRateLimit['retry_after']}s before sending another message.";
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $rateLimitMsg,
        'error' => $rateLimitMsg
    ]);
    exit;
}
record_rate_limit_attempt('ai_chat_user_' . $_SESSION['user_id']);

// 4. Check if AI API Key is Defined
if (!defined('AI_API_KEY') || empty(AI_API_KEY)) {
    http_response_code(500);
    $configMsg = 'AI API Key is not configured in the .env file.';
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $configMsg,
        'error' => $configMsg
    ]);
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'requestor';
$userName = $_SESSION['user_name'] ?? 'User';

// 5. Gather Role-Based System Context from DB
$dbData = [];

try {
    switch ($userRole) {
        case 'admin':
            // Users count
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $dbData['total_users'] = (int) $stmt->fetchColumn();

            // Roles breakdown
            $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            $dbData['roles_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Active projects count
            $stmt = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'active'");
            $dbData['active_projects'] = (int) $stmt->fetchColumn();

            // Total items
            $stmt = $pdo->query("SELECT COUNT(*) FROM inventory");
            $dbData['total_inventory_items'] = (int) $stmt->fetchColumn();
            break;

        case 'management':
        case 'approver':
            // Pending requisitions count and items
            $stmt = $pdo->query("SELECT id, rs_no, requestor_name, project_name, urgency, created_at FROM requisitions WHERE status = 'Pending Approval' ORDER BY created_at DESC LIMIT 5");
            $dbData['pending_requisitions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count of requisitions by status
            $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM requisitions GROUP BY status");
            $dbData['requisitions_status_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Critical stock items (quantity < 15)
            $stmt = $pdo->query("SELECT item_name, quantity, unit FROM inventory WHERE quantity < 15 ORDER BY quantity ASC LIMIT 5");
            $dbData['low_stock_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Top consumed items in last 30 days
            $stmt = $pdo->query("
                SELECT i.item_name, SUM(wi.quantity) as total_consumed 
                FROM withdrawal_items wi 
                JOIN withdrawals w ON wi.withdrawal_id = w.id 
                JOIN inventory i ON wi.item_code = i.item_code 
                WHERE w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                GROUP BY i.item_name 
                ORDER BY total_consumed DESC 
                LIMIT 5
            ");
            $dbData['top_consumed_last_30_days'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'purchasing':
            // Active suppliers count
            $stmt = $pdo->query("SELECT COUNT(*) FROM suppliers WHERE status = 'Active'");
            $dbData['active_suppliers_count'] = (int) $stmt->fetchColumn();

            // Pending delivery Purchase Orders
            $stmt = $pdo->query("SELECT po_no, status, created_at FROM purchase_orders WHERE status = 'Pending Delivery' ORDER BY created_at DESC LIMIT 5");
            $dbData['pending_delivery_pos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Items needing restock (quantity < 15)
            $stmt = $pdo->query("SELECT item_name, quantity, unit FROM inventory WHERE quantity < 15 ORDER BY quantity ASC LIMIT 10");
            $dbData['items_needing_restock'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Recent Purchase Orders
            $stmt = $pdo->query("
                SELECT po.po_no, s.company_name, po.status, po.created_at 
                FROM purchase_orders po 
                JOIN suppliers s ON po.supplier_id = s.id 
                ORDER BY po.created_at DESC 
                LIMIT 5
            ");
            $dbData['recent_pos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'warehouse':
            // Stock statistics
            $stmt = $pdo->query("SELECT SUM(quantity) as total_qty, COUNT(*) as unique_items FROM inventory");
            $dbData['inventory_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Out of stock
            $stmt = $pdo->query("SELECT item_name, item_code FROM inventory WHERE quantity = 0 LIMIT 5");
            $dbData['out_of_stock_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Low stock (quantity < 10)
            $stmt = $pdo->query("SELECT item_name, quantity, unit FROM inventory WHERE quantity < 10 AND quantity > 0 LIMIT 5");
            $dbData['low_stock_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Recent withdrawals
            $stmt = $pdo->query("SELECT w.withdrawal_no, w.project_name, w.date_withdrawn FROM withdrawals w ORDER BY w.date_withdrawn DESC LIMIT 5");
            $dbData['recent_withdrawals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Last audit discrepancy
            $stmt = $pdo->query("SELECT audit_month, total_discrepancy_items, remarks FROM inventory_audits ORDER BY created_at DESC LIMIT 1");
            $dbData['last_audit_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;

        case 'requestor':
            // Their own recent requisitions
            $stmt = $pdo->prepare("SELECT rs_no, project_name, urgency, status, created_at FROM requisitions WHERE requestor_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$_SESSION['user_id']]);
            $dbData['my_recent_requisitions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Their approved requisitions
            $stmt = $pdo->prepare("SELECT rs_no, project_name, created_at FROM requisitions WHERE requestor_id = ? AND status IN ('Approved', 'PO Created', 'Staged (Ready for Pickup)') ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$_SESSION['user_id']]);
            $dbData['my_approved_requisitions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Active projects
            $stmt = $pdo->query("SELECT project_name FROM projects WHERE status = 'active'");
            $dbData['active_projects_list'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}

// 6. Read Request Parameters
$inputJSON = file_get_contents('php://input');
if (empty($inputJSON) && php_sapi_name() === 'cli') {
    $inputJSON = file_get_contents('php://stdin');
}
$input = json_decode($inputJSON, true);
$messages = $input['messages'] ?? []; // Entire chat history array from client

if (empty($messages) || !is_array($messages)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'No message history provided.',
        'error' => 'No message history provided.'
    ]);
    exit;
}

// Sliding window: Retain last 10 messages for token efficiency and high speed
if (count($messages) > 10) {
    $messages = array_slice($messages, -10);
}

// 7. Assemble Prompt & AI Model Payload
$systemRoleDescriptions = [
    'admin' => "You are the SiteWare System Admin Assistant. Your personality is highly professional, technical, and security-minded. Assist the user with system configuration, database structures, user counts, and general backend administration. Keep responses concise.",
    'management' => "You are the SiteWare Management Advisor. Your personality is strategic, cost-conscious, and data-driven. Assist the user with inventory analysis, high-level reporting trends, pending Requisition Slip (RS) approvals, and critical alerts. Help them make fast business decisions.",
    'purchasing' => "You are the SiteWare Purchasing Assistant. Your personality is logistics-oriented, negotiator, and detail-focused. Assist the user with supplier availability, pending purchase orders, lead times (standard 3-5 days), and ordering suggestions.",
    'warehouse' => "You are the SiteWare Warehouse Logistics Assistant. Your personality is practical, focused on organization, material receipt, weekly counts, physical stock-outs, and withdrawal slips. Help with quick checks of item status and inventory audits.",
    'requestor' => "You are the SiteWare Project Engineer Assistant. Your personality is supportive, collaborative, and construction-focused. Assist the user with checking the status of their own requisitions, verifying active projects, and identifying material availability for their sites."
];

$roleDescription = $systemRoleDescriptions[$userRole] ?? "You are the SiteWare Intelligent Assistant.";
$today = date('F d, Y');

$systemInstructionText = $roleDescription . "\n\n" .
    "Today's date is: " . $today . "\n" .
    "The logged-in user is: " . htmlspecialchars($userName) . " (Role: " . htmlspecialchars($userRole) . ")\n\n" .
    "Here is the real-time system status and database context retrieved for this role:\n" .
    json_encode($dbData, JSON_PRETTY_PRINT) . "\n\n" .
    "IMPORTANT INSTRUCTIONS:\n" .
    "1. Answer questions based on the real-time database context provided above.\n" .
    "2. If the user asks about specific items, numbers, or statuses, use the numbers from the context. If you don't know something or it's not in the context, say: 'I don't have that specific record in my real-time database sync, but I can help you with...' rather than inventing facts.\n" .
    "3. Keep answers clear, structured, and helpful. Use bullet points or numbered lists where appropriate.\n" .
    "4. Limit responses to a maximum of 150-200 words. Keep it conversational but concise.\n" .
    "5. CRITICAL DIRECTIVE: You are strictly an inventory and logistics assistant for SiteWare. You must ONLY answer questions directly related to construction materials, stock levels, suppliers, withdrawals, purchase orders, requisitions, projects, and users. If the user's query is off-topic, gibberish, or casual chit-chat (e.g. 'waw', 'hi', 'what is the weather'), refuse politely using clean, natural grammar matching the user's language:\n" .
    "   - For English queries: 'I am only programmed to assist with inventory, logistics, and SiteWare system data. Please ask an inventory-related question.'\n" .
    "   - For Tagalog queries: 'Naka-program lamang ako para tumulong sa inventory, logistics, at SiteWare system data. Pakiusap magtanong ng tungkol sa inventory.'\n" .
    "   - For Bisaya queries: 'Naka-program lang ko para motabang sa inventory, logistics, ug SiteWare system data. Palihug pangutana bahin sa inventory.'\n" .
    "6. PERFECT DIALECT & GRAMMAR: Never mix Tagalog grammar markers ('ang', 'anong', 'pwedeng') with Bisaya vocabulary ('tanan', 'kaayo', 'unsa') into unnatural hybrid phrases (such as 'ang tanan ang tanan'). Keep Tagalog strictly proper Tagalog, Cebuano/Bisaya strictly proper Bisaya, and English strictly proper English.\n" .
    "7. INTERACTIVE ENTITY CODES: Whenever you mention or list any entity code (such as Requisition Slips **RS-2026-1960**, Purchase Orders **PO-20260805-123**, Material Withdrawals **WS-2026-001**, or Item Codes **ITM-4430**), simply write the code clearly in bold (e.g. **RS-2026-1960**, **ITM-4430**). DO NOT add extra text like '[View Details]' or '(link to modal)' after it, because the frontend automatically converts the entity code into an interactive clickable button that opens the details modal!";

$apiKey = trim(AI_API_KEY);
$model = defined('AI_MODEL') ? AI_MODEL : 'meta/llama-3.3-70b-instruct';

// Detect Provider from API Key format
$isOpenAICompatible = false;
$apiUrl = '';
$headers = [];
$payload = [];

if (strpos($apiKey, 'gsk_') === 0) {
    // 1. Groq API (Ultra-fast active model)
    $isOpenAICompatible = true;
    $apiUrl = "https://api.groq.com/openai/v1/chat/completions";
    $model = defined('AI_MODEL') && !empty(AI_MODEL) && strpos(AI_MODEL, 'nvidia/') === false ? AI_MODEL : 'groq/compound-mini';
} elseif (strpos($apiKey, 'sk-or-') === 0) {
    // 2. OpenRouter API
    $isOpenAICompatible = true;
    $apiUrl = "https://openrouter.ai/api/v1/chat/completions";
    $model = defined('AI_MODEL') ? AI_MODEL : 'meta-llama/llama-3.3-70b-instruct:free';
} elseif (strpos($apiKey, 'nvapi-') === 0) {
    // 3. NVIDIA NIM API
    $isOpenAICompatible = true;
    $apiUrl = "https://integrate.api.nvidia.com/v1/chat/completions";
    $model = defined('AI_MODEL') ? AI_MODEL : 'nvidia/llama-3.1-nemotron-70b-instruct';
} else {
    // 4. Google Gemini API (Default fallback for AIzaSy... keys)
    $isOpenAICompatible = false;
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
}

if ($isOpenAICompatible) {
    $formattedMessages = [];
    $formattedMessages[] = ['role' => 'system', 'content' => $systemInstructionText];

    foreach ($messages as $msg) {
        $role = ($msg['role'] === 'user') ? 'user' : 'assistant';
        $formattedMessages[] = [
            'role' => $role,
            'content' => $msg['text']
        ];
    }

    $payload = [
        'model' => $model,
        'messages' => $formattedMessages,
        'temperature' => 0.2,
        'max_tokens' => 1024
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
} else {
    $contents = [];
    foreach ($messages as $msg) {
        $role = ($msg['role'] === 'user') ? 'user' : 'model';
        $contents[] = [
            'role' => $role,
            'parts' => [
                ['text' => $msg['text']]
            ]
        ];
    }

    $payload = [
        'contents' => $contents,
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemInstructionText]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 800
        ]
    ];

    $headers = [
        'Content-Type: application/json'
    ];
}

$options = [
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers) . "\r\n",
        'content' => json_encode($payload),
        'ignore_errors' => true,
        'timeout' => 30
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($apiUrl, false, $context);

if ($response === false) {
    http_response_code(503);
    $connErrMsg = 'Connection Error: Unable to reach AI API endpoint. Please verify server internet connectivity.';
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $connErrMsg,
        'error' => $connErrMsg
    ]);
    exit;
}

$httpCode = 0;
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $h, $matches)) {
            $httpCode = intval($matches[1]);
            break;
        }
    }
}

$responseData = json_decode($response, true);

if ($httpCode !== 200) {
    http_response_code($httpCode >= 400 && $httpCode < 600 ? $httpCode : 500);
    $errorMessage = $responseData['error']['message'] ?? ($responseData['detail'] ?? 'API Error encountered.');
    
    if ($httpCode === 404 || $httpCode === 401 || $httpCode === 403) {
        $keyGuidance = "AI API Key Error (HTTP {$httpCode}): Your API key has expired, is unauthorized, or reached its free quota limit. Please generate a new key and update AI_API_KEY in your .env file.";
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => $keyGuidance,
            'error' => $keyGuidance
        ]);
    } else {
        $generalApiErr = "API Error (HTTP {$httpCode}): {$errorMessage}";
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => $generalApiErr,
            'error' => $generalApiErr
        ]);
    }
    exit;
}

// Parse Response
$replyText = '';
if ($isOpenAICompatible) {
    if (isset($responseData['choices'][0]['message']['content'])) {
        $replyText = $responseData['choices'][0]['message']['content'];
    }
} else {
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $replyText = $responseData['candidates'][0]['content']['parts'][0]['text'];
    }
}

if (!empty($replyText)) {
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Response generated successfully.',
        'data' => [
            'reply' => $replyText
        ],
        'reply' => $replyText // Backwards compatibility with existing frontends
    ]);
} else {
    http_response_code(502);
    $parseErr = 'Invalid or empty response structure received from AI provider.';
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $parseErr,
        'error' => $parseErr,
        'raw' => $responseData
    ]);
}
exit;
