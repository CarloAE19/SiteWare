/* ==========================================================
 * GB INVENTORY - UI & MODALS
 * Handles passwords toggles, modal triggers, and UI events
 * ========================================================== */

document.addEventListener("DOMContentLoaded", () => {
    // Receive QR Scanner Init
    document.body.addEventListener('click', (e) => {
        if (e.target.closest('#startReceiveScannerBtn')) {
            const modalEl = document.getElementById('receiveScanModal');
            if(modalEl) new bootstrap.Modal(modalEl).show();
            if (typeof window.resetReceiveScanner === 'function') window.resetReceiveScanner();
        }
    });

    // Reset Password Field on User Modal Close
    document.body.addEventListener('hidden.bs.modal', (e) => {
        if (e.target.id === 'userModal') {
            const input = document.getElementById('userPassword');
            const icon = document.getElementById('toggleUserIcon');
            if (input && icon) {
                input.type = "password";
                icon.classList.replace("bi-eye", "bi-eye-slash");
                icon.classList.replace("text-primary", "text-muted");
            }
        }
    });

    // Audit / Recount Logic (Physical Input change)
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('phys-input')) {
            let index = e.target.getAttribute('data-index');
            let sysQty = parseInt(document.getElementById('sysQty_' + index).innerText);
            let physQty = parseInt(e.target.value) || 0;
            let diff = physQty - sysQty;
            
            let badge = document.getElementById('diff_' + index);
            if(!badge) return;

            if (diff < 0) {
                badge.className = 'badge bg-danger fs-6 w-75 p-2 diff-badge';
                badge.innerHTML = diff + ' Missing';
            } else if (diff > 0) {
                badge.className = 'badge bg-info text-dark fs-6 w-75 p-2 diff-badge';
                badge.innerHTML = '+' + diff + ' Surplus';
            } else {
                badge.className = 'badge bg-secondary fs-6 w-75 p-2 diff-badge';
                badge.innerHTML = '0 Match';
            }
        }
    });
});

window.toggleUserPass = function() {
    const input = document.getElementById('userPassword');
    const icon = document.getElementById('toggleUserIcon');
    if (!input || !icon) return;
    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("bi-eye-slash", "bi-eye");
        icon.classList.replace("text-muted", "text-primary");
    } else {
        input.type = "password";
        icon.classList.replace("bi-eye", "bi-eye-slash");
        icon.classList.replace("text-primary", "text-muted");
    }
}

window.togglePass = function(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;
    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("bi-eye-slash", "bi-eye");
    } else {
        input.type = "password";
        icon.classList.replace("bi-eye", "bi-eye-slash");
    }
}

window.viewAuditDetails = function(month, remarks, itemsJson) {
    document.getElementById('modalAuditMonth').innerText = "Audit: " + month;
    document.getElementById('modalAuditRemarks').innerText = remarks ? remarks : 'No notes provided.';
    
    let tbody = document.getElementById('auditModalBody');
    tbody.innerHTML = '';
    let items = JSON.parse(itemsJson);
    items.forEach(item => {
        let diffColor = item.discrepancy < 0 ? 'text-danger fw-bold' : (item.discrepancy > 0 ? 'text-info fw-bold' : 'text-muted');
        let diffText = item.discrepancy > 0 ? '+' + item.discrepancy : item.discrepancy;
        tbody.innerHTML += `
            <tr>
                <td class="text-muted small">${item.item_code}</td>
                <td class="fw-bold">${item.item_name}</td>
                <td class="text-center">${item.system_qty}</td>
                <td class="text-center fw-bold">${item.physical_qty}</td>
                <td class="text-center ${diffColor}">${diffText}</td>
            </tr>
        `;
    });
    new bootstrap.Modal(document.getElementById('auditModal')).show();
}

// FIXED: Now selects the first available Category/Unit instead of hardcoding "Materials"!
window.openAddModal = function() {
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add New Item';
    document.getElementById('formAction').value = 'add';
    document.getElementById('itemId').value = '';
    document.getElementById('itemCode').value = 'ITM-' + Math.floor(Math.random() * 9000 + 1000);
    document.getElementById('itemName').value = '';
    
    const catSelect = document.getElementById('itemCategory');
    if(catSelect && catSelect.options.length > 0) catSelect.selectedIndex = 0;
    
    const unitSelect = document.getElementById('itemUnit');
    if(unitSelect && unitSelect.options.length > 0) unitSelect.selectedIndex = 0;
    
    document.getElementById('itemQuantity').value = '0';
    document.getElementById('itemPrice').value = '0.00';
}

