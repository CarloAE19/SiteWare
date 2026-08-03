<!-- ======================================================== -->
<!-- MODAL: CREATE WITHDRAWAL (MANUAL OR QR AUTO-FILLED)      -->
<!-- ======================================================== -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-brand text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-up-right me-2"></i>Release Materials</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form method="POST" action="process/process.php" id="withdrawalForm">
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="action" value="create_withdrawal">
                    <input type="hidden" name="rs_no" id="wdRsNo" value="">
                    <!-- RS Number Type & Auto-Load Section -->
                    <div class="p-3 bg-white border rounded shadow-sm mb-3" style="border-left: 4px solid var(--gb-blue, #0d6efd) !important;">
                        <label class="form-label fw-bold small text-muted text-uppercase mb-1">
                            <i class="bi bi-pencil-square text-primary me-1"></i> Type Requisition Slip (RS) Number
                        </label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-light text-muted fw-bold"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control fw-bold" id="manualRsInputText" placeholder="Type RS Number (e.g. RS-2026-6396 or RS-2026-1040)..." onkeydown="if(event.key==='Enter'){event.preventDefault(); window.lookupManualRsInput();}">
                            <button type="button" class="btn btn-primary fw-bold px-4" onclick="window.lookupManualRsInput()"><i class="bi bi-cloud-download me-1"></i> Load RS Items</button>
                        </div>
                        <div id="manualRsStatusFeedback" class="mt-2 text-muted small fw-bold d-none"></div>
                    </div>

                    <div class="row mb-3 g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Withdrawal Slip No.</label>
                            <input type="text" class="form-control text-danger fw-bold bg-white" name="withdrawal_no" value="WD-<?= date('Y') ?>-<?= rand(1000,9999) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Requested By</label>
                            <input type="text" class="form-control fw-bold bg-white text-dark shadow-sm" id="wdRequestorDisplay" placeholder="Auto-filled via RS..." readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Project Name / Assigned To <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold bg-white text-dark shadow-sm" id="wdProjectNameDisplay" placeholder="Auto-filled via RS..." readonly>
                            <input type="hidden" name="project_name" id="wdProjectName" required>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold text-dark d-flex justify-content-between align-items-center py-3">
                            <span><i class="bi bi-box-seam me-2"></i>Items to Release</span>
                        </div>
                        <div class="card-body p-3 bg-light" id="wdMaterialsContainer">
                            <div class="text-center text-muted py-3 fw-bold" id="emptyWdPrompt">
                                <i class="bi bi-info-circle me-1"></i> Type an approved RS Number above to load release items.
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-bold small text-muted text-uppercase">Remarks / Notes</label>
                        <textarea class="form-control" name="remarks" id="wdRemarks" rows="2" placeholder="Optional details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-white">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4 shadow-sm" onclick="return confirm('Confirm release? This will permanently deduct from inventory.');">
                        <i class="bi bi-check2-circle me-2"></i>Confirm Release
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>