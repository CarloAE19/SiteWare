<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
if (!in_array($_SESSION['user_role'], ['admin', 'management', 'purchasing'])) { header("Location: index"); exit; }

require_once 'Connection/db.php';
@include_once 'Connection/api.php'; 

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
        'aiPayload' => $aiPayload
    ]);
    exit; 
}

// ==========================================
// 3. NORMAL HTML PAGE LOAD
// ==========================================
include 'layout/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard Analytics</h4>
            <small class="text-muted">Overview of material consumption and AI stock predictions.</small>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <!-- PERCENTAGE BAR CHART -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-white fw-bold py-3 text-dark border-bottom-0">
                    <i class="bi bi-pie-chart-fill text-primary me-2"></i>Material Consumption Share (%)
                </div>
                <div class="card-body position-relative" style="height: 300px; width: 100%;">
                    <?php if (count($chartLabels) > 0): ?>
                        <canvas id="pctConsumptionChart"></canvas>
                    <?php else: ?>
                        <div class="text-center text-muted py-5" id="noDataPct"><i class="bi bi-clipboard-x fs-1 d-block mb-2"></i>No consumption recorded in the last 30 days.</div>
                        <canvas id="pctConsumptionChart" style="display:none;"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- DAYS LEFT BAR CHART -->
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-header bg-white fw-bold py-3 text-dark border-bottom-0">
                    <i class="bi bi-calendar-x text-danger me-2"></i>Critical Stock Alerts (Days Left)
                </div>
                <div class="card-body position-relative" style="height: 300px; width: 100%;">
                    <?php if (count($daysLeftLabels) > 0): ?>
                        <canvas id="newDaysLeftChart"></canvas>
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
            <span class="fs-5 text-dark"><i class="bi bi-stars text-warning me-2"></i>AI Smart Restock Engine</span>
            <div>
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
                <div class="text-center text-muted py-4"><i class="bi bi-cpu fs-2 d-block mb-2"></i>Click "Analyze Now" to generate AI Restock Predictions.</div>
            </div>
        </div>
    </div>
</div>

<!-- EXTERNALIZED JS DATA & SPA FIX -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    window.aiPayload = <?= json_encode($aiPayload) ?>;
    window.apiKey = "<?= defined('AI_API_KEY') ? AI_API_KEY : '' ?>"; 

    window.buildTheCharts = function() {
        var pctCtx = document.getElementById('pctConsumptionChart');
        var daysCtx = document.getElementById('newDaysLeftChart');

        if (!pctCtx || !daysCtx) return;

        if (window.pctChartInstance) { window.pctChartInstance.destroy(); }
        if (window.daysChartInstance) { window.daysChartInstance.destroy(); }

        // 1. DRAW PERCENTAGE CHART (Animations completely disabled)
        window.pctChartInstance = new Chart(pctCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Consumption Share (%)',
                    data: <?= json_encode($percentageData) ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    rawValues: <?= json_encode($rawHoverData) ?>
                }]
            },
            options: {
                responsive: true, 
                maintainAspectRatio: false,
                animation: false, // <-- DISABLED ANIMATION
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) { return context.raw + '% (' + context.dataset.rawValues[context.dataIndex] + ' units used)'; }
                        }
                    }
                },
                scales: { y: { beginAtZero: true, max: 100, ticks: { callback: function(value) { return value + '%'; } } } }
            }
        });

        // 2. DRAW DAYS LEFT CHART (Animations completely disabled)
        window.daysChartInstance = new Chart(daysCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($daysLeftLabels) ?>,
                datasets: [{
                    label: 'Estimated Days Left',
                    data: <?= json_encode($daysLeftData) ?>,
                    backgroundColor: <?= json_encode($daysLeftColors) ?>,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, 
                maintainAspectRatio: false, 
                indexAxis: 'y',
                animation: false, // <-- DISABLED ANIMATION
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });

        // BRUTE FORCE RESIZE TO FIX ZERO-WIDTH SPA BUG
        setTimeout(function() { window.dispatchEvent(new Event('resize')); }, 100);
        setTimeout(function() { window.dispatchEvent(new Event('resize')); }, 500);

        // 3. START LIVE SYNC ENGINE
        if (window.dashboardSyncInterval) { clearInterval(window.dashboardSyncInterval); }
        window.dashboardSyncInterval = setInterval(async function() {
            try {
                if (!document.getElementById('pctConsumptionChart')) return; 
                const response = await fetch('analytics.php?ajax=1');
                if (!response.ok) return;
                const data = await response.json();
                
                window.aiPayload = data.aiPayload;

                if (window.pctChartInstance && data.chartLabels.length > 0) {
                    var noDataEl = document.getElementById('noDataPct');
                    if(noDataEl) noDataEl.style.display = 'none';
                    pctCtx.style.display = 'block';
                    window.pctChartInstance.data.labels = data.chartLabels;
                    window.pctChartInstance.data.datasets[0].data = data.percentageData;
                    window.pctChartInstance.data.datasets[0].rawValues = data.rawHoverData;
                    window.pctChartInstance.update();
                }

                if (window.daysChartInstance && data.daysLeftLabels.length > 0) {
                    var noDataEl2 = document.getElementById('noDataDays');
                    if(noDataEl2) noDataEl2.style.display = 'none';
                    daysCtx.style.display = 'block';
                    window.daysChartInstance.data.labels = data.daysLeftLabels;
                    window.daysChartInstance.data.datasets[0].data = data.daysLeftData;
                    window.daysChartInstance.data.datasets[0].backgroundColor = data.daysLeftColors;
                    window.daysChartInstance.update();
                }
            } catch (error) { console.log("Silent sync waiting..."); }
        }, 8000); 
    };

    // BOOTLOADER
    window.safeChartLoader = function() {
        if (typeof Chart === 'undefined') {
            setTimeout(window.safeChartLoader, 50);
            return;
        }
        setTimeout(window.buildTheCharts, 100);
    };

    window.safeChartLoader();
</script>

<!-- Fail-safe inline trigger for strict SPA frameworks -->
<img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" onload="if(window.safeChartLoader) window.safeChartLoader();" style="display:none;">
<script src="assets/js/analytics.js"></script>

<?php include 'layout/footer.php'; ?>