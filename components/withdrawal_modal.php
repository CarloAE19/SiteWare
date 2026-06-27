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
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold small text-muted text-uppercase">Withdrawal Slip No.</label>
                            <input type="text" class="form-control text-danger fw-bold bg-white" name="withdrawal_no" value="WD-<?= date('Y') ?>-<?= rand(1000,9999) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Project Name / Assigned To <span class="text-danger">*</span></label>
                            <select class="form-select fw-bold" name="project_name" id="wdProjectName" required>
                                <option value="">Select Project...</option>
                                <?php foreach ($activeProjects as $proj): ?>
                                    <option value="<?= htmlspecialchars($proj['project_name']) ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold text-dark d-flex justify-content-between align-items-center py-3">
                            <span><i class="bi bi-box-seam me-2"></i>Items to Release</span>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="addWdMaterialBtn">
                                <i class="bi bi-plus-lg me-1"></i> Add Item
                            </button>
                        </div>
                        <div class="card-body p-3 bg-light" id="wdMaterialsContainer">
                            <!-- Dynamic Row Template -->
                            <div class="row g-2 wd-material-row mb-2 align-items-center bg-white p-2 rounded border shadow-sm mx-0">
                                <div class="col-md-7">
                                    <select class="form-select fw-bold text-dark wd-item-select" name="items[]" required>
                                        <option value="">Select Material from Inventory...</option>
                                        <?php foreach ($inventoryItems as $item): ?>
                                            <option value="<?= $item['item_code'] ?>" data-max="<?= $item['quantity'] ?>">
                                                [<?= $item['item_code'] ?>] <?= htmlspecialchars($item['item_name']) ?> (Stock: <?= $item['quantity'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mt-2 mt-md-0">
                                    <input type="number" class="form-control fw-bold text-center text-danger wd-qty-input" name="quantities[]" placeholder="Qty" required min="1">
                                </div>
                                <div class="col-md-2 text-center mt-2 mt-md-0">
                                    <button type="button" class="btn btn-outline-danger w-100 remove-wd-row" disabled><i class="bi bi-trash3"></i></button>
                                </div>
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