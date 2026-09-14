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

if (document.readyState !== "loading") {
    initInventoryPagination();
}