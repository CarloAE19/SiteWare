<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
if (!in_array($_SESSION['user_role'], ['admin', 'management', 'purchasing'])) {
    header("Location: index");
    exit;
}

require_once 'Connection/db.php';

// Determine logged-in user role
$currentUserRole = strtolower($_SESSION['user_role'] ?? 'admin');

// Fetch last saved AI prediction and timestamp (Role-tailored with global fallback)
$lastPrediction = null;
$lastTimestamp = null;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute(['last_ai_prediction_' . $currentUserRole]);
    $lastPrediction = $stmt->fetchColumn();

    if (!$lastPrediction) {
        $stmt->execute(['last_ai_prediction']);
        $lastPrediction = $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute(['last_ai_timestamp_' . $currentUserRole]);
    $lastTimestamp = $stmt->fetchColumn();

    if (!$lastTimestamp) {
        $stmt->execute(['last_ai_timestamp']);
        $lastTimestamp = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    // Database connection or table issue
}

// Helper to polish Executive Summary into an executive briefing container
function polishSummaryHtmlPhp($html) {
    if (empty($html)) return '';
    if (strpos($html, 'cims-summary-container') !== false) return $html;

    $date = date('F d, Y');
    if (preg_match('/(?:Analysis Date:\s*|<strong>Analysis Date:<\/strong>\s*)([^<]+)/i', $html, $m)) {
        $date = trim($m[1]);
    }

    $narrative = preg_replace('/<div[^>]*>.*?Analysis Date.*?<\/div>/is', '', $html);
    $narrative = preg_replace('/<\/?p[^>]*>/i', '', $narrative);
    $narrative = trim($narrative);

    $hasRisk = preg_match('/stockout|critical|deplet|urgenc/i', $narrative);
    $statusPill = $hasRisk 
        ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" style="font-size: 0.72rem;"><i class="bi bi-shield-exclamation me-1"></i>Replenishment Flagged</span>'
        : '<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1" style="font-size: 0.72rem;"><i class="bi bi-shield-check me-1"></i>Operations Healthy</span>';

    $out = '<div class="cims-summary-container">';
    $out .= '  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-2 mb-2 border-bottom">';
    $out .= '    <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.75rem;"><i class="bi bi-calendar3 text-primary me-1"></i> ' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</span>';
    $out .= '    ' . $statusPill;
    $out .= '  </div>';
    
    $out .= '  <div class="p-3 rounded-3 bg-light-subtle border shadow-none mb-2">';
    $out .= '    <div class="d-flex align-items-start gap-2">';
    $out .= '      <div class="rounded-circle bg-primary-subtle text-primary p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-0.5" style="width: 24px; height: 24px;"><i class="bi bi-lightning-charge-fill" style="font-size: 0.75rem;"></i></div>';
    $out .= '      <div class="flex-grow-1 text-dark" style="font-size: 0.86rem; line-height: 1.65;">' . $narrative . '</div>';
    $out .= '    </div>';
    $out .= '  </div>';

    $out .= '  <div class="d-flex flex-wrap gap-1 mt-2 pt-1">';
    $out .= '    <span class="badge bg-body-secondary text-secondary border px-2 py-1" style="font-size: 0.7rem;"><i class="bi bi-check2-circle text-success me-1"></i>Velocity Active</span>';
    $out .= '    <span class="badge bg-body-secondary text-secondary border px-2 py-1" style="font-size: 0.7rem;"><i class="bi bi-geo-alt text-primary me-1"></i>Site Allocation Monitored</span>';
    $out .= '    <span class="badge bg-body-secondary text-secondary border px-2 py-1" style="font-size: 0.7rem;"><i class="bi bi-clock-history text-info me-1"></i>Runway Tracked</span>';
    $out .= '  </div>';
    $out .= '</div>';

    return $out;
}

// Helper to polish Strategic Directives into modern interactive cards
function polishActionsHtmlPhp($html) {
    if (empty($html)) return '';
    if (strpos($html, 'cims-directives-container') !== false) return $html;

    preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches);
    if (empty($matches[1])) {
        $lines = preg_split('/\n+/', strip_tags($html));
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\d+\.\s*(.+)$/', $line, $lm)) {
                $items[] = $lm[1];
            }
        }
        if (!empty($items)) {
            $matches[1] = $items;
        }
    }

    if (empty($matches[1])) {
        return $html;
    }

    $out = '<div class="cims-directives-container d-flex flex-column gap-2">';
    $index = 1;

    foreach ($matches[1] as $itemText) {
        $itemText = trim($itemText);
        if (empty($itemText)) continue;

        $itemText = preg_replace('/^\d+\.\s*/', '', $itemText);

        $title = '';
        $desc = $itemText;
        if (preg_match('/^(?:<strong>|<b[^>]*>|<b>)?(.*?)(?:<\/strong>|<\/b>)?:\s*(.*)$/is', $itemText, $tm)) {
            $title = strip_tags($tm[1]);
            $desc = $tm[2];
        }

        $lower = strtolower($title . ' ' . $desc);
        if (strpos($lower, 'critical') !== false || strpos($lower, 'stockout') !== false || strpos($lower, 'fast-track') !== false || strpos($lower, 'urgent') !== false) {
            $badgeBg = 'bg-danger-subtle text-danger border-danger-subtle';
            $numBg = 'bg-danger text-white';
            $categoryLabel = 'Critical Restock';
        } elseif (strpos($lower, 'project') !== false || strpos($lower, 'coordination') !== false || strpos($lower, 'audit') !== false) {
            $badgeBg = 'bg-primary-subtle text-primary border-primary-subtle';
            $numBg = 'bg-primary text-white';
            $categoryLabel = 'Project Coordination';
        } elseif (strpos($lower, 'requisition') !== false || strpos($lower, 'warehouse') !== false || strpos($lower, 'approv') !== false) {
            $badgeBg = 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
            $numBg = 'bg-warning text-dark';
            $categoryLabel = 'Requisition Approval';
        } else {
            $badgeBg = 'bg-info-subtle text-info-emphasis border-info-subtle';
            $numBg = 'bg-info text-dark';
            $categoryLabel = 'Operational Directive';
        }

        $out .= '<div class="card border mb-2 p-2.5 rounded-3 shadow-none cims-directive-card">';
        $out .= '  <div class="d-flex align-items-start gap-2">';
        $out .= '    <span class="badge rounded-circle ' . $numBg . ' fw-bold flex-shrink-0 d-flex align-items-center justify-content-center mt-0.5" style="width: 22px; height: 22px; font-size: 0.72rem;">' . $index . '</span>';
        $out .= '    <div class="flex-grow-1">';
        $out .= '      <div class="d-flex align-items-center justify-content-between mb-1 gap-2 flex-wrap">';
        if ($title) {
            $out .= '        <span class="fw-bold text-dark" style="font-size: 0.84rem;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $out .= '        <span class="badge ' . $badgeBg . ' border px-2 py-0.5" style="font-size: 0.66rem;">' . $categoryLabel . '</span>';
        $out .= '      </div>';
        $out .= '      <div class="text-secondary" style="font-size: 0.82rem; line-height: 1.55;">' . $desc . '</div>';
        $out .= '    </div>';
        $out .= '  </div>';
        $out .= '</div>';

        $index++;
    }

    $out .= '</div>';
    return $out;
}

