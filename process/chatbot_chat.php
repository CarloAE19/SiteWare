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

// 5. Read Request Parameters
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

// Extract latest user message for targeted entity lookup
$latestUserQuery = '';
foreach (array_reverse($messages) as $m) {
    if (($m['role'] ?? '') === 'user') {
        $latestUserQuery = $m['text'] ?? '';
        break;
    }
}

$userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'requestor';
$userName = $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'User';

// 6. Gather Role-Based System Context from DB
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

    // -------------------------------------------------------------
    // 7. ON-DEMAND DEEP ENTITY LOOKUP ENGINE
    // If the user asks about a specific PO, RS, Withdrawal, or Item code,
    // fetch full metadata, line items, quantities, and pricing into context!
    // -------------------------------------------------------------
    if (!empty($latestUserQuery)) {
        // A. Look up Purchase Orders (PO-xxxx)
        if (preg_match_all('/\b(PO-(?:\d{4,8}-\d+|\d+))\b/i', $latestUserQuery, $poMatches)) {
            foreach (array_unique($poMatches[1]) as $poNo) {
                $poStmt = $pdo->prepare("
                    SELECT p.id, p.po_no, p.status, p.expected_delivery_date, p.created_at,
                           s.company_name as supplier_name, s.contact_person, s.contact_number as supplier_contact,
                           r.rs_no, r.project_name, u.name as prepared_by_name
                    FROM purchase_orders p
                    LEFT JOIN suppliers s ON p.supplier_id = s.id
                    LEFT JOIN requisitions r ON p.rs_id = r.id
                    LEFT JOIN users u ON p.prepared_by = u.id
                    WHERE p.po_no = ?
                ");
                $poStmt->execute([$poNo]);
                $po = $poStmt->fetch(PDO::FETCH_ASSOC);

                if ($po) {
                    $itemsStmt = $pdo->prepare("
                        SELECT pi.item_code, pi.quantity, 
                               COALESCE(pi.unit_price, i.unit_price, 0) as unit_price,
                               (pi.quantity * COALESCE(pi.unit_price, i.unit_price, 0)) as subtotal,
                               COALESCE(i.item_name, pi.custom_item_name, pi.item_code) as item_name, 
                               COALESCE(i.unit, pi.unit, 'pcs') as unit
                        FROM po_items pi
                        LEFT JOIN inventory i ON pi.item_code = i.item_code
                        WHERE pi.po_id = ?
                    ");
                    $itemsStmt->execute([$po['id']]);
                    $poItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                    $totalVal = array_sum(array_column($poItems, 'subtotal'));
                    $dbData['queried_purchase_orders'][$poNo] = [
                        'po_number' => $po['po_no'],
                        'status' => $po['status'],
                        'supplier' => $po['supplier_name'] ?: 'N/A',
                        'supplier_contact' => $po['supplier_contact'] ?: 'N/A',
                        'expected_delivery' => $po['expected_delivery_date'] ?: 'Not specified',
                        'project' => $po['project_name'] ?: 'General Stock',
                        'linked_rs_no' => $po['rs_no'] ?: 'Direct PO',
                        'total_order_amount' => 'PHP ' . number_format($totalVal, 2),
                        'line_items' => $poItems
                    ];
                }
            }
        }

        // B. Look up Requisition Slips (RS-xxxx)
        if (preg_match_all('/\b(RS-(?:\d{4}-\d+|\d+))\b/i', $latestUserQuery, $rsMatches)) {
            foreach (array_unique($rsMatches[1]) as $rsNo) {
                $rsStmt = $pdo->prepare("
                    SELECT r.id, r.rs_no, r.project_name, r.requestor_name, r.requestor_id,
                           r.status, r.urgency, r.remarks, r.type, r.created_at, app_u.name as approver_name
                    FROM requisitions r
                    LEFT JOIN users app_u ON r.approved_by = app_u.id
                    WHERE r.rs_no = ?
                ");
                $rsStmt->execute([$rsNo]);
                $rs = $rsStmt->fetch(PDO::FETCH_ASSOC);

                if ($rs) {
                    // RBAC check: Requestors can only inspect their own requisitions
                    if ($userRole === 'requestor' && (int)$rs['requestor_id'] !== (int)$_SESSION['user_id']) {
                        $dbData['queried_requisitions'][$rsNo] = [
                            'notice' => "Access Restricted: {$rsNo} belongs to another user. You may only query your own requisitions."
                        ];
                        continue;
                    }

                    $itemsStmt = $pdo->prepare("
                        SELECT ri.item_code, ri.quantity,
                               COALESCE(i.item_name, ri.new_item_name, ri.item_code) as item_name,
                               COALESCE(i.unit, ri.new_unit, 'pcs') as unit,
                               ri.item_status, ri.item_remarks
                        FROM requisition_items ri
                        LEFT JOIN inventory i ON ri.item_code = i.item_code
                        WHERE ri.requisition_id = ?
                    ");
                    $itemsStmt->execute([$rs['id']]);
                    $rsItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                    $dbData['queried_requisitions'][$rsNo] = [
                        'rs_number' => $rs['rs_no'],
                        'project' => $rs['project_name'],
                        'requestor' => $rs['requestor_name'],
                        'status' => $rs['status'],
                        'urgency' => $rs['urgency'],
                        'remarks' => $rs['remarks'] ?: 'None',
                        'type' => $rs['type'] ?: 'project',
                        'approver' => $rs['approver_name'] ?: 'Pending Review',
                        'created_date' => date('M d, Y', strtotime($rs['created_at'])),
                        'line_items' => $rsItems
                    ];
                }
            }
        }

        // C. Look up Material Withdrawals (WD-xxxx, WS-xxxx, WITH-xxxx)
        if (preg_match_all('/\b((?:WD|WS)-(?:\d{4}-\d+|\d+)|(?:WITH|WD|WS)-\d+)\b/i', $latestUserQuery, $wdMatches)) {
            foreach (array_unique($wdMatches[1]) as $wdNo) {
                $wStmt = $pdo->prepare("
                    SELECT w.id, w.withdrawal_no, w.project_name, w.received_by,
                           w.date_withdrawn, w.remarks, u.name as released_by_name
                    FROM withdrawals w
                    LEFT JOIN users u ON w.released_by = u.id
                    WHERE w.withdrawal_no = ?
                ");
                $wStmt->execute([$wdNo]);
                $w = $wStmt->fetch(PDO::FETCH_ASSOC);

                if ($w) {
                    $itemsStmt = $pdo->prepare("
                        SELECT wi.item_code, wi.quantity,
                               COALESCE(i.item_name, wi.item_code) as item_name,
                               COALESCE(i.unit, 'pcs') as unit
                        FROM withdrawal_items wi
                        LEFT JOIN inventory i ON wi.item_code = i.item_code
                        WHERE wi.withdrawal_id = ?
                    ");
                    $itemsStmt->execute([$w['id']]);
                    $wItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                    $dbData['queried_withdrawals'][$wdNo] = [
                        'withdrawal_no' => $w['withdrawal_no'],
                        'project' => $w['project_name'],
                        'received_by' => $w['received_by'] ?: 'Field Personnel',
                        'released_by' => $w['released_by_name'] ?: 'Warehouse Officer',
                        'date_withdrawn' => date('M d, Y g:i A', strtotime($w['date_withdrawn'])),
                        'remarks' => $w['remarks'] ?: 'None',
                        'withdrawn_items' => $wItems
                    ];
                }
            }
        }

        // D. Look up Inventory Item Profile (ITM-xxxx)
        if (preg_match_all('/\b(ITM-\d+)\b/i', $latestUserQuery, $itmMatches)) {
            foreach (array_unique($itmMatches[1]) as $itmCode) {
                $itemStmt = $pdo->prepare("SELECT * FROM inventory WHERE item_code = ?");
                $itemStmt->execute([$itmCode]);
                $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

                if ($item) {
                    // 30-day consumption
                    $cStmt = $pdo->prepare("
                        SELECT SUM(wi.quantity) as total_consumed
                        FROM withdrawal_items wi
                        JOIN withdrawals w ON wi.withdrawal_id = w.id
                        WHERE wi.item_code = ? AND w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ");
                    $cStmt->execute([$itmCode]);
                    $c30d = (int)($cStmt->fetchColumn() ?: 0);

                    // Recent withdrawals
                    $recentStmt = $pdo->prepare("
                        SELECT w.withdrawal_no, w.project_name, wi.quantity, w.date_withdrawn
                        FROM withdrawal_items wi
                        JOIN withdrawals w ON wi.withdrawal_id = w.id
                        WHERE wi.item_code = ?
                        ORDER BY w.date_withdrawn DESC
                        LIMIT 3
                    ");
                    $recentStmt->execute([$itmCode]);
                    $recentW = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($recentW as &$rw) {
                        $rw['date'] = date('M d, Y', strtotime($rw['date_withdrawn']));
                        unset($rw['date_withdrawn']);
                    }

                    $dbData['queried_items'][$itmCode] = [
                        'item_code' => $item['item_code'],
                        'item_name' => $item['item_name'],
                        'category' => $item['category'],
                        'current_stock' => (int)$item['quantity'],
                        'unit' => $item['unit'],
                        'unit_price' => 'PHP ' . number_format((float)($item['unit_price'] ?? 0), 2),
                        'reorder_level' => (int)($item['reorder_level'] ?? 10),
                        'status' => (int)$item['quantity'] === 0 ? 'Out of Stock' : ($item['status'] ?: 'In Stock'),
                        'consumed_last_30_days' => $c30d,
                        'recent_withdrawals' => $recentW
                    ];
                }
            }
        }
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

// 8. Assemble Prompt & AI Model Payload
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
    "7. INTERACTIVE ENTITY CODES: Whenever you mention or list any entity code (such as Requisition Slips **RS-2026-1960**, Purchase Orders **PO-20260805-123**, Material Withdrawals **WS-2026-001**, or Item Codes **ITM-4430**), simply write the code clearly in bold (e.g. **RS-2026-1960**, **ITM-4430**). DO NOT add extra text like '[View Details]' or '(link to modal)' after it, because the frontend automatically converts the entity code into an interactive clickable button that opens the details modal!\n" .
    "8. COMPLETE ENTITY BREAKDOWN: If the user asks about the contents, line items, or specific details of a PO, Requisition (RS), Withdrawal, or Item code, look at 'queried_purchase_orders', 'queried_requisitions', 'queried_withdrawals', or 'queried_items' in the database context. Provide a comprehensive, itemized breakdown including item names, item codes (in bold like **ITM-xxxx**), quantities, units, statuses, and pricing if available, organized cleanly in bullet points.";

$apiKey = trim(AI_API_KEY);

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
