<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title" id="userModalTitle"><span style="color: var(--gb-yellow);">Add User</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="action" id="userFormAction" value="add_user">
                    <input type="hidden" name="user_id" id="userId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="userName" placeholder="e.g. Juan Dela Cruz" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="userUsername" placeholder="e.g. jdelacruz" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">System Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="userRole" required>
                                <option value="requestor">Requestor</option>
                                <option value="purchasing">Purchasing Officer</option>
                                <option value="management">Management / Approver</option>
                                <option value="warehouse">Warehouse In-Charge</option>
                                <option value="admin">System Admin</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control border-end-0" name="password" id="userPassword">
                                <button class="btn border border-start-0 bg-white shadow-none" type="button" onclick="toggleUserPass()">
                                    <i class="bi bi-eye-slash text-muted" id="toggleUserIcon"></i>
                                </button>
                            </div>
                            <small id="passwordHelp" class="text-muted d-block mt-1" style="font-size: 0.75rem;"></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-white border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand shadow-sm fw-bold px-4" id="userSubmitBtn"><i class="bi bi-save me-1"></i> Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ==========================================================
// USER MODAL LOGIC (Directly injected to bypass caching!)
// ==========================================================

window.openAddUserModal = function() {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus me-2" style="color: var(--gb-yellow);"></i>Add New User';
    document.getElementById('userFormAction').value = 'add_user';
    document.getElementById('userId').value = '';
    document.getElementById('userName').value = '';
    document.getElementById('userUsername').value = '';
    document.getElementById('userRole').value = 'requestor';
    document.getElementById('userPassword').required = true;
    document.getElementById('passwordHelp').innerHTML = "<span class='text-muted'>Min. 8 chars (Must include A-Z, a-z, 0-9).</span>";
}

window.openEditUserModal = function(id, name, username, role) {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-gear me-2" style="color: var(--gb-yellow);"></i>Edit User';
    document.getElementById('userFormAction').value = 'edit_user';
    document.getElementById('userId').value = id;
    document.getElementById('userName').value = name;
    document.getElementById('userUsername').value = username;
    document.getElementById('userRole').value = role;
    document.getElementById('userPassword').required = false; // Not required on edit!
    document.getElementById('passwordHelp').innerHTML = "<span class='text-primary'>Leave blank to keep current password. (Min. 8 chars with A-Z, a-z, 0-9 if changing)</span>";
}

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
</script>