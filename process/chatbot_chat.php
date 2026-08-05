<?php
ini_set('display_errors', 0);
error_reporting(0);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized session. Please log in.']);
    exit;
}

require_once __DIR__ . '/../Connection/db.php';

// Check if AI API key is defined
if (!defined('AI_API_KEY') || empty(AI_API_KEY)) {
    echo json_encode(['error' => 'AI API Key is not configured in the .env file.']);
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'requestor';
$userName = $_SESSION['user_name'] ?? 'User';

// 1. Gather Role-Based System Context from DB
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
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// 2. Read Request Parameters
$inputJSON = file_get_contents('php://input');
if (empty($inputJSON) && php_sapi_name() === 'cli') {
    $inputJSON = file_get_contents('php://stdin');
}
$input = json_decode($inputJSON, TRUE);
$messages = $input['messages'] ?? []; // Entire chat history array from client

if (empty($messages)) {
    echo json_encode(['error' => 'No message history provided.']);
    exit;
}

// 3. Assemble Prompt & AI Model Payload
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
    "5. CRITICAL DIRECTIVE: You are strictly an inventory and logistics assistant. You must ONLY answer questions directly related to construction materials, stock levels, suppliers, withdrawals, purchase orders, requisitions, projects, and users. If the user's query is unrelated to inventory, logistics, or SiteWare data, you MUST refuse to answer by saying exactly: 'I am only programmed to assist with inventory, logistics, and SiteWare system data. Please ask an inventory-related question.' Do not engage in chit-chat, write code, or answer questions about general topics, AI technology, API integrations, or software development.\n" .
    "6. LANGUAGE MATCHING & GRAMMAR: Respond in the same language or dialect (English, Tagalog, Cebuano/Bisaya, or Taglish) that the user used. Ensure your grammar, vocabulary, and sentence structures are natural, fluent, and grammatically correct for that specific language or dialect. Avoid awkward word-for-word translation mixtures (e.g., do not mix Tagalog grammatical pronouns/markers like 'anong' with Bisaya words to form unnatural phrases like 'anong tanan'; use proper Cebuano/Bisaya like 'Unsa ang tanan' or proper Tagalog like 'Ano ang lahat ng'). When using Bisaya, use 'ko' (I/me) instead of 'ka' (you) when referring to yourself.\n" .
    "7. INTERACTIVE ENTITY CODES: Whenever you mention or list any entity code (such as Requisition Slips **RS-2026-1960**, Purchase Orders **PO-20260805-123**, Material Withdrawals **WS-2026-001**, or Item Codes **ITM-4430**), simply write the code clearly in bold (e.g. **RS-2026-1960**, **ITM-4430**). DO NOT add extra text like '[View Details]' or '(link to modal)' after it, because the frontend automatically converts the entity code into an interactive clickable button that opens the details modal!";

$apiKey = AI_API_KEY;
$model = defined('AI_MODEL') ? AI_MODEL : 'meta/llama-3.1-8b-instruct';

// Detect provider
$isNvidia = (strpos($apiKey, 'nvapi-') === 0) || defined('AI_MODEL');

if ($isNvidia) {
    // NVIDIA NIM API Endpoint (OpenAI Chat Completions format)
    $apiUrl = "https://integrate.api.nvidia.com/v1/chat/completions";

    $nvidiaMessages = [];
    $nvidiaMessages[] = ['role' => 'system', 'content' => $systemInstructionText];

    foreach ($messages as $msg) {
        $role = $msg['role'] === 'user' ? 'user' : 'assistant';
        $nvidiaMessages[] = [
            'role' => $role,
            'content' => $msg['text']
        ];
    }

    $payload = [
        'model' => $model,
        'messages' => $nvidiaMessages,
        'temperature' => 0.2,
        'max_tokens' => 1024
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
} else {
    // Google Gemini API generateContent
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    $contents = [];
    foreach ($messages as $msg) {
        $role = $msg['role'] === 'user' ? 'user' : 'model';
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
        'verify_peer' => true,
        'verify_peer_name' => true
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($apiUrl, false, $context);

if ($response === false) {
    $error_err = error_get_last();
    $error_msg = isset($error_err['message']) ? $error_err['message'] : 'Unknown connection error';
    echo json_encode(['error' => 'HTTP Request Failed: ' . $error_msg]);
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
    $errorMessage = $responseData['error']['message'] ?? 'Unknown API error.';
    echo json_encode(['error' => 'API Error (HTTP ' . $httpCode . '): ' . $errorMessage]);
    exit;
}

if ($isNvidia) {
    if (isset($responseData['choices'][0]['message']['content'])) {
        echo json_encode(['reply' => $responseData['choices'][0]['message']['content']]);
    } else {
        echo json_encode(['error' => 'Invalid response structure from Nvidia NIM API.', 'raw' => $responseData]);
    }
} else {
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        echo json_encode(['reply' => $responseData['candidates'][0]['content']['parts'][0]['text']]);
    } else {
        echo json_encode(['error' => 'Invalid response structure from Gemini API.', 'raw' => $responseData]);
    }
}
exit;
