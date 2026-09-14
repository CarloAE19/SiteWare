/* ==========================================================
 * GB INVENTORY - SCANNER & SYNC ENGINE
 * Handles QR scanning, Label Printing, AJAX, and Live Sync
 * ========================================================== */

document.addEventListener("DOMContentLoaded", () => {
    
    // ==========================================
    // 1. MULTI-DEVICE LIVE SYNC (BACKGROUND POLLING)
    // ==========================================
    setInterval(async () => {
        // FIXED 1: Now properly checks if we are on the Inventory page by looking for the table!
        if (!document.getElementById('inventoryTable')) return; 
        
        try {
            let formData = new FormData();
            formData.append('action', 'live_sync');
            
            const response = await fetch('process/process.php', { method: 'POST', body: formData });
            const liveData = await response.json();
            
            liveData.forEach(item => {
                let qtyEl = document.getElementById('qty_' + item.item_code);
                let statusEl = document.getElementById('status_' + item.item_code);
                
                if (qtyEl && parseInt(qtyEl.innerText) !== parseInt(item.quantity)) {
                    qtyEl.innerText = item.quantity;
                    qtyEl.className = 'fw-bold fs-5 text-primary'; 
                    setTimeout(() => { qtyEl.className = 'fw-bold fs-6'; }, 2000);
                    
                    if (statusEl) {
                        statusEl.innerText = item.status;
                        if(item.status === 'Out of Stock') statusEl.className = 'badge bg-danger';
                        else if(item.status === 'Low Stock') statusEl.className = 'badge bg-warning text-dark';
                        else statusEl.className = 'badge bg-success';
                    }
                }
            });
        } catch (e) {
            // Silently ignore network errors to prevent console spam
        }
    }, 3000); 

    // ==========================================
    // 2. ITEM MANAGEMENT & LIVE POLLING
    // ==========================================
});

// ==========================================
// 3. QR LABEL PRINTING (PHYSICAL SHELF LABELS)
// ==========================================

// QR Label Printing
window.showItemQR = function(itemCode, itemName) {
    document.getElementById('qrItemCode').innerText = itemCode;
    document.getElementById('qrItemName').innerText = itemName;
    
    const qrImg = document.getElementById('qrItemImg');
    const qrLogoImg = document.getElementById('qrLogoImg');
    
    if (qrLogoImg) qrLogoImg.classList.add('d-none');
    
    qrImg.onload = function() {
        if (qrLogoImg) qrLogoImg.classList.remove('d-none');
    };
    
    qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${itemCode}&ecc=H`;
    const modalEl = document.getElementById('itemQrModal');
    if (modalEl) new bootstrap.Modal(modalEl).show();
};

window.printItemLabel = function() {
    const printContent = document.getElementById('qrPrintArea').innerHTML;
    const originalContent = document.body.innerHTML;
    document.body.innerHTML = `<div style="display:flex; justify-content:center; align-items:center; height:100vh;">${printContent}</div>`;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload(); 
};

let searchQuery = '';

document.addEventListener("DOMContentLoaded", function() {
    
    // 1. COLUMN TOGGLE LOGIC
    document.querySelectorAll('.col-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const colIndex = this.value;
            const table = document.getElementById('inventoryTable');
            if (this.checked) {
                table.classList.remove('hide-col-' + colIndex);
            } else {
                table.classList.add('hide-col-' + colIndex);
            }
        });
    });

    // 2. LIVE SEARCH LOGIC
    const searchInput = document.getElementById('searchInventory');
    if(searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            searchQuery = e.target.value.toLowerCase();
            initInventoryPagination();
        });
        searchInput.closest('form').addEventListener('submit', function(e) {
            e.preventDefault();
        });
    }
    
    initInventoryPagination();
});

function initInventoryPagination() {
    const table = document.getElementById('inventoryTable');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const allRows = Array.from(tbody.querySelectorAll('tr.item-row'));
    
    if (allRows.length === 0) return;

    const activeRows = allRows.filter(row => {
        return searchQuery === '' || row.innerText.toLowerCase().includes(searchQuery);
    });

    allRows.forEach(row => row.style.display = 'none');

    let noDataRow = tbody.querySelector('.no-data-alert-row');
    if (activeRows.length === 0) {
        if (!noDataRow) {
            noDataRow = document.createElement('tr');
            noDataRow.className = 'no-data-alert-row';
            noDataRow.innerHTML = '<td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-search fs-1 d-block mb-2"></i>No matching items found.</td>';
            tbody.appendChild(noDataRow);
        }
        noDataRow.style.display = '';
        const pw = table.parentElement.querySelector('.pagination-wrapper');
        if (pw) pw.style.display = 'none';
        return;
    } else {
        if (noDataRow) noDataRow.style.display = 'none';
    }

    const rowsPerPage = 10;
    let currentPage = window.currentInvPage || 1; 
    const totalPages = Math.ceil(activeRows.length / rowsPerPage);
    if (currentPage > totalPages) currentPage = 1; 
    window.currentInvPage = currentPage;

    let paginationWrapper = table.parentElement.querySelector('.pagination-wrapper');
    if (!paginationWrapper) {
        paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'd-flex flex-column flex-md-row justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper gap-3';
        
        paginationWrapper.innerHTML = `
            <span class="text-muted small fw-bold" id="pageInfoText"></span>
            <div class="btn-group shadow-sm">
                <button class="btn btn-sm btn-outline-primary fw-bold px-3" id="prevPageBtn"><i class="bi bi-chevron-left me-1"></i> Prev</button>
                <button class="btn btn-sm btn-brand fw-bold px-3 pe-none" id="pageIndicatorBtn"></button>
                <button class="btn btn-sm btn-outline-primary fw-bold px-3" id="nextPageBtn">Next <i class="bi bi-chevron-right ms-1"></i></button>
            </div>
        `;
        table.parentElement.appendChild(paginationWrapper);

        document.getElementById('prevPageBtn').addEventListener('click', () => { 
            if (window.currentInvPage > 1) { window.currentInvPage--; showPage(); }
        });
        document.getElementById('nextPageBtn').addEventListener('click', () => { 
            if (window.currentInvPage < Math.ceil(activeRows.length / rowsPerPage)) { window.currentInvPage++; showPage(); }
        });
    }
    paginationWrapper.style.display = 'flex';

    function showPage() {
        activeRows.forEach(row => row.style.display = 'none'); 

        const start = (window.currentInvPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        for (let i = start; i < end && i < activeRows.length; i++) {
            activeRows[i].style.display = ''; 
        }

        document.getElementById('pageInfoText').innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, activeRows.length)}</b> of <b>${activeRows.length}</b> entries`;
        document.getElementById('pageIndicatorBtn').innerText = `Page ${window.currentInvPage} / ${totalPages}`;
        
        document.getElementById('prevPageBtn').disabled = window.currentInvPage === 1;
        document.getElementById('nextPageBtn').disabled = window.currentInvPage === totalPages;
    }

    showPage();
}

