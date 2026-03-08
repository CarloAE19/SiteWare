/* ==========================================================
 * GB INVENTORY - REQUISITIONS (RS) JAVASCRIPT
 * ========================================================== */

// 1. SPA-Safe Modal Trigger 
window.viewRsDetails = function(rsNo, project, remarks, status, requestor, date, itemsB64) {
    document.getElementById('viewRsNo').innerText = rsNo;
    document.getElementById('viewRsProject').innerText = project;
    
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

window.openRejectModal = function(id, rsNo) {
    document.getElementById('rejectRsId').value = id;
    document.getElementById('rejectRsNoDisplay').innerText = rsNo;
    new bootstrap.Modal(document.getElementById('rejectRsModal')).show();
}

window.printRSDocument = function() {
    const printContent = document.getElementById('rsPrintArea').innerHTML;
    const originalContent = document.body.innerHTML;
    document.body.innerHTML = `<div style="padding: 40px; background: white;">${printContent}</div>`;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload(); 
}

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

// 5. COLUMN TOGGLE ENGINE & PAGINATION
function initializeRequisitionsPage() {
    const searchInput = document.getElementById('searchRs');
    const table = document.getElementById('rsTable');
    
    // --- SHOW / HIDE COLUMNS LOGIC ---
    const columnToggles = document.querySelectorAll('.col-toggle');
    columnToggles.forEach(toggle => {
        // Init toggle
        toggle.addEventListener('change', function() {
            const colClass = this.value; // e.g., 'col-project'
            const elements = document.querySelectorAll('.' + colClass);
            elements.forEach(el => {
                if (this.checked) {
                    el.classList.remove('d-none');
                } else {
                    el.classList.add('d-none');
                }
            });
        });
    });

    // --- SEARCH & PAGINATION LOGIC ---
    if (!table || table.parentElement.querySelector('.pagination-wrapper')) return;

    const allRows = Array.from(table.querySelectorAll('tbody .rs-row'));
    let filteredRows = [...allRows];
    const rowsPerPage = 10;
    let currentPage = 1;

    const paginationWrapper = document.createElement('div');
    paginationWrapper.className = 'd-flex justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper';
    
    const infoText = document.createElement('span'); 
    infoText.className = 'text-muted small fw-bold';
    
    const btnGroup = document.createElement('div'); 
    btnGroup.className = 'btn-group shadow-sm';

    const prevBtn = document.createElement('button'); 
    prevBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3'; 
    prevBtn.innerHTML = '<i class="bi bi-chevron-left me-1"></i> Prev';
    
    const pageIndicator = document.createElement('button'); 
    pageIndicator.className = 'btn btn-sm btn-brand fw-bold px-3 pe-none';
    
    const nextBtn = document.createElement('button'); 
    nextBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3'; 
    nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right ms-1"></i>';

    btnGroup.append(prevBtn, pageIndicator, nextBtn);
    paginationWrapper.append(infoText, btnGroup);
    table.parentElement.appendChild(paginationWrapper);

    function updatePagination() {
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * rowsPerPage; 
        const end = start + rowsPerPage;

        allRows.forEach(row => row.style.display = 'none');
        const rowsToShow = filteredRows.slice(start, end);
        rowsToShow.forEach(row => row.style.display = '');

        const showingEnd = Math.min(end, filteredRows.length);
        const showingStart = filteredRows.length > 0 ? start + 1 : 0;

        infoText.innerHTML = `Showing <b>${showingStart}</b> to <b>${showingEnd}</b> of <b>${filteredRows.length}</b>`;
        pageIndicator.innerText = `Page ${currentPage} / ${totalPages}`;
        
        prevBtn.disabled = currentPage === 1; 
        nextBtn.disabled = currentPage === totalPages || totalPages === 0;
    }

    function filterData() {
        const term = searchInput ? searchInput.value.toLowerCase() : '';

        filteredRows = allRows.filter(row => {
            const no = row.querySelector('.rs-no').textContent.toLowerCase();
            const proj = row.querySelector('.rs-project').textContent.toLowerCase();
            return no.includes(term) || proj.includes(term);
        });

        currentPage = 1;
        updatePagination();
    }

    if (searchInput) searchInput.addEventListener('input', filterData);

    prevBtn.addEventListener('click', () => { if (currentPage > 1) { currentPage--; updatePagination(); } });
    nextBtn.addEventListener('click', () => { const totalPages = Math.ceil(filteredRows.length / rowsPerPage); if (currentPage < totalPages) { currentPage++; updatePagination(); } });

    updatePagination();
}

if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", initializeRequisitionsPage); } else { initializeRequisitionsPage(); }