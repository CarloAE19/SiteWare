// 1. Sidebar Toggle Logic
const sidebarCollapse = document.getElementById('sidebarCollapse');
const sidebar = document.getElementById('sidebar');
const sidebarClose = document.getElementById('sidebarClose');
const contentArea = document.getElementById('content');

if (sidebarCollapse && sidebar) {
    sidebarCollapse.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
}

if (sidebarClose && sidebar) {
    sidebarClose.addEventListener('click', () => {
        sidebar.classList.remove('active');
    });
}

if (contentArea && sidebar) {
    contentArea.addEventListener('click', (e) => {
        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
            if (!e.target.closest('#sidebarCollapse')) {
                sidebar.classList.remove('active');
            }
        }
    });
}

// 2. INVENTORY MODAL LOGIC
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
    document.getElementById('itemStatus').value = 'In Stock';
    document.getElementById('submitBtn').innerText = 'Save Item';
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
    document.getElementById('itemStatus').value = status;
    document.getElementById('submitBtn').innerText = 'Update Item';
    
    const modalEl = document.getElementById('itemModal');
    if(modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

// 3. USERS MODAL LOGIC (UPDATED FOR USERNAME)
function openAddUserModal() {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>Add New User';
    document.getElementById('userFormAction').value = 'add_user';
    document.getElementById('userId').value = '';
    document.getElementById('userName').value = '';
    document.getElementById('userUsername').value = '';
    document.getElementById('userRole').value = 'requestor';
    
    const pwdInput = document.getElementById('userPassword');
    pwdInput.required = true;
    pwdInput.value = '';
    document.getElementById('passwordHelp').innerText = "Required for new users.";
    document.getElementById('userSubmitBtn').innerText = 'Create Account';
}

function openEditUserModal(id, name, username, role) {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-gear me-2"></i>Edit User';
    document.getElementById('userFormAction').value = 'edit_user';
    document.getElementById('userId').value = id;
    document.getElementById('userName').value = name;
    document.getElementById('userUsername').value = username;
    document.getElementById('userRole').value = role;
    
    const pwdInput = document.getElementById('userPassword');
    pwdInput.required = false;
    pwdInput.value = '';
    document.getElementById('passwordHelp').innerText = "Leave blank to keep current password.";
    document.getElementById('userSubmitBtn').innerText = 'Update User';
    
    const modalEl = document.getElementById('userModal');
    if(modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

// 4. SUPPLIERS MODAL LOGIC
function openAddSupplier() {
    document.getElementById('supModalTitle').innerText = 'Add New Supplier';
    document.getElementById('supAction').value = 'add_supplier';
    document.getElementById('supId').value = '';
    document.getElementById('supCode').value = 'VEND-' + Math.floor(Math.random() * 9000 + 1000);
    document.getElementById('supName').value = '';
    document.getElementById('supPerson').value = '';
    document.getElementById('supPhone').value = '';
    document.getElementById('supEmail').value = '';
    document.getElementById('supAddress').value = '';
    document.getElementById('supStatus').value = 'Active';
    document.getElementById('supSubmitBtn').innerText = 'Save Supplier';
}

function openEditSupplier(id, code, name, person, phone, email, address, status) {
    document.getElementById('supModalTitle').innerText = 'Edit Supplier';
    document.getElementById('supAction').value = 'edit_supplier';
    document.getElementById('supId').value = id;
    document.getElementById('supCode').value = code;
    document.getElementById('supName').value = name;
    document.getElementById('supPerson').value = person;
    document.getElementById('supPhone').value = phone;
    document.getElementById('supEmail').value = email;
    document.getElementById('supAddress').value = address;
    document.getElementById('supStatus').value = status;
    document.getElementById('supSubmitBtn').innerText = 'Update Supplier';
    
    const modalEl = document.getElementById('supplierModal');
    if(modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

// 5. LOGIN PASSWORD TOGGLE LOGIC
const togglePassword = document.getElementById('togglePassword');
const passwordField = document.getElementById('passwordField');
const toggleIcon = document.getElementById('toggleIcon');

if (togglePassword && passwordField && toggleIcon) {
    togglePassword.addEventListener('click', function () {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        toggleIcon.classList.toggle('bi-eye');
        toggleIcon.classList.toggle('bi-eye-slash');
    });
}