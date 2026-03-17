<!-- ======================================================== -->
<!-- MODAL: VIEW DETAILS & PRINT QR DOCUMENT                  -->
<!-- ======================================================== -->
<div class="modal fade" id="viewRsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-card-list me-2" style="color: var(--gb-yellow);"></i>Requisition Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" id="rsPrintArea">

                <div class="d-none d-print-block text-center mb-4 border-bottom pb-3">
                    <h2 class="fw-bold mb-0">GB Construction & Enterprises</h2>
                    <h4 class="text-muted">Approved Material Requisition Slip</h4>
                </div>

                <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3">
                    <div>
                        <h4 class="fw-bold text-primary mb-0" id="viewRsNo">RS-0000</h4>
                        <div class="text-muted fw-bold text-uppercase small" id="viewRsProject">Project Name</div>
                        <div class="mt-2 text-muted small">
                            Requested By: <strong id="viewRsRequestor" class="text-dark">User</strong><br>
                            Date: <strong id="viewRsDate" class="text-dark">Date</strong>
                        </div>
                    </div>

                    <!-- QR Code Container -->
                    <div id="rsQrContainer" class="text-center d-none">
                        <img id="viewRsQrCode" src="" alt="RS QR Code" class="border p-1 bg-white shadow-sm" style="width: 100px; height: 100px; border-radius: 6px;">
                        <small class="d-block text-muted mt-1 fw-bold" style="font-size: 0.7rem;">SCAN AT WAREHOUSE</small>
                    </div>
                </div>

                <h6 class="fw-bold text-uppercase small text-muted mb-2">Requested Items:</h6>
                <div class="table-responsive mb-4 rounded border shadow-sm">
                    <table class="table table-sm table-hover mb-0 bg-white text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <!-- FIXED: Shortened headers so they fit perfectly inside the mobile modal without wrapping -->
                                <th class="text-center">Qty</th>
                                <th class="text-center d-print-none text-primary">Stock</th>
                            </tr>
                        </thead>
                        <tbody id="viewRsItemsBody"></tbody>
                    </table>
                </div>

                <div>
                    <h6 class="fw-bold mb-2 text-dark small text-uppercase">Remarks / Notes:</h6>
                    <p class="text-muted small border p-3 bg-white rounded shadow-sm mb-0" id="viewRsRemarks" style="min-height: 50px;">No remarks.</p>
                </div>
            </div>

            <div class="modal-footer d-flex justify-content-between bg-white border-top-0">
                <button type="button" id="printRsBtn" class="btn btn-outline-primary fw-bold px-4 d-none" onclick="printRSDocument()"><i class="bi bi-printer me-2"></i>Print Approved RS</button>
                <button type="button" class="btn btn-secondary fw-bold px-4 ms-auto" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: REJECT REASON                                     -->
<!-- ======================================================== -->
<div class="modal fade" id="rejectRsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Reject Requisition</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="reject_rs">
                    <input type="hidden" name="rs_id" id="rejectRsId">

                    <p class="fw-bold text-dark mb-4">You are rejecting Requisition: <span id="rejectRsNoDisplay" class="text-danger fs-5"></span></p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control fw-bold" name="reject_reason" rows="3" required placeholder="e.g. Insufficient stock in inventory..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top-0">
                    <button type="button" class="btn btn-light fw-bold text-muted px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4 shadow-sm"><i class="bi bi-x-circle me-2"></i>Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CREATE RS (ONLY REQUESTORS & WAREHOUSE)           -->
<!-- ======================================================== -->
<?php if (in_array($role, ['requestor', 'warehouse', 'admin'])): ?>
    <div class="modal fade" id="rsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus me-2" style="color: var(--gb-yellow);"></i>Create Requisition Slip (RS)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form method="POST" action="process/process.php" id="rsForm">
                    <div class="modal-body bg-light p-4">
                        <input type="hidden" name="action" value="create_rs">
                        <input type="hidden" name="requestor_id" value="<?= $_SESSION['user_id'] ?>">
                        <input type="hidden" name="requestor_name" value="<?= htmlspecialchars($_SESSION['user_name']) ?>">

                        <div class="row mb-3">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label fw-bold small text-muted text-uppercase">RS Number</label>
                                <input type="text" class="form-control text-primary fw-bold bg-white" name="rs_no" value="RS-<?= date('Y') ?>-<?= rand(1000, 9999) ?>" readonly>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label fw-bold small text-muted text-uppercase">Date</label>
                                <input type="text" class="form-control bg-white fw-bold text-muted" value="<?= date('M d, Y') ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">Urgency</label>
                                <select class="form-select fw-bold" name="urgency" required>
                                    <option value="Normal">Normal</option>
                                    <option value="High" class="text-danger">High</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Project Name / Purpose <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold" name="project_name" required placeholder="e.g. City Hall Renovation Phase 1">
                        </div>

                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white fw-bold text-dark d-flex justify-content-between align-items-center py-3">
                                <span><i class="bi bi-box-seam me-2"></i>Requested Materials</span>
                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="addMaterialBtn">
                                    <i class="bi bi-plus-lg me-1"></i> Add Item
                                </button>
                            </div>
                            <div class="card-body p-3 bg-light" id="materialsContainer">
                                <div class="row g-2 material-row mb-2 align-items-center bg-white p-2 rounded border shadow-sm mx-0">
                                    <div class="col-md-7">
                                        <select class="form-select fw-bold text-dark" name="items[]" required>
                                            <option value="">Select Material from Inventory...</option>
                                            <?php foreach ($inventoryItems as $item): ?>
                                                <option value="<?= $item['item_code'] ?>">
                                                    [<?= $item['item_code'] ?>] <?= htmlspecialchars($item['item_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mt-2 mt-md-0">
                                        <input type="number" class="form-control fw-bold text-center text-primary" name="quantities[]" placeholder="Qty" required min="1">
                                    </div>
                                    <div class="col-md-2 text-center mt-2 mt-md-0">
                                        <button type="button" class="btn btn-outline-danger w-100 remove-row" disabled><i class="bi bi-trash3"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label fw-bold small text-muted text-uppercase">Remarks / Notes</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Optional details for management or purchasing..."></textarea>
                        </div>

                    </div>
                    <div class="modal-footer border-top-0 bg-white">
                        <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-brand fw-bold px-4 shadow-sm"><i class="bi bi-send me-2"></i>Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>