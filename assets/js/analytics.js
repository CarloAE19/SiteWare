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

window.formatInteractiveEntities = function(html) {
    if (!html) return '';
    let text = html;
    text = text.replace(/\bITM\s+(\d+)\b/gi, 'ITM-$1');
    text = text.replace(/\bPO\s+(\d+)\b/gi, 'PO-$1');
    text = text.replace(/\bRS\s+(\d+)\b/gi, 'RS-$1');
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/(?:<button[^>]*>)?\b(PO-(?:\d{4,8}-\d+|\d+))\b(?:<\/button>)?/g, (fullMatch, match) => {
        if (fullMatch.startsWith('<button')) return fullMatch;
        return `<button type="button" class="btn btn-xs btn-outline-primary fw-bold text-nowrap py-0 px-2 shadow-none entity-btn" style="font-size: 0.76rem; border-radius: 6px;" onclick="if(typeof openPoDetailsModal==='function'){openPoDetailsModal(null,'${match}')}else{window.location.href='po?highlight=${match}'}" title="View Purchase Order Details"><i class="bi bi-receipt me-1"></i>${match}</button>`;
    });
    text = text.replace(/(?:<button[^>]*>)?\b(RS-(?:\d{4}-\d+|\d+))\b(?:<\/button>)?/g, (fullMatch, match) => {
        if (fullMatch.startsWith('<button')) return fullMatch;
        return `<button type="button" class="btn btn-xs btn-outline-info fw-bold text-nowrap py-0 px-2 shadow-none entity-btn" style="font-size: 0.76rem; border-radius: 6px;" onclick="if(typeof openRequisitionModal==='function'){openRequisitionModal(null,'${match}')}else{window.location.href='requisition?highlight=${match}'}" title="View Requisition Details"><i class="bi bi-file-earmark-text me-1"></i>${match}</button>`;
    });
    text = text.replace(/(?:<button[^>]*>)?\b(ITM-\d+)\b(?:<\/button>)?/g, (fullMatch, match) => {
        if (fullMatch.startsWith('<button')) return fullMatch;
        return `<button type="button" class="btn btn-xs btn-outline-success fw-bold text-nowrap py-0 px-2 shadow-none entity-btn" style="font-size: 0.76rem; border-radius: 6px;" onclick="if(typeof openItemDetailsModal==='function'){openItemDetailsModal('${match}')}else{window.location.href='inventory?highlight=${match}'}" title="View Inventory Details"><i class="bi bi-box-seam me-1"></i>${match}</button>`;
    });
    return text;
};

window.polishSummaryHtml = function(html) {
    if (!html) return '';
    if (html.includes('cims-summary-container')) return html;

    let date = new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    const dateMatch = html.match(/(?:Analysis Date:\s*|<strong>Analysis Date:<\/strong>\s*)([^<]+)/i);
    if (dateMatch) {
        date = dateMatch[1].trim();
    }

    let narrative = html.replace(/<div[^>]*>.*?Analysis Date.*?<\/div>/is, '');
    narrative = narrative.replace(/<\/?p[^>]*>/gi, '').trim();

    const hasRisk = /stockout|critical|deplet|urgenc/i.test(narrative);
    const statusPill = hasRisk
        ? `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" style="font-size: 0.72rem;"><i class="bi bi-shield-exclamation me-1"></i>Replenishment Flagged</span>`
        : `<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1" style="font-size: 0.72rem;"><i class="bi bi-shield-check me-1"></i>Operations Healthy</span>`;

    return `
        <div class="cims-summary-container">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-2 mb-2 border-bottom">
                <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.75rem;">
                    <i class="bi bi-calendar3 text-primary me-1"></i> ${date}
                </span>
                ${statusPill}
            </div>
            <div class="p-3 rounded-3 bg-light-subtle border shadow-none mb-2">
                <div class="d-flex align-items-start gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-0.5" style="width: 24px; height: 24px;">
                        <i class="bi bi-lightning-charge-fill" style="font-size: 0.75rem;"></i>
                    </div>
                    <div class="flex-grow-1 text-dark" style="font-size: 0.86rem; line-height: 1.65;">
                        ${narrative}
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1 mt-2 pt-1">
                <span class="badge bg-body-secondary text-secondary border px-2 py-1" style="font-size: 0.7rem;">
                    <i class="bi bi-check2-circle text-success me-1"></i>Velocity Active
                </span>
                <span class="badge bg-body-secondary text-secondary border px-2 py-1" style="font-size: 0.7rem;">
                    <i class="bi bi-geo-alt text-primary me-1"></i>Site Allocation Monitored
                </span>
                <span class="badge bg-body-secondary text-secondary border px-2 py-1" style="font-size: 0.7rem;">
                    <i class="bi bi-clock-history text-info me-1"></i>Runway Tracked
                </span>
            </div>
        </div>
    `;
};

