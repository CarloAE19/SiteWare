<?php
if (defined('CIMS_REQUISITION_MODALS_LOADED')) {
    return;
}
define('CIMS_REQUISITION_MODALS_LOADED', true);

$role = $_SESSION['user_role'] ?? 'requestor';
$activeProjects = $activeProjects ?? [];
$inventoryItems = $inventoryItems ?? [];
$categories = $categories ?? [];
$units = $units ?? [];
?>
<style>
/* ==========================================================
 * CIMS TYPEAHEAD SEARCHABLE COMBOBOX STYLES (MULTI-DEVICE)
 * ========================================================== */
.cims-typeahead-wrap {
    position: relative;
    width: 100%;
}
.cims-typeahead-wrap .input-group {
    border-radius: 8px;
    transition: all 0.2s ease-in-out;
}
.cims-typeahead-wrap .cims-typeahead-input {
    font-size: 0.9rem;
    background-color: #ffffff;
    cursor: text;
    border-color: #dee2e6;
    min-height: 42px;
}
.cims-typeahead-wrap .cims-typeahead-input:focus {
    box-shadow: none;
    border-color: var(--gb-blue, #0d6efd);
}
.cims-typeahead-wrap .cims-typeahead-clear {
    background: transparent;
    cursor: pointer;
    padding: 0 12px;
    display: flex;
    align-items: center;
    min-height: 42px;
}
.cims-typeahead-wrap .cims-typeahead-clear:hover {
    color: #dc3545 !important;
}
.cims-typeahead-wrap .cims-typeahead-toggle {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    padding: 0 14px;
    min-height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cims-typeahead-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1065;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.15);
    border-radius: 8px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    max-height: 270px;
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    margin-top: 4px;
    padding: 4px 0;
}
.cims-typeahead-item {
    padding: 10px 12px;
    min-height: 44px;
    cursor: pointer;
    font-size: 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 4px 8px;
    transition: background 0.15s ease-in-out;
}
.cims-typeahead-item:last-child {
    border-bottom: none;
}
.cims-typeahead-item:hover,
.cims-typeahead-item:active,
.cims-typeahead-item.active {
    background-color: #eff6ff !important;
    color: #1e40af;
}
.cims-typeahead-item .item-title {
    font-weight: 600;
    color: #1e293b;
    word-break: break-word;
}
.cims-typeahead-item:hover .item-title,
.cims-typeahead-item.active .item-title {
    color: #1d4ed8;
}
.cims-typeahead-item .match-highlight {
    background-color: #fef08a;
    color: #854d0e;
    font-weight: 800;
    padding: 0 3px;
    border-radius: 3px;
}
.cims-typeahead-empty {
    padding: 18px 14px;
    text-align: center;
    color: #64748b;
    font-size: 0.85rem;
}

