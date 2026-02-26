<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
if (!in_array($_SESSION['user_role'], ['admin', 'management', 'purchasing'])) { 
    header("Location: index"); 
    exit; 
}

// 1. INCLUDE BOTH DB AND API CONFIG FILES
require_once 'Connection/db.php';
require_once 'Connection/api.php'; 

// ==========================================
// 2. CALCULATE BURN RATES & PREPARE DATA
// ==========================================

// Query A: Overall Material Consumption & Stock Levels
$query = "
    SELECT 
        i.item_name, 
        i.item_code, 
        COALESCE(SUM(wi.quantity), 0) as total_consumed, 
        i.quantity as current_stock, 
        i.unit
    FROM inventory i
    LEFT JOIN withdrawal_items wi ON i.item_code = wi.item_code
    LEFT JOIN withdrawals w ON wi.withdrawal_id = w.id AND w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY i.item_code, i.item_name, i.quantity, i.unit
    ORDER BY current_stock ASC, total_consumed DESC
";
$consumptionData = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// NEW Query B: Project-Specific Consumption Breakdown (Last 30 Days)
$projQuery = "
    SELECT wi.item_code, w.project_name, SUM(wi.quantity) as project_consumed
    FROM withdrawal_items wi
    JOIN withdrawals w ON wi.withdrawal_id = w.id
    WHERE w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY wi.item_code, w.project_name
    ORDER BY wi.item_code, project_consumed DESC
";
$projData = $pdo->query($projQuery)->fetchAll(PDO::FETCH_ASSOC);

// Organize Project Data by Item Code
$projectBreakdown = [];
foreach($projData as $row) {
    // Format: "City Hall Phase 1 (50)"
    $projectBreakdown[$row['item_code']][] = $row['project_name'] . " (" . $row['project_consumed'] . ")";
}

$chartLabels = [];
$consumedData = [];
$daysLeftLabels = [];
$daysLeftData = [];
$daysLeftColors = [];

$aiPayload = [];

foreach ($consumptionData as $item) {
    $dailyBurn = $item['total_consumed'] / 30;
    
    if ($item['current_stock'] <= 0) {
        $daysLeft = 0; 
    } elseif ($dailyBurn > 0) {
        $daysLeft = floor($item['current_stock'] / $dailyBurn);
    } else {
        $daysLeft = 999; 
    }
    
    // Grab the top consuming projects for this specific item
    $topProjects = isset($projectBreakdown[$item['item_code']]) ? implode(", ", $projectBreakdown[$item['item_code']]) : "No recent projects";

    // NEW: Add 'Consuming_Projects' to the AI Payload!
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
        
        if ($daysLeft <= 7) $daysLeftColors[] = 'rgba(220, 53, 69, 0.8)'; 
        elseif ($daysLeft <= 14) $daysLeftColors[] = 'rgba(255, 193, 7, 0.8)'; 
        else $daysLeftColors[] = 'rgba(25, 135, 84, 0.8)'; 
    }
}

usort($aiPayload, fn($a, $b) => $a['Est_Days_Left'] <=> $b['Est_Days_Left']);