// Helper to clean tables according to Option B (remove Daily Burn, remove Total Units Drawn, rename Forecast Runway)
function cleanOptionBTablesPhp($html) {
    if (empty($html)) return $html;

    $html = str_ireplace('<th>Forecast Runway</th>', '<th>Estimated Days Left</th>', $html);

    if (stripos($html, '<th>Daily Burn</th>') !== false) {
        $html = str_ireplace('<th>Daily Burn</th>', '', $html);

        $html = preg_replace_callback('/(<div class="card[^>]*>[\s\S]*?Frequent Materials[\s\S]*?<tbody>)([\s\S]*?)(<\/tbody>)/i', function($cardMatch) {
            $tbody = $cardMatch[2];
            $tbody = preg_replace_callback('/<tr>([\s\S]*?)<\/tr>/i', function($trMatch) {
                preg_match_all('/<td\b[^>]*>[\s\S]*?<\/td>/i', $trMatch[1], $tds);
                if (count($tds[0]) === 6) {
                    unset($tds[0][3]);
                    return "<tr>\n            " . implode("\n            ", $tds[0]) . "\n          </tr>";
                }
                return $trMatch[0];
            }, $tbody);
            return $cardMatch[1] . $tbody . $cardMatch[3];
        }, $html);
    }

    if (stripos($html, '<th>Total Units Drawn</th>') !== false) {
        $html = str_ireplace('<th>Total Units Drawn</th>', '', $html);

        $html = preg_replace_callback('/(<div class="card[^>]*>[\s\S]*?Project Sites[\s\S]*?<tbody>)([\s\S]*?)(<\/tbody>)/i', function($cardMatch) {
            $tbody = $cardMatch[2];
            $tbody = preg_replace_callback('/<tr>([\s\S]*?)<\/tr>/i', function($trMatch) {
                preg_match_all('/<td\b[^>]*>[\s\S]*?<\/td>/i', $trMatch[1], $tds);
                if (count($tds[0]) === 6) {
                    unset($tds[0][2]);
                    return "<tr>\n            " . implode("\n            ", $tds[0]) . "\n          </tr>";
                }
                return $trMatch[0];
            }, $tbody);
            return $cardMatch[1] . $tbody . $cardMatch[3];
        }, $html);
    }

    return $html;
}

// Helper to parse AI report sections into Executive Summary, Strategic Actions, and Detailed Analytics
function parseAiAnalyticsSections($rawHtml) {
    $summary = '';
    $actions = '';
    $analytics = $rawHtml ?? '';

    // 1. Try comment delimiters first
    if (preg_match('/<!--\s*===\s*EXECUTIVE_SUMMARY_START\s*===\s*-->(.*?)<!--\s*===\s*EXECUTIVE_SUMMARY_END\s*===\s*-->/is', $rawHtml, $m)) {
        $summary = trim($m[1]);
    }
    if (preg_match('/<!--\s*===\s*STRATEGIC_ACTIONS_START\s*===\s*-->(.*?)<!--\s*===\s*STRATEGIC_ACTIONS_END\s*===\s*-->/is', $rawHtml, $m)) {
        $actions = trim($m[1]);
    }
    if (preg_match('/<!--\s*===\s*DETAILED_ANALYTICS_START\s*===\s*-->(.*?)<!--\s*===\s*DETAILED_ANALYTICS_END\s*===\s*-->/is', $rawHtml, $m)) {
        $analytics = trim($m[1]);
    } else {
        // Defensively strip summary and actions so they never leak into the bottom container
        $analytics = preg_replace('/<!--\s*===\s*EXECUTIVE_SUMMARY_START\s*===\s*-->[\s\S]*?<!--\s*===\s*EXECUTIVE_SUMMARY_END\s*===\s*-->/is', '', $analytics);
        $analytics = preg_replace('/<!--\s*===\s*STRATEGIC_ACTIONS_START\s*===\s*-->[\s\S]*?<!--\s*===\s*STRATEGIC_ACTIONS_END\s*===\s*-->/is', '', $analytics);
        $analytics = preg_replace('/<!--\s*===\s*DETAILED_ANALYTICS_START\s*===\s*-->/is', '', $analytics);
        $analytics = preg_replace('/<!--\s*===\s*DETAILED_ANALYTICS_END\s*===\s*-->/is', '', $analytics);
    }

    // 2. Fallback: Extract from full card structures if delimiters were omitted or for legacy stored predictions
    if (empty($summary) && preg_match('/<div class="card[^>]*>.*?Executive Operational.*?<div class="card-body[^>]*>(.*?)<\/div>\s*<\/div>/is', $rawHtml, $m)) {
        $summary = trim($m[1]);
        $analytics = preg_replace('/<div class="card[^>]*>.*?Executive Operational.*?<\/div>\s*<\/div>/is', '', $analytics);
    }
    if (empty($actions) && preg_match('/<div class="card[^>]*>.*?Strategic Action Items.*?<div class="card-body[^>]*>(.*?)<\/div>\s*<\/div>/is', $rawHtml, $m)) {
        $actions = trim($m[1]);
        $analytics = preg_replace('/<div class="card[^>]*>.*?Strategic Action Items.*?<\/div>\s*<\/div>/is', '', $analytics);
    }

    return [
        'summary' => polishSummaryHtmlPhp($summary),
        'actions' => polishActionsHtmlPhp($actions),
        'analytics' => cleanOptionBTablesPhp(trim($analytics))
    ];
}

