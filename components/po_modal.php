<!-- ======================================================== -->
<!-- MODAL: GENERATE PO (ONLY PURCHASING & ADMIN)             -->
<!-- ======================================================== -->
<?php if (in_array($role, ['purchasing', 'admin'])): ?>
<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-file-earmark-plus me-2" style="color: var(--gb-yellow);"></i>Generate Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" value="create_po">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">PO Number</label>
                            <input type="text" class="form-control text-primary fw-bold" name="po_no" value="PO-<?= date('Y') ?>-<?= rand(1000,9999) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date Issued</label>
                            <input type="text" class="form-control" value="<?= date('M d, Y') ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Link to Approved Requisition (RS) <span class="text-danger">*</span></label>
                        <select class="form-select" name="rs_id" required>
                            <option value="">Select an Approved RS...</option>
                            <?php foreach ($approvedRS as $rs): ?>
                                <option value="<?= $rs['id'] ?>">
                                    <?= htmlspecialchars($rs['rs_no']) ?> - <?= htmlspecialchars($rs['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only RS with 'Approved' status are shown.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Supplier <span class="text-danger">*</span></label>
                        <select class="form-select" name="supplier_id" required>
                            <option value="">Choose Supplier / Vendor...</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>">
                                    <?= htmlspecialchars($sup['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alert alert-info py-2 shadow-sm" style="font-size: 0.85rem;">
                        <i class="bi bi-info-circle me-1"></i> When you submit, the system will automatically copy all requested materials from the RS directly into this PO.
                    </div>

                </div>
                <div class="modal-footer border-top-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand">Generate PO</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>