// =====================================================================
//  AUDIT PAGE — SPA-Proof Logic & Real-Time Discrepancy Calculator
//  GB Construction & Enterprise Smart Inventory System
// =====================================================================

// 1. Pagination Logic
window.initAuditPagination = function() {
    function setupPagination(tableId, rowsPerPage) {
        const table = document.getElementById(tableId);
        if (!table) return;
        if (table.parentElement.querySelector('.pagination-wrapper')) return;

        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
        if (rows.length <= rowsPerPage) return;

        let currentPage = 1;
        const totalPages = Math.ceil(rows.length / rowsPerPage);

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
            currentPage = page;
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            rows.forEach((row, index) => {
                row.style.display = (index >= start && index < end) ? '' : 'none';
            });

            infoText.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, rows.length)}</b> of <b>${rows.length}</b> entries`;
            pageIndicator.innerText = `Page ${page} / ${totalPages}`;

            prevBtn.disabled = page === 1;
            nextBtn.disabled = page === totalPages;
        }

        prevBtn.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
        nextBtn.addEventListener('click', () => { if (currentPage < totalPages) showPage(currentPage + 1); });

        showPage(1);
    }

    setupPagination('historyTable', 10);
    setupPagination('recountTable', 10);
};

// 2. Real-Time Discrepancy Calculator
window.initDiscrepancyCalculator = function() {
    document.querySelectorAll('.phys-input').forEach(input => {
        // Remove old listeners to prevent double-firing in SPA
        const new_input = input.cloneNode(true);
        input.parentNode.replaceChild(new_input, input);

        // Auto-select value on tap/focus so user can type immediately without erasing
        new_input.addEventListener('focus', function() { this.select(); });
        new_input.addEventListener('click', function() { this.select(); });

        new_input.addEventListener('input', function() {
            const index  = this.getAttribute('data-index');
            const sysQty = parseInt(document.getElementById('sysQty_' + index).innerText) || 0;
            const physQty = parseInt(this.value) || 0;
            const diff   = physQty - sysQty;
            const badge  = document.getElementById('diff_' + index);

            if (diff === 0) {
                badge.className = 'badge bg-success fs-6 w-100 py-2 shadow-sm text-uppercase';
                badge.innerHTML = '<i class="bi bi-check-circle me-1"></i> Match';
            } else if (diff > 0) {
                badge.className = 'badge bg-warning text-dark fs-6 w-100 py-2 shadow-sm text-uppercase';
                badge.innerHTML = '<i class="bi bi-arrow-up-circle me-1"></i> +' + diff + ' Over';
            } else {
                badge.className = 'badge bg-danger fs-6 w-100 py-2 shadow-sm text-uppercase';
                badge.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i> ' + diff + ' Short';
            }
        });

        // Trigger once on load to initialize discrepancy badges
        new_input.dispatchEvent(new Event('input'));
    });
};

// Initialize Everything on page load
window.initAuditPagination();
window.initDiscrepancyCalculator();
