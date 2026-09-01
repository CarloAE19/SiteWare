<!-- ======================================================== -->
<!-- MODAL: RELEASE MATERIALS WITHDRAWAL FORM               -->
<!-- ======================================================== -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-brand text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-right me-2"></i>Release Materials (RS Issue)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="POST" action="process/process.php" id="withdrawalForm" enctype="multipart/form-data">
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

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Received By (Person Picking Up Materials) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control fw-bold shadow-sm" name="received_by" id="wdReceivedBy" placeholder="Enter Full Name of Receiver..." required>
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

                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2">
                                <i class="bi bi-pen text-primary me-1"></i> Receiver Digital Signature <span class="text-danger">*</span>
                            </label>
                            <input type="hidden" name="signature_data" id="signatureData">

                            <div class="text-center">
                                <!-- Drawn Signature Preview Box (shown after drawing in modal) -->
                                <div id="wdSigDrawnPreview" class="d-none border rounded p-2 mb-2 sig-preview-box shadow-sm position-relative" style="background-color: #ffffff !important;">
                                    <small class="d-block fw-bold text-uppercase mb-1" style="font-size: 0.65rem; color: #475569 !important;">Recipient Signature Preview</small>
                                    <img id="wdSigPreviewImg" src="" alt="Recipient Signature" style="max-height: 80px; object-fit: contain;">
                                    <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-1 py-0 px-2" id="clearWdDrawnSigBtn" title="Clear signature">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                <!-- Open Fullscreen Pad Button -->
                                <button type="button" class="btn btn-outline-primary fw-bold w-100 py-3 shadow-sm border-2" id="openFullSigBtn">
                                    <i class="bi bi-arrows-fullscreen display-6 d-block mb-1 text-primary"></i>
                                    <span class="fs-6">Open Fullscreen Signature Pad</span>
                                    <small class="d-block text-muted fw-normal mt-1" style="font-size: 0.75rem;">Touch or click to open full-screen pad without scroll interference</small>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-1">
                                <i class="bi bi-camera text-primary me-1"></i> Photo Proof of Handed-Over Items
                            </label>
                            <input type="file" class="form-control shadow-sm mb-2" name="photo_proof" id="photoProofInput" accept="image/*" capture="environment">
                            <div id="photoProofPreviewContainer" class="d-none text-center border rounded bg-white p-1">
                                <img id="photoProofPreview" src="" class="img-fluid rounded" style="max-height: 75px;">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-bold small text-muted text-uppercase">Remarks / Notes</label>
                        <textarea class="form-control shadow-sm" name="remarks" id="wdRemarks" rows="2" placeholder="Optional details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-white">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="confirmWithdrawalBtn" class="btn btn-brand fw-bold px-4 shadow-sm">
                        <i class="bi bi-check2-circle me-2"></i>Confirm Release
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: FULLSCREEN SIGNATURE PAD                         -->
<!-- ======================================================== -->
<style>
#fullSigModal {
    z-index: 1080 !important;
}
/* Forced Landscape Mode for Mobile Signature Modal */
@media screen and (max-width: 991px) and (orientation: portrait) {
    #fullSigModal .modal-dialog {
        margin: 0 !important;
        max-width: 100vw !important;
        width: 100vw !important;
        height: 100vh !important;
        overflow: hidden !important;
    }
    #fullSigModal .modal-content {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        width: 100vh !important;
        height: 100vw !important;
        transform: translate(-50%, -50%) rotate(90deg) !important;
        transform-origin: center center !important;
        border-radius: 0 !important;
    }
}
</style>
<div class="modal fade" id="fullSigModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark text-white py-2">
                <div class="d-flex align-items-center">
                    <button type="button" class="btn-close btn-close-white me-2" id="cancelFullSigBtn" aria-label="Close"></button>
                    <h6 class="modal-title fw-bold text-uppercase mb-0">
                        <i class="bi bi-pen text-warning me-2"></i> Receiver Signature - Fullscreen Pad
                    </h6>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-light fw-bold me-2" id="clearFullSigBtn">
                        <i class="bi bi-eraser me-1"></i> Clear
                    </button>
                    <button type="button" class="btn btn-sm btn-success fw-bold px-3" id="saveFullSigBtn">
                        <i class="bi bi-check-lg me-1"></i> Use Signature
                    </button>
                </div>
            </div>
            <div class="modal-body p-2 bg-light d-flex flex-column justify-content-center align-items-center position-relative" style="overflow: hidden;">
                <canvas id="fullSigCanvas" class="border rounded shadow-sm sig-white-bg" style="width: 100%; height: 100%; touch-action: none; cursor: crosshair; background-color: #ffffff !important;"></canvas>
                <div id="fullSigPlaceholder" class="position-absolute top-50 start-50 translate-middle fw-bold pe-none fs-5 text-center" style="pointer-events: none; opacity: 0.6; color: #6c757d !important;">
                    <i class="bi bi-phone-landscape me-2 fs-3 d-block mb-1"></i> Sign here with finger...
                </div>
            </div>
        </div>
    </div>
</div>