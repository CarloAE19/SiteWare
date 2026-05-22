function initProjectPagination() {
    function setupPagination(tableId, rowsPerPage) {
        const table = document.getElementById(tableId);
        if (!table || table.parentElement.querySelector('.pagination-wrapper')) return;
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
        if (rows.length <= rowsPerPage) return;
        let currentPage = 1;
        const totalPages = Math.ceil(rows.length / rowsPerPage);
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
            rows.forEach((row, index) => { row.style.display = (index >= start && index < end) ? '' : 'none'; });
            infoText.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, rows.length)}</b>`;
            pageIndicator.innerText = `Page ${page} / ${totalPages}`;
            prevBtn.disabled = page === 1; nextBtn.disabled = page === totalPages;
        }
        prevBtn.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
        nextBtn.addEventListener('click', () => { if (currentPage < totalPages) showPage(currentPage + 1); });
        showPage(1);
    }
    setupPagination('projectsTable', 10);
}

if (document.readyState === "loading") { 
    document.addEventListener("DOMContentLoaded", initProjectPagination); 
} else { 
    initProjectPagination(); 
}

window.openAddProjectModal = function() {
    document.getElementById('projectModalTitle').innerText = 'Add New Project';
    document.getElementById('projectFormAction').value = 'add_project';
    document.getElementById('projectId').value = '';
    document.getElementById('projectCode').value = '';
    document.getElementById('projectName').value = '';
    document.getElementById('projectDesc').value = '';
    document.getElementById('projectStatus').value = 'active';
};

window.openEditProjectModal = function(id, code, name, desc, status) {
    document.getElementById('projectModalTitle').innerText = 'Edit Project';
    document.getElementById('projectFormAction').value = 'edit_project';
    document.getElementById('projectId').value = id;
    document.getElementById('projectCode').value = code;
    document.getElementById('projectName').value = name;
    document.getElementById('projectDesc').value = desc;
    document.getElementById('projectStatus').value = status;
    new bootstrap.Modal(document.getElementById('projectModal')).show();
};