window.polishActionsHtml = function(html) {
    if (!html) return '';
    if (html.includes('cims-directives-container')) return html;

    const liRegex = /<li[^>]*>([\s\S]*?)<\/li>/gi;
    let matches = [];
    let match;
    while ((match = liRegex.exec(html)) !== null) {
        matches.push(match[1].trim());
    }

    if (matches.length === 0) {
        const lines = html.replace(/<[^>]+>/g, '\n').split('\n');
        for (let l of lines) {
            let clean = l.trim();
            let mNum = clean.match(/^\d+\.\s*(.+)$/);
            if (mNum) matches.push(mNum[1]);
        }
    }

    if (matches.length === 0) return html;

    let out = '<div class="cims-directives-container d-flex flex-column gap-2">';
    let index = 1;

    for (let item of matches) {
        let cleanItem = item.replace(/^\d+\.\s*/, '').trim();
        let title = '';
        let desc = cleanItem;

        const titleMatch = cleanItem.match(/^(?:<strong>|<b[^>]*>|<b>)?(.*?)(?:<\/strong>|<\/b>)?:\s*([\s\S]*)$/i);
        if (titleMatch) {
            title = titleMatch[1].replace(/<[^>]+>/g, '').trim();
            desc = titleMatch[2].trim();
        }

        const lower = (title + ' ' + desc).toLowerCase();
        let badgeBg, numBg, categoryLabel;

        if (lower.includes('critical') || lower.includes('stockout') || lower.includes('fast-track') || lower.includes('urgent')) {
            badgeBg = 'bg-danger-subtle text-danger border-danger-subtle';
            numBg = 'bg-danger text-white';
            categoryLabel = 'Critical Restock';
        } else if (lower.includes('project') || lower.includes('coordination') || lower.includes('audit')) {
            badgeBg = 'bg-primary-subtle text-primary border-primary-subtle';
            numBg = 'bg-primary text-white';
            categoryLabel = 'Project Coordination';
        } else if (lower.includes('requisition') || lower.includes('warehouse') || lower.includes('approv')) {
            badgeBg = 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
            numBg = 'bg-warning text-dark';
            categoryLabel = 'Requisition Approval';
        } else {
            badgeBg = 'bg-info-subtle text-info-emphasis border-info-subtle';
            numBg = 'bg-info text-dark';
            categoryLabel = 'Operational Directive';
        }

        out += `
            <div class="card border mb-2 p-2.5 rounded-3 shadow-none cims-directive-card">
                <div class="d-flex align-items-start gap-2">
                    <span class="badge rounded-circle ${numBg} fw-bold flex-shrink-0 d-flex align-items-center justify-content-center mt-0.5" style="width: 22px; height: 22px; font-size: 0.72rem;">${index}</span>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between mb-1 gap-2 flex-wrap">
                            ${title ? `<span class="fw-bold text-dark" style="font-size: 0.84rem;">${title}</span>` : ''}
                            <span class="badge ${badgeBg} border px-2 py-0.5" style="font-size: 0.66rem;">${categoryLabel}</span>
                        </div>
                        <div class="text-secondary" style="font-size: 0.82rem; line-height: 1.55;">${desc}</div>
                    </div>
                </div>
            </div>
        `;
        index++;
    }

    out += '</div>';
    return out;
};

window.cleanOptionBTables = function(html) {
    if (!html) return '';
    let out = html.replace(/<th>Forecast Runway<\/th>/gi, '<th>Estimated Days Left</th>');

    if (/<th>Daily Burn<\/th>/i.test(out)) {
        out = out.replace(/<th>Daily Burn<\/th>/gi, '');
        out = out.replace(/(<div class="card[^>]*>[\s\S]*?Frequent Materials[\s\S]*?<tbody>)([\s\S]*?)(<\/tbody>)/gi, (cardMatch, p1, p2, p3) => {
            const newTbody = p2.replace(/<tr>([\s\S]*?)<\/tr>/gi, (trMatch, rowInner) => {
                const tds = rowInner.match(/<td\b[^>]*>[\s\S]*?<\/td>/gi);
                if (tds && tds.length === 6) {
                    tds.splice(3, 1);
                    return `<tr>\n            ${tds.join('\n            ')}\n          </tr>`;
                }
                return trMatch;
            });
            return p1 + newTbody + p3;
        });
    }

    if (/<th>Total Units Drawn<\/th>/i.test(out)) {
        out = out.replace(/<th>Total Units Drawn<\/th>/gi, '');
        out = out.replace(/(<div class="card[^>]*>[\s\S]*?Project Sites[\s\S]*?<tbody>)([\s\S]*?)(<\/tbody>)/gi, (cardMatch, p1, p2, p3) => {
            const newTbody = p2.replace(/<tr>([\s\S]*?)<\/tr>/gi, (trMatch, rowInner) => {
                const tds = rowInner.match(/<td\b[^>]*>[\s\S]*?<\/td>/gi);
                if (tds && tds.length === 6) {
                    tds.splice(2, 1);
                    return `<tr>\n            ${tds.join('\n            ')}\n          </tr>`;
                }
                return trMatch;
            });
            return p1 + newTbody + p3;
        });
    }

    return out;
};

