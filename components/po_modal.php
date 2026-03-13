<?php
// Fetch Data needed for Create PO Modal
$suppliers = $pdo->query("SELECT id, company_name FROM suppliers WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
$approvedRS = $pdo->query("SELECT id, rs_no, project_name FROM requisitions WHERE status = 'Approved'")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ==========================================
  1. MODAL: CREATE NEW PURCHASE ORDER
=========================================== -->
<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus me-2" style="color: var(--gb-yellow);"></i>Generate Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <!-- Added p-4 for premium spacing -->
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="action" value="create_po">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Auto-Generated PO Number</label>
                        <input type="text" class="form-control fw-bold text-primary bg-white shadow-sm" name="po_no" value="PO-<?= date('Ymd') ?>-<?= rand(100,999) ?>" readonly>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Select Approved Requisition (RS) <span class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm" name="rs_id" required>
                            <option value="" disabled selected>-- Select an Approved RS --</option>
                            <?php foreach ($approvedRS as $rs): ?>
                                <option value="<?= $rs['id'] ?>"><?= $rs['rs_no'] ?> - <?= htmlspecialchars($rs['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-2" style="font-size: 0.75rem;"><i class="bi bi-info-circle me-1"></i>Only RS approved by Management appear here.</small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Select Supplier <span class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm" name="supplier_id" required>
                            <option value="" disabled selected>-- Select Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Clean white footer -->
                <div class="modal-footer justify-content-between bg-white border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4 shadow-sm"><i class="bi bi-check-circle me-1"></i> Generate & Save PO</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ==========================================
  2. MODAL: LOG WEATHER/LOGISTICS DELAY
=========================================== -->
<div class="modal fade" id="delayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 border-danger border-top border-4 shadow-lg">
            <div class="modal-header bg-white">
                <h5 class="modal-title text-danger fw-bold"><i class="bi bi-cloud-lightning-rain-fill me-2"></i>Log Supply Delay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="log_po_delay">
                    <input type="hidden" name="po_id" id="delayPoId">
                    <input type="hidden" name="po_no" id="delayPoNo">
                    
                    <div class="alert alert-danger px-3 py-2 mb-4 shadow-sm" style="font-size: 0.8rem; border-left: 3px solid #dc3545;">
                        <i class="bi bi-info-circle-fill me-1"></i> Flagging this PO will instantly notify Management.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Reason for Delay <span class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm mb-2 text-danger" name="delay_type" required>
                            <option value="Weather / Typhoon">Weather / Typhoon</option>
                            <option value="Road / Traffic Conditions">Road / Traffic Conditions</option>
                            <option value="Supplier Out of Stock">Supplier Out of Stock</option>
                        </select>
                    </div>
                    
                    <div class="mb-1">
                        <label class="form-label fw-bold small text-muted text-uppercase">Additional Remarks</label>
                        <textarea class="form-control fw-bold shadow-sm" name="remarks" rows="2" placeholder="e.g. Typhoon Basyang blocking port..."></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-white p-3 border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold shadow-sm px-3"><i class="bi bi-exclamation-triangle-fill me-1"></i> Submit Alert</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ==========================================================
// DELAY MODAL LOGIC (SPA-Proofed & Cache Bypassed)
// ==========================================================
window.openDelayModal = function(id, po_no) {
    document.getElementById('delayPoId').value = id;
    document.getElementById('delayPoNo').value = po_no;
    
    // Safely retrieve or instantiate the Bootstrap modal to prevent backdrop glitches
    var myModalEl = document.getElementById('delayModal');
    var delayModal = bootstrap.Modal.getInstance(myModalEl);
    if (!delayModal) {
        delayModal = new bootstrap.Modal(myModalEl);
    }
    delayModal.show();
}
</script>