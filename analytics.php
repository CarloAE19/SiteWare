<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
if (!in_array($_SESSION['user_role'], ['admin', 'management', 'purchasing'])) { header("Location: index"); exit; }

require_once 'Connection/db.php';

// Fetch last saved AI prediction and timestamp
$lastPrediction = null;
$lastTimestamp = null;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'last_ai_prediction'");
    $stmt->execute();
    $lastPrediction = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'last_ai_timestamp'");
    $stmt->execute();
    $lastTimestamp = $stmt->fetchColumn();
} catch (Exception $e) {
    // Database connection or table issue
}

// Handle AJAX Generate Request (Proxy to NVIDIA NIM API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'generate_ai_report') {
    ini_set('display_errors', 0);
    error_reporting(0);
    header('Content-Type: application/json');
    
    if (!defined('AI_API_KEY') || empty(AI_API_KEY) || AI_API_KEY === 'YOUR_NVIDIA_API_KEY') {
        echo json_encode(['status' => 'error', 'message' => 'NVIDIA API Key is not configured in .env']);
        exit;
    }
    
    try {
        // 1. Fetch latest data to construct the payload
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
        foreach($projData as $row) {
            $projectBreakdown[$row['item_code']][] = $row['project_name'] . " (" . $row['project_consumed'] . ")";
        }

        $aiPayload = [];
        foreach ($consumptionData as $item) {
            $dailyBurn = $item['total_consumed'] / 30;
            if ($item['current_stock'] <= 0) { $daysLeft = 0; } 
            elseif ($dailyBurn > 0) { $daysLeft = floor($item['current_stock'] / $dailyBurn); } 
            else { $daysLeft = 999; }
            
            $topProjects = isset($projectBreakdown[$item['item_code']]) ? implode(", ", $projectBreakdown[$item['item_code']]) : "No recent projects";

            $aiPayload[] = [
                'Item' => $item['item_name'], 'Unit' => $item['unit'], 'Current_Stock' => $item['current_stock'],
                'Used_Last_30_Days' => $item['total_consumed'], 'Daily_Burn_Rate' => round($dailyBurn, 2),
                'Est_Days_Left' => $daysLeft, 'Consuming_Projects' => $topProjects 
            ];
        }
        usort($aiPayload, fn($a, $b) => $a['Est_Days_Left'] <=> $b['Est_Days_Left']);

        $today = date('F d, Y');
        $systemPrompt = defined('AI_SYSTEM_PROMPT') ? AI_SYSTEM_PROMPT : '';
        $systemPrompt = str_replace(['\n', '{TODAY}'], ["\n", $today], $systemPrompt);

        $userMessage = json_encode($aiPayload);

        // NVIDIA NIM API Details
        $apiUrl = 'https://integrate.api.nvidia.com/v1/chat/completions';
        $model = defined('AI_MODEL') ? AI_MODEL : 'meta/llama-3.1-8b-instruct';

        $postData = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Data: " . $userMessage]
            ],
            'temperature' => 0.2,
            'max_tokens' => 2048
        ];

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                             "Authorization: Bearer " . AI_API_KEY . "\r\n",
                'content' => json_encode($postData),
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
            echo json_encode(['status' => 'error', 'message' => 'HTTP Request Failed: ' . $error_msg]);
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
            $errData = json_decode($response, true);
            $errMessage = isset($errData['error']['message']) ? $errData['error']['message'] : 'HTTP Code ' . $httpCode;
            echo json_encode(['status' => 'error', 'message' => 'NVIDIA API Error: ' . $errMessage]);
            exit;
        }

        $resData = json_decode($response, true);
        if (isset($resData['choices'][0]['message']['content'])) {
            $aiText = $resData['choices'][0]['message']['content'];
            
            // Un-escape markdown wrapper in content if model output it
            $aiText = preg_replace('/^```html\s*|\s*```$/i', '', $aiText);
            
            $timestamp = time() * 1000; // milliseconds

            // Save prediction to database
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('last_ai_prediction', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$aiText, $aiText]);
            
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('last_ai_timestamp', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$timestamp, $timestamp]);

            echo json_encode(['status' => 'success', 'prediction' => $aiText, 'timestamp' => $timestamp]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid response format from NVIDIA NIM API']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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
foreach($projData as $row) {
    $projectBreakdown[$row['item_code']][] = $row['project_name'] . " (" . $row['project_consumed'] . ")";
}

$chartLabels = []; $consumedData = []; $daysLeftLabels = []; $daysLeftData = []; $daysLeftColors = []; $aiPayload = [];

$pieLabels = []; $pieData = []; $pieColors = [];
$colorPalette = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6f42c1', '#d63384', '#fd7e14', '#20c997', '#6c757d'];
$cIndex = 0;

foreach ($consumptionData as $item) {
    $dailyBurn = $item['total_consumed'] / 30;
    if ($item['current_stock'] <= 0) { $daysLeft = 0; } 
    elseif ($dailyBurn > 0) { $daysLeft = floor($item['current_stock'] / $dailyBurn); } 
    else { $daysLeft = 999; }
    
    $topProjects = isset($projectBreakdown[$item['item_code']]) ? implode(", ", $projectBreakdown[$item['item_code']]) : "No recent projects";

    $aiPayload[] = [
        'Item' => $item['item_name'], 'Unit' => $item['unit'], 'Current_Stock' => $item['current_stock'],
        'Used_Last_30_Days' => $item['total_consumed'], 'Daily_Burn_Rate' => round($dailyBurn, 2),
        'Est_Days_Left' => $daysLeft, 'Consuming_Projects' => $topProjects 
    ];

    if ($item['total_consumed'] > 0) {
        $chartLabels[] = $item['item_name']; 
        $consumedData[] = $item['total_consumed'];
    }
    
    if ($daysLeft <= 60 || $item['current_stock'] <= 0) {
        $daysLeftLabels[] = $item['item_name'];
        $daysLeftData[] = ($daysLeft == 0) ? 0.5 : $daysLeft; 
        if ($daysLeft <= 7) $daysLeftColors[] = 'rgba(220, 53, 69, 0.85)'; 
        elseif ($daysLeft <= 14) $daysLeftColors[] = 'rgba(255, 193, 7, 0.85)'; 
        else $daysLeftColors[] = 'rgba(25, 135, 84, 0.85)'; 
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

include 'layout/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h3>
        </div>
    </div>

    <div class="row mb-4 g-3">
        
        <!-- PERCENTAGE BAR CHART -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-white fw-bold py-3 text-dark border-bottom-0">
                    <i class="bi bi-bar-chart-fill text-primary me-2"></i>Consumption Share (%)
                </div>
                <div class="card-body position-relative d-flex justify-content-center align-items-center" style="height: 300px; width: 100%;">
                    <?php if (count($chartLabels) > 0): ?>
                        <canvas id="pctConsumptionChart" style="max-height: 100%; max-width: 100%;"></canvas>
                    <?php else: ?>
                        <div class="text-center text-muted py-5" id="noDataPct"><i class="bi bi-clipboard-x fs-1 d-block mb-2"></i>No consumption recorded in the last 30 days.</div>
                        <canvas id="pctConsumptionChart" style="display:none;"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- OVERALL STOCK PIE CHART -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-white fw-bold py-3 text-dark border-bottom-0">
                    <i class="bi bi-pie-chart-fill text-success me-2"></i>Overall Stock Distribution
                </div>
                <div class="card-body position-relative d-flex justify-content-center align-items-center" style="height: 300px; width: 100%;">
                    <?php if (count($pieLabels) > 0): ?>
                        <canvas id="overallStockPieChart" style="max-height: 100%; max-width: 100%;"></canvas>
                    <?php else: ?>
                        <div class="text-center text-muted py-5" id="noDataPie"><i class="bi bi-box-seam fs-1 d-block mb-2"></i>Inventory is currently completely empty.</div>
                        <canvas id="overallStockPieChart" style="display:none;"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DAYS LEFT BAR CHART -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-white fw-bold py-3 text-dark border-bottom-0">
                    <i class="bi bi-calendar-x text-danger me-2"></i>Critical Stock Alerts (Days Left)
                </div>
                <div class="card-body position-relative d-flex justify-content-center align-items-center" style="height: 300px; width: 100%;">
                    <?php if (count($daysLeftLabels) > 0): ?>
                        <canvas id="newDaysLeftChart" style="max-height: 100%; max-width: 100%;"></canvas>
                    <?php else: ?>
                        <div class="text-center text-success py-5" id="noDataDays"><i class="bi bi-check-circle fs-1 d-block mb-2"></i>All stock levels are healthy!</div>
                        <canvas id="newDaysLeftChart" style="display:none;"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- AI ASSISTANT -->
    <div class="card border-0 shadow-sm rounded-3" style="border-top: 5px solid var(--gb-blue) !important;">
        <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center border-bottom-0">
            <span class="fs-5 text-dark"><i class="bi bi-stars text-warning me-2"></i>AI POWERED ANALYTICS</span>
            <div class="d-flex align-items-center gap-3">
                <small id="lastUpdatedText" class="text-muted fw-semibold" data-timestamp="<?= $lastTimestamp ?: '' ?>">
                    <?php if ($lastTimestamp): ?>
                        Last Updated: <?= date('M d, Y h:i:s A', $lastTimestamp / 1000) ?>
                    <?php endif; ?>
                </small>
                <button class="btn btn-sm btn-brand fw-bold shadow-sm px-3" id="generateAiBtn" onclick="generateAIPrediction(true)">
                    <i class="bi bi-arrow-clockwise me-1"></i> Analyze Now
                </button>
            </div>
        </div>
        <div class="card-body bg-light position-relative rounded-bottom-3" style="min-height: 200px;">
            <div id="aiLoading" class="position-absolute w-100 h-100 top-0 start-0 bg-white bg-opacity-75 d-flex flex-column justify-content-center align-items-center" style="display: none !important; z-index: 10;">
                <div class="spinner-border text-primary mb-2" role="status"></div>
                <small class="fw-bold text-primary blink-text">AI is calculating optimal restock dates...</small>
            </div>
            <div id="aiOutput" class="p-3 bg-white border rounded shadow-sm" style="font-size: 1rem; line-height: 1.7; color: #333;">
                <?php if ($lastPrediction): ?>
                    <?= $lastPrediction ?>
                <?php else: ?>
                    <div class="text-center text-muted py-4"><i class="bi bi-cpu fs-2 d-block mb-2"></i>Click "Analyze Now" to generate AI Restock Predictions.</div>
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