// Helper to wrap entity codes (PO, RS, ITM) in interactive modal buttons
function formatInteractiveEntitiesPhp($text) {
    if (empty($text)) return '';
    $text = preg_replace('/\bITM\s+(\d+)\b/i', 'ITM-$1', $text);
    $text = preg_replace('/\bPO\s+(\d+)\b/i', 'PO-$1', $text);
    $text = preg_replace('/\bRS\s+(\d+)\b/i', 'RS-$1', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace_callback('/(?:<button[^>]*>)?\b(PO-(?:\d{4,8}-\d+|\d+))\b(?:<\/button>)?/', function($m) {
        $code = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
        return '<button type="button" class="btn btn-xs btn-outline-primary fw-bold text-nowrap py-0 px-2 shadow-none entity-btn" style="font-size: 0.76rem; border-radius: 6px;" onclick="if(typeof openPoDetailsModal===\'function\'){openPoDetailsModal(null,\''.$code.'\')}else{window.location.href=\'po?highlight='.$code.'\'}" title="View Purchase Order Details"><i class="bi bi-receipt me-1"></i>'.$code.'</button>';
    }, $text);
    $text = preg_replace_callback('/(?:<button[^>]*>)?\b(RS-(?:\d{4}-\d+|\d+))\b(?:<\/button>)?/', function($m) {
        $code = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
        return '<button type="button" class="btn btn-xs btn-outline-info fw-bold text-nowrap py-0 px-2 shadow-none entity-btn" style="font-size: 0.76rem; border-radius: 6px;" onclick="if(typeof openRequisitionModal===\'function\'){openRequisitionModal(null,\''.$code.'\')}else{window.location.href=\'requisition?highlight='.$code.'\'}" title="View Requisition Details"><i class="bi bi-file-earmark-text me-1"></i>'.$code.'</button>';
    }, $text);
    $text = preg_replace_callback('/(?:<button[^>]*>)?\b(ITM-\d+)\b(?:<\/button>)?/', function($m) {
        $code = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
        return '<button type="button" class="btn btn-xs btn-outline-success fw-bold text-nowrap py-0 px-2 shadow-none entity-btn" style="font-size: 0.76rem; border-radius: 6px;" onclick="if(typeof openItemDetailsModal===\'function\'){openItemDetailsModal(\''.$code.'\')}else{window.location.href=\'inventory?highlight='.$code.'\'}" title="View Inventory Details"><i class="bi bi-box-seam me-1"></i>'.$code.'</button>';
    }, $text);
    return $text;
}

// Handle AJAX Generate Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'generate_ai_report') {
    ini_set('display_errors', 0);
    error_reporting(0);
    header('Content-Type: application/json');

    // 1. CSRF Token Validation (Quality Standards Rule 5)
    $clientCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (function_exists('validate_csrf_token')) {
        if (!validate_csrf_token($clientCsrf)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'message' => 'CSRF validation failed. Please refresh your page.'
            ]);
            exit;
        }
    }

    if (!defined('AI_API_KEY') || empty(AI_API_KEY) || AI_API_KEY === 'YOUR_NVIDIA_API_KEY') {
        echo json_encode(['status' => 'error', 'message' => 'AI API Key is not configured in .env']);
        exit;
    }

    try {
        // 1. Top Requestors (Users with most requests and pending/approved breakdown)
        $topRequestors = $pdo->query("
            SELECT u.name, u.role, 
                   COUNT(r.id) as total_requests,
                   SUM(CASE WHEN r.status = 'Pending Approval' THEN 1 ELSE 0 END) as pending_approval,
                   SUM(CASE WHEN r.status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
                   SUM(CASE WHEN r.status = 'Released' THEN 1 ELSE 0 END) as released_count
            FROM requisitions r
            JOIN users u ON r.requestor_id = u.id
            GROUP BY r.requestor_id, u.name, u.role
            ORDER BY total_requests DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 2. High-Frequency Materials & 30-Day Consumption Velocity
        $frequentMaterials = $pdo->query("
            SELECT i.item_code, i.item_name, i.quantity as current_stock, i.unit,
                   COALESCE(SUM(wi.quantity), 0) as total_withdrawn_30d,
                   COUNT(DISTINCT w.id) as withdrawal_events,
                   ROUND(COALESCE(SUM(wi.quantity), 0) / 30, 2) as daily_burn_rate,
                   CASE 
                       WHEN i.quantity <= 0 THEN 0
                       WHEN COALESCE(SUM(wi.quantity), 0) > 0 THEN FLOOR(i.quantity / (SUM(wi.quantity) / 30))
                       ELSE 999 
                   END as est_days_left
            FROM inventory i
            JOIN withdrawal_items wi ON i.item_code = wi.item_code
            JOIN withdrawals w ON wi.withdrawal_id = w.id
            WHERE w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY i.item_code, i.item_name, i.quantity, i.unit
            ORDER BY total_withdrawn_30d DESC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 3. Project Sites Material Consumption & Demand Intelligence
        $projectWithdrawals = $pdo->query("
            SELECT w.project_name,
                   COUNT(DISTINCT w.id) as withdrawal_count,
                   SUM(wi.quantity) as total_units_withdrawn
            FROM withdrawals w
            JOIN withdrawal_items wi ON w.id = wi.withdrawal_id
            WHERE w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY w.project_name
            ORDER BY total_units_withdrawn DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $projectItemUsage = $pdo->query("
            SELECT w.project_name, i.item_name, i.unit, SUM(wi.quantity) as qty_consumed
            FROM withdrawal_items wi
            JOIN withdrawals w ON wi.withdrawal_id = w.id
            JOIN inventory i ON wi.item_code = i.item_code
            WHERE w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY w.project_name, i.item_name, i.unit
            ORDER BY w.project_name, qty_consumed DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $projectTopItems = [];
        foreach ($projectItemUsage as $row) {
            if (!isset($projectTopItems[$row['project_name']])) {
                $projectTopItems[$row['project_name']] = [];
            }
            if (count($projectTopItems[$row['project_name']]) < 3) {
                $projectTopItems[$row['project_name']][] = $row['item_name'] . " (" . $row['qty_consumed'] . " " . $row['unit'] . ")";
            }
        }

        $projectOpenReqs = $pdo->query("
            SELECT project_name, COUNT(id) as pending_reqs
            FROM requisitions
            WHERE status IN ('Pending Approval', 'Approved')
            GROUP BY project_name
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $projectSitesIntelligence = [];
        foreach ($projectWithdrawals as $pw) {
            $pName = $pw['project_name'];
            $projectSitesIntelligence[] = [
                'project_name' => $pName,
                'withdrawal_count' => (int)$pw['withdrawal_count'],
                'total_units_withdrawn' => (int)$pw['total_units_withdrawn'],
                'dominant_materials' => isset($projectTopItems[$pName]) ? implode(', ', $projectTopItems[$pName]) : 'None',
                'active_open_requisitions' => (int)($projectOpenReqs[$pName] ?? 0)
            ];
        }

        foreach ($projectOpenReqs as $pName => $openCount) {
            $alreadyIncluded = false;
            foreach ($projectSitesIntelligence as $pEntry) {
                if ($pEntry['project_name'] === $pName) {
                    $alreadyIncluded = true;
                    break;
                }
            }
            if (!$alreadyIncluded) {
                $projectSitesIntelligence[] = [
                    'project_name' => $pName,
                    'withdrawal_count' => 0,
                    'total_units_withdrawn' => 0,
                    'dominant_materials' => 'No recent withdrawals',
                    'active_open_requisitions' => (int)$openCount
                ];
            }
        }

        // 4. Purchase Orders & Delivery Timeline (ETAs, Overdue days, Suppliers, Item Count, Spend)
        $poPipeline = $pdo->query("
            SELECT p.po_no, s.company_name as supplier, p.status, p.expected_delivery_date,
                   DATEDIFF(p.expected_delivery_date, CURDATE()) as days_until_delivery,
                   (SELECT COUNT(*) FROM po_items pi WHERE pi.po_id = p.id) as item_count,
                   (SELECT COALESCE(SUM(pi.quantity * COALESCE(pi.unit_price, 0)), 0) FROM po_items pi WHERE pi.po_id = p.id) as total_amount
            FROM purchase_orders p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.status NOT IN ('Delivered', 'Cancelled')
            ORDER BY p.expected_delivery_date ASC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);

        // 5. Critical Stock & Low Runway Items
        $criticalStock = $pdo->query("
            SELECT i.item_code, i.item_name, i.quantity as current_stock, i.unit,
                   COALESCE((SELECT SUM(wi.quantity) FROM withdrawal_items wi JOIN withdrawals w ON wi.withdrawal_id = w.id WHERE wi.item_code = i.item_code AND w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) as used_30d
            FROM inventory i
            WHERE i.quantity < 20
            ORDER BY i.quantity ASC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Assemble 360 Operations & Predictive Material Intelligence Payload
        $payload360 = [
            'analysis_date' => date('F d, Y'),
            'frequent_materials_and_velocity' => $frequentMaterials,
            'project_sites_material_demand' => $projectSitesIntelligence,
            'purchase_orders_and_deliveries' => $poPipeline,
            'critical_and_low_stock' => $criticalStock,
            'top_requesting_personnel' => $topRequestors
        ];

        $roleFocusGuides = [
            'purchasing' => "ROLE PERSPECTIVE: The logged-in user is a PURCHASING OFFICER. Prioritize procurement intelligence: lead times, high-velocity material reorder thresholds, supplier fulfillment reliability, upcoming & overdue PO deliveries, price spend totals, vendor contact coordination, and urgent reorder recommendations for materials nearing stockout.",
            'management' => "ROLE PERSPECTIVE: The logged-in user is an EXECUTIVE MANAGEMENT LEADER. Prioritize executive decision-making: cross-site material allocation trends, high-consumption project sites, project delay risks due to material shortages, budget trends, and strategic supply chain mitigation.",
            'admin' => "ROLE PERSPECTIVE: The logged-in user is the SYSTEM ADMINISTRATOR. Provide a comprehensive, end-to-end operational briefing covering high-frequency material burn rates, stockout forecasts, project site material demand distribution, pending delivery tracking, and overall system health."
        ];

        $roleGuide = $roleFocusGuides[$currentUserRole] ?? $roleFocusGuides['admin'];

        $today = date('F d, Y');
        $systemPrompt = "You are the CIMS 360° Operations, Predictive Material Forecasting & Supply Intelligence Suite.
" . $roleGuide . "
Analyze the live multi-dimensional dataset (Frequent Materials & Velocity, Project Sites Material Demand, Critical Stock, Purchase Orders & Deliveries, and User Requestors).
Provide advanced predictive forecasting on material depletion, identify which items are most frequently used, and break down project site demand.
Generate an executive-grade operational briefing strictly divided into the 3 sections shown below using the EXACT delimiter comments.

MANDATORY DELIMITED OUTPUT STRUCTURE:

<!-- === EXECUTIVE_SUMMARY_START === -->
<div class=\"mb-2\"><strong>Analysis Date:</strong> {$today}</div>
<p class=\"mb-0\">[Write 2-3 concise, high-impact sentences synthesizing overall material velocity, top stockout risks, key project site drivers, and procurement status tailored to the {$currentUserRole} role.]</p>
<!-- === EXECUTIVE_SUMMARY_END === -->

<!-- === STRATEGIC_ACTIONS_START === -->
<ol class=\"mb-0 ps-3\">
  <!-- 4-5 numbered actionable items tailored to {$currentUserRole}:
       Include specific material codes like **ITM-xxxx**, project names, and PO numbers like **PO-xxxx** or requisition codes **RS-xxxx** where relevant.
       1. <strong>Predictive Restock / Project Coordination / Lead-time Action:</strong> Specific actionable instruction... -->
</ol>
<!-- === STRATEGIC_ACTIONS_END === -->

<!-- === DETAILED_ANALYTICS_START === -->
<!-- CARD 1: Frequent Materials & Predictive Stockout Forecast -->
<div class=\"card mb-3 border-0 shadow-sm rounded-3 overflow-hidden\">
  <div class=\"card-header text-white fw-bold py-2 px-3\" style=\"background: #0d6efd;\">
    <i class=\"bi bi-graph-up-arrow me-2\"></i>Frequent Materials &amp; Predictive Stockout Forecast
  </div>
  <div class=\"card-body p-3 bg-white\">
    <div class=\"table-responsive\">
      <table class=\"table table-hover table-sm align-middle mb-2\" style=\"border-top: 2px solid #ffc107;\">
        <thead class=\"table-light\">
          <tr>
            <th>Material Code &amp; Name</th>
            <th>Current Stock</th>
            <th>30-Day Consumed</th>
            <th>Estimated Days Left</th>
            <th>Depletion Risk</th>
          </tr>
        </thead>
        <tbody>
          <!-- Generate a <tr> row for each frequent material in frequent_materials_and_velocity dataset.
               In Material column: bold the code like **ITM-xxxx** and show name: **ITM-xxxx** - Item Name (Unit).
               In Estimated Days Left column: show est_days_left (e.g. '14 days' or 'Depleted' if 0 or '>90 days' if 999).
               In Depletion Risk column:
               - If est_days_left <= 7: <span class=\"badge bg-danger\">Critical (&lt; 7d)</span>
               - If est_days_left > 7 AND <= 14: <span class=\"badge bg-warning text-dark\">Urgent (8-14d)</span>
               - If est_days_left > 14 AND <= 30: <span class=\"badge bg-info text-dark\">Moderate (15-30d)</span>
               - If est_days_left > 30: <span class=\"badge bg-success\">Stable (&gt; 30d)</span> -->
        </tbody>
      </table>
    </div>
    <div class=\"small text-muted fst-italic mt-2\">
      <i class=\"bi bi-info-circle me-1\"></i>[Write 1-2 analytical sentences summarizing the highest-frequency materials used, any sudden burn rate spikes, and estimated dates for impending stockouts.]
    </div>
  </div>
</div>

<!-- CARD 2: Project Sites Material Demand & Consumption Distribution -->
<div class=\"card mb-3 border-0 shadow-sm rounded-3 overflow-hidden\">
  <div class=\"card-header text-white fw-bold py-2 px-3\" style=\"background: #0d6efd;\">
    <i class=\"bi bi-geo-alt-fill me-2\"></i>Project Sites Material Demand &amp; Allocation
  </div>
  <div class=\"card-body p-3 bg-white\">
    <div class=\"table-responsive\">
      <table class=\"table table-hover table-sm align-middle mb-2\" style=\"border-top: 2px solid #ffc107;\">
        <thead class=\"table-light\">
          <tr>
            <th>Project Site</th>
            <th>Withdrawals (30d)</th>
            <th>Dominant Materials Consumed</th>
            <th>Open Requisitions</th>
            <th>Site Status</th>
          </tr>
        </thead>
        <tbody>
          <!-- Generate a <tr> row for each project in project_sites_material_demand dataset.
               In Project Site column: write project_name in bold.
               In Open Requisitions column: if active_open_requisitions > 0 show <span class=\"badge bg-warning text-dark\">X Pending</span> else <span class=\"badge bg-light text-muted border\">0</span>.
               In Site Status column:
               - If open_requisitions > 0 AND total_units_withdrawn > 0: <span class=\"badge bg-primary\">Active Demand</span>
               - If total_units_withdrawn == 0 AND open_requisitions > 0: <span class=\"badge bg-warning text-dark\">Awaiting Restock</span>
               - Else: <span class=\"badge bg-success\">Operating Normally</span> -->
        </tbody>
      </table>
    </div>
    <div class=\"small text-muted fst-italic mt-2\">
      <i class=\"bi bi-lightbulb me-1\"></i>[Write 1-2 analytical sentences evaluating project site consumption patterns, cross-site imbalances, and site delay risks.]
    </div>
  </div>
</div>

<!-- CARD 3: Procurement Pipeline & Incoming Deliveries -->
<div class=\"card mb-3 border-0 shadow-sm rounded-3 overflow-hidden\">
  <div class=\"card-header text-white fw-bold py-2 px-3\" style=\"background: #0d6efd;\">
    <i class=\"bi bi-truck me-2\"></i>Procurement Pipeline &amp; Delivery Alignment
  </div>
  <div class=\"card-body p-3 bg-white\">
    <div class=\"table-responsive\">
      <table class=\"table table-hover table-sm align-middle mb-2\" style=\"border-top: 2px solid #ffc107;\">
        <thead class=\"table-light\">
          <tr>
            <th>PO No.</th>
            <th>Supplier</th>
            <th>Status</th>
            <th>Expected Delivery</th>
            <th>Timing</th>
          </tr>
        </thead>
        <tbody>
          <!-- Generate a <tr> row for each PO in purchase_orders_and_deliveries.
               In PO No. column, write code bold like **PO-xxxx**.
               In Timing column:
               - If days_until_delivery < 0: <span class=\"badge bg-danger\">Overdue X days</span>
               - If days_until_delivery == 0: <span class=\"badge bg-warning text-dark\">Due Today</span>
               - If days_until_delivery > 0: <span class=\"badge bg-success\">In X days</span> -->
        </tbody>
      </table>
    </div>
    <div class=\"d-flex gap-2 mt-2 flex-wrap\">
      <span class=\"badge bg-success\">On-time: [count]</span>
      <span class=\"badge bg-danger\">Overdue: [count]</span>
      <span class=\"badge bg-warning text-dark\">Due Today: [count]</span>
    </div>
  </div>
</div>
<!-- === DETAILED_ANALYTICS_END === -->

CRITICAL RULES:
- Output pure HTML matching this exact 3-section structure delimited by the comments.
- DO NOT wrap output in markdown code fences (no ```html).
- Bold entity codes like **PO-xxxx**, **RS-xxxx**, and **ITM-xxxx** so they can be converted to interactive links.
- Strictly ground all predictions and figures on the provided dataset.";

        $userMessage = json_encode($payload360, JSON_PRETTY_PRINT);
        $apiKey = trim(AI_API_KEY);

        // Multi-Provider Auto-Detection
        $isOpenAICompatible = false;
        $apiUrl = '';
        $headers = [];
        $postData = [];

        if (strpos($apiKey, 'gsk_') === 0) {
            // 1. Groq API (Ultra-fast active model)
            $isOpenAICompatible = true;
            $apiUrl = "https://api.groq.com/openai/v1/chat/completions";
            $model = defined('AI_MODEL') && !empty(AI_MODEL) && strpos(AI_MODEL, 'nvidia/') === false ? AI_MODEL : 'openai/gpt-oss-120b';
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
            $postData = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "Data: " . $userMessage]
                ],
                'temperature' => 0.2,
                'max_tokens' => 2800
            ];
            $headers = [
                "Content-Type: application/json",
                "Authorization: Bearer " . $apiKey
            ];
        } else {
            $postData = [
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => "Data: " . $userMessage]]]
                ],
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 2800
                ]
            ];
            $headers = [
                "Content-Type: application/json"
            ];
        }

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => json_encode($postData),
                'ignore_errors' => true,
                'timeout' => 35
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
            $error_err = error_get_last();
            $error_msg = isset($error_err['message']) ? $error_err['message'] : 'Connection timeout or network failure';
            echo json_encode(['success' => false, 'status' => 'error', 'message' => 'HTTP Request Failed: ' . $error_msg]);
            exit;
        }

        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                    $httpCode = intval($matches[1]);
                    break;
                }
            }
        }

        if ($httpCode !== 200) {
            http_response_code($httpCode >= 400 && $httpCode < 600 ? $httpCode : 500);
            $errData = json_decode($response, true);
            $errMessage = $errData['error']['message'] ?? ($errData['detail'] ?? 'HTTP Code ' . $httpCode);
            echo json_encode(['success' => false, 'status' => 'error', 'message' => "AI API Error (HTTP {$httpCode}): {$errMessage}"]);
            exit;
        }

        $resData = json_decode($response, true);
        $aiText = '';

        if ($isOpenAICompatible) {
            if (isset($resData['choices'][0]['message']['content'])) {
                $aiText = $resData['choices'][0]['message']['content'];
            }
        } else {
            if (isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
                $aiText = $resData['candidates'][0]['content']['parts'][0]['text'];
            }
        }

        if (!empty($aiText)) {
            // Un-escape markdown wrapper in content if model output it
            $aiText = preg_replace('/^```html\s*|\s*```$/i', '', trim($aiText));

            $timestamp = time() * 1000; // milliseconds

            // Save prediction to database (Role-tailored and global fallback)
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['last_ai_prediction_' . $currentUserRole, $aiText, $aiText]);
            $stmt->execute(['last_ai_prediction', $aiText, $aiText]);

            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(['last_ai_timestamp_' . $currentUserRole, $timestamp, $timestamp]);
            $stmt->execute(['last_ai_timestamp', $timestamp, $timestamp]);

            $parsedOutput = parseAiAnalyticsSections($aiText);
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'AI report generated successfully.',
                'prediction' => $aiText,
                'summary' => $parsedOutput['summary'],
                'actions' => $parsedOutput['actions'],
                'analytics' => $parsedOutput['analytics'],
                'timestamp' => $timestamp
            ]);
        } else {
            http_response_code(502);
            echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid or empty response format received from AI provider.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


// ==========================================
// 1. DATA CALCULATION ENGINE
// ==========================================
$query = "
    SELECT i.item_code, i.item_name, i.quantity as current_stock, i.unit,
        COALESCE((SELECT SUM(wi.quantity) FROM withdrawal_items wi JOIN withdrawals w ON wi.withdrawal_id = w.id WHERE wi.item_code = i.item_code AND w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) as total_consumed
    FROM inventory i ORDER BY current_stock ASC
";
$consumptionData = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

$projQuery = "
    SELECT wi.item_code, w.project_name, SUM(wi.quantity) as project_consumed
    FROM withdrawal_items wi JOIN withdrawals w ON wi.withdrawal_id = w.id
    WHERE w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY wi.item_code, w.project_name ORDER BY wi.item_code, project_consumed DESC
";
$projData = $pdo->query($projQuery)->fetchAll(PDO::FETCH_ASSOC);

$projectBreakdown = [];
foreach ($projData as $row) {
    $projectBreakdown[$row['item_code']][] = $row['project_name'] . " (" . $row['project_consumed'] . ")";
}

$chartLabels = [];
$consumedData = [];
$daysLeftLabels = [];
$daysLeftData = [];
$daysLeftColors = [];
$aiPayload = [];

$pieLabels = [];
$pieData = [];
$pieColors = [];
$colorPalette = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6f42c1', '#d63384', '#fd7e14', '#20c997', '#6c757d'];
$cIndex = 0;

foreach ($consumptionData as $item) {
    $dailyBurn = $item['total_consumed'] / 30;
    if ($item['current_stock'] <= 0) {
        $daysLeft = 0;
    } elseif ($dailyBurn > 0) {
        $daysLeft = floor($item['current_stock'] / $dailyBurn);
    } else {
        $daysLeft = 999;
    }

    $topProjects = isset($projectBreakdown[$item['item_code']]) ? implode(", ", $projectBreakdown[$item['item_code']]) : "No recent projects";

    $aiPayload[] = [
        'Item' => $item['item_name'],
        'Unit' => $item['unit'],
        'Current_Stock' => $item['current_stock'],
        'Used_Last_30_Days' => $item['total_consumed'],
        'Daily_Burn_Rate' => round($dailyBurn, 2),
        'Est_Days_Left' => $daysLeft,
        'Consuming_Projects' => $topProjects
    ];

    if ($item['total_consumed'] > 0) {
        $chartLabels[] = $item['item_name'];
        $consumedData[] = $item['total_consumed'];
    }

    if ($daysLeft <= 60 || $item['current_stock'] <= 0) {
        $daysLeftLabels[] = $item['item_name'];
        $daysLeftData[] = ($daysLeft == 0) ? 0.5 : $daysLeft;
        if ($daysLeft <= 7)
            $daysLeftColors[] = 'rgba(220, 53, 69, 0.85)';
        elseif ($daysLeft <= 14)
            $daysLeftColors[] = 'rgba(255, 193, 7, 0.85)';
        else
            $daysLeftColors[] = 'rgba(25, 135, 84, 0.85)';
    }

    if ($item['current_stock'] > 0) {
        $pieLabels[] = $item['item_name'];
        $pieData[] = $item['current_stock'];
        $pieColors[] = $colorPalette[$cIndex % count($colorPalette)];
        $cIndex++;
    }
}
usort($aiPayload, fn($a, $b) => $a['Est_Days_Left'] <=> $b['Est_Days_Left']);

$totalConsumedAll = array_sum($consumedData);
$percentageData = [];
$rawHoverData = [];
foreach ($consumedData as $val) {
    $pct = ($totalConsumedAll > 0) ? round(($val / $totalConsumedAll) * 100, 1) : 0;
    $percentageData[] = $pct;
    $rawHoverData[] = $val;
}

// Fetch Incoming Warehouse Supply ETAs for Analytics Card
$todayStr = date('Y-m-d');
$incomingSupplyStmt = $pdo->prepare("
    SELECT p.*, s.company_name 
    FROM purchase_orders p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.status NOT IN ('Delivered', 'Delivered (Discrepancy)', 'Cancelled')
    ORDER BY 
        CASE 
            WHEN p.expected_delivery_date = ? THEN 1
            WHEN p.expected_delivery_date < ? THEN 2
            ELSE 3 
        END,
        p.expected_delivery_date ASC
    LIMIT 6
");
$incomingSupplyStmt->execute([$todayStr, $todayStr]);
$incomingSupplies = $incomingSupplyStmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 2. AJAX REAL-TIME ENDPOINT
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'chartLabels' => $chartLabels,
        'percentageData' => $percentageData,
        'rawHoverData' => $rawHoverData,
        'daysLeftLabels' => $daysLeftLabels,
        'daysLeftData' => $daysLeftData,
        'daysLeftColors' => $daysLeftColors,
        'pieLabels' => $pieLabels,
        'pieData' => $pieData,
        'pieColors' => $pieColors,
        'aiPayload' => $aiPayload
    ]);
    exit;
}
// Parse stored prediction into Executive Summary, Strategic Actions, and Detailed Analytics
$parsedPrediction = [
    'summary' => '',
    'actions' => '',
    'analytics' => ''
];
if (!empty($lastPrediction)) {
    $parsed = parseAiAnalyticsSections($lastPrediction);
    $parsedPrediction = [
        'summary' => formatInteractiveEntitiesPhp($parsed['summary']),
        'actions' => formatInteractiveEntitiesPhp($parsed['actions']),
        'analytics' => formatInteractiveEntitiesPhp($parsed['analytics'])
    ];
}

include 'layout/header.php';
?>

<style>
.cims-analytics-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 0, 0, 0.18) transparent;
}
.cims-analytics-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.cims-analytics-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.cims-analytics-scroll::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.16);
    border-radius: 4px;
}
.cims-analytics-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.28);
}
[data-bs-theme="dark"] .cims-analytics-scroll {
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}
[data-bs-theme="dark"] .cims-analytics-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
}
.cims-directive-card {
    transition: transform 0.18s ease, box-shadow 0.18s ease;
    border-color: rgba(0, 0, 0, 0.08) !important;
    background-color: #ffffff;
}
.cims-directive-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}
[data-bs-theme="dark"] .cims-directive-card {
    background-color: #1e2430 !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
[data-bs-theme="dark"] .cims-directive-card .text-dark {
    color: #e6edf3 !important;
}
[data-bs-theme="dark"] .cims-directive-card .text-secondary {
    color: #8b949e !important;
}
[data-bs-theme="dark"] .cims-summary-container .text-dark {
    color: #e6edf3 !important;
}
[data-bs-theme="dark"] .cims-summary-container .bg-light-subtle {
    background-color: rgba(255, 255, 255, 0.03) !important;
    border-color: #30363d !important;
}
@keyframes cimsFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
.cims-fade-in {
    animation: cimsFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.entity-btn {
    transition: all 0.15s ease-in-out;
    vertical-align: baseline;
    font-size: 0.76rem !important;
    padding: 0.1rem 0.45rem !important;
}
.entity-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.12) !important;
}
#topStrategicActionsBox ol {
    padding-left: 1.15rem;
    margin-bottom: 0;
}
#topStrategicActionsBox ol li {
    margin-bottom: 0.65rem;
    line-height: 1.55;
}
#topStrategicActionsBox ol li:last-child {
    margin-bottom: 0;
}
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-speedometer2 me-2 text-primary"></i>AI Analytics
            </h3>
        </div>
    </div>

    <div class="row mb-4 g-3">

        <!-- 1. EXECUTIVE OPERATIONAL & MATERIAL FORECAST SUMMARY (Top Left) -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-white fw-bold py-3 text-dark border-bottom-0 d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-bar-chart-fill text-primary me-2"></i>Executive Summary &amp; Forecast
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size:0.68rem;">AI Briefing</span>
                </div>
                <div class="card-body p-3 overflow-auto cims-analytics-scroll" id="topExecutiveSummaryBox" style="height: 300px; font-size: 0.88rem; line-height: 1.6;">
                    <?php if (!empty($parsedPrediction['summary'])): ?>
                        <?= $parsedPrediction['summary'] ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-cpu display-6 opacity-25 d-block mb-2 text-primary"></i>
                            <p class="small mb-1 fw-semibold">No operational briefing generated yet</p>
                            <small class="text-muted" style="font-size: 0.72rem;">Click "Analyze Operations Now" below</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2. OVERALL STOCK PIE CHART (Middle - PRESERVED!) -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-white fw-bold py-3 text-dark border-bottom-0">
                    <i class="bi bi-pie-chart-fill text-success me-2"></i>Overall Stock Distribution
                </div>
                <div class="card-body position-relative d-flex justify-content-center align-items-center"
                    style="height: 300px; width: 100%;">
                    <?php if (count($pieLabels) > 0): ?>
                        <canvas id="overallStockPieChart" style="max-height: 100%; max-width: 100%;"></canvas>
                    <?php else: ?>
                        <div class="text-center text-muted py-5" id="noDataPie"><i
                                class="bi bi-box-seam fs-1 d-block mb-2"></i>Inventory is currently completely empty.</div>
                        <canvas id="overallStockPieChart" style="display:none;"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 3. STRATEGIC ACTION ITEMS & PREDICTIVE DIRECTIVES (Top Right) -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-white fw-bold py-3 text-dark border-bottom-0 d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-bullseye text-danger me-2"></i>Strategic Action Items &amp; Directives
                    </div>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size:0.68rem;">Action Plan</span>
                </div>
                <div class="card-body p-3 overflow-auto cims-analytics-scroll" id="topStrategicActionsBox" style="height: 300px; font-size: 0.88rem; line-height: 1.6;">
                    <?php if (!empty($parsedPrediction['actions'])): ?>
                        <?= $parsedPrediction['actions'] ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-list-check display-6 opacity-25 d-block mb-2 text-danger"></i>
                            <p class="small mb-1 fw-semibold">No action directives generated yet</p>
                            <small class="text-muted" style="font-size: 0.72rem;">Click "Analyze Operations Now" below</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- AI ASSISTANT -->
    <div class="card border-0 shadow-sm rounded-3" style="border-top: 5px solid var(--gb-blue) !important;">
        <div
            class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center border-bottom-0">
            <span class="fs-5 text-dark"><i class="bi bi-stars text-warning me-2"></i>AI Powered Analytics</span>
            <div class="d-flex align-items-center gap-3">
                <small id="lastUpdatedText" class="text-muted fw-semibold" data-timestamp="<?= $lastTimestamp ?: '' ?>">
                    <?php if ($lastTimestamp): ?>
                        Last Updated: <?= date('M d, Y h:i:s A', $lastTimestamp / 1000) ?>
                    <?php endif; ?>
                </small>
                <button class="btn btn-sm btn-brand fw-bold shadow-sm px-3" id="generateAiBtn"
                    onclick="generateAIPrediction(true)">
                    <i class="bi bi-arrow-clockwise me-1"></i> Analyze Operations Now
                </button>
            </div>
        </div>
        <div class="card-body bg-light position-relative rounded-bottom-3" style="min-height: 200px;">
            <div id="aiLoading"
                class="position-absolute w-100 h-100 top-0 start-0 bg-white bg-opacity-75 d-flex flex-column justify-content-center align-items-center"
                style="display: none !important; z-index: 10;">
                <div class="spinner-border text-primary mb-2" role="status"></div>
                <small class="fw-bold text-primary blink-text">AI is synthesizing operations: material burn rates, stockout forecasts, project site demand &amp; delivery ETAs...</small>
            </div>
            <div id="aiOutput" class="p-3 rounded shadow-sm" style="font-size: 1rem; line-height: 1.7;">
                <?php if (!empty($parsedPrediction['analytics'])): ?>
                    <?= $parsedPrediction['analytics'] ?>
                <?php else: ?>
                    <div class="text-center text-muted py-4"><i class="bi bi-cpu fs-2 d-block mb-2"></i>Click "Analyze
                        Operations Now"
                        to generate real-time 360° Operations Intelligence.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS SECTION -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- FIX 2: Load the external JS file FIRST before triggering it -->
<script src="assets/js/analytics.js?v=<?= time() ?>"></script>

<!-- PASS PHP DATA TO JAVASCRIPT EXTERNALLY -->
<script>
    window.currentUserRole = <?= json_encode($currentUserRole) ?>;
    window.aiPayload = <?= json_encode($aiPayload) ?>;

    window.chartData = {
        chartLabels: <?= json_encode($chartLabels) ?>,
        percentageData: <?= json_encode($percentageData) ?>,
        rawHoverData: <?= json_encode($rawHoverData) ?>,
        pieLabels: <?= json_encode($pieLabels) ?>,
        pieData: <?= json_encode($pieData) ?>,
        pieColors: <?= json_encode($pieColors) ?>,
        daysLeftLabels: <?= json_encode($daysLeftLabels) ?>,
        daysLeftData: <?= json_encode($daysLeftData) ?>,
        daysLeftColors: <?= json_encode($daysLeftColors) ?>
    };

    // FIX 3: Robust trigger loop
    function bootDashboard() {
        if (typeof window.initDashboardCharts === 'function') {
            window.initDashboardCharts();
        } else {
            setTimeout(bootDashboard, 50); // Wait 50ms and try again
        }
    }
    bootDashboard();
</script>

<?php include 'layout/footer.php'; ?>