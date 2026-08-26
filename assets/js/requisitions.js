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

    const isRequestor = window.currentUserRole === 'requestor';
    const theadRow = document.getElementById('viewRsTableHeadRow');
    if (theadRow) {
        if (isRequestor) {
            theadRow.innerHTML = `
                <th class="text-center" style="width:110px;">Item Code</th>
                <th class="text-center">Item Name / Notes</th>
                <th class="text-center" style="width:90px;">Qty</th>
                <th class="text-center d-print-none" style="width:140px;">Item Status</th>
            `;
        } else {
            theadRow.innerHTML = `
                <th class="text-center" style="width:100px;">Item Code</th>
                <th class="text-center">Item Name / Notes</th>
                <th class="text-center" style="width:80px;">Qty</th>
                <th class="text-center d-print-none" style="width:130px;">Item Status</th>
                <th class="text-center d-print-none text-primary" style="width:90px;">Stock</th>
                <th class="text-center d-print-none text-warning" style="width:130px;">Pending</th>
            `;
        }
    }
    
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

                // --- Per-item notes (requestor) & remarks (reviewer) ---
                let notesAndRemarksHtml = '';
                if (item.item_notes) {
                    notesAndRemarksHtml += `<div class="text-muted small mt-1"><i class="bi bi-chat-left-text me-1 text-primary"></i>${item.item_notes}</div>`;
                }
                if (item.item_remarks) {
                    notesAndRemarksHtml += `<div class="d-flex justify-content-center mt-1"><div class="item-remark-pill"><i class="bi bi-info-circle-fill me-1"></i><span>${item.item_remarks}</span></div></div>`;
                }

                // --- Per-item status badge ---
                const iStatus = item.item_status || 'Pending';
                const statusBadgeMap = { 'Pending': 'bg-warning text-dark', 'Approved': 'bg-success', 'Rejected': 'bg-danger' };
                const statusIconMap  = { 'Pending': 'bi-hourglass-split', 'Approved': 'bi-check-circle-fill', 'Rejected': 'bi-x-circle-fill' };
                const sBadgeClass = statusBadgeMap[iStatus] || 'bg-secondary';
                const sIcon       = statusIconMap[iStatus]  || 'bi-question';
                const itemStatusHtml = `<span class="badge ${sBadgeClass} shadow-sm px-2.5 py-1.5"><i class="bi ${sIcon} me-1"></i>${iStatus}</span>`;
                
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
                
                const statusCol = `<td class="text-center align-middle d-print-none">${itemStatusHtml}</td>`;
                const stockCols = isRequestor ? '' : `
                    <td class="text-center align-middle d-print-none">${stockDisplay}</td>
                    <td class="text-center align-middle d-print-none">${pendingDisplay}</td>
                `;

                tbody.innerHTML += `
                    <tr>
                        <td class="text-center align-middle"><span class="item-code-badge">${item.item_code}</span></td>
                        <td class="text-center align-middle"><div class="fw-bold text-dark">${itemName}</div>${notesAndRemarksHtml}</td>
                        <td class="text-center align-middle">
                            <div class="fw-bold text-dark fs-6">${reqQty}</div>
                            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem;">${unit}</small>
                        </td>
                        ${statusCol}
                        ${stockCols}
                    </tr>
                `;
            });
        } else {
            const colspan = isRequestor ? 4 : 6;
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-muted py-3">No items found.</td></tr>`;
        }
    } catch (e) {
        const colspan = isRequestor ? 4 : 6;
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-center text-danger py-3">Error loading items.</td></tr>`;
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
                const isNewItem = parseInt(item.is_new_item) === 1;
                const rawName  = item.item_name || item.item_code || 'Unknown Item';
                const qty      = parseInt(item.quantity) || 0;
                const unit     = item.unit || '';
                const notes    = item.item_notes
                    ? `<div class="text-muted small fst-italic mt-1"><i class="bi bi-chat-left-text me-1"></i>${item.item_notes}</div>`
                    : '';

                const newBadge = isNewItem ? `<span class="badge bg-success ms-2 shadow-sm" style="font-size:0.65rem;"><i class="bi bi-sparkles me-1"></i>NEW / UNLISTED ITEM</span>` : '';

                const typoEditHtml = isNewItem ? `
                    <div class="mt-2 p-2 bg-success-subtle rounded border border-success-subtle">
                        <label class="form-label text-success-emphasis small fw-bold mb-1 d-flex align-items-center">
                            <i class="bi bi-pencil-square me-1"></i>Edit Item Name (Fix typo/spelling if needed):
                        </label>
                        <input type="text" class="form-control form-control-sm fw-bold border-success" name="item_names[${itemId}]" value="${rawName.replace(/"/g, '&quot;')}" placeholder="Correct item name...">
                    </div>
                ` : '';

                return `
                <div class="card border shadow-sm mb-3 approve-item-card" data-item-id="${itemId}">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div class="flex-grow-1 me-3">
                                <div class="fw-bold text-dark fs-6">${rawName} ${newBadge}</div>
                                <div class="text-muted small mt-1">
                                    <span class="badge bg-light text-dark border me-1">${item.item_code}</span>
                                    <span>Quantity: <strong>${qty} ${unit}</strong></span>
                                </div>
                                ${notes}
                                ${typoEditHtml}
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
    const editContainer = document.getElementById('editMaterialsContainer');

    if (e.target.closest('#addMaterialBtn') && container) {
        window.appendExistingItemRow(container, false);
    }

    if (e.target.closest('#addNewMaterialBtn') && container) {
        window.appendNewItemRow(container);
    }

    if (e.target.closest('#addRestockMaterialBtn') && restockContainer) {
        window.appendExistingItemRow(restockContainer, true);
    }

    if (e.target.closest('#addNewRestockMaterialBtn') && restockContainer) {
        window.appendNewItemRow(restockContainer);
    }

    if (e.target.closest('#addEditMaterialBtn') && editContainer) {
        window.appendExistingItemRow(editContainer, false);
    }

    if (e.target.closest('#addNewEditMaterialBtn') && editContainer) {
        window.appendNewItemRow(editContainer);
    }

    if (e.target.closest('.remove-row')) {
        const rowToRemove = e.target.closest('.material-row');
        const parentContainer = rowToRemove ? rowToRemove.closest('#materialsContainer, #restockMaterialsContainer, #editMaterialsContainer') : null;
        if (parentContainer && parentContainer.querySelectorAll('.material-row').length > 1) {
            rowToRemove.remove();
            window.updateDeleteButtons(parentContainer);
        }
    }
};

document.body.addEventListener('click', window.rsGlobalClickListener);

// Function to append an existing inventory material row (works even if all standard rows were deleted)
window.appendExistingItemRow = function(container, isRestock = false) {
    if (!container) return null;

    const invTemplate = document.getElementById('jsInventoryOptionsTemplate');
    let optionsHtml = invTemplate ? invTemplate.innerHTML : '';
    if (!optionsHtml) {
        const existingSelect = document.querySelector('select[name="items[]"]');
        if (existingSelect) {
            optionsHtml = existingSelect.innerHTML;
        } else {
            optionsHtml = '<option value="">Select Material from Inventory...</option>';
        }
    }

    const placeholderText = isRestock 
        ? 'Optional: Notes for this item (e.g. target quantity, reason for restock)...' 
        : 'Optional: Notes for this item (e.g. specific brand, size, color, purpose)...';

    const row = document.createElement('div');
    row.className = 'material-row mb-2.5 bg-white p-3 rounded border shadow-sm mx-0';
    row.innerHTML = `
        <input type="hidden" name="is_new_items[]" value="0">
        <input type="hidden" name="new_item_names[]" value="">
        <input type="hidden" name="new_categories[]" value="">
        <input type="hidden" name="new_units[]" value="">
        
        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
            <span class="badge bg-light text-dark border small fw-bold row-index-badge"><i class="bi bi-box me-1 text-primary"></i>Item</span>
            <span class="small text-muted fst-italic item-stock-hint" style="font-size: 0.75rem;"></span>
        </div>

        <div class="row g-2">
            <div class="col-12 col-md-8">
                <label class="form-label small fw-bold text-muted mb-1">Select Material <span class="text-danger">*</span></label>
                <select class="form-select fw-bold text-dark item-select-control" name="items[]" required>
                    ${optionsHtml}
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">Quantity <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" class="form-control fw-bold text-center text-primary item-qty-input" name="quantities[]" placeholder="Qty" required min="1" step="any">
                    <span class="input-group-text bg-light text-muted small fw-bold item-unit-badge" style="min-width: 55px; font-size: 0.72rem;">Unit</span>
                </div>
            </div>
            <div class="col-12 mt-2">
                <input type="text" class="form-control form-control-sm text-muted" name="item_notes[]" placeholder="${placeholderText}" maxlength="255">
            </div>
        </div>
        <div class="d-flex justify-content-end mt-2 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-outline-danger remove-row" aria-label="Remove item" title="Remove item">
                <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Remove Item
            </button>
        </div>
    `;
    container.appendChild(row);
    window.updateDeleteButtons(container);
    return row;
};

// Sync unit badge next to quantity field when an item is selected & check duplicates
window.syncRowUnitBadge = function(selectEl) {
    if (!selectEl) return;
    const row = selectEl.closest('.material-row');
    if (!row) return;
    const unitBadge = row.querySelector('.item-unit-badge');
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const unit = selectedOption ? (selectedOption.getAttribute('data-unit') || '') : '';
    
    if (unitBadge) {
        if (unit) {
            unitBadge.textContent = unit;
            unitBadge.classList.remove('text-muted');
            unitBadge.classList.add('text-primary');
        } else {
            unitBadge.textContent = 'Unit';
            unitBadge.classList.remove('text-primary');
            unitBadge.classList.add('text-muted');
        }
    }

    // Check for duplicate material selections in the same container (HCI Error Prevention)
    const container = row.closest('#materialsContainer, #restockMaterialsContainer, #editMaterialsContainer');
    if (container && selectEl.value) {
        const selects = container.querySelectorAll('select[name="items[]"]');
        let count = 0;
        selects.forEach(s => {
            if (s.value && s.value === selectEl.value) count++;
        });

        if (count > 1) {
            row.classList.add('border-warning');
            let dupWarning = row.querySelector('.dup-warning-pill');
            if (!dupWarning) {
                dupWarning = document.createElement('span');
                dupWarning.className = 'badge bg-warning text-dark small fw-bold dup-warning-pill ms-2';
                dupWarning.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Duplicate Item';
                row.querySelector('.row-index-badge')?.parentElement.appendChild(dupWarning);
            }
        } else {
            row.classList.remove('border-warning');
            row.querySelector('.dup-warning-pill')?.remove();
        }
    }
};

document.addEventListener('change', function(e) {
    if (e.target && e.target.matches('select[name="items[]"]')) {
        window.syncRowUnitBadge(e.target);
    }
});

window.appendNewItemRow = function(container) {
    if (!container) return null;

    const catTemplate = document.getElementById('jsCategoryOptionsTemplate');
    const unitTemplate = document.getElementById('jsUnitOptionsTemplate');

    const catHtml = catTemplate ? catTemplate.innerHTML : '<option value="Materials">Materials</option>';
    const unitHtml = unitTemplate ? unitTemplate.innerHTML : '<option value="Pieces">Pieces</option>';

    const row = document.createElement('div');
    row.className = 'material-row new-item-row mb-2.5 bg-white p-3 rounded border border-success shadow-sm mx-0';
    row.innerHTML = `
        <input type="hidden" name="is_new_items[]" value="1">
        <input type="hidden" name="items[]" value="">
        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
            <span class="badge bg-success shadow-sm row-index-badge"><i class="bi bi-plus-circle me-1"></i>New Item / Unlisted Material</span>
            <button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash3 me-1"></i> Remove</button>
        </div>
        <div class="row g-2">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-bold text-muted mb-1">Item Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm fw-bold" name="new_item_names[]" placeholder="e.g. Solar Panel Mounting Bracket" required>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Category <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm fw-bold" name="new_categories[]" required>
                    ${catHtml}
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Unit <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm fw-bold" name="new_units[]" required>
                    ${unitHtml}
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Qty <span class="text-danger">*</span></label>
                <input type="number" class="form-control form-control-sm fw-bold text-center text-primary" name="quantities[]" placeholder="Qty" required min="1" step="any">
            </div>
        </div>
        <div class="mt-2">
            <input type="text" class="form-control form-control-sm text-muted" name="item_notes[]" placeholder="Optional: Notes for this item (e.g. brand, specs)..." maxlength="255">
        </div>
    `;
    container.appendChild(row);
    window.updateDeleteButtons(container);
    return row;
};

window.updateDeleteButtons = function(container) {
    if (!container) return;
    const rows = container.querySelectorAll('.material-row');
    
    // Update live item count badge in the modal card header
    const modal = container.closest('.modal');
    if (modal) {
        const countBadge = modal.querySelector('.material-count-badge');
        if (countBadge) {
            countBadge.textContent = `${rows.length} ${rows.length === 1 ? 'Item' : 'Items'}`;
        }
    }

    rows.forEach((row, index) => {
        // Update item index label for standard rows
        if (!row.classList.contains('new-item-row')) {
            const indexBadge = row.querySelector('.row-index-badge');
            if (indexBadge) {
                indexBadge.innerHTML = `<i class="bi bi-box me-1 text-primary"></i>Item #${index + 1}`;
            }
        }

        const btn = row.querySelector('.remove-row');
        if (btn) {
            btn.disabled = (rows.length === 1);
        }
    });
};