/* Mobile viewport adjustments for touch screens & virtual keyboard */
@media (max-width: 768px) {
    .cims-typeahead-menu {
        max-height: 220px;
    }
    .cims-typeahead-item {
        padding: 11px 12px;
    }
    .cims-typeahead-item .item-title {
        font-size: 0.88rem;
    }
}
</style>
<!-- ======================================================== -->
<!-- MODAL: VIEW DETAILS & PRINT QR DOCUMENT                  -->
<!-- ======================================================== -->
<div class="modal fade" id="viewRsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
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

                <div class="doc-summary-card d-flex justify-content-between align-items-start mb-4 shadow-sm">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h4 class="fw-bold text-primary mb-0" id="viewRsNo">RS-0000</h4>
                            <span id="viewRsStatus" class="badge shadow-sm" style="font-size: 0.75rem;">Pending Approval</span>
                        </div>
                        <div class="text-secondary fw-bold text-uppercase small" id="viewRsProject">Project Name</div>
                        <div class="mt-2 text-muted small d-flex flex-wrap gap-3">
                            <div><i class="bi bi-person me-1 text-primary"></i>Requested By: <strong id="viewRsRequestor" class="text-dark">User</strong></div>
                            <div><i class="bi bi-calendar3 me-1 text-primary"></i>Date: <strong id="viewRsDate" class="text-dark">Date</strong></div>
                        </div>
                    </div>

                    <!-- QR Code Container -->
                    <div id="rsQrContainer" class="text-center d-none ms-3">
                        <img id="viewRsQrCode" src="" alt="RS QR Code" class="border p-1 bg-white shadow-sm" style="width: 90px; height: 90px; border-radius: 8px;">
                        <small class="d-block text-muted mt-1 fw-bold" style="font-size: 0.65rem;">SCAN AT WAREHOUSE</small>
                    </div>
                </div>

                <h6 class="fw-bold text-uppercase small text-muted mb-2"><i class="bi bi-box-seam me-1 text-primary"></i>Requested Items</h6>
                <div class="table-responsive table-container-custom mb-4 shadow-sm">
                    <table class="table table-sm table-hover mb-0" style="min-width: 500px;">
                        <thead class="table-light">
                            <tr id="viewRsTableHeadRow">
                                <th class="text-center" style="width:110px;">Item Code</th>
                                <th class="text-center">Item Name / Notes</th>
                                <th class="text-center" style="width:90px;">Qty</th>
                                <th class="text-center d-print-none" style="width:140px;">Item Status</th>
                                <?php if ($role !== 'requestor'): ?>
                                    <th class="text-center d-print-none text-primary" style="width:90px;">Stock</th>
                                    <th class="text-center d-print-none text-warning" style="width:130px;">Pending</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="viewRsItemsBody"></tbody>
                    </table>
                </div>

                <div>
                    <h6 class="fw-bold mb-2 text-muted small text-uppercase"><i class="bi bi-chat-left-text me-1 text-primary"></i>Remarks / Notes</h6>
                    <div class="remarks-box shadow-sm mb-0" id="viewRsRemarks" style="min-height: 50px;">No remarks provided.</div>
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
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Reject Requisition</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php" id="rejectRsForm">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="reject_rs">
                    <input type="hidden" name="rs_id" id="rejectRsId">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>

                    <p class="fw-bold text-dark mb-4">You are rejecting Requisition: <span id="rejectRsNoDisplay" class="text-danger fs-5"></span></p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control fw-bold" name="reject_reason" id="rejectReasonInput" rows="3" required placeholder="e.g. Insufficient stock in inventory..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top-0">
                    <button type="button" class="btn btn-light fw-bold text-muted px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4 shadow-sm" id="rejectRsSubmitBtn"><i class="bi bi-x-circle me-2"></i>Confirm Reject</button>
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
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus me-2" style="color: var(--gb-yellow);"></i>Create Requisition Slip (RS)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="POST" action="process/process.php" id="rsForm">
                    <div class="modal-body bg-light p-3 p-md-4">
                        <input type="hidden" name="action" value="create_rs">
                        <input type="hidden" name="requestor_id" value="<?= $_SESSION['user_id'] ?>">
                        <input type="hidden" name="requestor_name" value="<?= htmlspecialchars($_SESSION['user_name']) ?>">
                        <input type="hidden" name="rs_no" value="RS-<?= date('Y') ?>-<?= rand(1000, 9999) ?>">
                        <?php if (function_exists('generate_csrf_token')): ?>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                        <?php endif; ?>

                        <!-- 1. METADATA SUMMARY STRIP (HCI Information Chunking) -->
                        <div class="card border-0 bg-white rounded-3 shadow-sm mb-3 p-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="bi bi-file-earmark-text-fill"></i>
                                        </div>
                                        <div>
                                            <span class="text-muted text-uppercase d-block" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px;">RS Slip Number</span>
                                            <span class="fw-bold text-primary" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.92rem;">RS-<?= date('Y') ?>-<?= rand(1000, 9999) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle p-2 bg-secondary bg-opacity-10 text-secondary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="bi bi-calendar3"></i>
                                        </div>
                                        <div>
                                            <span class="text-muted text-uppercase d-block" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px;">Date Logged</span>
                                            <span class="fw-bold text-dark" style="font-size: 0.88rem;"><?= date('M d, Y') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold small text-muted text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Urgency Priority <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm fw-bold shadow-sm" name="urgency" required>
                                        <option value="Normal">🟢 Normal Priority</option>
                                        <option value="High">🟠 High Priority</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- 2. PROJECT DESTINATION -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Project Name / Destination <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-geo-alt-fill text-danger"></i></span>
                                <select class="form-select fw-bold" name="project_name" required>
                                    <option value="">Select Project...</option>
                                    <?php foreach ($activeProjects as $proj): ?>
                                        <option value="<?= htmlspecialchars($proj['project_name']) ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- 3. REQUESTED MATERIALS SECTION -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white fw-bold text-dark d-flex justify-content-between align-items-center flex-wrap gap-2 py-2.5">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-box-seam text-primary"></i>
                                    <span>Requested Materials</span>
                                    <span class="badge bg-primary rounded-pill material-count-badge" style="font-size: 0.72rem;">1 Item</span>
                                </div>
                                <div class="d-flex gap-1.5 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="addMaterialBtn">
                                        <i class="bi bi-plus-lg me-1"></i> Add Existing Item
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success fw-bold text-white shadow-sm" id="addNewMaterialBtn">
                                        <i class="bi bi-plus-circle me-1"></i> + Request New Item
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-3 bg-light" id="materialsContainer">
                                <div class="material-row mb-2.5 bg-white p-3 rounded border shadow-sm mx-0">
                                    <input type="hidden" name="is_new_items[]" value="0">
                                    <input type="hidden" name="new_item_names[]" value="">
                                    <input type="hidden" name="new_categories[]" value="">
                                    <input type="hidden" name="new_units[]" value="">
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                        <span class="badge bg-light text-dark border small fw-bold row-index-badge"><i class="bi bi-box me-1 text-primary"></i>Item #1</span>
                                        <span class="small text-muted fst-italic item-stock-hint" style="font-size: 0.75rem;"></span>
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-12 col-md-8">
                                            <label class="form-label small fw-bold text-muted mb-1">Select Material <span class="text-danger">*</span></label>
                                            <select class="form-select fw-bold text-dark item-select-control" name="items[]" required>
                                                <option value="">Select Material from Inventory...</option>
                                                <?php foreach ($inventoryItems as $item): 
                                                    $stock = (float)($item['quantity'] ?? 0);
                                                    $stockFormatted = ($stock == (int)$stock) ? (int)$stock : $stock;
                                                    $category = htmlspecialchars($item['category'] ?? 'Materials');
                                                    $unit = htmlspecialchars($item['unit'] ?? '');
                                                    $code = htmlspecialchars($item['item_code']);
                                                    $name = htmlspecialchars($item['item_name']);
                                                ?>
                                                    <option value="<?= $code ?>" data-unit="<?= $unit ?>" data-stock="<?= $stockFormatted ?>" data-category="<?= $category ?>" data-name="<?= $name ?>">
                                                        [<?= $code ?>] <?= $name ?><?= !empty($unit) ? ' (' . $unit . ')' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Quantity <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" class="form-control fw-bold text-center text-primary item-qty-input" name="quantities[]" placeholder="Qty" required min="1" step="any">
                                                <span class="input-group-text bg-light text-muted small fw-bold item-unit-badge" style="min-width: 55px; font-size: 0.72rem;">Unit</span>
                                            </div>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <input type="text" class="form-control form-control-sm text-muted" name="item_notes[]" placeholder="Optional: Notes for this item (e.g. specific brand, size, color, purpose)..." maxlength="255">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end mt-2 pt-2 border-top">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row" disabled aria-label="Remove item" title="Remove item">
                                            <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Remove Item
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. REMARKS / NOTES -->
                        <div>
                            <label class="form-label fw-bold small text-muted text-uppercase">General Requisition Remarks / Notes</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Optional overall notes for reviewer or project instructions..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer border-top-0 bg-white d-flex justify-content-between p-3">
                        <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-brand fw-bold px-4 shadow-sm" id="rsSubmitBtn"><i class="bi bi-send me-2"></i>Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: REQUEST RESTOCK (WAREHOUSE ONLY)                  -->
    <!-- ======================================================== -->
    <div class="modal fade" id="restockModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                    <h5 class="modal-title fw-bold"><i class="bi bi-arrow-repeat me-2" style="color: var(--gb-yellow);"></i>Request Inventory Restock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="process/process.php" id="restockForm">
                    <div class="modal-body bg-light p-4">
                        <input type="hidden" name="action" value="create_rs">
                        <input type="hidden" name="project_name" value="Warehouse Restock">
                        <input type="hidden" name="requisition_type" value="restock">
                        <?php if (function_exists('generate_csrf_token')): ?>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                        <?php endif; ?>

                        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-3">
                            <i class="bi bi-info-circle-fill fs-4 me-3 text-primary"></i>
                            <div class="small">
                                You are submitting a <strong>Warehouse Restock Requisition</strong>. This will be routed to Management for PO creation.
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white fw-bold text-dark d-flex justify-content-between align-items-center flex-wrap gap-2 py-2.5">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-boxes text-primary"></i>
                                    <span>Items to Restock</span>
                                    <span class="badge bg-primary rounded-pill material-count-badge" style="font-size: 0.72rem;">1 Item</span>
                                </div>
                                <div class="d-flex gap-1.5 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="addRestockMaterialBtn">
                                        <i class="bi bi-plus-lg me-1"></i> Add Existing Item
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success fw-bold text-white shadow-sm" id="addNewRestockMaterialBtn">
                                        <i class="bi bi-plus-circle me-1"></i> + Request New Item
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-3 bg-light" id="restockMaterialsContainer">
                                <div class="material-row mb-2.5 bg-white p-3 rounded border shadow-sm mx-0">
                                    <input type="hidden" name="is_new_items[]" value="0">
                                    <input type="hidden" name="new_item_names[]" value="">
                                    <input type="hidden" name="new_categories[]" value="">
                                    <input type="hidden" name="new_units[]" value="">

                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                        <span class="badge bg-light text-dark border small fw-bold row-index-badge"><i class="bi bi-box me-1 text-primary"></i>Item #1</span>
                                        <span class="small text-muted fst-italic item-stock-hint" style="font-size: 0.75rem;"></span>
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-12 col-md-8">
                                            <label class="form-label small fw-bold text-muted mb-1">Select Material <span class="text-danger">*</span></label>
                                            <select class="form-select fw-bold text-dark item-select-control" name="items[]" required>
                                                <option value="">Select Material from Inventory...</option>
                                                <?php foreach ($inventoryItems as $item): 
                                                    $stock = (float)($item['quantity'] ?? 0);
                                                    $stockFormatted = ($stock == (int)$stock) ? (int)$stock : $stock;
                                                    $category = htmlspecialchars($item['category'] ?? 'Materials');
                                                    $unit = htmlspecialchars($item['unit'] ?? '');
                                                    $code = htmlspecialchars($item['item_code']);
                                                    $name = htmlspecialchars($item['item_name']);
                                                ?>
                                                    <option value="<?= $code ?>" data-unit="<?= $unit ?>" data-stock="<?= $stockFormatted ?>" data-category="<?= $category ?>" data-name="<?= $name ?>">
                                                        [<?= $code ?>] <?= $name ?><?= !empty($unit) ? ' (' . $unit . ')' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label small fw-bold text-muted mb-1">Target Quantity <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" class="form-control fw-bold text-center text-primary item-qty-input" name="quantities[]" placeholder="Qty" required min="1" step="any">
                                                <span class="input-group-text bg-light text-muted small fw-bold item-unit-badge" style="min-width: 55px; font-size: 0.72rem;">Unit</span>
                                            </div>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <input type="text" class="form-control form-control-sm text-muted" name="item_notes[]" placeholder="Optional: Notes for this item (e.g. target quantity, reason for restock)..." maxlength="255">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end mt-2 pt-2 border-top">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row" disabled aria-label="Remove item" title="Remove item">
                                            <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Remove Item
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label fw-bold small text-muted text-uppercase">General Restock Remarks / Notes</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Explain the restock need (e.g. low stock, new material requirement)..."></textarea>
                        </div>

                    </div>
                    <div class="modal-footer border-top-0 bg-white d-flex justify-content-between p-3">
                        <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-brand fw-bold px-4 shadow-sm" id="restockSubmitBtn"><i class="bi bi-send me-2"></i>Submit Restock Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden templates for JS to clone available options -->
    <select id="jsInventoryOptionsTemplate" class="d-none">
        <option value="">Select Material from Inventory...</option>
        <?php foreach ($inventoryItems as $item): 
            $stock = (float)($item['quantity'] ?? 0);
            $stockFormatted = ($stock == (int)$stock) ? (int)$stock : $stock;
            $category = htmlspecialchars($item['category'] ?? 'Materials');
            $unit = htmlspecialchars($item['unit'] ?? '');
            $code = htmlspecialchars($item['item_code']);
            $name = htmlspecialchars($item['item_name']);
        ?>
            <option value="<?= $code ?>" data-unit="<?= $unit ?>" data-stock="<?= $stockFormatted ?>" data-category="<?= $category ?>" data-name="<?= $name ?>">
                [<?= $code ?>] <?= $name ?><?= !empty($unit) ? ' (' . $unit . ')' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select id="jsCategoryOptionsTemplate" class="d-none">
        <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="jsUnitOptionsTemplate" class="d-none">
        <?php foreach ($units as $u): ?>
            <option value="<?= htmlspecialchars($u['unit_name']) ?>"><?= htmlspecialchars($u['unit_name']) ?></option>
        <?php endforeach; ?>
    </select>
