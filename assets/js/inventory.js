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
    // 2. AJAX STOCK-IN FORM SUBMISSION (SPA SAFE)
    // ==========================================
    // FIXED 2: We use Event Delegation so this survives page transitions perfectly
    document.body.addEventListener('submit', async (e) => {
        if (e.target.id === 'stockInForm') {
            e.preventDefault(); 
            
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            const formData = new FormData(form);
            // FIXED 3: This tells the PHP backend to return JSON instead of reloading the page!
            formData.append('ajax', '1'); 
            
            const itemCode = formData.get('item_code');
            const addedQty = formData.get('added_qty');
            
            try {
                const response = await fetch('process/process.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Play success sound
                    new Audio('assets/sounds/success.mp3').play().catch(err => {});
                    
                    // Dynamically update UI
                    const qtyEl = document.getElementById('qty_' + itemCode);
                    const statusEl = document.getElementById('status_' + itemCode);
                    
                    if (qtyEl && statusEl) {
                        qtyEl.innerText = data.new_qty;
                        qtyEl.className = 'fw-bold fs-5 text-success'; 
                        setTimeout(() => { qtyEl.className = 'fw-bold fs-6'; }, 2000);
                        
                        statusEl.innerText = data.new_status;
                        if(data.new_status === 'Out of Stock') statusEl.className = 'badge bg-danger';
                        else if(data.new_status === 'Low Stock') statusEl.className = 'badge bg-warning text-dark';
                        else statusEl.className = 'badge bg-success';
                    }
                    
                    // Update global memory if it exists
                    if (typeof inventoryData !== 'undefined') {
                        let foundItem = inventoryData.find(item => item.item_code === itemCode);
                        if(foundItem) foundItem.quantity = data.new_qty;
                    }

                    // Close Modal gracefully
                    const modalEl = document.getElementById('deliveryScannerModal');
                    if (modalEl) {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }
                    
                } else {
                    alert("Server Error: " + data.message);
                }
            } catch (error) {
                alert("Network Error. Could not process stock-in.");
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }
    });
});

// ==========================================
// 3. QR SCANNER & PRINTING LOGIC
// ==========================================
window.html5QrcodeScanner = null;

// FIXED 4: Updated IDs to match the new mobile-responsive 'index.php' modal!
window.startDeliveryScanner = function() {
    const modalEl = document.getElementById('deliveryScannerModal');
    if (!modalEl) return;
    
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    document.getElementById('reader').style.display = 'block';
    document.getElementById('stockInForm').classList.add('d-none');
    document.getElementById('scannerResult').innerHTML = "Point your camera at the item's QR Code...";

    if (!window.html5QrcodeScanner) {
        window.html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);
    }

    window.html5QrcodeScanner.render((decodedText, decodedResult) => {
        const itemCode = decodedText.trim();
        
        // Check if item exists in global inventoryData
        const item = (typeof inventoryData !== 'undefined') ? inventoryData.find(i => i.item_code === itemCode) : null;
        
        if (item) {
            new Audio('assets/sounds/scan.mp3').play().catch(e => {});
            
            // Stop scanner camera
            window.html5QrcodeScanner.clear().then(() => { window.html5QrcodeScanner = null; }).catch(e=>{});
            document.getElementById('reader').style.display = 'none';
            
            // Populate the Delivery Form
            document.getElementById('scannerResult').innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i> QR Code Recognized!</span>`;
            document.getElementById('scan_item_code').value = item.item_code;
            
            // These IDs must match your index.php!
            const nameEl = document.getElementById('scan_item_name');
            const catEl = document.getElementById('scan_item_category');
            const unitEl = document.getElementById('scan_item_unit');
            
            if(nameEl) nameEl.innerText = item.item_name;
            if(catEl) catEl.innerText = item.category;
            if(unitEl) unitEl.innerText = item.unit;
            
            document.getElementById('stockInForm').classList.remove('d-none');
            document.getElementById('stockInForm').reset(); // Clear previous inputs
            
            // Auto-focus the quantity box so the user can just start typing!
            setTimeout(() => {
                const qtyInput = document.getElementById('scan_added_qty');
                if(qtyInput) qtyInput.focus();
            }, 300);
        } else {
            document.getElementById('scannerResult').innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i> Item "${itemCode}" not found.</span>`;
        }
    }, (error) => {});
};

window.stopScanner = function() {
    if (window.html5QrcodeScanner) {
        window.html5QrcodeScanner.clear().then(() => { window.html5QrcodeScanner = null; }).catch(e=>{});
    }
};

// Auto-stop scanner camera when the user clicks out of the modal
document.body.addEventListener('hidden.bs.modal', function (e) {
    if (e.target.id === 'deliveryScannerModal') {
        window.stopScanner();
    }
});

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