window.openEditModal = function(id, code, name, category, quantity, unit, price, status) {
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Item';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('itemId').value = id;
    document.getElementById('itemCode').value = code;
    document.getElementById('itemName').value = name;
    document.getElementById('itemCategory').value = category;
    document.getElementById('itemQuantity').value = quantity;
    document.getElementById('itemUnit').value = unit;
    document.getElementById('itemPrice').value = price;
}

window.openAddUserModal = function() {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>Add New User';
    document.getElementById('userFormAction').value = 'add_user';
    document.getElementById('userId').value = '';
    document.getElementById('userName').value = '';
    document.getElementById('userUsername').value = '';
    document.getElementById('userRole').value = 'requestor';
    document.getElementById('userPassword').required = true;
    document.getElementById('passwordHelp').innerText = "Required for new users.";
}

window.openEditUserModal = function(id, name, username, role) {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-gear me-2"></i>Edit User';
    document.getElementById('userFormAction').value = 'edit_user';
    document.getElementById('userId').value = id;
    document.getElementById('userName').value = name;
    document.getElementById('userUsername').value = username;
    document.getElementById('userRole').value = role;
    document.getElementById('userPassword').required = false;
    document.getElementById('passwordHelp').innerText = "Leave blank to keep current password.";
}

/* Global RS Modal Opener - Fetches RS details via AJAX and opens #viewRsModal */
window.openRsModalByNo = async function(rsNo) {
    if (!rsNo) return;

    const modalEl = document.getElementById('viewRsModal');
    if (!modalEl) {
        console.error('viewRsModal element not found in DOM');
        return;
    }

    try {
        const basePath = window.cimsBasePath || '';
        const response = await fetch(`${basePath}/process/get_rs_details.php?rs_no=${encodeURIComponent(rsNo)}`);
        const data = await response.json();

        if (!data.success || !data.requisition) {
            alert(data.error || 'Requisition Slip not found.');
            return;
        }

        const rs = data.requisition;
        const items = data.items || [];

        const rsNoEl = document.getElementById('viewRsNo');
        if (rsNoEl) rsNoEl.innerText = rs.rs_no;

        const projEl = document.getElementById('viewRsProject');
        if (projEl) projEl.innerText = rs.project_name;

        const statusEl = document.getElementById('viewRsStatus');
        if (statusEl) {
            statusEl.innerText = rs.status;
            statusEl.className = 'badge shadow-sm';
            if (rs.status === 'Pending Approval') {
                statusEl.classList.add('bg-warning', 'text-dark');
            } else if (rs.status === 'Approved') {
                statusEl.classList.add('bg-success');
            } else if (rs.status === 'Staged (Ready for Pickup)') {
                statusEl.classList.add('bg-info', 'text-dark');
            } else if (rs.status === 'Rejected') {
                statusEl.classList.add('bg-danger');
            } else if (rs.status === 'PO Created') {
                statusEl.classList.add('bg-info', 'text-dark');
            } else if (rs.status === 'Released') {
                statusEl.classList.add('bg-success');
            } else {
                statusEl.classList.add('bg-secondary');
            }
        }

        const remarksEl = document.getElementById('viewRsRemarks');
        if (remarksEl) {
            remarksEl.innerText = rs.remarks ? rs.remarks : 'No remarks provided.';
            remarksEl.style.whiteSpace = 'pre-wrap';
        }

        const reqEl = document.getElementById('viewRsRequestor');
        if (reqEl) reqEl.innerText = rs.requestor_name;

        const dateEl = document.getElementById('viewRsDate');
        if (dateEl) dateEl.innerText = rs.formatted_date;

        const qrContainer = document.getElementById('rsQrContainer');
        const printBtn = document.getElementById('printRsBtn');

        if ((rs.status === 'Approved' || rs.status === 'PO Created' || rs.status === 'Staged (Ready for Pickup)') && rs.type !== 'restock') {
            const qrData = encodeURIComponent(`REQ-DATA:${rs.rs_no}`);
            const qrImg = document.getElementById('viewRsQrCode');
            if (qrImg) qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${qrData}`;
            if (qrContainer) qrContainer.classList.remove('d-none');
            if (printBtn) printBtn.classList.remove('d-none');
        } else {
            if (qrContainer) qrContainer.classList.add('d-none');
            if (rs.status === 'Approved' || rs.status === 'PO Created' || rs.status === 'Staged (Ready for Pickup)') {
                if (printBtn) printBtn.classList.remove('d-none');
            } else {
                if (printBtn) printBtn.classList.add('d-none');
            }
        }

        const tbody = document.getElementById('viewRsItemsBody');
        if (tbody) {
            tbody.innerHTML = '';
            if (items.length > 0) {
                items.forEach(item => {
                    const itemName = item.item_name ? item.item_name : '<span class="text-danger">Item deleted</span>';
                    const unit = item.unit ? item.unit : '';
                    const reqQty = parseInt(item.quantity) || 0;
                    const curStock = parseInt(item.current_stock) || 0;
                    const totalPending = parseInt(item.total_pending) || 0;

                    let stockDisplay = '';
                    if (rs.type === 'restock') {
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
                                    return `<div class="mb-1 pb-1 border-bottom-dashed small"><div class="fw-bold text-dark text-truncate" style="max-width:180px;" title="${match[1]}">${match[1]}</div><div class="d-flex justify-content-between text-muted" style="font-size:0.65rem;"><span>Qty: <b>${match[2]}</b></span><span>By: <b>${match[3]}</b></span></div></div>`;
                                }
                                return `<div>${entry}</div>`;
                            }).join('');
                        }
                        pendingDisplay = `
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-warning text-dark dropdown-toggle py-0 px-2 fw-bold shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.75rem;">
                                    ${totalPending} ${unit} Pending
                                </button>
                                <div class="dropdown-menu dropdown-menu-end p-3 shadow-lg border-0" style="min-width: 240px;">
                                    <h6 class="dropdown-header px-0 text-uppercase fw-bold text-muted small border-bottom pb-2 mb-2">Pending RS Demand Details</h6>
                                    ${formattedDetails}
                                </div>
                            </div>
                        `;
                    } else {
                        pendingDisplay = `<span class="text-muted small">None</span>`;
                    }

                    tbody.innerHTML += `
                        <tr>
                            <td class="fw-bold text-primary">${item.item_code}</td>
                            <td class="fw-bold text-dark">${itemName}</td>
                            <td class="text-center fw-bold fs-6">${reqQty} ${unit}</td>
                            <td class="text-center">${stockDisplay}</td>
                            <td class="text-center">${pendingDisplay}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No items found in this requisition.</td></tr>`;
            }
        }

        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();

    } catch (err) {
        console.error('Error fetching RS details:', err);
        alert('Failed to load Requisition Slip details.');
    }
};

/* Global PO Modal Opener - Fetches PO details via AJAX and opens #viewPoModal */
window.openPoModalByNo = async function(poNo) {
    if (!poNo) return;
    const modalEl = document.getElementById('viewPoModal');
    if (!modalEl) return;

    try {
        const basePath = window.cimsBasePath || '';
        const response = await fetch(`${basePath}/process/get_po_details.php?po_no=${encodeURIComponent(poNo)}`);
        const data = await response.json();

        if (!data.success || !data.po) {
            alert(data.error || 'Purchase Order not found.');
            return;
        }

        const po = data.po;
        const items = data.items || [];

        document.getElementById('viewPoNo').innerText = po.po_no;
        document.getElementById('viewPoSupplier').innerText = po.supplier_name;
        document.getElementById('viewPoProject').innerText = po.project_name;
        document.getElementById('viewPoRsNo').innerText = po.rs_no;
        document.getElementById('viewPoEta').innerText = po.expected_delivery;
        document.getElementById('viewPoPreparedBy').innerText = po.prepared_by;
        document.getElementById('viewPoTotalVal').innerText = '₱' + Number(po.total_value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const statusEl = document.getElementById('viewPoStatus');
        if (statusEl) {
            statusEl.innerText = po.status;
            statusEl.className = 'badge shadow-sm';
            if (po.status.includes('Delayed')) {
                statusEl.classList.add('bg-danger');
            } else if (po.status === 'Delivered') {
                statusEl.classList.add('bg-success');
            } else if (po.status === 'Pending Delivery' || po.status === 'SMS Sent') {
                statusEl.classList.add('bg-warning', 'text-dark');
            } else {
                statusEl.classList.add('bg-info', 'text-dark');
            }
        }

        const delayBox = document.getElementById('viewPoDelayBox');
        const delayRemarks = document.getElementById('viewPoDelayRemarks');
        if (po.delay_remarks) {
            if (delayRemarks) delayRemarks.innerText = po.delay_remarks;
            if (delayBox) delayBox.classList.remove('d-none');
        } else {
            if (delayBox) delayBox.classList.add('d-none');
        }

        const tbody = document.getElementById('viewPoItemsBody');
        if (tbody) {
            tbody.innerHTML = '';
            if (items.length > 0) {
                items.forEach(item => {
                    const priceFormatted = '₱' + Number(item.unit_price || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const totalFormatted = '₱' + Number(item.total_price || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    tbody.innerHTML += `
                        <tr>
                            <td class="fw-bold text-primary">${item.item_code}</td>
                            <td class="fw-bold text-dark">${item.item_name}</td>
                            <td class="text-center fw-bold fs-6">${item.quantity} ${item.unit}</td>
                            <td class="text-end fw-semibold">${priceFormatted}</td>
                            <td class="text-end fw-bold text-success">${totalFormatted}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No items found in this Purchase Order.</td></tr>`;
            }
        }

        new bootstrap.Modal(modalEl).show();
    } catch (err) {
        console.error('Error fetching PO details:', err);
        alert('Failed to load Purchase Order details.');
    }
};

/* Global Withdrawal Modal Opener - Fetches Withdrawal details via AJAX and opens #viewWithdrawalModal */
window.openWithdrawalModalByNo = async function(withdrawalNo) {
    if (!withdrawalNo) return;
    const modalEl = document.getElementById('viewWithdrawalModal');
    if (!modalEl) return;

    try {
        const basePath = window.cimsBasePath || '';
        const response = await fetch(`${basePath}/process/get_withdrawal_details.php?withdrawal_no=${encodeURIComponent(withdrawalNo)}`);
        const data = await response.json();

        if (!data.success || !data.withdrawal) {
            alert(data.error || 'Material Withdrawal Slip not found.');
            return;
        }

        const wd = data.withdrawal;
        const items = data.items || [];

        document.getElementById('viewWdNo').innerText = wd.withdrawal_no;
        document.getElementById('viewWdProject').innerText = wd.project_name;
        document.getElementById('viewWdDate').innerText = wd.date_withdrawn;
        document.getElementById('viewWdReleaser').innerText = wd.releaser_name;
        document.getElementById('viewWdReceiver').innerText = wd.received_by;
        document.getElementById('viewWdRemarks').innerText = wd.remarks;

        const tbody = document.getElementById('viewWdItemsBody');
        if (tbody) {
            tbody.innerHTML = '';
            if (items.length > 0) {
                items.forEach(item => {
                    tbody.innerHTML += `
                        <tr>
                            <td class="fw-bold text-primary">${item.item_code}</td>
                            <td class="fw-bold text-dark">${item.item_name}</td>
                            <td class="text-center fw-bold fs-6">${item.quantity} ${item.unit}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-muted">No items found in this withdrawal slip.</td></tr>`;
            }
        }

        new bootstrap.Modal(modalEl).show();
    } catch (err) {
        console.error('Error fetching Withdrawal details:', err);
        alert('Failed to load Withdrawal Slip details.');
    }
};

