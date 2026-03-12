/* ==========================================================
 * GB INVENTORY - DASHBOARD ENGINE
 * Handles: SPA Chart Animations, Live Sync, and Gemini AI
 * ========================================================== */

// =========================================================
// 1. GEMINI AI AUTO-PILOT ENGINE
// =========================================================
let countdown = 60;
let timerInterval;

document.addEventListener("DOMContentLoaded", () => {
    if (!document.getElementById('aiOutput')) return;

    const savedPrediction = localStorage.getItem('gb_ai_prediction');
    const savedTime = localStorage.getItem('gb_ai_timestamp');
    
    if (savedPrediction && savedTime && (Date.now() - savedTime < 300000)) {
        document.getElementById('aiOutput').innerHTML = savedPrediction;
        const date = new Date(parseInt(savedTime));
        let updatedTextEl = document.getElementById('lastUpdatedText');
        if (updatedTextEl) updatedTextEl.innerText = "Last Updated: " + date.toLocaleTimeString();
    } else {
        generateAIPrediction(false);
    }
    startTimer();
});

function startTimer() {
    clearInterval(timerInterval);
    countdown = 60;
    timerInterval = setInterval(() => {
        countdown--;
        if(countdown <= 0) { countdown = 60; }
    }, 1000);
}

window.generateAIPrediction = async function(isManualClick) {
    const loading = document.getElementById('aiLoading');
    const output = document.getElementById('aiOutput');
    const btn = document.getElementById('generateAiBtn');

    if (!loading || !output) return;

    loading.style.setProperty('display', 'flex', 'important');
    if (isManualClick && btn) { 
        btn.disabled = true; 
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing...'; 
    }

    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

    const systemPrompt = `You are the GB Construction AI Restock Assistant. Today is ${today}.
    Analyze this JSON data showing inventory items, current stock, daily burn rates, and consuming projects.
    
    CRITICAL INSTRUCTIONS TO SATISFY USE CASE 13.2 (Supplier Lead Time):
    Rule 1: Always account for a "3 to 5 Day Supplier Lead Time". If stock runs out in 8 days, they must order in 3 days!
    Rule 2: If Current_Stock is 0, the Recommended Restock Date must be "IMMEDIATELY". Recommend an order quantity of at least 50.
    Rule 3: For any item with less than 20 days of stock left, calculate a specific 'Recommended Restock Date' (Lead Time considered).
    Rule 4: Look at 'Consuming_Projects' and explicitly state WHICH project is driving the usage. If none, write "General Stock".
    
    Format output clearly in HTML using <ul> and <li>. NO MARKDOWN (**). 
    Use exactly this format for critical items:
    <li style="margin-bottom: 15px; padding: 10px; border-left: 4px solid #dc3545; background-color: #fff;">
        <span style="font-size: 1.1rem; color: #dc3545;">⚠️ <strong>[Item Name]</strong></span> - [Reason: e.g. Out of Stock / Runs out in X days].
        <br>🏢 <strong>Top Consumer:</strong> [Name of the Project]
        <br>👉 <strong>Recommended Restock Date:</strong> [Specific Date] (Accounts for 5-day Lead Time)
        <br>👉 <strong>Recommended Order Qty:</strong> [Calculated Number] [Unit]
    </li>`;

    const userMessage = JSON.stringify(window.aiPayload); 
    const apiKey = window.apiKey; 
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
            
            let updatedTextEl = document.getElementById('lastUpdatedText');
            if (updatedTextEl) updatedTextEl.innerText = "Last Updated: " + new Date().toLocaleTimeString();
        } else if (data.error) {
            output.innerHTML = `<div class='alert alert-danger'><strong>Google API Error:</strong> ${data.error.message}</div>`;
        }
    } catch (error) {
        console.error("AI Error:", error);
    } finally {
        loading.style.setProperty('display', 'none', 'important');
        if (isManualClick && btn) { 
            btn.disabled = false; 
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Analyze Now'; 
        }
        startTimer();
    }
}

// =========================================================
// 2. DASHBOARD CHART ENGINE (MIXED ANIMATIONS)
// =========================================================

window.lastChartState = { pct: "", pie: "", days: "" };