window.parseAiAnalyticsSections = function(rawHtml) {
    if (!rawHtml) return { summary: '', actions: '', analytics: '' };

    let summary = '';
    let actions = '';
    let analytics = rawHtml;

    // 1. Try comment delimiters first
    const sumMatch = rawHtml.match(/<!--\s*===\s*EXECUTIVE_SUMMARY_START\s*===\s*-->([\s\S]*?)<!--\s*===\s*EXECUTIVE_SUMMARY_END\s*===\s*-->/i);
    if (sumMatch) summary = sumMatch[1].trim();

    const actMatch = rawHtml.match(/<!--\s*===\s*STRATEGIC_ACTIONS_START\s*===\s*-->([\s\S]*?)<!--\s*===\s*STRATEGIC_ACTIONS_END\s*===\s*-->/i);
    if (actMatch) actions = actMatch[1].trim();

    const anaMatch = rawHtml.match(/<!--\s*===\s*DETAILED_ANALYTICS_START\s*===\s*-->([\s\S]*?)<!--\s*===\s*DETAILED_ANALYTICS_END\s*===\s*-->/i);
    if (anaMatch) {
        analytics = anaMatch[1].trim();
    } else {
        analytics = analytics.replace(/<!--\s*===\s*EXECUTIVE_SUMMARY_START\s*===\s*-->[\s\S]*?<!--\s*===\s*EXECUTIVE_SUMMARY_END\s*===\s*-->/gi, '');
        analytics = analytics.replace(/<!--\s*===\s*STRATEGIC_ACTIONS_START\s*===\s*-->[\s\S]*?<!--\s*===\s*STRATEGIC_ACTIONS_END\s*===\s*-->/gi, '');
        analytics = analytics.replace(/<!--\s*===\s*DETAILED_ANALYTICS_START\s*===\s*-->/gi, '');
        analytics = analytics.replace(/<!--\s*===\s*DETAILED_ANALYTICS_END\s*===\s*-->/gi, '');
    }

    // 2. Fallback: Extract from full card structures if delimiters were omitted or for legacy stored predictions
    if (!summary) {
        const cardSum = analytics.match(/<div class="card[^>]*>[\s\S]*?Executive Operational[\s\S]*?<div class="card-body[^>]*>([\s\S]*?)<\/div>\s*<\/div>/i);
        if (cardSum) {
            summary = cardSum[1].trim();
            analytics = analytics.replace(/<div class="card[^>]*>[\s\S]*?Executive Operational[\s\S]*?<\/div>\s*<\/div>/i, '');
        }
    }

    if (!actions) {
        const cardAct = analytics.match(/<div class="card[^>]*>[\s\S]*?Strategic Action Items[\s\S]*?<div class="card-body[^>]*>([\s\S]*?)<\/div>\s*<\/div>/i);
        if (cardAct) {
            actions = cardAct[1].trim();
            analytics = analytics.replace(/<div class="card[^>]*>[\s\S]*?Strategic Action Items[\s\S]*?<\/div>\s*<\/div>/i, '');
        }
    }

    return {
        summary: window.polishSummaryHtml(summary),
        actions: window.polishActionsHtml(actions),
        analytics: window.cleanOptionBTables(analytics.trim())
    };
};

document.addEventListener("DOMContentLoaded", () => {
    const aiOutput = document.getElementById('aiOutput');
    const sumEl = document.getElementById('topExecutiveSummaryBox');
    const actEl = document.getElementById('topStrategicActionsBox');
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

    // Ensure interactive buttons & polished layout on DB loaded content
    if (sumEl) sumEl.innerHTML = window.formatInteractiveEntities(window.polishSummaryHtml(sumEl.innerHTML));
    if (actEl) actEl.innerHTML = window.formatInteractiveEntities(window.polishActionsHtml(actEl.innerHTML));
    if (aiOutput) aiOutput.innerHTML = window.formatInteractiveEntities(window.cleanOptionBTables(aiOutput.innerHTML));

    // Check if the database loaded a prediction (not the default placeholder icon)
    const hasDbPrediction = aiOutput.querySelector('.bi-cpu') === null;

    if (!hasDbPrediction) {
        // Fallback to localStorage if database had no prediction (role-aware)
        const roleKey = window.currentUserRole || 'admin';
        const savedPrediction = localStorage.getItem('gb_ai_prediction_' + roleKey) || localStorage.getItem('gb_ai_prediction');
        const savedTime = localStorage.getItem('gb_ai_timestamp_' + roleKey) || localStorage.getItem('gb_ai_timestamp');
        if (savedPrediction && savedTime) {
            const parsed = window.parseAiAnalyticsSections(savedPrediction);
            if (sumEl && parsed.summary) sumEl.innerHTML = window.formatInteractiveEntities(parsed.summary);
            if (actEl && parsed.actions) actEl.innerHTML = window.formatInteractiveEntities(parsed.actions);
            aiOutput.innerHTML = window.formatInteractiveEntities(parsed.analytics || savedPrediction);
            const date = new Date(parseInt(savedTime));
            if (updatedTextEl) {
                updatedTextEl.innerText = "Last Updated: " + formatDateTime(date);
            }
        }
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
    const sumEl = document.getElementById('topExecutiveSummaryBox');
    const actEl = document.getElementById('topStrategicActionsBox');

    if (!loading || !output) return;

    // 1. Immediate visual feedback: Animated skeleton loaders in all 3 containers
    loading.style.setProperty('display', 'flex', 'important');
    if (sumEl) {
        sumEl.innerHTML = `
            <div class="cims-skeleton-shimmer p-1">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom placeholder-glow">
                    <span class="placeholder col-4 rounded-pill py-2"></span>
                    <span class="placeholder col-3 rounded-pill py-2"></span>
                </div>
                <div class="p-3 rounded-3 bg-light border placeholder-glow mb-2">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="spinner-border spinner-border-sm text-primary flex-shrink-0" role="status"></div>
                        <span class="small fw-bold text-primary">Synthesizing Executive Operational Briefing...</span>
                    </div>
                    <span class="placeholder col-12 mb-2 d-block"></span>
                    <span class="placeholder col-10 mb-2 d-block"></span>
                    <span class="placeholder col-8 d-block"></span>
                </div>
                <div class="placeholder-glow d-flex gap-1">
                    <span class="placeholder col-3 rounded-pill py-1"></span>
                    <span class="placeholder col-4 rounded-pill py-1"></span>
                </div>
            </div>
        `;
    }
    if (actEl) {
        actEl.innerHTML = `
            <div class="cims-skeleton-shimmer p-1">
                <div class="card border mb-2 p-2 rounded-3 placeholder-glow">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="spinner-border spinner-border-sm text-danger flex-shrink-0" role="status"></div>
                        <span class="small fw-bold text-danger">Generating Strategic Directives...</span>
                    </div>
                    <span class="placeholder col-11 mb-1 d-block"></span>
                    <span class="placeholder col-8 d-block"></span>
                </div>
                <div class="card border mb-2 p-2 rounded-3 placeholder-glow">
                    <span class="placeholder col-5 mb-1 d-block"></span>
                    <span class="placeholder col-10 d-block"></span>
                </div>
                <div class="card border mb-2 p-2 rounded-3 placeholder-glow">
                    <span class="placeholder col-6 mb-1 d-block"></span>
                    <span class="placeholder col-9 d-block"></span>
                </div>
            </div>
        `;
    }

    if (isManualClick && btn) { 
        btn.disabled = true; 
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Analyzing Operations...'; 
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch('analytics.php?action=generate_ai_report', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        });

        const data = await response.json();
        const isSuccess = (data.success === true || data.status === 'success') && data.prediction;
        
        if (response.ok && isSuccess) {
            let aiText = data.prediction || '';
            aiText = aiText.replace(/^```(?:html)?\s*/i, '').replace(/\s*```$/i, '');
            const parsed = window.parseAiAnalyticsSections(aiText);

            const rawSummary = data.summary || parsed.summary || '';
            const rawActions = data.actions || parsed.actions || '';
            const rawAnalytics = data.analytics || parsed.analytics || aiText;

            if (sumEl && rawSummary) {
                sumEl.innerHTML = window.formatInteractiveEntities(window.polishSummaryHtml(rawSummary));
                sumEl.classList.remove('cims-fade-in');
                void sumEl.offsetWidth; // Trigger reflow for clean re-animation
                sumEl.classList.add('cims-fade-in');
            }
            if (actEl && rawActions) {
                actEl.innerHTML = window.formatInteractiveEntities(window.polishActionsHtml(rawActions));
                actEl.classList.remove('cims-fade-in');
                void actEl.offsetWidth;
                actEl.classList.add('cims-fade-in');
            }
            output.innerHTML = window.formatInteractiveEntities(rawAnalytics);
            output.classList.remove('cims-fade-in');
            void output.offsetWidth;
            output.classList.add('cims-fade-in');

            const roleKey = window.currentUserRole || 'admin';
            try {
                localStorage.setItem('gb_ai_prediction_' + roleKey, aiText);
                localStorage.setItem('gb_ai_timestamp_' + roleKey, data.timestamp);
                localStorage.setItem('gb_ai_prediction', aiText);
                localStorage.setItem('gb_ai_timestamp', data.timestamp);
            } catch (e) {}
            
            let updatedTextEl = document.getElementById('lastUpdatedText');
            if (updatedTextEl && data.timestamp) {
                updatedTextEl.setAttribute('data-timestamp', data.timestamp);
                updatedTextEl.innerHTML = '<span class="badge bg-success-subtle text-success me-1 border border-success-subtle">Updated</span> Last Updated: ' + formatDateTime(new Date(data.timestamp));
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Operations Intelligence Analyzed',
                    text: 'Executive forecast and strategic action items refreshed.',
                    showConfirmButton: false,
                    timer: 3500,
                    timerProgressBar: true
                });
            }
        } else {
            const errMessage = data.message || data.error || 'Failed to generate operational intelligence report.';
            output.innerHTML = `<div class='alert alert-danger border-0 shadow-sm d-flex align-items-center mb-0'><i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i><div><strong>AI Analysis Notice:</strong> ${errMessage}</div></div>`;
            if (sumEl) {
                sumEl.innerHTML = `<div class="alert alert-warning border-0 small mb-0 py-2"><i class="bi bi-exclamation-triangle me-1"></i>Could not refresh Executive Summary.</div>`;
            }
            if (actEl) {
                actEl.innerHTML = `<div class="alert alert-warning border-0 small mb-0 py-2"><i class="bi bi-exclamation-triangle me-1"></i>Could not refresh Strategic Actions.</div>`;
            }
        }
    } catch (error) {
        console.error("AI Error:", error);
        output.innerHTML = `<div class='alert alert-danger border-0 shadow-sm d-flex align-items-center mb-0'><i class="bi bi-wifi-off fs-5 me-2 flex-shrink-0"></i><div><strong>Connection Error:</strong> Could not reach backend AI analytics service. Please verify your connection.</div></div>`;
        if (sumEl) {
            sumEl.innerHTML = `<div class="alert alert-danger border-0 small mb-0 py-2"><i class="bi bi-wifi-off me-1"></i>Connection error.</div>`;
        }
        if (actEl) {
            actEl.innerHTML = `<div class="alert alert-danger border-0 small mb-0 py-2"><i class="bi bi-wifi-off me-1"></i>Connection error.</div>`;
        }
    } finally {
        loading.style.setProperty('display', 'none', 'important');
        if (isManualClick && btn) { 
            btn.disabled = false; 
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Analyze Operations Now'; 
        }
        startTimer();
    }
};

// =========================================================
// 2. DASHBOARD CHART ENGINE (MIXED ANIMATIONS)
// =========================================================

window.lastChartState = { pct: "", pie: "", days: "" };

window.buildTheCharts = function() {
    var pctCtx = document.getElementById('pctConsumptionChart');
    var daysCtx = document.getElementById('newDaysLeftChart');
    var pieCtx = document.getElementById('overallStockPieChart'); 

    if (!pctCtx && !daysCtx && !pieCtx) return;

    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    if (window.Chart) {
        Chart.defaults.color = isDark ? '#adbac7' : '#666666';
        Chart.defaults.borderColor = isDark ? '#30363d' : '#e0e0e0';
    }
    const gridColor = isDark ? '#21262d' : '#f0f0f0';
    const pieBorder = isDark ? '#161b22' : '#ffffff';

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
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, max: 100, border: { dash: [4, 4] }, grid: { color: gridColor }, ticks: { callback: function(value) { return value + '%'; } } } }
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
                    borderWidth: 2, borderColor: pieBorder, hoverOffset: 6 
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
                scales: { y: { grid: { display: false } }, x: { beginAtZero: true, border: { dash: [4, 4] }, grid: { color: gridColor } } }
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