<?php endif; ?>

<!-- ======================================================== -->
<!-- MODAL: PER-ITEM APPROVAL (MANAGEMENT / ADMIN)            -->
<!-- ======================================================== -->
<?php if (in_array($role, ['management', 'admin'])): ?>
<div class="modal fade" id="approveItemsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-check2-square me-2" style="color: var(--gb-yellow);"></i>Review &amp; Approve Items — <span id="approveRsNoLabel">RS-0000</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php" id="approveItemsForm">
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="action" value="approve_rs">
                    <input type="hidden" name="rs_id" id="approveRsIdField">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>

                    <p class="text-muted small mb-3"><i class="bi bi-info-circle-fill me-1 text-primary"></i>For each item, choose <strong>Approve</strong> or <strong>Reject</strong>. Add a remark for any rejected item.</p>

                    <div id="approveItemsList">
                        <!-- Populated by JS openApproveItemsModal() -->
                        <div class="text-center text-muted py-4"><i class="bi bi-hourglass-split me-2"></i>Loading items...</div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top-0 d-flex justify-content-between">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success fw-bold" onclick="setAllItemStatuses('Approved')"><i class="bi bi-check2-all me-1"></i>Approve All</button>
                        <button type="button" class="btn btn-sm btn-outline-danger fw-bold" onclick="setAllItemStatuses('Rejected')"><i class="bi bi-x-circle me-1"></i>Reject All</button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" id="approveItemsSubmitBtn"><i class="bi bi-send me-2"></i>Submit Decision</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- MODAL: EDIT & RESUBMIT REQUISITION                       -->