// Form submission feedback and double-click prevention (HCI Usability Principle)
document.addEventListener('DOMContentLoaded', function() {
    ['rsForm', 'restockForm', 'editRsForm'].forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';
                }
            });
        }
    });
});

// --- EDIT & RESUBMIT REQUISITION MODAL ---
window.openEditRsModal = function(rsId, rsNo, project, urgency, remarks, itemsB64, type) {
    const idField = document.getElementById('editRsIdField');
    const label = document.getElementById('editRsNoLabel');
    const input = document.getElementById('editRsNoInput');
    const urg = document.getElementById('editRsUrgency');
    const proj = document.getElementById('editRsProject');
    const rem = document.getElementById('editRsRemarks');
    const container = document.getElementById('editMaterialsContainer');

    if (idField) idField.value = rsId;
    if (label) label.innerText = rsNo;
    if (input) input.value = rsNo;
    if (urg) urg.value = urgency || 'Normal';
    if (proj) proj.value = project || '';
    if (rem) rem.value = remarks || '';

    if (container) {
        container.innerHTML = '';
        try {
            const items = JSON.parse(atob(itemsB64));
            if (items && items.length > 0) {
                const catTemplate = document.getElementById('jsCategoryOptionsTemplate');
                const unitTemplate = document.getElementById('jsUnitOptionsTemplate');
                const catHtml = catTemplate ? catTemplate.innerHTML : '<option value="Materials">Materials</option>';
                const unitHtml = unitTemplate ? unitTemplate.innerHTML : '<option value="Pieces">Pieces</option>';

                items.forEach(item => {
                    const isNew = parseInt(item.is_new_item) === 1;
                    const qty = parseInt(item.quantity) || 1;
                    const note = item.item_notes || '';

                    if (isNew) {
                        const row = document.createElement('div');
                        row.className = 'material-row new-item-row mb-2 bg-white p-3 rounded border border-success shadow-sm mx-0';
                        const safeName = (item.item_name || '').replace(/"/g, '&quot;');
                        const safeNote = note.replace(/"/g, '&quot;');

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
                                    <input type="text" class="form-control form-control-sm fw-bold" name="new_item_names[]" value="${safeName}" placeholder="e.g. Solar Panel Mounting Bracket" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1">Category <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm fw-bold new-cat-select" name="new_categories[]" required>
                                        ${catHtml}
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Unit <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm fw-bold new-unit-select" name="new_units[]" required>
                                        ${unitHtml}
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted mb-1">Qty <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm fw-bold text-center text-primary" name="quantities[]" value="${qty}" required min="1">
                                </div>
                            </div>
                            <div class="mt-2">
                                <input type="text" class="form-control form-control-sm text-muted" name="item_notes[]" value="${safeNote}" placeholder="Optional: Notes for this item..." maxlength="255">
                            </div>
                        `;
                        container.appendChild(row);
                        if (item.new_category) {
                            const catSel = row.querySelector('.new-cat-select');
                            if (catSel) catSel.value = item.new_category;
                        }
                        if (item.new_unit || item.unit) {
                            const unitSel = row.querySelector('.new-unit-select');
                            if (unitSel) unitSel.value = item.new_unit || item.unit;
                        }
                    } else {
                        const row = window.appendExistingItemRow(container, false);
                        if (row) {
                            const select = row.querySelector('select[name="items[]"]');
                            if (select) {
                                select.value = item.item_code;
                                window.syncRowUnitBadge(select);
                            }
                            const qtyInput = row.querySelector('input[name="quantities[]"]');
                            if (qtyInput) qtyInput.value = qty;
                            const notesInput = row.querySelector('input[name="item_notes[]"]');
                            if (notesInput) notesInput.value = note;
                        }
                    }
                });
            }
        } catch(e) {
            console.error('Error populating edit RS items:', e);
            container.innerHTML = '<div class="alert alert-danger py-2">Error parsing item list.</div>';
        }
        window.updateDeleteButtons(container);
    }

    new bootstrap.Modal(document.getElementById('editRsModal')).show();
};

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

        allRows.forEach(row => {
            row.classList.add('d-none', 'rs-row-hidden');
            row.style.setProperty('display', 'none', 'important');
        });
        const rowsToShow = filteredRows.slice(start, end);
        rowsToShow.forEach(row => {
            row.classList.remove('d-none', 'rs-row-hidden');
            row.style.removeProperty('display');
        });

        const tbody = table.querySelector('tbody');
        let emptyRow = tbody ? tbody.querySelector('.rs-empty-row') : null;
        if (filteredRows.length === 0) {
            if (!emptyRow && tbody) {
                emptyRow = document.createElement('tr');
                emptyRow.className = 'rs-empty-row text-center';
                emptyRow.innerHTML = `
                    <td colspan="7" class="py-5 text-muted">
                        <div class="py-3">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-bold text-dark">No requisitions found</h6>
                            <p class="small text-muted mb-2">No records match your active filter or search keyword.</p>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold rs-reset-filter-btn px-3">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(emptyRow);
                emptyRow.querySelector('.rs-reset-filter-btn')?.addEventListener('click', () => {
                    if (window.resetAllRsFilters) {
                        window.resetAllRsFilters();
                    }
                });
            }
            if (emptyRow) emptyRow.style.display = '';
        } else if (emptyRow) {
            emptyRow.style.display = 'none';
        }

        const showingEnd = Math.min(end, filteredRows.length);
        const showingStart = filteredRows.length > 0 ? start + 1 : 0;

        infoText.innerHTML = `Showing <b>${showingStart}</b> to <b>${showingEnd}</b> of <b>${filteredRows.length}</b>`;
        pageIndicator.innerText = `Page ${currentPage} / ${totalPages}`;
        
        prevBtn.disabled = currentPage === 1; 
        nextBtn.disabled = currentPage === totalPages || totalPages === 0;
    }

    let currentStatusFilter = 'all';
    const filterTiles = document.querySelectorAll('.rs-filter-tile');
    const requestorSelect = document.getElementById('filterRsRequestor');
    const projectSelect = document.getElementById('filterRsProject');
    const statusSelect = document.getElementById('filterRsStatus');
    const urgencySelect = document.getElementById('filterRsUrgency');
    const dateInput = document.getElementById('filterRsDate');
    const activeBadge = document.getElementById('activeRsFilterBadge');

    window.filterRsTable = function() {
        const term = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const requestorVal = requestorSelect ? requestorSelect.value : 'all';
        const projectVal = projectSelect ? projectSelect.value : 'all';
        const statusVal = statusSelect ? statusSelect.value : 'all';
        const urgencyVal = urgencySelect ? urgencySelect.value : 'all';
        const dateVal = dateInput ? dateInput.value : '';

        filteredRows = allRows.filter(row => {
            const no = (row.querySelector('.rs-no')?.textContent || '').toLowerCase();
            const proj = (row.getAttribute('data-project') || row.querySelector('.rs-project')?.textContent || '').toLowerCase();
            const req = (row.getAttribute('data-requestor-name') || row.querySelector('.rs-requestor')?.textContent || '').toLowerCase();
            const rawStatus = (row.getAttribute('data-status') || row.querySelector('.col-status')?.textContent || '').trim();
            const rawUrgency = (row.getAttribute('data-urgency') || row.querySelector('.col-urgency')?.textContent || '').trim();
            const rawDate = row.getAttribute('data-created-date') || '';

            // Search Keyword
            const matchesSearch = !term || no.includes(term) || proj.includes(term) || req.includes(term);

            // KPI Stat Tile Filter
            let matchesTileStatus = true;
            if (currentStatusFilter === 'pending') {
                matchesTileStatus = (rawStatus === 'Pending Approval');
            } else if (currentStatusFilter === 'approved') {
                matchesTileStatus = ['Approved', 'Partially Approved', 'PO Created', 'Staged (Ready for Pickup)', 'Released'].includes(rawStatus);
            }

            // Advanced Dropdown Filters
            let matchesRequestor = true;
            if (requestorVal === 'me') {
                const youBadge = row.querySelector('.col-requestor .badge');
                matchesRequestor = (youBadge !== null);
            } else if (requestorVal !== 'all') {
                matchesRequestor = (req === requestorVal.toLowerCase());
            }

            const matchesProject = (projectVal === 'all') || (proj === projectVal.toLowerCase());
            const matchesStatus = (statusVal === 'all') || (rawStatus === statusVal);
            const matchesUrgency = (urgencyVal === 'all') || (rawUrgency === urgencyVal);
            const matchesDate = !dateVal || (rawDate === dateVal);

            return matchesSearch && matchesTileStatus && matchesRequestor && matchesProject && matchesStatus && matchesUrgency && matchesDate;
        });

        // Update Active Filter Badge Count
        let activeFilterCount = 0;
        if (requestorVal !== 'all') activeFilterCount++;
        if (projectVal !== 'all') activeFilterCount++;
        if (statusVal !== 'all') activeFilterCount++;
        if (urgencyVal !== 'all') activeFilterCount++;
        if (dateVal !== '') activeFilterCount++;

        if (activeBadge) {
            if (activeFilterCount > 0) {
                activeBadge.innerText = activeFilterCount;
                activeBadge.classList.remove('d-none');
            } else {
                activeBadge.classList.add('d-none');
            }
        }

        currentPage = 1;
        updatePagination();
    };

    window.resetAllRsFilters = function() {
        if (searchInput) searchInput.value = '';
        if (requestorSelect) requestorSelect.value = 'all';
        if (projectSelect) projectSelect.value = 'all';
        if (statusSelect) statusSelect.value = 'all';
        if (urgencySelect) urgencySelect.value = 'all';
        if (dateInput) dateInput.value = '';

        currentStatusFilter = 'all';
        filterTiles.forEach(t => {
            if ((t.getAttribute('data-filter') || 'all') === 'all') {
                t.classList.add('active-filter');
            } else {
                t.classList.remove('active-filter');
            }
        });

        window.filterRsTable();
    };

    filterTiles.forEach(tile => {
        tile.addEventListener('click', function() {
            const targetFilter = this.getAttribute('data-filter') || 'all';
            
            // If already selected, clicking it again resets to 'all'
            if (currentStatusFilter === targetFilter && targetFilter !== 'all') {
                currentStatusFilter = 'all';
            } else {
                currentStatusFilter = targetFilter;
            }

            // Sync active classes
            filterTiles.forEach(t => {
                const f = t.getAttribute('data-filter') || 'all';
                if (f === currentStatusFilter) {
                    t.classList.add('active-filter');
                } else {
                    t.classList.remove('active-filter');
                }
            });

            window.filterRsTable();
        });

        // Accessibility: Keyboard Enter / Space support
        tile.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    if (searchInput) searchInput.addEventListener('input', window.filterRsTable);
    if (requestorSelect) requestorSelect.addEventListener('change', window.filterRsTable);
    if (projectSelect) projectSelect.addEventListener('change', window.filterRsTable);
    if (statusSelect) statusSelect.addEventListener('change', window.filterRsTable);
    if (urgencySelect) urgencySelect.addEventListener('change', window.filterRsTable);
    if (dateInput) dateInput.addEventListener('change', window.filterRsTable);

    prevBtn.addEventListener('click', () => { if (currentPage > 1) { currentPage--; updatePagination(); } });
    nextBtn.addEventListener('click', () => { const totalPages = Math.ceil(filteredRows.length / rowsPerPage); if (currentPage < totalPages) { currentPage++; updatePagination(); } });

    updatePagination();

    // Check URL parameters for shortcut search and auto-open modals
    const urlParams = new URLSearchParams(window.location.search);
    const searchTerm = urlParams.get('search') || urlParams.get('q');
    const autoOpenRs = urlParams.get('rs_no') || urlParams.get('auto_open');
    const action = urlParams.get('action');

    if (searchTerm && searchInput) {
        searchInput.value = searchTerm;
        filterData();
    }

    if (autoOpenRs) {
        const targetRow = allRows.find(r => {
            const no = r.querySelector('.rs-no')?.textContent.trim().toLowerCase();
            return no === autoOpenRs.trim().toLowerCase();
        });
        if (targetRow) {
            const viewBtn = targetRow.querySelector('button[title="View Details"]');
            if (viewBtn) viewBtn.click();
        }
    }

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

    // --- CREATE RS FORM AJAX SUBMISSION ---
    const rsForm = document.getElementById('rsForm');
    const rsModalEl = document.getElementById('rsModal');

    if (rsForm) {
        rsForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('rsSubmitBtn');
            const originalText = submitBtn ? submitBtn.innerHTML : '<i class="bi bi-send me-2"></i>Submit Request';

            if (!rsForm.checkValidity()) {
                rsForm.reportValidity();
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Submitting...';
            }

            try {
                const formData = new FormData(rsForm);
                const response = await fetch('process/process.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const result = await response.json();

                if (result.status === 'success' || result.success) {
                    const modalInstance = bootstrap.Modal.getInstance(rsModalEl);
                    if (modalInstance) modalInstance.hide();

                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Requisition Created!',
                            text: result.message || 'Requisition submitted successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                    window.location.reload();
                } else {
                    throw new Error(result.message || 'Failed to submit requisition.');
                }
            } catch (err) {
                console.error('Error submitting RS:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Submission Failed',
                        text: err.message || 'An error occurred while creating the requisition.'
                    });
                } else {
                    alert(err.message || 'An error occurred while creating the requisition.');
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    }

    if (rsModalEl) {
        rsModalEl.addEventListener('hidden.bs.modal', () => {
            const container = document.getElementById('materialsContainer');
            if (container) {
                // Keep only the first template row and reset its values
                const rows = container.querySelectorAll('.material-row');
                rows.forEach((row, idx) => {
                    if (idx > 0) row.remove();
                    else {
                        row.querySelectorAll('input, select').forEach(el => {
                            if (el.type !== 'hidden') el.value = '';
                        });
                    }
                });
                window.updateDeleteButtons(container);
            }
            if (rsForm) rsForm.reset();
        });
    }

    // --- RESTOCK RS FORM AJAX SUBMISSION ---
    const restockForm = document.getElementById('restockForm');
    const restockModalEl = document.getElementById('restockModal');

    if (restockForm) {
        restockForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('restockSubmitBtn');
            const originalText = submitBtn ? submitBtn.innerHTML : '<i class="bi bi-send me-2"></i>Submit Restock Request';

            if (!restockForm.checkValidity()) {
                restockForm.reportValidity();
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Submitting...';
            }

            try {
                const formData = new FormData(restockForm);
                const response = await fetch('process/process.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const result = await response.json();

                if (result.status === 'success' || result.success) {
                    const modalInstance = bootstrap.Modal.getInstance(restockModalEl);
                    if (modalInstance) modalInstance.hide();

                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Restock Request Created!',
                            text: result.message || 'Restock request submitted successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                    window.location.reload();
                } else {
                    throw new Error(result.message || 'Failed to submit restock request.');
                }
            } catch (err) {
                console.error('Error submitting restock RS:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Submission Failed',
                        text: err.message || 'An error occurred while creating restock request.'
                    });
                } else {
                    alert(err.message || 'An error occurred while creating restock request.');
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    }

    if (restockModalEl) {
        restockModalEl.addEventListener('hidden.bs.modal', () => {
            const container = document.getElementById('restockMaterialsContainer');
            if (container) {
                const rows = container.querySelectorAll('.material-row');
                rows.forEach((row, idx) => {
                    if (idx > 0) row.remove();
                    else {
                        row.querySelectorAll('input, select').forEach(el => {
                            if (el.type !== 'hidden') el.value = '';
                        });
                    }
                });
                window.updateDeleteButtons(container);
            }
            if (restockForm) restockForm.reset();
        });
    }

    // --- EDIT RS FORM AJAX SUBMISSION (cims-modal-ajax-handler standard) ---
    const editForm = document.getElementById('editRsForm');
    const editModalEl = document.getElementById('editRsModal');

    if (editForm) {
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('editRsSubmitBtn');
            const originalText = submitBtn ? submitBtn.innerHTML : '<i class="bi bi-check2-circle me-2"></i>Save &amp; Resubmit';

            if (!editForm.checkValidity()) {
                editForm.reportValidity();
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
            }

            try {
                const formData = new FormData(editForm);
                const response = await fetch('process/process.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const result = await response.json();

                if (result.status === 'success' || result.success) {
                    const modalInstance = bootstrap.Modal.getInstance(editModalEl);
                    if (modalInstance) modalInstance.hide();

                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Requisition Updated!',
                            text: result.message || 'Requisition details updated successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                    window.location.reload();
                } else {
                    throw new Error(result.message || 'Failed to update requisition.');
                }
            } catch (err) {
                console.error('Error submitting edit RS:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: err.message || 'An error occurred while updating the requisition.'
                    });
                } else {
                    alert(err.message || 'An error occurred while updating the requisition.');
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    }

    if (editModalEl) {
        editModalEl.addEventListener('hidden.bs.modal', () => {
            const container = document.getElementById('editMaterialsContainer');
            if (container) container.innerHTML = '';
        });
    }
}

if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", initializeRequisitionsPage); } else { initializeRequisitionsPage(); }