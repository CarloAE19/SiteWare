/* ==========================================================
 * GB INVENTORY - MAIN SCRIPTS
 * Handles Sidebar Toggle, Passwords, Modals, and Scanners
 * ========================================================== */

document.addEventListener('DOMContentLoaded', () => {
    // 1. SIDEBAR TOGGLE LOGIC
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const sidebar = document.getElementById('sidebar');
    const sidebarClose = document.getElementById('sidebarClose');
    const contentArea = document.getElementById('content');

    // Desktop Toggle
    if (sidebarCollapse && sidebar) {
        sidebarCollapse.addEventListener('click', () => sidebar.classList.toggle('active'));
    }
    
    // Mobile Close Button
    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', () => sidebar.classList.remove('active'));
    }
    
    // Close sidebar when clicking outside on mobile
    if (contentArea && sidebar) {
        contentArea.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active') && !e.target.closest('#sidebarCollapse')) {
                sidebar.classList.remove('active');
            }
        });
    }

    // 2. RECEIVE QR SCANNER INIT
    const startBtn = document.getElementById('startReceiveScannerBtn');
    if (startBtn) {
        startBtn.addEventListener('click', function() {
            new bootstrap.Modal(document.getElementById('receiveScanModal')).show();
            resetReceiveScanner();
        });
    }

    // 3. RESET PASSWORD FIELD ON MODAL CLOSE
    const userModalEl = document.getElementById('userModal');
    if (userModalEl) {
        userModalEl.addEventListener('hidden.bs.modal', function () {
            const input = document.getElementById('userPassword');
            const icon = document.getElementById('toggleUserIcon');
            if (input && icon) {
                input.type = "password";
                icon.classList.replace("bi-eye", "bi-eye-slash");
                icon.classList.replace("text-primary", "text-muted");
            }
        });
    }
});

/* --- PASSWORD TOGGLES --- */
function toggleUserPass() {
    const input = document.getElementById('userPassword');
    const icon = document.getElementById('toggleUserIcon');
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

function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("bi-eye-slash", "bi-eye");
    } else {
        input.type = "password";
        icon.classList.replace("bi-eye", "bi-eye-slash");
    }
}

/* --- QR SCANNER & PRINTING LOGIC --- */
let receiveScanner;

