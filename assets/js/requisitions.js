/* ==========================================================
 * GB INVENTORY - REQUISITIONS (RS) JAVASCRIPT
 * ========================================================== */

// 1. SPA-Safe Modal Trigger 
window.viewRsDetails = function(rsNo, project, remarks, status, requestor, date, itemsB64, type = 'project') {
    document.getElementById('viewRsNo').innerText = rsNo;
    document.getElementById('viewRsProject').innerText = project;
    
    const statusEl = document.getElementById('viewRsStatus');
    if (statusEl) {
        statusEl.innerText = status;
        statusEl.className = 'badge shadow-sm';
        if (status === 'Pending Approval') {
            statusEl.classList.add('bg-warning', 'text-dark');
        } else if (status === 'Approved') {
            statusEl.classList.add('bg-success');
        } else if (status === 'Partially Approved') {
            statusEl.classList.add('bg-warning', 'text-dark');
        } else if (status === 'Staged (Ready for Pickup)') {
            statusEl.classList.add('bg-info', 'text-dark');
        } else if (status === 'Rejected') {
            statusEl.classList.add('bg-danger');
        } else if (status === 'PO Created') {
            statusEl.classList.add('bg-info', 'text-dark');
        } else if (status === 'Released') {
            statusEl.classList.add('bg-success');
        } else {
            statusEl.classList.add('bg-secondary');
        }
    }
    
    const remarksEl = document.getElementById('viewRsRemarks');
    remarksEl.innerText = remarks ? remarks : 'No remarks provided.';
    remarksEl.style.whiteSpace = 'pre-wrap'; 
    
    document.getElementById('viewRsRequestor').innerText = requestor;
    document.getElementById('viewRsDate').innerText = date;
    
    const qrContainer = document.getElementById('rsQrContainer');
    const printBtn = document.getElementById('printRsBtn');
    
    if ((status === 'Approved' || status === 'PO Created' || status === 'Staged (Ready for Pickup)') && type !== 'restock') {
        const qrData = encodeURIComponent(`REQ-DATA:${rsNo}`);
        document.getElementById('viewRsQrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${qrData}`;
        qrContainer.classList.remove('d-none');
        printBtn.classList.remove('d-none');
    } else {
        qrContainer.classList.add('d-none');
        if (status === 'Approved' || status === 'Partially Approved' || status === 'PO Created' || status === 'Staged (Ready for Pickup)') {
            printBtn.classList.remove('d-none');
        } else {
            printBtn.classList.add('d-none');
        }
    }
    
    const tbody = document.getElementById('viewRsItemsBody');
    tbody.innerHTML = ''; 
    
    try {
        const itemsJson = atob(itemsB64);
        const items = JSON.parse(itemsJson);
        
        if (items.length > 0) {
            items.forEach(item => {
                const isNewItem = parseInt(item.is_new_item) === 1;
                const newBadge = isNewItem ? `<span class="badge bg-success ms-2 shadow-sm" style="font-size: 0.65rem;"><i class="bi bi-sparkles me-1"></i>NEW ITEM</span>` : '';
                const itemName = (item.item_name ? item.item_name : '<span class="text-danger">Item deleted</span>') + newBadge;
                const unit = item.unit ? item.unit : '';
                const reqQty = parseInt(item.quantity);
                const curStock = parseInt(item.current_stock) || 0;
                const totalPending = parseInt(item.total_pending) || 0;

                // --- Per-item notes (requestor) ---
                const itemNotesHtml = item.item_notes
                    ? `<div class="text-muted small mt-1 fst-italic"><i class="bi bi-chat-left-text me-1"></i>${item.item_notes}</div>`
                    : '';

                // --- Per-item status badge ---
                const iStatus = item.item_status || 'Pending';
                const statusBadgeMap = { 'Pending': 'bg-warning text-dark', 'Approved': 'bg-success', 'Rejected': 'bg-danger' };
                const statusIconMap  = { 'Pending': 'bi-hourglass-split', 'Approved': 'bi-check-circle-fill', 'Rejected': 'bi-x-circle-fill' };
                const sBadgeClass = statusBadgeMap[iStatus] || 'bg-secondary';
                const sIcon       = statusIconMap[iStatus]  || 'bi-question';
                let itemStatusHtml = `<span class="badge ${sBadgeClass} shadow-sm"><i class="bi ${sIcon} me-1"></i>${iStatus}</span>`;
                if (item.item_remarks) {
                    itemStatusHtml += `<div class="text-danger mt-1 fst-italic item-remark-text"><i class="bi bi-chat-right-text me-1"></i>${item.item_remarks}</div>`;
                }
                
                let stockDisplay = '';
                if (isNewItem) {
                    stockDisplay = `<span class="badge bg-info text-dark fs-6 shadow-sm"><i class="bi bi-plus-circle me-1"></i>New Item (0 Stock)</span>`;
                } else if (type === 'restock') {
                    if (curStock === 0) {
                        stockDisplay = `<span class="badge bg-danger fs-6 shadow-sm">0 (Out of Stock)</span>`;
                    } else {
                        stockDisplay = `<span class="badge bg-success fs-6 shadow-sm">${curStock}</span>`;
                    }
                } else {
                    if (curStock < reqQty) {
                        stockDisplay = `<span class="badge bg-danger fs-6 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i>${curStock} (Short)</span>`;
                    } else {
                        stockDisplay = `<span class="badge bg-success fs-6 shadow-sm">${curStock}</span>`;
                    }
                }
                
                let pendingDisplay = '';
                if (totalPending > 0) {
                    let formattedDetails = '';
                    if (item.pending_details) {
                        const entries = item.pending_details.split('; ');
                        formattedDetails = entries.map(entry => {
                            const match = entry.match(/(.+) \[(.+) by (.+)\]/);
                            if (match) {
                                const project = match[1];
                                const qty = match[2];
                                const name = match[3];
                                return `
                                    <div class="mb-1 pb-1 border-bottom-dashed small">
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 180px;" title="${project}">${project}</div>
                                        <div class="d-flex justify-content-between text-muted" style="font-size: 0.65rem;">
                                            <span>Qty: <b>${qty}</b></span>
                                            <span>by ${name}</span>
                                        </div>
                                    </div>
                                `;
                            }
                            return `<div class="text-truncate small mb-1">${entry}</div>`;
                        }).join('');
                    } else {
                        formattedDetails = '<div class="text-muted small">No details available.</div>';
                    }

                    const badgeClass = totalPending > curStock ? 'bg-warning text-dark' : 'bg-light text-dark border';
                    const label = type === 'restock' ? '' : (totalPending > curStock ? (totalPending > reqQty ? ' (Conflict)' : ' (Deficit)') : '');
                    
                    pendingDisplay = `
                        <div class="d-flex flex-column align-items-center">
                            <span class="badge ${badgeClass} fs-6 shadow-sm mb-1">${totalPending}${label}</span>
                            <details class="w-100 text-center" style="font-size: 0.7rem;">
                                <summary class="text-primary fw-bold" style="cursor: pointer; list-style: none; font-size: 0.65rem;">
                                    <i class="bi bi-info-circle-fill me-1"></i>Who?
                                </summary>
                                <div class="mt-2 text-muted border rounded-3 p-2 bg-light text-start shadow-sm" style="line-height: 1.3; min-width: 200px; max-width: 220px; margin: 0 auto; white-space: normal;">
                                    <div class="fw-bold text-muted border-bottom pb-1 mb-2 text-uppercase" style="font-size: 0.6rem; letter-spacing: 0.5px;">
                                        <i class="bi bi-diagram-3-fill me-1 text-primary"></i>Pending Breakdown
                                    </div>
                                    ${formattedDetails}
                                </div>
                            </details>
                        </div>
                    `;
                } else {
                    pendingDisplay = `<span class="text-muted small fw-bold">-</span>`;
                }
                
                const isRequestor = window.currentUserRole === 'requestor';
                // Non-requestors get Item Status + Stock + Pending columns
                const stockColsHtml = isRequestor ? '' : `
                    <td class="text-center align-middle d-print-none">${itemStatusHtml}</td>
                    <td class="text-center align-middle d-print-none">${stockDisplay}</td>
                    <td class="text-center align-middle d-print-none">${pendingDisplay}</td>
                `;
                // Requestors only see Item Status
                const requestorStatusCol = isRequestor
                    ? `<td class="text-center align-middle d-print-none">${itemStatusHtml}</td>`
                    : '';

                tbody.innerHTML += `
                    <tr>
                        <td class="text-muted small align-middle">${item.item_code}</td>
                        <td class="fw-bold align-middle">${itemName}${itemNotesHtml}</td>
                        <td class="text-dark fw-bold text-center align-middle fs-5">${reqQty} <span class="fs-6 fw-normal">${unit}</span></td>
                        ${requestorStatusCol}
                        ${stockColsHtml}
                    </tr>
                `;
            });
        } else {
            const colspan = window.currentUserRole === 'requestor' ? 4 : 6;
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-muted py-3">No items found.</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">Error loading items.</td></tr>`;
    }
    
    new bootstrap.Modal(document.getElementById('viewRsModal')).show();
}

window.openRejectModal = function(id, rsNo) {
    document.getElementById('rejectRsId').value = id;
    document.getElementById('rejectRsNoDisplay').innerText = rsNo;
    new bootstrap.Modal(document.getElementById('rejectRsModal')).show();
}

// --- Approve Items Modal ---
window.openApproveItemsModal = function(rsId, rsNo, itemsB64) {
    document.getElementById('approveRsIdField').value = rsId;
    document.getElementById('approveRsNoLabel').innerText = rsNo;

    const list = document.getElementById('approveItemsList');
    list.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-hourglass-split me-2"></i>Loading items...</div>';

    try {
        const items = JSON.parse(atob(itemsB64));
        if (!items || items.length === 0) {
            list.innerHTML = '<div class="alert alert-warning">No items found for this requisition.</div>';
        } else {
            list.innerHTML = items.map((item) => {
                const itemId   = item.item_id || '';
                const itemName = item.item_name || item.item_code || 'Unknown Item';
                const qty      = parseInt(item.quantity) || 0;
                const unit     = item.unit || '';
                const notes    = item.item_notes
                    ? `<div class="text-muted small fst-italic mt-1"><i class="bi bi-chat-left-text me-1"></i>${item.item_notes}</div>`
                    : '';

                return `
                <div class="card border shadow-sm mb-3 approve-item-card" data-item-id="${itemId}">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="fw-bold text-dark">${itemName}</div>
                                <div class="text-muted small">${item.item_code} &bull; Qty: <strong>${qty} ${unit}</strong></div>
                                ${notes}
                            </div>
                            <div class="btn-group btn-group-sm shadow-sm" role="group">
                                <input type="radio" class="btn-check" name="item_statuses[${itemId}]" id="approve_${itemId}" value="Approved" required checked>
                                <label class="btn btn-outline-success fw-bold px-3" for="approve_${itemId}"><i class="bi bi-check-lg me-1"></i>Approve</label>
                                <input type="radio" class="btn-check" name="item_statuses[${itemId}]" id="reject_${itemId}" value="Rejected">
                                <label class="btn btn-outline-danger fw-bold px-3" for="reject_${itemId}"><i class="bi bi-x-lg me-1"></i>Reject</label>
                            </div>
                        </div>
                        <div class="mt-2 remark-field">
                            <input type="text" class="form-control form-control-sm" name="item_remarks[${itemId}]" placeholder="Remark (optional)..." maxlength="255">
                        </div>
                    </div>
                </div>`;
            }).join('');

            // Dynamic remark field styling based on approve/reject selection
            list.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const card = this.closest('.approve-item-card');
                    const remarkInput = card.querySelector('.remark-field input');
                    if (this.value === 'Rejected') {
                        remarkInput.classList.add('border-danger');
                        remarkInput.placeholder = 'Reason for rejection (required)...';
                        remarkInput.required = true;
                    } else {
                        remarkInput.classList.remove('border-danger');
                        remarkInput.placeholder = 'Remark (optional)...';
                        remarkInput.required = false;
                    }
                });
            });
        }
    } catch(e) {
        list.innerHTML = '<div class="alert alert-danger">Error loading items. Please try again.</div>';
    }

    new bootstrap.Modal(document.getElementById('approveItemsModal')).show();
};

window.setAllItemStatuses = function(status) {
    const radioPrefix = status === 'Approved' ? 'approve_' : 'reject_';
    document.querySelectorAll('#approveItemsList .approve-item-card').forEach(card => {
        const itemId = card.dataset.itemId;
        const radio = document.getElementById(radioPrefix + itemId);
        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        }
    });
};

window.printRSDocument = function() {
    const printContent = document.getElementById('rsPrintArea').innerHTML;
    const originalContent = document.body.innerHTML;
    document.body.innerHTML = `<div style="padding: 40px; background: white;">${printContent}</div>`;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload(); 
}

if (window.rsGlobalClickListener) {
    document.body.removeEventListener('click', window.rsGlobalClickListener);
}

window.rsGlobalClickListener = function(e) {
    const container = document.getElementById('materialsContainer');
    const restockContainer = document.getElementById('restockMaterialsContainer');

    if (e.target.closest('#addMaterialBtn') && container) {
        const stdRow = container.querySelector('.material-row:not(.new-item-row)');
        if (stdRow) {
            const newRow = stdRow.cloneNode(true);
            const select = newRow.querySelector('select[name="items[]"]');
            if (select) select.value = '';
            const qtyInput = newRow.querySelector('input[name="quantities[]"]');
            if (qtyInput) qtyInput.value = '';
            const notesInput = newRow.querySelector('input[name="item_notes[]"]');
            if (notesInput) notesInput.value = '';
            newRow.querySelector('.remove-row').disabled = false;
            container.appendChild(newRow);
        }
        window.updateDeleteButtons(container);
    }

    if (e.target.closest('#addRestockMaterialBtn') && restockContainer) {
        const stdRow = restockContainer.querySelector('.material-row:not(.new-item-row)');
        if (stdRow) {
            const newRow = stdRow.cloneNode(true);
            const select = newRow.querySelector('select[name="items[]"]');
            if (select) select.value = '';
            const qtyInput = newRow.querySelector('input[name="quantities[]"]');
            if (qtyInput) qtyInput.value = '';
            const notesInput = newRow.querySelector('input[name="item_notes[]"]');
            if (notesInput) notesInput.value = '';
            newRow.querySelector('.remove-row').disabled = false;
            restockContainer.appendChild(newRow);
        }
        window.updateDeleteButtons(restockContainer);
    }

    if (e.target.closest('#addNewRestockMaterialBtn') && restockContainer) {
        window.appendNewItemRow(restockContainer);
    }

    if (e.target.closest('.remove-row')) {
        const rowToRemove = e.target.closest('.material-row');
        const parentContainer = rowToRemove.closest('#materialsContainer, #restockMaterialsContainer');
        if (parentContainer && parentContainer.querySelectorAll('.material-row').length > 1) {
            rowToRemove.remove();
            window.updateDeleteButtons(parentContainer);
        }
    }
};

document.body.addEventListener('click', window.rsGlobalClickListener);

window.appendNewItemRow = function(container) {
    if (!container) return;

    const catTemplate = document.getElementById('jsCategoryOptionsTemplate');
    const unitTemplate = document.getElementById('jsUnitOptionsTemplate');

    const catHtml = catTemplate ? catTemplate.innerHTML : '<option value="Materials">Materials</option>';
    const unitHtml = unitTemplate ? unitTemplate.innerHTML : '<option value="Pieces">Pieces</option>';

    const row = document.createElement('div');
    row.className = 'material-row new-item-row mb-2 bg-white p-3 rounded border border-success shadow-sm mx-0';
    row.innerHTML = `
        <input type="hidden" name="is_new_items[]" value="1">
        <input type="hidden" name="items[]" value="">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge bg-success shadow-sm"><i class="bi bi-plus-circle me-1"></i> New Item / Unlisted Material</span>
            <button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash3 me-1"></i> Remove</button>
        </div>
        <div class="row g-2">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted mb-1">Item Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm fw-bold" name="new_item_names[]" placeholder="e.g. Solar Panel Mounting Bracket" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Category <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm fw-bold" name="new_categories[]" required>
                    ${catHtml}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Unit <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm fw-bold" name="new_units[]" required>
                    ${unitHtml}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Qty <span class="text-danger">*</span></label>
                <input type="number" class="form-control form-control-sm fw-bold text-center text-primary" name="quantities[]" placeholder="Qty" required min="1">
            </div>
        </div>
        <div class="mt-2">
            <input type="text" class="form-control form-control-sm text-muted" name="item_notes[]" placeholder="Optional: Notes for this item..." maxlength="255">
        </div>
    `;
    container.appendChild(row);
    window.updateDeleteButtons(container);
};

window.updateDeleteButtons = function(container) {
    if (!container) return;
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

    // Check URL parameters for shortcut auto-open modals
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action');
    if (action === 'new') {
        const rsModalEl = document.getElementById('rsModal');
        if (rsModalEl) {
            new bootstrap.Modal(rsModalEl).show();
        }
    } else if (action === 'restock') {
        const restockModalEl = document.getElementById('restockModal');
        if (restockModalEl) {
            new bootstrap.Modal(restockModalEl).show();
        }
    }
}

if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", initializeRequisitionsPage); } else { initializeRequisitionsPage(); }