include 'layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-speedometer2 me-2 text-primary"></i>System Dashboard</h4>
            <small class="text-muted">Live Inventory Tracking & AI Restock Recommendations</small>
        </div>
        <div class="badge bg-success bg-opacity-10 text-success border border-success p-2">
            <i class="bi bi-circle-fill blink-icon me-2" style="font-size: 0.6rem;"></i>SYSTEM LIVE
        </div>
    </div>

    <div class="row mb-4">
        <!-- Chart 1: Consumption -->
        <div class="col-xl-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3 text-dark">
                    <i class="bi bi-bar-chart-fill text-primary me-2"></i>Recent Material Consumption
                </div>
                <div class="card-body">
                    <?php if (count($chartLabels) > 0): ?>
                        <canvas id="consumptionChart" height="250"></canvas>
                    <?php else: ?>
                        <div class="text-center text-muted py-5"><i class="bi bi-clipboard-x fs-1 d-block mb-2"></i>No consumption recorded recently.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Chart 2: Days Remaining -->
        <div class="col-xl-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3 text-dark d-flex justify-content-between">
                    <span><i class="bi bi-calendar-x text-danger me-2"></i>Critical Stock Alerts (Days Left)</span>
                    <span class="badge bg-danger">Critical First</span>
                </div>
                <div class="card-body">
                    <?php if (count($daysLeftLabels) > 0): ?>
                        <canvas id="daysLeftChart" height="250"></canvas>
                    <?php else: ?>
                        <div class="text-center text-success py-5"><i class="bi bi-check-circle fs-1 d-block mb-2"></i>All stock levels are healthy!</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- LIVE AI ASSISTANT -->
    <div class="card border-0 shadow-sm" style="border-top: 5px solid var(--gb-blue);">
        <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
            <span class="fs-5"><i class="bi bi-stars text-warning me-2"></i>AI Smart Restock Recommendations</span>
            
            <div class="d-flex align-items-center">
                <span class="text-muted small fw-bold me-3" id="timerText">Refreshing in: 60s</span>
                <button class="btn btn-sm btn-brand" id="generateAiBtn" onclick="generateAIPrediction(true)">
                    <i class="bi bi-arrow-clockwise me-1"></i> Force Sync
                </button>
            </div>
        </div>
        <div class="card-body bg-light position-relative" style="min-height: 200px;">
            
            <div id="aiLoading" class="position-absolute w-100 h-100 top-0 start-0 bg-white bg-opacity-75 d-flex flex-column justify-content-center align-items-center" style="display: none !important; z-index: 10;">
                <div class="spinner-border text-primary mb-2" role="status"></div>
                <small class="fw-bold text-primary blink-text">AI is calculating optimal restock dates & tracking project usage...</small>
            </div>

            <div id="aiOutput" class="p-3 bg-white border rounded shadow-sm" style="font-size: 1rem; line-height: 1.7; color: #333;">
                <div class="text-center text-muted py-4"><i class="bi bi-hourglass-split fs-2 d-block mb-2"></i>Initializing AI Engine...</div>
            </div>
            
            <div class="mt-2 text-end">
                <small class="text-muted" id="lastUpdatedText">Last Updated: Just now</small>
            </div>
        </div>
    </div>
</div>