window.buildTheCharts = function() {
    var pctCtx = document.getElementById('pctConsumptionChart');
    var daysCtx = document.getElementById('newDaysLeftChart');
    var pieCtx = document.getElementById('overallStockPieChart'); 

    if (!pctCtx && !daysCtx && !pieCtx) return;

    if (window.pctChartInstance) { window.pctChartInstance.destroy(); window.pctChartInstance = null; }
    if (window.daysChartInstance) { window.daysChartInstance.destroy(); window.daysChartInstance = null; }
    if (window.pieChartInstance) { window.pieChartInstance.destroy(); window.pieChartInstance = null; }

    // Save actual states so AJAX doesn't overwrite immediately
    window.lastChartState.pct = JSON.stringify(window.chartData.percentageData);
    window.lastChartState.pie = JSON.stringify(window.chartData.pieData);
    window.lastChartState.days = JSON.stringify(window.chartData.daysLeftData);

    // 1. DRAW PERCENTAGE BAR CHART (Starts at Zero, grows UP)
    if (pctCtx && window.chartData.chartLabels.length > 0) {
        let zeroData = window.chartData.percentageData.map(() => 0); 
        window.pctChartInstance = new Chart(pctCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: window.chartData.chartLabels,
                datasets: [{
                    label: 'Consumption Share (%)',
                    data: zeroData, 
                    backgroundColor: 'rgba(13, 110, 253, 0.85)', 
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 0, borderRadius: 6, maxBarThickness: 45, 
                    rawValues: window.chartData.rawHoverData
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, 
                animation: { duration: 1500, easing: 'easeOutQuart' },
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { return context.raw + '% (' + context.dataset.rawValues[context.dataIndex] + ' units used)'; } } } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, max: 100, border: { dash: [4, 4] }, grid: { color: '#f0f0f0' }, ticks: { callback: function(value) { return value + '%'; } } } }
            }
        });
    }

    // 2. DRAW OVERALL STOCK PIE CHART (Starts with Real Data for Native Circular Rotation!)
    if (pieCtx && window.chartData.pieLabels.length > 0) {
        window.pieChartInstance = new Chart(pieCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: window.chartData.pieLabels, // Provided immediately
                datasets: [{
                    data: window.chartData.pieData, // Provided immediately
                    backgroundColor: window.chartData.pieColors,
                    borderWidth: 2, borderColor: '#ffffff', hoverOffset: 6 
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, 
                // THE FIX: animateScale is false, animateRotate is true. This forces a pure circular sweep!
                animation: { animateScale: false, animateRotate: true, duration: 2000, easing: 'easeOutQuart' },
                cutout: '65%', layout: { padding: 15 }, 
                plugins: { legend: { position: 'right', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, font: { size: 11 } } }, tooltip: { callbacks: { label: function(context) { return ' ' + context.label + ': ' + context.raw + ' in stock'; } } } }
            }
        });
    }

    // 3. DRAW DAYS LEFT BAR CHART (Starts at Zero, slides RIGHT)
    if (daysCtx && window.chartData.daysLeftLabels.length > 0) {
        let zeroDays = window.chartData.daysLeftData.map(() => 0); 
        window.daysChartInstance = new Chart(daysCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: window.chartData.daysLeftLabels,
                datasets: [{
                    label: 'Estimated Days Left',
                    data: zeroDays, 
                    backgroundColor: window.chartData.daysLeftColors,
                    borderRadius: 6, maxBarThickness: 35 
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, indexAxis: 'y', 
                animation: { duration: 1500, easing: 'easeOutQuart' },
                plugins: { legend: { display: false } },
                scales: { y: { grid: { display: false } }, x: { beginAtZero: true, border: { dash: [4, 4] }, grid: { color: '#f0f0f0' } } }
            }
        });
    }

    // ========================================================
    // TRIGGER BAR CHART ANIMATIONS
    // Only update the BAR charts, leave the Pie chart alone so its rotation finishes!
    // ========================================================
    setTimeout(() => {
        if (window.pctChartInstance) {
            window.pctChartInstance.data.datasets[0].data = window.chartData.percentageData;
            window.pctChartInstance.update(); 
        }
        if (window.daysChartInstance) {
            window.daysChartInstance.data.datasets[0].data = window.chartData.daysLeftData;
            window.daysChartInstance.update(); 
        }
    }, 200); // Trigger slightly faster for a snappier feel

    // BACKGROUND LIVE SYNC ENGINE
    if (window.dashboardSyncInterval) { clearInterval(window.dashboardSyncInterval); }
    window.dashboardSyncInterval = setInterval(async function() {
        if (!document.getElementById('pctConsumptionChart')) {
            clearInterval(window.dashboardSyncInterval);
            return;
        }
        try {
            const response = await fetch('analytics.php?ajax=1');
            if (!response.ok) return;
            const data = await response.json();
            
            window.aiPayload = data.aiPayload;

            if (window.pctChartInstance && data.chartLabels.length > 0) {
                let newPctState = JSON.stringify(data.percentageData);
                if (window.lastChartState.pct !== newPctState) { 
                    window.lastChartState.pct = newPctState;
                    window.pctChartInstance.data.labels = data.chartLabels;
                    window.pctChartInstance.data.datasets[0].data = data.percentageData;
                    window.pctChartInstance.data.datasets[0].rawValues = data.rawHoverData;
                    window.pctChartInstance.update(); 
                }
            }

            if (window.pieChartInstance && data.pieLabels.length > 0) {
                let newPieState = JSON.stringify(data.pieData);
                if (window.lastChartState.pie !== newPieState) { 
                    window.lastChartState.pie = newPieState;
                    window.pieChartInstance.data.labels = data.pieLabels;
                    window.pieChartInstance.data.datasets[0].data = data.pieData;
                    window.pieChartInstance.data.datasets[0].backgroundColor = data.pieColors;
                    window.pieChartInstance.update(); 
                }
            }

            if (window.daysChartInstance && data.daysLeftLabels.length > 0) {
                let newDaysState = JSON.stringify(data.daysLeftData);
                if (window.lastChartState.days !== newDaysState) { 
                    window.lastChartState.days = newDaysState;
                    window.daysChartInstance.data.labels = data.daysLeftLabels;
                    window.daysChartInstance.data.datasets[0].data = data.daysLeftData;
                    window.daysChartInstance.data.datasets[0].backgroundColor = data.daysLeftColors;
                    window.daysChartInstance.update(); 
                }
            }
        } catch (error) { console.log("Silent sync waiting..."); }
    }, 8000); 
};

// =========================================================
// SPA TRIGGER
// =========================================================
window.initDashboardCharts = function() {
    if (typeof Chart === 'undefined') {
        setTimeout(window.initDashboardCharts, 50);
        return;
    }
    if (window.dashboardSyncInterval) clearInterval(window.dashboardSyncInterval);
    
    // Visibility checker ensures we don't draw until SPA animation finishes
    let visibilityCheck = setInterval(function() {
        let canvas = document.getElementById('pctConsumptionChart');
        if (canvas && canvas.offsetHeight > 0) {
            clearInterval(visibilityCheck);
            setTimeout(window.buildTheCharts, 50); 
        }
    }, 50); 
    setTimeout(() => { clearInterval(visibilityCheck); }, 3000);
};

window.initDashboardCharts();