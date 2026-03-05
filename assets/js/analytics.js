/* ==========================================================
 * GB INVENTORY - AI DASHBOARD LOGIC
 * Renders Chart.js and communicates with Google Gemini
 * ========================================================== */

// RENDER CHARTS
document.addEventListener("DOMContentLoaded", function() {
    if (typeof chartLabels !== 'undefined' && chartLabels.length > 0) {
        new Chart(document.getElementById('consumptionChart').getContext('2d'), {
            type: 'bar',
            data: { labels: chartLabels, datasets: [{ label: 'Total Used (Last 30 Days)', data: consumedData, backgroundColor: 'rgba(0, 51, 204, 0.7)', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    if (typeof daysLeftLabels !== 'undefined' && daysLeftLabels.length > 0) {
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

// function startTimer() {
//     clearInterval(timerInterval);
//     countdown = 60;
//     document.getElementById('timerText').innerText = `Refreshing in: ${countdown}s`;
    
//     timerInterval = setInterval(() => {
//         countdown--;
//         document.getElementById('timerText').innerText = `Refreshing in: ${countdown}s`;
//         if (countdown <= 0) generateAIPrediction(false);
//     }, 1000);
// }

async function generateAIPrediction(isManualClick) {
    const loading = document.getElementById('aiLoading');
    const output = document.getElementById('aiOutput');
    const btn = document.getElementById('generateAiBtn');

    loading.style.setProperty('display', 'flex', 'important');
    if (isManualClick) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing...'; }

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

    const userMessage = JSON.stringify(aiPayload); // Grabs from global PHP scope
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