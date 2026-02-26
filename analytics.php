<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
// Limit access to Management, Admin, and Purchasing
if (!in_array($_SESSION['user_role'], ['admin', 'management', 'purchasing'])) { 
    header("Location: index"); 
    exit; 
}
require_once 'Connection/db.php';

// ==========================================
// 1. GATHER DATA FOR CHARTS & AI
// ==========================================

// Get 30-Day Withdrawal Data (Consumption Rate)
$consumptionQuery = "
    SELECT i.item_name, i.item_code, SUM(wi.quantity) as total_consumed, i.quantity as current_stock, i.unit
    FROM withdrawal_items wi
    JOIN withdrawals w ON wi.withdrawal_id = w.id
    JOIN inventory i ON wi.item_code = i.item_code
    WHERE w.date_withdrawn >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY wi.item_code
    ORDER BY total_consumed DESC
";
$consumptionData = $pdo->query($consumptionQuery)->fetchAll(PDO::FETCH_ASSOC);

// Format Data for Chart.js
$chartLabels = [];
$chartData = [];
foreach ($consumptionData as $item) {
    $chartLabels[] = $item['item_name'];
    $chartData[] = $item['total_consumed'];
}

// Format Data into JSON for the AI Prompt
$aiPayloadData = json_encode($consumptionData);

include 'layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-graph-up me-2"></i>Analytics & AI Predictions</h4>
            <small class="text-muted">30-Day Material Consumption & Smart Restock Insights</small>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: Charts & Data -->
        <div class="col-xl-7 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-bar-chart-fill text-primary me-2"></i>Top Consumed Materials (Last 30 Days)
                </div>
                <div class="card-body">
                    <?php if (count($chartLabels) > 0): ?>
                        <canvas id="consumptionChart" height="250"></canvas>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-clipboard-x fs-1 d-block mb-2"></i>
                            Not enough withdrawal data in the last 30 days to generate charts.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: AI Prediction Engine -->
        <div class="col-xl-5 mb-4">
            <div class="card border-0 shadow-sm h-100" style="border-top: 5px solid var(--gb-blue);">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-robot text-primary me-2"></i>AI Restock Assistant</span>
                    <button class="btn btn-sm btn-brand" id="generateAiBtn" onclick="generateAIPrediction()">
                        <i class="bi bi-magic me-1"></i> Analyze Data
                    </button>
                </div>
                <div class="card-body bg-light" id="aiResultContainer" style="overflow-y: auto; max-height: 400px;">
                    
                    <div id="aiPlaceholder" class="text-center text-muted py-5">
                        <i class="bi bi-cpu fs-1 d-block mb-3 text-secondary"></i>
                        <p class="mb-0">Click "Analyze Data" to feed your inventory and 30-day consumption data to the AI.</p>
                        <small>The AI will calculate burn rates and predict when you will run out of stock.</small>
                    </div>

                    <div id="aiLoading" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="text-muted fw-bold blink-text">AI is analyzing burn rates...</p>
                    </div>

                    <div id="aiOutput" class="p-2" style="display: none; font-size: 0.95rem; line-height: 1.6;">
                        <!-- AI Response gets injected here -->
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ==========================================
// CHART.JS INITIALIZATION
// ==========================================
document.addEventListener("DOMContentLoaded", function() {
    const labels = <?= json_encode($chartLabels) ?>;
    const data = <?= json_encode($chartData) ?>;
    
    if (labels.length > 0) {
        const ctx = document.getElementById('consumptionChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Quantity Consumed',
                    data: data,
                    backgroundColor: 'rgba(0, 51, 204, 0.7)', // GB Blue
                    borderColor: 'rgba(0, 51, 204, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    }
});

// ==========================================
// GEMINI AI API INTEGRATION
// ==========================================
async function generateAIPrediction() {
    const btn = document.getElementById('generateAiBtn');
    const placeholder = document.getElementById('aiPlaceholder');
    const loading = document.getElementById('aiLoading');
    const output = document.getElementById('aiOutput');

    // 1. UPDATE UI
    btn.disabled = true;
    placeholder.style.display = 'none';
    output.style.display = 'none';
    loading.style.display = 'block';

    // 2. PREPARE THE DATA & PROMPT
    // We grab the PHP JSON data we prepared earlier
    const inventoryData = <?= $aiPayloadData ?>;
    
    const systemPrompt = `You are an expert Construction Inventory AI Assistant. 
    I am providing you with JSON data showing the materials consumed in the last 30 days, along with their current stock levels.
    Calculate the "burn rate" (how fast it's being used) and predict approximately how many days/weeks until the current stock runs out.
    Identify any critical items that need immediate Purchase Orders.
    Format your response in clean, modern HTML using <ul>, <li>, and <strong> tags. Do not use markdown (like ** or ##). Keep it concise, professional, and easy to read for a Purchasing Officer.`;

    const userMessage = JSON.stringify(inventoryData);

    // 3. YOUR GEMINI API KEY
    // GET A FREE KEY HERE: https://aistudio.google.com/app/apikey
    const apiKey = "AIzaSyAP82mjxlekRC1PnZQItQecRhOEMuj1C2E"; // <-- REPLACE WITH YOUR KEY

    const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=${apiKey}`;

    try {
        // 4. CALL THE API
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                contents: [{ parts: [{ text: systemPrompt + "\n\nData: " + userMessage }] }]
            })
        });

        const data = await response.json();
        
        // 5. DISPLAY RESULT
        if (data.candidates && data.candidates[0].content.parts[0].text) {
            let aiText = data.candidates[0].content.parts[0].text;
            output.innerHTML = aiText;
        } else {
            output.innerHTML = "<div class='alert alert-danger'>AI failed to generate a response. Please check your API key.</div>";
        }
    } catch (error) {
        output.innerHTML = `<div class='alert alert-danger'>API Connection Error: ${error.message}</div>`;
    } finally {
        loading.style.display = 'none';
        output.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Refresh Prediction';
    }
}
</script>

<style>
/* Simple CSS animation for the loading text */
.blink-text { animation: blinker 1.5s linear infinite; }
@keyframes blinker { 50% { opacity: 0.5; } }
</style>

<?php include 'layout/footer.php'; ?>