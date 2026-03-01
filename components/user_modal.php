<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title" id="userModalTitle"><span style="color: var(--gb-yellow);">Add User</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" id="userFormAction" value="add_user">
                    <input type="hidden" name="user_id" id="userId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" class="form-control" name="name" id="userName" placeholder="e.g. Juan Dela Cruz" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <input type="text" class="form-control" name="username" id="userUsername" placeholder="e.g. jdelacruz" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">System Role</label>
                            <select class="form-select" name="role" id="userRole">
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
                                <button class="btn border border-start-0 bg-white" type="button" onclick="toggleUserPass()">
                                    <i class="bi bi-eye-slash text-muted" id="toggleUserIcon"></i>
                                </button>
                            </div>
                            <small id="passwordHelp" class="text-muted d-block mt-1" style="font-size: 0.75rem;"></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand" id="userSubmitBtn">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>