function resetReceiveScanner() {
    document.getElementById('receive-qr-reader').style.display = 'block';
    document.getElementById('receiveFormContainer').style.display = 'none';
    if (receiveScanner) receiveScanner.clear();
    
    if (typeof Html5QrcodeScanner !== 'undefined') {
        receiveScanner = new Html5QrcodeScanner("receive-qr-reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);
        receiveScanner.render(onReceiveScanSuccess);
    }
}

function stopReceiveScanner() {
    if (receiveScanner) receiveScanner.clear();
}

function onReceiveScanSuccess(decodedText) {
    // FIXED: Play local scanner beep sound!
    let scanAudio = new Audio('assets/sounds/scan.mp3');
    scanAudio.play().catch(e => console.log("Audio play blocked by browser."));
    
    let foundItem = typeof inventoryData !== 'undefined' ? inventoryData.find(item => item.item_code === decodedText) : null;
    
    if(foundItem) {
        document.getElementById('scannedItemDisplayName').innerText = foundItem.item_name + " (" + foundItem.item_code + ")";
        document.getElementById('scannedInputCode').value = decodedText;
        
        receiveScanner.clear();
        document.getElementById('receive-qr-reader').style.display = 'none';
        document.getElementById('receiveFormContainer').style.display = 'block';
        setTimeout(() => document.querySelector('input[name="added_qty"]').focus(), 500);
    } else {
        alert("Scanned item (" + decodedText + ") not found in inventory!");
    }
}

function showItemQR(itemCode, itemName) {
    document.getElementById('qrItemCode').innerText = itemCode;
    document.getElementById('qrItemName').innerText = itemName;
    document.getElementById('qrItemImg').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${itemCode}`;
    new bootstrap.Modal(document.getElementById('itemQrModal')).show();
}

function printItemLabel() {
    const printContent = document.getElementById('qrPrintArea').innerHTML;
    const originalContent = document.body.innerHTML;
    document.body.innerHTML = `<div style="display:flex; justify-content:center; align-items:center; height:100vh;">${printContent}</div>`;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload(); 
}

/* --- AUDIT / RECOUNT LOGIC --- */
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('phys-input')) {
        let index = e.target.getAttribute('data-index');
        let sysQty = parseInt(document.getElementById('sysQty_' + index).innerText);
        let physQty = parseInt(e.target.value) || 0;
        let diff = physQty - sysQty;
        
        let badge = document.getElementById('diff_' + index);
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

function viewAuditDetails(month, remarks, itemsJson) {
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

/* --- BASIC MODAL OPENERS --- */
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add New Item';
    document.getElementById('formAction').value = 'add';
    document.getElementById('itemId').value = '';
    document.getElementById('itemCode').value = 'ITM-' + Math.floor(Math.random() * 9000 + 1000);
    document.getElementById('itemName').value = '';
    document.getElementById('itemCategory').value = 'Materials';
    document.getElementById('itemQuantity').value = '0';
    document.getElementById('itemUnit').value = 'Pieces';
    document.getElementById('itemPrice').value = '0.00';
}

function openEditModal(id, code, name, category, quantity, unit, price, status) {
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Item';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('itemId').value = id;
    document.getElementById('itemCode').value = code;
    document.getElementById('itemName').value = name;
    document.getElementById('itemCategory').value = category;
    document.getElementById('itemQuantity').value = quantity;
    document.getElementById('itemUnit').value = unit;
    document.getElementById('itemPrice').value = price;
    new bootstrap.Modal(document.getElementById('itemModal')).show();
}

function openAddUserModal() {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>Add New User';
    document.getElementById('userFormAction').value = 'add_user';
    document.getElementById('userId').value = '';
    document.getElementById('userName').value = '';
    document.getElementById('userUsername').value = '';
    document.getElementById('userRole').value = 'requestor';
    document.getElementById('userPassword').required = true;
    document.getElementById('passwordHelp').innerText = "Required for new users.";
}

function openEditUserModal(id, name, username, role) {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-gear me-2"></i>Edit User';
    document.getElementById('userFormAction').value = 'edit_user';
    document.getElementById('userId').value = id;
    document.getElementById('userName').value = name;
    document.getElementById('userUsername').value = username;
    document.getElementById('userRole').value = role;
    document.getElementById('userPassword').required = false;
    document.getElementById('passwordHelp').innerText = "Leave blank to keep current password.";
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

/* --- AJAX QR STOCK-IN LOGIC --- */
async function submitReceiveAjax(e) {
    e.preventDefault(); // STOPS THE PAGE FROM REFRESHING!
    
    const form = e.target;
    const btn = document.getElementById('receiveSubmitBtn');
    const successMsg = document.getElementById('ajaxSuccessMsg');
    const originalBtnText = btn.innerHTML;
    
    // UI Loading State
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving to Database...';
    
    const formData = new FormData(form);
    const itemCode = formData.get('item_code');
    const addedQty = formData.get('added_qty');
    
    try {
        // Send data to PHP without reloading the page
        const response = await fetch('process/process.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // 1. DYNAMICALLY UPDATE THE TABLE IN THE BACKGROUND
            const qtyEl = document.getElementById('qty_' + itemCode);
            const statusEl = document.getElementById('status_' + itemCode);
            
            if (qtyEl && statusEl) {
                // Update Number & Add a quick "Green Text" flash effect
                qtyEl.innerText = data.new_qty;
                qtyEl.className = 'fw-bold fs-5 text-success'; 
                setTimeout(() => { qtyEl.className = 'fw-bold fs-6'; }, 2000);
                
                // Update Badge Color instantly
                statusEl.innerText = data.new_status;
                if(data.new_status === 'Out of Stock') statusEl.className = 'badge bg-danger';
                else if(data.new_status === 'Low Stock') statusEl.className = 'badge bg-warning text-dark';
                else statusEl.className = 'badge bg-success';
            }
            
            // 2. Update the internal JS memory so it knows the new stock!
            let foundItem = typeof inventoryData !== 'undefined' ? inventoryData.find(item => item.item_code === itemCode) : null;
            if(foundItem) foundItem.quantity = data.new_qty;
            
            // FIXED: Play local success beep sound!
            let successAudio = new Audio('assets/sounds/success.mp3');
            successAudio.play().catch(e => console.log("Audio play blocked by browser."));
            successMsg.innerHTML = `<i class="bi bi-check-circle-fill me-2 fs-5"></i> Successfully added +${addedQty} units!`;
            successMsg.classList.remove('d-none');
            
            // 4. Wait 1.5 seconds, then AUTOMATICALLY open camera for next box!
            setTimeout(() => {
                successMsg.classList.add('d-none');
                form.reset();
                resetReceiveScanner();
            }, 1500);

        } else {
            alert("❌ Server Error: " + data.message);
        }
    } catch (error) {
        console.error("AJAX Error:", error);
        alert("❌ Network Error. The database could not be reached.");
    } finally {
        // Reset Button
        btn.disabled = false;
        btn.innerHTML = originalBtnText;
    }
}

/* ==========================================================
 * MULTI-DEVICE LIVE SYNC (BACKGROUND POLLING)
 * Magically updates the computer screen when a phone scans!
 * ========================================================== */
// Only run this if we are on the Inventory page (checks if the scanner button exists)
if (document.getElementById('startReceiveScannerBtn')) {
    
    // Check the database every 3 seconds (3000 milliseconds)
    setInterval(async () => {
        try {
            let formData = new FormData();
            formData.append('action', 'live_sync');
            
            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData
            });
            
            const liveData = await response.json();
            
            // Loop through all items in the database
            liveData.forEach(item => {
                let qtyEl = document.getElementById('qty_' + item.item_code);
                let statusEl = document.getElementById('status_' + item.item_code);
                
                // If the element exists on screen AND the number is different from the database!
                if (qtyEl && parseInt(qtyEl.innerText) !== parseInt(item.quantity)) {
                    
                    // 1. Update the Quantity Number
                    qtyEl.innerText = item.quantity;
                    
                    // 2. Flash it Blue to show it was updated remotely by another device!
                    qtyEl.className = 'fw-bold fs-5 text-primary'; 
                    setTimeout(() => { qtyEl.className = 'fw-bold fs-6'; }, 2000);
                    
                    // 3. Update the Badge Color
                    if (statusEl) {
                        statusEl.innerText = item.status;
                        if(item.status === 'Out of Stock') statusEl.className = 'badge bg-danger';
                        else if(item.status === 'Low Stock') statusEl.className = 'badge bg-warning text-dark';
                        else statusEl.className = 'badge bg-success';
                    }
                }
            });
        } catch (e) {
            // Silently ignore errors if the internet drops temporarily
            console.log("Sync paused...");
        }
    }, 3000); 
}