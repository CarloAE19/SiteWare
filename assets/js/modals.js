/* ==========================================================
 * GB INVENTORY - UI & MODALS
 * Handles passwords toggles, modal triggers, and UI events
 * ========================================================== */

// --- EVENT LISTENERS FOR MODALS ---
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

// --- GLOBAL MODAL OPENERS & TOGGLES ---
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

window.openAddModal = function() {
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