<!-- ======================================================== -->
<div class="modal fade" id="editRsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2" style="color: var(--gb-yellow);"></i>Edit Requisition — <span id="editRsNoLabel">RS-0000</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="process/process.php" id="editRsForm">
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="action" value="edit_rs">
                    <input type="hidden" name="rs_id" id="editRsIdField">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>

                    <div class="alert alert-info border-0 shadow-sm small py-2 mb-3 d-flex align-items-center">
                        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                        <div>You can modify requested items, quantities, notes, or fix typos. Resubmitting a rejected requisition will change its status back to <strong>Pending Approval</strong>.</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label class="form-label fw-bold small text-muted text-uppercase">RS Number</label>
                            <input type="text" class="form-control text-primary fw-bold bg-white" id="editRsNoInput" readonly>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label class="form-label fw-bold small text-muted text-uppercase">Urgency Priority <span class="text-danger">*</span></label>
                            <select class="form-select fw-bold" name="urgency" id="editRsUrgency" required>
                                <option value="Normal">🟢 Normal Priority</option>
                                <option value="High">🟠 High Priority</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Project Name / Purpose <span class="text-danger">*</span></label>
                            <select class="form-select fw-bold" name="project_name" id="editRsProject" required>
                                <option value="">Select Project...</option>
                                <?php foreach ($activeProjects as $proj): ?>
                                    <option value="<?= htmlspecialchars($proj['project_name']) ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold text-dark d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                            <span><i class="bi bi-box-seam me-2"></i>Requested Materials</span>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold me-1" id="addEditMaterialBtn">
                                    <i class="bi bi-plus-lg me-1"></i> Add Existing Item
                                </button>
                                <button type="button" class="btn btn-sm btn-success fw-bold text-white shadow-sm" id="addNewEditMaterialBtn">
                                    <i class="bi bi-plus-circle me-1"></i> + Request New Item
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-3 bg-light" id="editMaterialsContainer">
                            <!-- Populated dynamically via openEditRsModal() -->
                            <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split me-2"></i>Loading items...</div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-bold small text-muted text-uppercase">Remarks / Notes</label>
                        <textarea class="form-control" name="remarks" id="editRsRemarks" rows="2" placeholder="Optional notes for reviewer..."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top-0 bg-white d-flex justify-content-between">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4 shadow-sm" id="editRsSubmitBtn"><i class="bi bi-check2-circle me-2"></i>Save &amp; Resubmit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initSearchableCombobox === 'function') {
        window.initSearchableCombobox(document);
    }
});
</script>