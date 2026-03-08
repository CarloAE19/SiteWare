/* ==========================================================
 * GB INVENTORY - REQUISITIONS (RS) JAVASCRIPT
 * ========================================================== */

// 1. SPA-Safe Modal Trigger (View Details & Check Stock Validation)
// FIXED: Accepts Base64 encoded items to prevent syntax errors
window.viewRsDetails = function(rsNo, project, remarks, status, requestor, date, itemsB64) {
    document.getElementById('viewRsNo').innerText = rsNo;
    document.getElementById('viewRsProject').innerText = project;
    
    // FIXED: Properly display line breaks in remarks!
    const remarksEl = document.getElementById('viewRsRemarks');
    remarksEl.innerText = remarks ? remarks : 'No remarks provided.';
    remarksEl.style.whiteSpace = 'pre-wrap'; 
    
    document.getElementById('viewRsRequestor').innerText = requestor;
    document.getElementById('viewRsDate').innerText = date;
    
    const qrContainer = document.getElementById('rsQrContainer');
    const printBtn = document.getElementById('printRsBtn');
    
    if (status === 'Approved' || status === 'PO Created') {
        const qrData = encodeURIComponent(`REQ-DATA:${rsNo}`);
        document.getElementById('viewRsQrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${qrData}`;
        qrContainer.classList.remove('d-none');
        printBtn.classList.remove('d-none');
    } else {
        qrContainer.classList.add('d-none');
        printBtn.classList.add('d-none');
    }
    
    const tbody = document.getElementById('viewRsItemsBody');
    tbody.innerHTML = ''; 
    
    try {
        // FIXED: Safely Decode Base64 string into usable JSON!
        const itemsJson = atob(itemsB64);
        const items = JSON.parse(itemsJson);
        
        if (items.length > 0) {
            items.forEach(item => {
                const itemName = item.item_name ? item.item_name : '<span class="text-danger">Item deleted</span>';
                const unit = item.unit ? item.unit : '';
                const reqQty = parseInt(item.quantity);
                const curStock = parseInt(item.current_stock) || 0;
                
                let stockDisplay = '';
                if (curStock < reqQty) {
                    stockDisplay = `<span class="badge bg-danger fs-6 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i>${curStock} (Short)</span>`;
                } else {
                    stockDisplay = `<span class="badge bg-success fs-6 shadow-sm">${curStock}</span>`;
                }
                
                tbody.innerHTML += `
                    <tr>
                        <td class="text-muted small align-middle">${item.item_code}</td>
                        <td class="fw-bold align-middle">${itemName}</td>
                        <td class="text-dark fw-bold text-center align-middle fs-5">${reqQty} <span class="fs-6 fw-normal">${unit}</span></td>
                        <td class="text-center align-middle d-print-none">${stockDisplay}</td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">No items found.</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-3">Error loading items.</td></tr>`;
    }
    
    new bootstrap.Modal(document.getElementById('viewRsModal')).show();
}

// 2. Open Reject Reason Modal
window.openRejectModal = function(id, rsNo) {
    document.getElementById('rejectRsId').value = id;
    document.getElementById('rejectRsNoDisplay').innerText = rsNo;
    new bootstrap.Modal(document.getElementById('rejectRsModal')).show();
}

// 3. RS Document Print Function
window.printRSDocument = function() {
    const printContent = document.getElementById('rsPrintArea').innerHTML;
    const originalContent = document.body.innerHTML;
    document.body.innerHTML = `<div style="padding: 40px; background: white;">${printContent}</div>`;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload(); 
}

// 4. Dynamic Row Addition
if (!window.rsGlobalListenersAttached) {
    window.rsGlobalListenersAttached = true;
    document.body.addEventListener('click', function(e) {
        const container = document.getElementById('materialsContainer');
        if (!container) return;

        if (e.target.closest('#addMaterialBtn')) {
            const firstRow = container.querySelector('.material-row');
            const newRow = firstRow.cloneNode(true);
            newRow.querySelector('select').value = '';
            newRow.querySelector('input[type="number"]').value = '';
            newRow.querySelector('.remove-row').disabled = false;
            container.appendChild(newRow);
            window.updateDeleteButtons(container);
        }

        if (e.target.closest('.remove-row')) {
            const rowToRemove = e.target.closest('.material-row');
            if (container.querySelectorAll('.material-row').length > 1) {
                rowToRemove.remove();
                window.updateDeleteButtons(container);
            }
        }
    });
}

window.updateDeleteButtons = function(container) {
    const rows = container.querySelectorAll('.material-row');
    if (rows.length === 1) {
        rows[0].querySelector('.remove-row').disabled = true;
    } else {
        rows.forEach(row => row.querySelector('.remove-row').disabled = false);
    }
}

// 5. Pagination & Search
function initializeRequisitionsPage() {
    const searchInput = document.getElementById('searchRs');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.rs-row');
            rows.forEach(row => {
                const no = row.querySelector('.rs-no').textContent.toLowerCase();
                const proj = row.querySelector('.rs-project').textContent.toLowerCase();
                row.style.display = (no.includes(term) || proj.includes(term)) ? '' : 'none';
            });
        });
    }

    const table = document.getElementById('rsTable');
    if (!table || table.parentElement.querySelector('.pagination-wrapper')) return;

    const rowsPerPage = 10;
    const rows = Array.from(table.querySelectorAll('tbody .rs-row'));
    if (rows.length <= rowsPerPage) return;

    let currentPage = 1;
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    const paginationWrapper = document.createElement('div');
    paginationWrapper.className = 'd-flex justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper';
    
    const infoText = document.createElement('span'); infoText.className = 'text-muted small fw-bold';
    const btnGroup = document.createElement('div'); btnGroup.className = 'btn-group shadow-sm';

    const prevBtn = document.createElement('button'); prevBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3'; prevBtn.innerHTML = '<i class="bi bi-chevron-left me-1"></i> Prev';
    const pageIndicator = document.createElement('button'); pageIndicator.className = 'btn btn-sm btn-brand fw-bold px-3 pe-none';
    const nextBtn = document.createElement('button'); nextBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3'; nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right ms-1"></i>';

    btnGroup.append(prevBtn, pageIndicator, nextBtn);
    paginationWrapper.append(infoText, btnGroup);
    table.parentElement.appendChild(paginationWrapper);

    function showPage(page) {
        currentPage = page;
        const start = (page - 1) * rowsPerPage; const end = start + rowsPerPage;
        rows.forEach((row, index) => { row.style.display = (index >= start && index < end) ? '' : 'none'; });
        infoText.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, rows.length)}</b> of <b>${rows.length}</b>`;
        pageIndicator.innerText = `Page ${page} / ${totalPages}`;
        prevBtn.disabled = page === 1; nextBtn.disabled = page === totalPages;
    }

    prevBtn.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
    nextBtn.addEventListener('click', () => { if (currentPage < totalPages) showPage(currentPage + 1); });
    showPage(1);
}

if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", initializeRequisitionsPage); } else { initializeRequisitionsPage(); }