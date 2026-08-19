let currentProjectFilter = 'all';

function initProjectPagination() {
    function setupPagination(tableId, rowsPerPage) {
        const table = document.getElementById(tableId);
        if (!table) return;

        // Remove old wrapper if re-initializing
        const oldWrapper = table.parentElement.querySelector('.pagination-wrapper');
        if (oldWrapper) oldWrapper.remove();

        const tbody = table.querySelector('tbody');
        const allRows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));

        // Filter rows based on current active filter
        const visibleRows = allRows.filter(row => {
            if (currentProjectFilter === 'all') return true;
            const status = (row.getAttribute('data-status') || '').toLowerCase();
            return status === currentProjectFilter;
        });

        // Hide rows that don't match the filter
        allRows.forEach(row => {
            if (!visibleRows.includes(row)) {
                row.style.display = 'none';
            }
        });

        if (visibleRows.length === 0) return;
        if (visibleRows.length <= rowsPerPage) {
            visibleRows.forEach(row => { row.style.display = ''; });
            return;
        }

        let currentPage = 1;
        const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
        const paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'd-flex justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper';
        const infoText = document.createElement('span');
        infoText.className = 'text-muted small fw-bold';
        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group shadow-sm';
        const prevBtn = document.createElement('button');
        prevBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        prevBtn.type = 'button'; prevBtn.innerHTML = '<i class="bi bi-chevron-left me-1"></i> Prev';
        const pageIndicator = document.createElement('button');
        pageIndicator.className = 'btn btn-sm btn-brand fw-bold px-3 pe-none'; pageIndicator.type = 'button';
        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        nextBtn.type = 'button'; nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right ms-1"></i>';
        btnGroup.appendChild(prevBtn); btnGroup.appendChild(pageIndicator); btnGroup.appendChild(nextBtn);
        paginationWrapper.appendChild(infoText); paginationWrapper.appendChild(btnGroup);
        table.parentElement.appendChild(paginationWrapper);

        function showPage(page) {
            currentPage = page;
            const start = (page - 1) * rowsPerPage; const end = start + rowsPerPage;
            visibleRows.forEach((row, index) => { row.style.display = (index >= start && index < end) ? '' : 'none'; });
            infoText.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, visibleRows.length)}</b> of <b>${visibleRows.length}</b>`;
            pageIndicator.innerText = `Page ${page} / ${totalPages}`;
            prevBtn.disabled = page === 1; nextBtn.disabled = page === totalPages;
        }
        prevBtn.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
        nextBtn.addEventListener('click', () => { if (currentPage < totalPages) showPage(currentPage + 1); });
        showPage(1);
    }

    setupPagination('projectsTable', 10);
}

window.filterProjectsTable = function(filter, btnEl) {
    currentProjectFilter = filter;

    // Update active pill styling
    document.querySelectorAll('.proj-filter-btn').forEach(btn => {
        btn.classList.remove('active', 'btn-dark', 'btn-success', 'btn-secondary');
        const f = btn.getAttribute('data-filter');
        if (f === 'all') btn.classList.add('btn-outline-dark');
        else if (f === 'active') btn.classList.add('btn-outline-success');
        else if (f === 'inactive') btn.classList.add('btn-outline-secondary');
    });

    if (btnEl) {
        btnEl.classList.add('active');
        if (filter === 'all') {
            btnEl.classList.remove('btn-outline-dark');
            btnEl.classList.add('btn-dark');
        } else if (filter === 'active') {
            btnEl.classList.remove('btn-outline-success');
            btnEl.classList.add('btn-success');
        } else if (filter === 'inactive') {
            btnEl.classList.remove('btn-outline-secondary');
            btnEl.classList.add('btn-secondary');
        }
    }

    initProjectPagination();
};

if (document.readyState === "loading") { 
    document.addEventListener("DOMContentLoaded", initProjectPagination); 
} else { 
    initProjectPagination(); 
}

window.generateAutoProjectCode = function() {
    const year = new Date().getFullYear();
    const randNum = Math.floor(100 + Math.random() * 900);
    const autoCode = `PRJ-${year}-${randNum}`;
    const input = document.getElementById('projectCode');
    if (input) input.value = autoCode;
};

window.openAddProjectModal = function() {
    document.getElementById('projectModalTitle').innerText = 'Add New Project';
    document.getElementById('projectFormAction').value = 'add_project';
    document.getElementById('projectId').value = '';
    
    // Auto-generate a suggested Project ID
    generateAutoProjectCode();

    document.getElementById('projectName').value = '';
    const addrInput = document.getElementById('projectAddress');
    if (addrInput) addrInput.value = '';
    document.getElementById('projectDesc').value = '';
    document.getElementById('projectStatus').value = 'active';
};

window.openEditProjectModal = function(id, code, name, address, desc, status) {
    document.getElementById('projectModalTitle').innerText = 'Edit Project';
    document.getElementById('projectFormAction').value = 'edit_project';
    document.getElementById('projectId').value = id;
    document.getElementById('projectCode').value = code;
    document.getElementById('projectName').value = name;
    const addrInput = document.getElementById('projectAddress');
    if (addrInput) addrInput.value = address || '';
    document.getElementById('projectDesc').value = desc;
    document.getElementById('projectStatus').value = status;
    new bootstrap.Modal(document.getElementById('projectModal')).show();
};

