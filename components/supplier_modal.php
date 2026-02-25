<?php if (in_array($role, ['admin', 'purchasing'])): ?>
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: var(--gb-yellow);">
                <h5 class="modal-title" id="supModalTitle">Add Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" id="supAction" value="add_supplier">
                    <input type="hidden" name="id" id="supId" value="">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Code</label><input type="text" class="form-control" name="supplier_code" id="supCode" required></div>
                        <div class="col-md-8 mb-3"><label class="form-label fw-bold">Company Name</label><input type="text" class="form-control" name="company_name" id="supName" required></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Contact Person</label><input type="text" class="form-control" name="contact_person" id="supPerson"></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Phone</label><input type="text" class="form-control" name="contact_number" id="supPhone"></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Email</label><input type="email" class="form-control" name="email" id="supEmail"></div>
                    </div>
                    
                    <div class="mb-3"><label class="form-label fw-bold">Address</label><textarea class="form-control" name="address" id="supAddress" rows="2"></textarea></div>
                    <div class="mb-3 w-50"><label class="form-label fw-bold">Status</label><select class="form-select" name="status" id="supStatus"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand" id="supSubmitBtn">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>