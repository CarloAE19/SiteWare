// =====================================================================
//  AUDIT & PHYSICAL COUNT WORKSTATION — SPA & AJAX LOGIC
//  GB Construction & Enterprise Smart Inventory System
//  Standards: cims-modal-ajax-handler & quality-standards.md
// =====================================================================

// 1. Pagination & View Controller for Recount and History Tables
window.initAuditPagination = function() {
    function setupPagination(tableId, defaultRowsPerPage) {
        const table = document.getElementById(tableId);
        if (!table) return;

        // Clean up any existing pagination wrapper in SPA re-init
        const existingWrapper = table.parentElement.querySelector('.pagination-wrapper');
        if (existingWrapper) existingWrapper.remove();

        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
        if (rows.length === 0) return;

        let rowsPerPage = defaultRowsPerPage;
        let currentPage = 1;
        let isViewAll = false;
        let isSearching = false;

        const totalPages = () => Math.ceil(rows.length / rowsPerPage);

        const paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'd-flex flex-column flex-md-row justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper gap-2';

        const infoText = document.createElement('span');
        infoText.className = 'text-muted small fw-bold';

        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group shadow-sm';

        const prevBtn = document.createElement('button');
        prevBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        prevBtn.type = 'button';
        prevBtn.innerHTML = '<i class="bi bi-chevron-left me-1"></i> Prev';

        const pageIndicator = document.createElement('button');
        pageIndicator.className = 'btn btn-sm btn-brand fw-bold px-3 pe-none';
        pageIndicator.type = 'button';

        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        nextBtn.type = 'button';
        nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right ms-1"></i>';

        btnGroup.appendChild(prevBtn);
        btnGroup.appendChild(pageIndicator);
        btnGroup.appendChild(nextBtn);

        paginationWrapper.appendChild(infoText);
        paginationWrapper.appendChild(btnGroup);
        table.parentElement.appendChild(paginationWrapper);

        function showPage(page) {
            if (isViewAll || isSearching) return;
            currentPage = Math.max(1, Math.min(page, totalPages()));
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            rows.forEach((row, index) => {
                row.style.display = (index >= start && index < end) ? '' : 'none';
            });

            infoText.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, rows.length)}</b> of <b>${rows.length}</b> entries`;
            pageIndicator.innerText = `Page ${currentPage} / ${totalPages()}`;

            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages();
            paginationWrapper.style.display = 'flex';
        }

        prevBtn.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
        nextBtn.addEventListener('click', () => { if (currentPage < totalPages()) showPage(currentPage + 1); });

        // Wire View Mode buttons for recountTable if present
        if (tableId === 'recountTable') {
            const btnPaged = document.getElementById('btnViewPaged');
            const btnAll = document.getElementById('btnViewAll');

            if (btnPaged && btnAll) {
                btnPaged.addEventListener('click', () => {
                    isViewAll = false;
                    btnPaged.classList.add('active');
                    btnAll.classList.remove('active');
                    paginationWrapper.style.display = 'flex';
                    showPage(1);
                });

                btnAll.addEventListener('click', () => {
                    isViewAll = true;
                    btnAll.classList.add('active');
                    btnPaged.classList.remove('active');
                    rows.forEach(row => row.style.display = '');
                    paginationWrapper.style.display = 'none';
                });
            }

            // Synchronized Search handling
            const searchInput = document.getElementById('searchRecount');
            const clearBtn = document.getElementById('clearSearchRecount');

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.trim().toLowerCase();
                    if (clearBtn) clearBtn.classList.toggle('d-none', query.length === 0);

                    if (query.length > 0) {
                        isSearching = true;
                        paginationWrapper.style.display = 'none';
                        rows.forEach(row => {
                            const text = row.innerText.toLowerCase();
                            row.style.display = text.includes(query) ? '' : 'none';
                        });
                    } else {
                        isSearching = false;
                        if (isViewAll) {
                            rows.forEach(row => row.style.display = '');
                            paginationWrapper.style.display = 'none';
                        } else {
                            showPage(currentPage);
                        }
                    }
                });

                if (clearBtn) {
                    clearBtn.addEventListener('click', function() {
                        searchInput.value = '';
                        clearBtn.classList.add('d-none');
                        isSearching = false;
                        if (isViewAll) {
                            rows.forEach(row => row.style.display = '');
                            paginationWrapper.style.display = 'none';
                        } else {
                            showPage(currentPage);
                        }
                        searchInput.focus();
                    });
                }
            }
        }

        // Synchronized Search handling for historyTable
        if (tableId === 'historyTable') {
            const searchInput = document.getElementById('searchAuditHistory');
            const clearBtn = document.getElementById('clearSearchHistory');

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.trim().toLowerCase();
                    if (clearBtn) clearBtn.classList.toggle('d-none', query.length === 0);

                    if (query.length > 0) {
                        isSearching = true;
                        paginationWrapper.style.display = 'none';
                        rows.forEach(row => {
                            const text = row.innerText.toLowerCase();
                            row.style.display = text.includes(query) ? '' : 'none';
                        });
                    } else {
                        isSearching = false;
                        showPage(currentPage);
                    }
                });

                if (clearBtn) {
                    clearBtn.addEventListener('click', function() {
                        searchInput.value = '';
                        clearBtn.classList.add('d-none');
                        isSearching = false;
                        showPage(currentPage);
                        searchInput.focus();
                    });
                }
            }
        }

        showPage(1);
    }

    setupPagination('historyTable', 10);
    setupPagination('recountTable', 10);
};

// 1.1 Delegate View Trail modal clicks safely (Data attribute handler)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-view-audit');
    if (!btn) return;
    const month = btn.getAttribute('data-month') || '';
    const remarks = btn.getAttribute('data-remarks') || '';
    const itemsJson = btn.getAttribute('data-items') || '[]';
    if (typeof window.viewAuditDetails === 'function') {
        window.viewAuditDetails(month, remarks, itemsJson);
    }
});

// 2. Real-Time Discrepancy Calculator & Summary Tracker
window.stepPhysQty = function(index, delta) {
    const input = document.getElementById('physInput_' + index);
    if (!input) return;
    let currentVal = parseInt(input.value) || 0;
    let newVal = Math.max(0, currentVal + delta);
    input.value = newVal;
    input.dispatchEvent(new Event('input'));
};

window.switchAuditTab = function(tabId) {
    const btn = document.getElementById(tabId + '-tab');
    if (btn && typeof bootstrap !== 'undefined') {
        const tab = new bootstrap.Tab(btn);
        tab.show();
    }
};

window.initDiscrepancyCalculator = function() {
    function updateSummary() {
        let matchCount = 0;
        let diffCount = 0;
        document.querySelectorAll('.phys-input').forEach(inp => {
            const idx = inp.getAttribute('data-index');
            const sys = parseInt(document.getElementById('sysQty_' + idx)?.innerText) || 0;
            const phys = parseInt(inp.value) || 0;
            if (phys === sys) {
                matchCount++;
            } else {
                diffCount++;
            }
        });
        const matchEl = document.getElementById('recountMatchCount');
        const diffEl = document.getElementById('recountDiffCount');
        if (matchEl) matchEl.innerText = matchCount;
        if (diffEl) diffEl.innerText = diffCount;
    }

    document.querySelectorAll('.phys-input').forEach(input => {
        // Auto-select value on tap/focus so auditor can type directly
        input.addEventListener('focus', function() { this.select(); });
        input.addEventListener('click', function() { this.select(); });

        input.addEventListener('input', function() {
            const index  = this.getAttribute('data-index');
            const sysEl  = document.getElementById('sysQty_' + index);
            if (!sysEl) return;

            const sysQty  = parseInt(sysEl.innerText) || 0;
            const physQty = Math.max(0, parseInt(this.value) || 0);
            const diff    = physQty - sysQty;
            const badge   = document.getElementById('diff_' + index);

            if (badge) {
                if (diff === 0) {
                    badge.className = 'badge bg-success fs-6 w-100 py-2 shadow-sm text-uppercase diff-badge';
                    badge.innerHTML = '<i class="bi bi-check-circle me-1"></i> Match';
                } else if (diff > 0) {
                    badge.className = 'badge bg-warning text-dark fs-6 w-100 py-2 shadow-sm text-uppercase diff-badge';
                    badge.innerHTML = '<i class="bi bi-arrow-up-circle-fill me-1"></i> +' + diff + ' Over';
                } else {
                    badge.className = 'badge bg-danger fs-6 w-100 py-2 shadow-sm text-uppercase diff-badge';
                    badge.innerHTML = '<i class="bi bi-arrow-down-circle-fill me-1"></i> ' + diff + ' Short';
                }
            }

            updateSummary();
        });

        // Trigger once on load to initialize discrepancy badges
        input.dispatchEvent(new Event('input'));
    });
};

// 3. Physical Recount Review Modal & AJAX Submission Handler
window.initPhysicalCountReviewAndSubmit = function() {
    const btnOpenReview = document.getElementById('btnOpenRecountReview');
    const confirmModalEl = document.getElementById('confirmRecountModal');
    const form = document.getElementById('physicalCountForm');
    const btnConfirmSubmit = document.getElementById('btnConfirmRecountSubmit');

    if (!btnOpenReview || !confirmModalEl || !form || !btnConfirmSubmit) return;

    let confirmModal = null;
    if (typeof bootstrap !== 'undefined') {
        confirmModal = bootstrap.Modal.getInstance(confirmModalEl) || new bootstrap.Modal(confirmModalEl);
    }

    // A. Open Review Modal & Populate Discrepancies
    btnOpenReview.addEventListener('click', () => {
        // Validate form inputs
        let hasInvalidInput = false;
        document.querySelectorAll('.phys-input').forEach(inp => {
            const val = parseInt(inp.value);
            if (isNaN(val) || val < 0) {
                hasInvalidInput = true;
                inp.classList.add('is-invalid');
            } else {
                inp.classList.remove('is-invalid');
            }
        });

        if (hasInvalidInput) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Quantities',
                    text: 'Please ensure all physical count quantities are valid non-negative numbers.'
                });
            } else {
                alert('Please ensure all physical count quantities are valid non-negative numbers.');
            }
            return;
        }

        const rows = document.querySelectorAll('#recountTable tbody tr');
        let totalCount = rows.length;
        let matchCount = 0;
        let diffCount = 0;
        const discrepancies = [];

        rows.forEach(row => {
            const title = row.querySelector('.item-title')?.innerText.trim() || 'Item';
            const code = row.querySelector('.item-code')?.innerText.trim() || '';
            const unit = row.querySelector('.item-unit')?.innerText.trim() || '';
            const physInput = row.querySelector('.phys-input');
            const idx = physInput?.getAttribute('data-index');
            const sysQty = parseInt(document.getElementById('sysQty_' + idx)?.innerText) || 0;
            const physQty = Math.max(0, parseInt(physInput?.value) || 0);
            const diff = physQty - sysQty;

            if (diff === 0) {
                matchCount++;
            } else {
                diffCount++;
                discrepancies.push({
                    title: title,
                    code: code,
                    unit: unit,
                    sysQty: sysQty,
                    physQty: physQty,
                    diff: diff
                });
            }
        });

        // Set summary cards in modal
        const modalTotal = document.getElementById('modalReviewTotal');
        const modalMatches = document.getElementById('modalReviewMatches');
        const modalDiffs = document.getElementById('modalReviewDiffs');
        const modalBadge = document.getElementById('modalReviewBadge');
        const modalRemarks = document.getElementById('modalReviewRemarks');
        const modalBody = document.getElementById('modalDiscrepanciesBody');

        if (modalTotal) modalTotal.innerText = totalCount;
        if (modalMatches) modalMatches.innerText = matchCount;
        if (modalDiffs) modalDiffs.innerText = diffCount;
        if (modalBadge) modalBadge.innerText = diffCount + ' adjustment' + (diffCount === 1 ? '' : 's');

        const remarksVal = document.getElementById('auditRemarks')?.value.trim();
        if (modalRemarks) {
            modalRemarks.innerText = remarksVal ? remarksVal : 'None provided.';
            modalRemarks.style.fontStyle = remarksVal ? 'normal' : 'italic';
        }

        // Build Discrepancy Table Rows
        if (modalBody) {
            if (discrepancies.length === 0) {
                modalBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-success py-3 fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> All physical counts match system inventory perfectly! No stock adjustments required.
                        </td>
                    </tr>
                `;
            } else {
                modalBody.innerHTML = discrepancies.map(item => {
                    const badgeClass = item.diff > 0 ? 'bg-warning text-dark' : 'bg-danger';
                    const diffText = item.diff > 0 ? `+${item.diff} Over` : `${item.diff} Short`;
                    const icon = item.diff > 0 ? 'bi-arrow-up-circle-fill' : 'bi-arrow-down-circle-fill';

                    return `
                        <tr>
                            <td class="ps-3">
                                <span class="fw-bold text-dark d-block">${item.title}</span>
                                <small class="text-muted text-uppercase fw-bold">${item.code}</small>
                            </td>
                            <td class="text-center fw-bold text-secondary">${item.sysQty} <small class="text-muted">${item.unit}</small></td>
                            <td class="text-center fw-bold text-primary">${item.physQty} <small class="text-muted">${item.unit}</small></td>
                            <td class="text-center pe-3">
                                <span class="badge ${badgeClass} shadow-sm px-2.5 py-1.5 text-uppercase">
                                    <i class="bi ${icon} me-1"></i>${diffText}
                                </span>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        }

        if (confirmModal) {
            confirmModal.show();
        }
    });

    // B. AJAX Submission on Confirmation
    btnConfirmSubmit.addEventListener('click', async () => {
        const originalBtnHtml = btnConfirmSubmit.innerHTML;

        // Prevent double submit and display loading spinner
        btnConfirmSubmit.disabled = true;
        btnConfirmSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Reconciling...';

        const cancelBtns = confirmModalEl.querySelectorAll('[data-bs-dismiss="modal"]');
        cancelBtns.forEach(btn => btn.disabled = true);

        try {
            const formData = new FormData(form);
            const targetAction = (typeof form.getAttribute === 'function' ? form.getAttribute('action') : null) || 'process/process.php';
            const basePath = window.cimsBasePath || (window.location.pathname.includes('/CIMS') ? '/CIMS' : '');
            const submitUrl = targetAction.startsWith('/') 
                ? targetAction 
                : (basePath ? `${basePath}/${targetAction.replace(/^\/+/, '')}` : targetAction);

            const response = await fetch(submitUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'ngrok-skip-browser-warning': 'true'
                }
            });

            const rawText = await response.text();
            let result = null;
            try {
                result = JSON.parse(rawText);
            } catch (jsonErr) {
                console.error('Non-JSON server response:', rawText);
                throw new Error('Server returned an unexpected response (Status ' + response.status + '). Please try again.');
            }

            if (result && (result.success || result.status === 'success')) {
                if (confirmModal) confirmModal.hide();

                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Inventory Reconciled!',
                        text: result.message || 'Weekly recount submitted successfully. Inventory has been adjusted.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    alert(result.message || 'Weekly recount submitted successfully.');
                }

                // Redirect to audit history page
                window.location.href = 'audit';
            } else {
                throw new Error(result?.message || 'Failed to submit physical count.');
            }
        } catch (error) {
            console.error('Audit Submit Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Error',
                    text: error.message || 'Failed to process recount. Please try again.'
                });
            } else {
                alert('Submission Error: ' + (error.message || 'Failed to process recount.'));
            }
        } finally {
            btnConfirmSubmit.disabled = false;
            btnConfirmSubmit.innerHTML = originalBtnHtml;
            cancelBtns.forEach(btn => btn.disabled = false);
        }
    });
};

// Initialize Everything on DOM Load
document.addEventListener('DOMContentLoaded', () => {
    window.initAuditPagination();
    window.initDiscrepancyCalculator();
    window.initPhysicalCountReviewAndSubmit();
});

// Immediate execution fallback for dynamic/SPA environments
if (document.readyState === 'interactive' || document.readyState === 'complete') {
    window.initAuditPagination();
    window.initDiscrepancyCalculator();
    window.initPhysicalCountReviewAndSubmit();
}

