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
            let formData = new FormData();
            formData.append('action', 'fetch_rs_data');
            formData.append('rs_no', decodedText);

            fetch('process/process.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    // Close Scanner Modal
                    bootstrap.Modal.getInstance(document.getElementById('rsScannerModal')).hide();
                    
                    // PRE-FILL THE WITHDRAWAL FORM!
                    document.getElementById('wdProjectName').value = data.project_name;
                    document.getElementById('wdRemarks').value = "Auto-filled via QR Scanner for " + data.rs_no;
                    
                    // Defensively create/get hidden rs_no input to bypass any HTML caching
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
                    
                    const container = document.getElementById('wdMaterialsContainer');
                    const templateRow = container.querySelector('.wd-material-row').cloneNode(true);
                    container.innerHTML = ''; // Clear container
                    
                    data.items.forEach((item, index) => {
                        let newRow = templateRow.cloneNode(true);
                        
                        // Select the correct item in the dropdown
                        let select = newRow.querySelector('.wd-item-select');
                        let optionFound = false;
                        for (let i = 0; i < select.options.length; i++) {
                            if (select.options[i].value === item.item_code) {
                                select.selectedIndex = i;
                                optionFound = true; break;
                            }
                        }
                        
                        if(!optionFound) {
                            // If item is out of stock, create a disabled warning option
                            let opt = document.createElement('option');
                            opt.value = item.item_code;
                            opt.text = `[${item.item_code}] OUT OF STOCK`;
                            select.add(opt); select.value = item.item_code;
                            newRow.classList.add('border-danger');
                        }

                        newRow.querySelector('.wd-qty-input').value = item.quantity;
                        newRow.querySelector('.remove-wd-row').disabled = false;
                        container.appendChild(newRow);
                    });
                    
                    window.updateWdDeleteButtons(container);
                    
                    // Show the Pre-filled Withdrawal Modal!
                    setTimeout(() => {
                        new bootstrap.Modal(document.getElementById('withdrawModal')).show();
                    }, 500);

                } else {
                    document.getElementById('rsScannerResult').innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i> ${data.message}</span>`;
                }
            }).catch(err => {
                document.getElementById('rsScannerResult').innerHTML = `<span class="text-danger fw-bold">Network Error.</span>`;
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

if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", initWithdrawalsPage); } else { initWithdrawalsPage(); }