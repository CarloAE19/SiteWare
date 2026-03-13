<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title" id="supplierModalTitle"><span style="color: var(--gb-yellow);">Add Supplier</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="action" id="supplierFormAction" value="add_supplier">
                    <input type="hidden" name="id" id="supplierId" value="">
                    
                    <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary"><i class="bi bi-building me-2"></i>Company Details</h6>
                    <div class="row mb-3">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label class="form-label fw-bold small text-muted text-uppercase">Supplier Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold text-success bg-white shadow-sm" name="supplier_code" id="supplierCode" required readonly>
                            <small class="text-muted" style="font-size: 0.7rem;">Auto-generated</small>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted text-uppercase">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold shadow-sm" name="company_name" id="supplierCompany" placeholder="e.g. Holcim Philippines" required>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary mt-4"><i class="bi bi-person-lines-fill me-2"></i>Contact Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold small text-muted text-uppercase">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold shadow-sm" name="contact_person" id="supplierContactPerson" placeholder="e.g. Maria Clara" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold shadow-sm" name="contact_number" id="supplierContactNumber" placeholder="e.g. 0917-123-4567" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold small text-muted text-uppercase">Email Address</label>
                            <input type="email" class="form-control fw-bold shadow-sm" name="email" id="supplierEmail" placeholder="e.g. sales@holcim.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Status</label>
                            <select class="form-select fw-bold shadow-sm" name="status" id="supplierStatus">
                                <option value="Active">Active</option>
                                <option value="Inactive" class="text-danger">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2 mt-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Business Address</label>
                        <textarea class="form-control fw-bold shadow-sm" name="address" id="supplierAddress" rows="2" placeholder="Full business address..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer justify-content-between bg-white border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4 shadow-sm" id="supplierSubmitBtn"><i class="bi bi-save me-1"></i> Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ==========================================================
// SUPPLIER MODAL LOGIC (Directly injected to bypass mobile cache!)
// ==========================================================

window.openAddSupplierModal = function() {
    document.getElementById('supplierModalTitle').innerHTML = '<i class="bi bi-building-add me-2" style="color: var(--gb-yellow);"></i>Add New Supplier';
    document.getElementById('supplierFormAction').value = 'add_supplier';
    document.getElementById('supplierId').value = '';
    
    // Auto-generate the code
    const randomNum = Math.floor(Math.random() * 9000 + 1000);
    document.getElementById('supplierCode').value = 'SUP-' + randomNum;
    
    // Clear form
    document.getElementById('supplierCompany').value = '';
    document.getElementById('supplierContactPerson').value = '';
    document.getElementById('supplierContactNumber').value = '';
    document.getElementById('supplierEmail').value = '';
    document.getElementById('supplierAddress').value = '';
    document.getElementById('supplierStatus').value = 'Active';
}

window.openEditSupplierModal = function(id, code, company, person, number, email, address, status) {
    document.getElementById('supplierModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2" style="color: var(--gb-yellow);"></i>Edit Supplier';
    document.getElementById('supplierFormAction').value = 'edit_supplier';
    document.getElementById('supplierId').value = id;
    
    // Fill the form
    document.getElementById('supplierCode').value = code;
    document.getElementById('supplierCompany').value = company;
    document.getElementById('supplierContactPerson').value = person;
    document.getElementById('supplierContactNumber').value = number;
    document.getElementById('supplierEmail').value = email;
    document.getElementById('supplierAddress').value = address;
    document.getElementById('supplierStatus').value = status;
}
</script>