/* Global Item Quick View Modal Opener - Fetches Item profile via AJAX and opens #viewItemQuickModal */
window.openItemModalByCode = async function(itemCode) {
    if (!itemCode) return;
    const modalEl = document.getElementById('viewItemQuickModal');
    if (!modalEl) return;

    try {
        const basePath = window.cimsBasePath || '';
        const response = await fetch(`${basePath}/process/get_item_details.php?item_code=${encodeURIComponent(itemCode)}`);
        const data = await response.json();

        if (!data.success || !data.item) {
            alert(data.error || 'Inventory item not found.');
            return;
        }

        const item = data.item;
        const recent = data.recent_withdrawals || [];

        document.getElementById('viewItemName').innerText = item.item_name;
        document.getElementById('viewItemCode').innerText = item.item_code;
        document.getElementById('viewItemCategory').innerText = item.category;
        document.getElementById('viewItemQty').innerText = item.quantity;
        document.getElementById('viewItemUnit').innerText = item.unit;
        document.getElementById('viewItemPrice').innerText = '₱' + item.price;
        document.getElementById('viewItem30d').innerText = item.consumed_30d + ' ' + item.unit;

        const badgeEl = document.getElementById('viewItemStatusBadge');
        if (badgeEl) {
            badgeEl.innerText = item.status;
            badgeEl.className = 'badge fs-6 shadow-sm';
            if (item.status === 'Out of Stock') {
                badgeEl.classList.add('bg-danger');
            } else if (item.status === 'Low Stock') {
                badgeEl.classList.add('bg-warning', 'text-dark');
            } else {
                badgeEl.classList.add('bg-success');
            }
        }

        const tbody = document.getElementById('viewItemRecentBody');
        if (tbody) {
            tbody.innerHTML = '';
            if (recent.length > 0) {
                recent.forEach(r => {
                    tbody.innerHTML += `
                        <tr>
                            <td class="fw-bold text-primary">${r.withdrawal_no}</td>
                            <td class="fw-bold text-dark text-truncate" style="max-width: 140px;">${r.project_name}</td>
                            <td class="text-center fw-bold">${r.quantity}</td>
                            <td class="text-end text-muted small">${r.formatted_date}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-muted small">No recent material releases.</td></tr>`;
            }
        }

        new bootstrap.Modal(modalEl).show();
    } catch (err) {
        console.error('Error fetching Item details:', err);
        alert('Failed to load item profile.');
    }
};