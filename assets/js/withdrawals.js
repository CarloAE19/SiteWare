/* ==========================================================
 * GB INVENTORY - WITHDRAWALS JAVASCRIPT & RS SCANNER
 * ========================================================== */

// 1. VIEW WITHDRAWAL DETAILS MODAL
window.viewWdDetails = function(wdNo, project, remarks, itemsJson) {
    document.getElementById('viewWdNo').innerText = wdNo;
    document.getElementById('viewWdProject').innerText = project;
    document.getElementById('viewWdRemarks').innerText = remarks ? remarks : 'No remarks.';
    
    const qrData = encodeURIComponent(`Slip: ${wdNo} | Proj: ${project}`);
    document.getElementById('viewWdQrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${qrData}`;
    
    const tbody = document.getElementById('viewWdItemsBody');
    tbody.innerHTML = ''; 
    try {
        const items = JSON.parse(itemsJson);
        items.forEach(item => { 
            tbody.innerHTML += `
                <tr>
                    <td class="text-muted small align-middle">${item.item_code}</td>
                    <td class="fw-bold align-middle">${item.item_name}</td>
                    <td class="text-danger fw-bold text-end align-middle">-${item.quantity} ${item.unit}</td>
                </tr>`; 
        });
    } catch (e) { tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-3">Error loading items.</td></tr>`; }
    
    new bootstrap.Modal(document.getElementById('viewWdModal')).show();
}

// 2. DYNAMIC ROWS FOR MANUAL WITHDRAWALS
if (!window.wdGlobalListenersAttached) {
    window.wdGlobalListenersAttached = true;
    
    document.body.addEventListener('click', function(e) {
        const container = document.getElementById('wdMaterialsContainer');
        if (!container) return;

        // Add Row
        if (e.target.closest('#addWdMaterialBtn')) {
            const firstRow = container.querySelector('.wd-material-row');
            const newRow = firstRow.cloneNode(true);
            newRow.querySelector('select').value = '';
            newRow.querySelector('input[type="number"]').value = '';
            newRow.querySelector('.remove-wd-row').disabled = false;
            container.appendChild(newRow);
            window.updateWdDeleteButtons(container);
        }

        // Remove Row
        if (e.target.closest('.remove-wd-row')) {
            const rowToRemove = e.target.closest('.wd-material-row');
            if (container.querySelectorAll('.wd-material-row').length > 1) {
                rowToRemove.remove();
                window.updateWdDeleteButtons(container);
            }
        }
    });
}

window.updateWdDeleteButtons = function(container) {
    const rows = container.querySelectorAll('.wd-material-row');
    if (rows.length === 1) {
        rows[0].querySelector('.remove-wd-row').disabled = true;
    } else {
        rows.forEach(row => row.querySelector('.remove-wd-row').disabled = false);
    }
}

// 3. THE MAGIC RS SCANNER ENGINE
window.html5RsScanner = window.html5RsScanner || null;

window.startRsScanner = function() {
    const modal = new bootstrap.Modal(document.getElementById('rsScannerModal'));
    modal.show();

    document.getElementById('rsReader').style.display = 'block';
    document.getElementById('rsScannerResult').innerHTML = "Point your camera at the Approved RS Document QR Code...";

    if (!window.html5RsScanner) {
        window.html5RsScanner = new Html5QrcodeScanner("rsReader", { fps: 10, qrbox: {width: 250, height: 250} }, false);
    }

    window.html5RsScanner.render((decodedText) => {
        // Only accept valid RS QR Codes
        if(decodedText.startsWith("REQ-DATA:")) {
            new Audio('assets/sounds/scan.mp3').play().catch(e => {});
            
            // Stop Camera
            window.html5RsScanner.clear().then(() => { window.html5RsScanner = null; }).catch(e=>{});
            document.getElementById('rsReader').style.display = 'none';
            document.getElementById('rsScannerResult').innerHTML = `<span class="spinner-border spinner-border-sm me-2 text-primary"></span>Fetching RS Data from Server...`;

            // Fetch data from backend via AJAX
            window.loadRsDataToWithdrawalForm(decodedText, function(success, msg) {
                if (success) {
                    bootstrap.Modal.getInstance(document.getElementById('rsScannerModal')).hide();
                    setTimeout(() => {
                        new bootstrap.Modal(document.getElementById('withdrawModal')).show();
                    }, 500);
                } else {
                    document.getElementById('rsScannerResult').innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i> ${msg}</span>`;
                }
            });
        } else {
            document.getElementById('rsScannerResult').innerHTML = `<span class="text-danger fw-bold">Invalid QR Code format. Scan an RS Document.</span>`;
        }
    }, (error) => {});
}

window.stopRsScanner = function() {
    if (window.html5RsScanner) {
        window.html5RsScanner.clear().then(() => { window.html5RsScanner = null; }).catch(e=>{});
    }
}

// Reset form and container on modal hidden (SPA Router Safe)
function setupWithdrawalModalListeners() {
    const withdrawModalEl = document.getElementById('withdrawModal');
    if (withdrawModalEl) {
        if (withdrawModalEl.dataset.listenerAttached) return; // avoid duplicate binding
        withdrawModalEl.dataset.listenerAttached = "true";

        const container = document.getElementById('wdMaterialsContainer');
        let wdRowTemplate = null;
        if (container) {
            const firstRow = container.querySelector('.wd-material-row');
            if (firstRow) {
                wdRowTemplate = firstRow.cloneNode(true);
                wdRowTemplate.querySelector('select').value = '';
                wdRowTemplate.querySelector('input[type="number"]').value = '';
                wdRowTemplate.querySelector('.remove-wd-row').disabled = true;
            }
        }

        withdrawModalEl.addEventListener('hidden.bs.modal', function () {
            const form = document.getElementById('withdrawalForm');
            if (form) form.reset();
            const rsNoField = document.getElementById('wdRsNo');
            if (rsNoField) rsNoField.value = '';
            
            // Restore default template row in materials container
            if (container && wdRowTemplate) {
                container.innerHTML = '';
                container.appendChild(wdRowTemplate.cloneNode(true));
                window.updateWdDeleteButtons(container);
            }
        });
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setupWithdrawalModalListeners);
} else {
    setupWithdrawalModalListeners();
}

// 4. INITIALIZE PAGE
function initWithdrawalsPage() {
    // Real-time Search
    document.getElementById('searchWithdrawals')?.addEventListener('keyup', function(e) {
        const term = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('.withdrawal-row');
        rows.forEach(row => {
            const slip = row.querySelector('.wd-slip').textContent.toLowerCase();
            const proj = row.querySelector('.wd-project').textContent.toLowerCase();
            row.style.display = (slip.includes(term) || proj.includes(term)) ? '' : 'none';
        });
    });

    // Pagination
    const table = document.getElementById('withdrawalsTable');
    if (!table) return;
    if (table.parentElement.querySelector('.pagination-wrapper')) return;

    const rowsPerPage = 10;
    const rows = Array.from(table.querySelectorAll('tbody .withdrawal-row'));
    if (rows.length <= rowsPerPage) return;

    let currentPage = 1;
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper';
    
    const info = document.createElement('span'); info.className = 'text-muted small fw-bold';
    const btnGroup = document.createElement('div'); btnGroup.className = 'btn-group shadow-sm';

    const prev = document.createElement('button'); prev.className = 'btn btn-sm btn-outline-primary fw-bold px-3'; prev.innerHTML = 'Prev';
    const indicator = document.createElement('button'); indicator.className = 'btn btn-sm btn-brand fw-bold px-3 pe-none';
    const next = document.createElement('button'); next.className = 'btn btn-sm btn-outline-primary fw-bold px-3'; next.innerHTML = 'Next';

    btnGroup.append(prev, indicator, next);
    wrapper.append(info, btnGroup);
    table.parentElement.appendChild(wrapper);

    function showPage(page) {
        currentPage = page;
        const start = (page - 1) * rowsPerPage; const end = start + rowsPerPage;
        rows.forEach((row, index) => { row.style.display = (index >= start && index < end) ? '' : 'none'; });
        info.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, rows.length)}</b>`;
        indicator.innerText = `Page ${page} / ${totalPages}`;
        prev.disabled = page === 1; next.disabled = page === totalPages;
    }

    prev.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
    next.addEventListener('click', () => { if (currentPage < totalPages) showPage(currentPage + 1); });
    showPage(1);

    // Check URL parameters for shortcut auto-open modals
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'new') {
        const withdrawModalEl = document.getElementById('withdrawModal');
        if (withdrawModalEl) {
            new bootstrap.Modal(withdrawModalEl).show();
        }
    }
}

// Global Manual RS Lookup Helper
window.loadRsDataToWithdrawalForm = function(rsNo, callback) {
    const feedbackEl = document.getElementById('manualRsStatusFeedback');
    if (feedbackEl) {
        feedbackEl.classList.remove('d-none', 'text-danger', 'text-success');
        feedbackEl.classList.add('text-muted');
        feedbackEl.innerHTML = `<span class="spinner-border spinner-border-sm me-2 text-primary"></span>Fetching RS ${rsNo}...`;
    }

    let formData = new FormData();
    formData.append('action', 'fetch_rs_data');
    formData.append('rs_no', rsNo);

    fetch('process/process.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('wdProjectName').value = data.project_name;
            if (data.rs_status === 'Staged (Ready for Pickup)') {
                document.getElementById('wdRemarks').value = "Pre-picked & Staged Express Pickup for " + data.rs_no;
            } else {
                document.getElementById('wdRemarks').value = "Auto-filled via RS Lookup for " + data.rs_no;
            }
            
            let rsNoField = document.getElementById('wdRsNo');
            if (!rsNoField) {
                rsNoField = document.createElement('input');
                rsNoField.type = 'hidden';
                rsNoField.name = 'rs_no';
                rsNoField.id = 'wdRsNo';
                const form = document.getElementById('withdrawalForm');
                if (form) form.appendChild(rsNoField);
            }
            if (rsNoField) rsNoField.value = data.rs_no;
            
            const projInput = document.getElementById('wdProjectName');
            if (projInput) projInput.value = data.project_name;
            const projDisplay = document.getElementById('wdProjectNameDisplay');
            if (projDisplay) projDisplay.value = data.project_name;

            const container = document.getElementById('wdMaterialsContainer');
            container.innerHTML = '';
            
            data.items.forEach((item) => {
                const row = document.createElement('div');
                row.className = 'row g-2 wd-material-row mb-2 align-items-center bg-white p-2 rounded border shadow-sm mx-0';
                row.innerHTML = `
                    <div class="col-md-8">
                        <input type="text" class="form-control fw-bold text-dark bg-white" value="[${item.item_code}] ${item.item_name}" readonly>
                        <input type="hidden" name="items[]" value="${item.item_code}">
                    </div>
                    <div class="col-md-4 mt-2 mt-md-0">
                        <input type="number" class="form-control fw-bold text-center text-danger bg-white" name="quantities[]" value="${item.quantity}" readonly>
                    </div>
                `;
                container.appendChild(row);
            });
            
            const addBtn = document.getElementById('addWdMaterialBtn');
            if (addBtn) addBtn.style.display = 'none';

            if (feedbackEl) {
                feedbackEl.classList.remove('text-muted', 'text-danger');
                feedbackEl.classList.add('text-success');
                feedbackEl.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Loaded ${data.rs_no} (${data.rs_status}) successfully! ${data.items.length} item(s) auto-filled.`;
            }

            if (callback) callback(true, 'Success');
        } else {
            if (feedbackEl) {
                feedbackEl.classList.remove('text-muted', 'text-success');
                feedbackEl.classList.add('text-danger');
                feedbackEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> ${data.message || 'RS not found or invalid.'}`;
            }

            if (callback) callback(false, data.message || 'RS not found or invalid.');
        }
    })
    .catch(err => {
        if (feedbackEl) {
            feedbackEl.classList.remove('text-muted', 'text-success');
            feedbackEl.classList.add('text-danger');
            feedbackEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> Server error fetching RS data.`;
        }
        if (callback) callback(false, 'Server error fetching RS data.');
    });
};

window.lookupManualRsInput = function() {
    const inputVal = document.getElementById('manualRsInputText')?.value.trim();
    if (!inputVal) {
        alert("Please enter or select an RS Number.");
        return;
    }
    window.loadRsDataToWithdrawalForm(inputVal);
};

// Modal Reset Handler on Close
document.addEventListener('DOMContentLoaded', function() {
    const withdrawModalEl = document.getElementById('withdrawModal');
    if (withdrawModalEl) {
        withdrawModalEl.addEventListener('hidden.bs.modal', function () {
            const projInput = document.getElementById('wdProjectName');
            if (projInput) projInput.value = '';
            const projDisplay = document.getElementById('wdProjectNameDisplay');
            if (projDisplay) projDisplay.value = '';
            const container = document.getElementById('wdMaterialsContainer');
            if (container) {
                container.innerHTML = `
                    <div class="text-center text-muted py-3 fw-bold" id="emptyWdPrompt">
                        <i class="bi bi-info-circle me-1"></i> Type an approved RS Number above to load release items.
                    </div>
                `;
            }
            const feedbackEl = document.getElementById('manualRsStatusFeedback');
            if (feedbackEl) feedbackEl.classList.add('d-none');
            const rsInput = document.getElementById('manualRsInputText');
            if (rsInput) rsInput.value = '';
        });
    }
});

if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", initWithdrawalsPage); } else { initWithdrawalsPage(); }