// ==========================================================
// 4. ITEM MODAL AJAX HANDLER & LIFECYCLE (SKILL & QUALITY STANDARDS)
// ==========================================================
function initItemModalAjax() {
    const form = document.getElementById('itemModalForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '<i class="bi bi-save me-1.5"></i>Save Item';

        // 1. Client-side validation check
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const qtyInput = form.querySelector('[name="quantity"]');
        if (qtyInput && parseInt(qtyInput.value, 10) < 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Invalid Quantity', text: 'Quantity cannot be negative.' });
            } else {
                alert('Quantity cannot be negative.');
            }
            return;
        }

        const priceInput = form.querySelector('[name="unit_price"]');
        if (priceInput && parseFloat(priceInput.value) < 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Invalid Price', text: 'Unit price cannot be negative.' });
            } else {
                alert('Unit price cannot be negative.');
            }
            return;
        }

        // 2. Prevent duplicate submits & show loading state
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1.5" role="status" aria-hidden="true"></span> Saving...';
        }

        try {
            const formData = new FormData(form);
            formData.append('ajax', '1');
            const csrfToken = form.querySelector('input[name="csrf_token"]')?.value ||
                              document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();
            const isSuccess = result.success === true || result.status === 'success';

            if (isSuccess) {
                // Hide modal
                const modalEl = document.getElementById('itemModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                // Success notification & seamless reload
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: result.message || 'Material saved successfully.',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    alert(result.message || 'Material saved successfully.');
                    window.location.reload();
                }
            } else {
                throw new Error(result.message || 'Failed to save material.');
            }
        } catch (error) {
            console.error('AJAX Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to process request.'
                });
            } else {
                alert(error.message || 'Failed to process request.');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    });

    // Lifecycle: Auto-focus & cleanup
    const itemModalEl = document.getElementById('itemModal');
    if (itemModalEl) {
        itemModalEl.addEventListener('shown.bs.modal', () => {
            const nameInput = document.getElementById('itemName');
            if (nameInput) nameInput.focus();
        });

        itemModalEl.addEventListener('hidden.bs.modal', () => {
            form.reset();
            form.classList.remove('was-validated');
        });
    }
}

// ==========================================================
// 5. ASYNC ITEM DELETION (SWEETALERT2 CONFIRMATION & AUDIT)
// ==========================================================
window.deleteInventoryItem = function(id, itemCode, itemName) {
    const doDelete = async () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);
        formData.append('ajax', '1');

        try {
            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();
            const isSuccess = result.success === true || result.status === 'success';

            if (isSuccess) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: result.message || 'Material deleted successfully.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }

                // Smooth row transition & DOM removal
                const row = document.getElementById('inventory_row_' + id);
                if (row) {
                    row.style.transition = 'all 0.35s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        row.remove();
                        if (typeof initInventoryPagination === 'function') {
                            initInventoryPagination();
                        }
                    }, 350);
                }
            } else {
                throw new Error(result.message || 'Failed to delete material.');
            }
        } catch (err) {
            console.error('Delete error:', err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Delete Failed',
                    text: err.message || 'Could not delete item. Please try again.'
                });
            } else {
                alert(err.message || 'Could not delete item. Please try again.');
            }
        }
    };

    if (typeof Swal === 'undefined') {
        if (confirm(`Are you sure you want to delete material "${itemName}" (${itemCode})? This action cannot be undone.`)) {
            doDelete();
        }
        return;
    }

    Swal.fire({
        title: 'Delete Material?',
        html: `Are you sure you want to delete <strong>${itemName}</strong> (<code>${itemCode}</code>)?<br><small class="text-danger mt-2 d-block"><i class="bi bi-exclamation-triangle me-1"></i>This action cannot be undone and will be permanently recorded in audit logs.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Yes, delete item',
        cancelButtonText: 'Cancel'
    }).then((res) => {
        if (res.isConfirmed) {
            doDelete();
        }
    });
};

document.addEventListener("DOMContentLoaded", function() {
    initItemModalAjax();
});

if (document.readyState !== "loading") {
    initInventoryPagination();
    initItemModalAjax();
}