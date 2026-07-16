/* ==========================================================
 * GB INVENTORY - DASHBOARD ENGINE
 * Handles: SPA Chart Animations, Live Sync, and Gemini AI
 * ========================================================== */

// =========================================================
// 1. GEMINI AI AUTO-PILOT ENGINE
// =========================================================
let countdown = 60;
let timerInterval;

function formatDateTime(date) {
    return date.toLocaleString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
}

document.addEventListener("DOMContentLoaded", () => {
    const aiOutput = document.getElementById('aiOutput');
    if (!aiOutput) return;

    let updatedTextEl = document.getElementById('lastUpdatedText');
    
    // Format the database timestamp in the user's local timezone
    if (updatedTextEl) {
        const dbTimestamp = updatedTextEl.getAttribute('data-timestamp');
        if (dbTimestamp) {
            const date = new Date(parseInt(dbTimestamp));
            updatedTextEl.innerText = "Last Updated: " + formatDateTime(date);
        }
    }

    // Check if the database loaded a prediction (not the default placeholder icon)
    const hasDbPrediction = aiOutput.querySelector('.bi-cpu') === null;

    if (!hasDbPrediction) {
        // Fallback to localStorage if database had no prediction
        const savedPrediction = localStorage.getItem('gb_ai_prediction');
        const savedTime = localStorage.getItem('gb_ai_timestamp');
        if (savedPrediction && savedTime) {
            aiOutput.innerHTML = savedPrediction;
            const date = new Date(parseInt(savedTime));
            if (updatedTextEl) {
                updatedTextEl.innerText = "Last Updated: " + formatDateTime(date);
            }
        }
    }
    
    // We no longer trigger generateAIPrediction(false) on page load.
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

    try {
        const response = await fetch('analytics.php?action=generate_ai_report', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        const data = await response.json();
        
        if (response.ok && data.status === 'success') {
            let aiText = data.prediction;
            // Clean up bold/markdown if any was returned by the LLM
            aiText = aiText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            output.innerHTML = aiText;
            localStorage.setItem('gb_ai_prediction', aiText);
            localStorage.setItem('gb_ai_timestamp', data.timestamp);
            
            let updatedTextEl = document.getElementById('lastUpdatedText');
            if (updatedTextEl) {
                updatedTextEl.setAttribute('data-timestamp', data.timestamp);
                updatedTextEl.innerText = "Last Updated: " + formatDateTime(new Date(data.timestamp));
            }
            if (updatedTextEl) updatedTextEl.innerText = "Last Updated: " + formatDateTime(new Date());
        } else if (data.error) {
            output.innerHTML = `<div class='alert alert-danger'><strong>AI API Error:</strong> ${data.error}</div>`;
        } else {
            output.innerHTML = `<div class='alert alert-danger'><strong>AI API Error:</strong> Failed to fetch analysis details.</div>`;
        }
    } catch (error) {
        console.error("AI Error:", error);
        output.innerHTML = `<div class='alert alert-danger'><strong>Connection Error:</strong> Could not connect to backend AI module.</div>`;
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

    // 2. DRAW OVERALL STOCK PIE CHART (Starts from Zero, grows OUT)
    if (pieCtx && window.chartData.pieLabels.length > 0) {
        let zeroPieData = window.chartData.pieData.map(() => 0); // Start array with zeros
        window.pieChartInstance = new Chart(pieCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: window.chartData.pieLabels,
                datasets: [{
                    data: zeroPieData, // Init with zeros for animation
                    backgroundColor: window.chartData.pieColors,
                    borderWidth: 2, borderColor: '#ffffff', hoverOffset: 6 
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, 
                // Changed to standard animateScale and animateRotate for a clean data-in animation
                animation: { animateScale: true, animateRotate: true, duration: 2000, easing: 'easeOutQuart' },
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
    // Update all charts including Pie chart to trigger the data-in animations!
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
        if (window.pieChartInstance) {
            window.pieChartInstance.data.datasets[0].data = window.chartData.pieData;
            window.pieChartInstance.update(); 
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
    // Check ANY visible canvas - pctConsumptionChart may be hidden when no consumption data exists
    let visibilityCheck = setInterval(function() {
        let pctCanvas  = document.getElementById('pctConsumptionChart');
        let pieCanvas  = document.getElementById('overallStockPieChart');
        let daysCanvas = document.getElementById('newDaysLeftChart');
        let anyVisible = (pctCanvas  && pctCanvas.offsetHeight  > 0) ||
                         (pieCanvas  && pieCanvas.offsetHeight  > 0) ||
                         (daysCanvas && daysCanvas.offsetHeight > 0);
        if (anyVisible) {
            clearInterval(visibilityCheck);
            setTimeout(window.buildTheCharts, 50); 
        }
    }, 50); 
    setTimeout(() => { clearInterval(visibilityCheck); }, 3000);
};

window.initDashboardCharts();