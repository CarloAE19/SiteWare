<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title" id="supplierModalTitle"><span style="color: var(--gb-yellow);">Add Supplier</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" id="supplierFormAction" value="add_supplier">
                    <input type="hidden" name="id" id="supplierId" value="">
                    
                    <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary">Company Details</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Supplier Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold text-success bg-white" name="supplier_code" id="supplierCode" required readonly>
                            <!-- <small class="text-muted" style="font-size: 0.7rem;">Auto-generated</small> -->
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="company_name" id="supplierCompany" placeholder="e.g. Holcim Philippines" required>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary mt-2">Contact Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="contact_person" id="supplierContactPerson" placeholder="e.g. Maria Clara" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="contact_number" id="supplierContactNumber" placeholder="e.g. 0917-123-4567" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Email Address</label>
                            <input type="email" class="form-control" name="email" id="supplierEmail" placeholder="e.g. sales@holcim.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status" id="supplierStatus">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Business Address</label>
                        <textarea class="form-control" name="address" id="supplierAddress" rows="2" placeholder="Full business address..."></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand" id="supplierSubmitBtn"><i class="bi bi-save me-1"></i> Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>