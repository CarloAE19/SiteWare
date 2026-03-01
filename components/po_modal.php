<?php
// Fetch Data needed for Create PO Modal
$suppliers = $pdo->query("SELECT id, company_name FROM suppliers WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
$approvedRS = $pdo->query("SELECT id, rs_no, project_name FROM requisitions WHERE status = 'Approved'")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- MODAL: CREATE NEW PURCHASE ORDER -->
<div class="modal fade" id="poModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-file-earmark-plus me-2" style="color: var(--gb-yellow);"></i>Generate Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" value="create_po">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">PO Number</label>
                        <input type="text" class="form-control fw-bold text-primary" name="po_no" value="PO-<?= date('Ymd') ?>-<?= rand(100,999) ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Approved Requisition (RS) <span class="text-danger">*</span></label>
                        <select class="form-select" name="rs_id" required>
                            <option value="" disabled selected>-- Select an Approved RS --</option>
                            <?php foreach ($approvedRS as $rs): ?>
                                <option value="<?= $rs['id'] ?>"><?= $rs['rs_no'] ?> - <?= htmlspecialchars($rs['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-1"><i class="bi bi-info-circle me-1"></i>Only RS approved by Management appear here.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Supplier <span class="text-danger">*</span></label>
                        <select class="form-select" name="supplier_id" required>
                            <option value="" disabled selected>-- Select Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand"><i class="bi bi-check-circle me-1"></i> Generate & Save PO</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: LOG WEATHER/LOGISTICS DELAY -->
<div class="modal fade" id="delayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 border-danger border-top border-4">
            <div class="modal-header">
                <h5 class="modal-title text-danger fw-bold"><i class="bi bi-cloud-lightning-rain-fill me-2"></i>Log Supply Delay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="log_po_delay">
                    <input type="hidden" name="po_id" id="delayPoId">
                    <input type="hidden" name="po_no" id="delayPoNo">
                    
                    <p class="small text-muted mb-3">Flagging this PO as delayed will instantly notify Management and the Warehouse In-Charge.</p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Delay <span class="text-danger">*</span></label>
                        <select class="form-select mb-2" name="delay_type" required>
                            <option value="Weather / Typhoon">Weather / Typhoon</option>
                            <option value="Road / Traffic Conditions">Road / Traffic Conditions</option>
                            <option value="Supplier Out of Stock">Supplier Out of Stock</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Additional Remarks</label>
                        <textarea class="form-control" name="remarks" rows="2" placeholder="e.g. Typhoon Basyang blocking port..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Submit Alert</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openDelayModal(id, po_no) {
    document.getElementById('delayPoId').value = id;
    document.getElementById('delayPoNo').value = po_no;
    new bootstrap.Modal(document.getElementById('delayModal')).show();
}
</script>