<script>
// RENDER CHARTS
document.addEventListener("DOMContentLoaded", function() {
    const labels = <?= json_encode($chartLabels) ?>;
    const consumedData = <?= json_encode($consumedData) ?>;
    const daysLeftLabels = <?= json_encode($daysLeftLabels) ?>;
    const daysLeftData = <?= json_encode($daysLeftData) ?>;
    const daysLeftColors = <?= json_encode($daysLeftColors) ?>;
    
    if (labels.length > 0) {
        new Chart(document.getElementById('consumptionChart').getContext('2d'), {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: 'Total Used (Last 30 Days)', data: consumedData, backgroundColor: 'rgba(0, 51, 204, 0.7)', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    if (daysLeftLabels.length > 0) {
        new Chart(document.getElementById('daysLeftChart').getContext('2d'), {
            type: 'bar',
            data: { labels: daysLeftLabels, datasets: [{ label: 'Estimated Days Remaining', data: daysLeftData, backgroundColor: daysLeftColors, borderRadius: 4 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false }
        });
    }
});

// GEMINI AI AUTO-PILOT ENGINE
let countdown = 60;
let timerInterval;

document.addEventListener("DOMContentLoaded", () => {
    const savedPrediction = localStorage.getItem('gb_ai_prediction');
    const savedTime = localStorage.getItem('gb_ai_timestamp');
    
    if (savedPrediction && savedTime && (Date.now() - savedTime < 300000)) {
        document.getElementById('aiOutput').innerHTML = savedPrediction;
        const date = new Date(parseInt(savedTime));
        document.getElementById('lastUpdatedText').innerText = "Last Updated: " + date.toLocaleTimeString();
    } else {
        generateAIPrediction(false);
    }
    startTimer();
});

function startTimer() {
    clearInterval(timerInterval);
    countdown = 60;
    document.getElementById('timerText').innerText = `Refreshing in: ${countdown}s`;
    
    timerInterval = setInterval(() => {
        countdown--;
        document.getElementById('timerText').innerText = `Refreshing in: ${countdown}s`;
        if (countdown <= 0) generateAIPrediction(false);
    }, 1000);
}

async function generateAIPrediction(isManualClick) {
    const loading = document.getElementById('aiLoading');
    const output = document.getElementById('aiOutput');
    const btn = document.getElementById('generateAiBtn');

    loading.style.setProperty('display', 'flex', 'important');
    if (isManualClick) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing...'; }

    const inventoryData = <?= json_encode($aiPayload) ?>;
    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

    // NEW PROMPT: Forces AI to report the specific project consuming the materials!
    const systemPrompt = `You are the GB Construction AI Restock Assistant. Today is ${today}.
    Analyze this JSON data showing inventory items, current stock, daily burn rates, and the specific projects consuming them.
    
    CRITICAL INSTRUCTIONS FOR THE PANELIST:
    Rule 1: If Current_Stock is 0, the Recommended Restock Date must be "IMMEDIATELY / TODAY". Recommend an order quantity of at least 50.
    Rule 2: For any item with less than 20 days of stock left, calculate a specific 'Recommended Restock Date' (5 days before it hits 0).
    Rule 3: Calculate a specific 'Recommended Order Qty' (Daily Burn Rate * 30, minimum 20).
    Rule 4: Look at the 'Consuming_Projects' data and explicitly state WHICH project is driving this high usage!
    
    Format output clearly in HTML using <ul> and <li>. NO MARKDOWN (**). 
    Use exactly this format for critical items:
    <li style="margin-bottom: 15px; padding: 10px; border-left: 4px solid #dc3545; background-color: #fff;">
        <span style="font-size: 1.1rem; color: #dc3545;">⚠️ <strong>[Item Name]</strong></span> - [Reason: e.g. Out of Stock / Runs out in X days].
        <br>🏢 <strong>Top Consumer:</strong> [Name of the Project using this item from the JSON data]
        <br>👉 <strong>Recommended Restock Date:</strong> [Specific Date]
        <br>👉 <strong>Recommended Order Qty:</strong> [Calculated Number] [Unit]
    </li>`;

    const userMessage = JSON.stringify(inventoryData);
    
    // FETCH THE API KEY SECURELY FROM PHP
    const apiKey = "<?= AI_API_KEY ?>"; 
    
    const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=${apiKey}`;

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contents: [{ parts: [{ text: systemPrompt + "\n\nData: " + userMessage }] }] })
        });

        const data = await response.json();
        
        if (response.ok && data.candidates) {
            let aiText = data.candidates[0].content.parts[0].text;
            aiText = aiText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/```html/g, '').replace(/```/g, '');

            output.innerHTML = aiText;
            localStorage.setItem('gb_ai_prediction', aiText);
            localStorage.setItem('gb_ai_timestamp', Date.now());
            
            document.getElementById('lastUpdatedText').innerText = "Last Updated: " + new Date().toLocaleTimeString();
        } else if (data.error) {
            output.innerHTML = `<div class='alert alert-danger'><strong>Google API Error:</strong> ${data.error.message}</div>`;
        }
    } catch (error) {
        console.error(error);
    } finally {
        loading.style.setProperty('display', 'none', 'important');
        if (isManualClick) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Force Sync'; }
        startTimer();
    }
}
</script>

<style>
.blink-icon { animation: blinker 1.5s linear infinite; }
.blink-text { animation: blinker 2s linear infinite; }
@keyframes blinker { 50% { opacity: 0.3; } }
</style>

<?php include 'layout/footer.php'; ?>