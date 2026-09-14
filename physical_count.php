<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];
if (!in_array($role, ['warehouse', 'admin'])) {
    header("Location: audit");
    exit;
}

$inventory = $pdo->query("SELECT item_code, item_name, quantity, unit FROM inventory ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<!-- Audit & Physical Count Styles -->
<link rel="stylesheet" href="assets/css/audit.css">

<div class="container-fluid px-3 px-md-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-calculator me-2 text-primary"></i>Perform Physical Count</h4>
            <small class="text-muted fw-semibold">Weekly inventory count, physical verification & stock reconciliation workstation</small>
        </div>
        <div>
            <a href="audit" class="btn btn-outline-secondary fw-bold shadow-sm px-3 py-2">
                <i class="bi bi-clock-history me-1"></i> View Audit History
            </a>
        </div>
    </div>

    <!-- Physical Count Workstation Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body bg-light p-3 p-md-4">
            
            <!-- Live Summary Statistics Toolbar -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-white border rounded shadow-sm d-flex align-items-center justify-content-between h-100">
                        <div>
                            <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">Total Items to Count</small>
                            <h4 class="mb-0 fw-bold text-dark"><?= count($inventory) ?> <small class="text-muted fs-6">Items</small></h4>
                        </div>
                        <div class="fs-2 text-primary opacity-75"><i class="bi bi-box-seam"></i></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-white border rounded shadow-sm d-flex align-items-center justify-content-between h-100">
                        <div>
                            <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">Matching Items</small>
                            <h4 class="mb-0 fw-bold text-success" id="recountMatchCount">0</h4>
                        </div>
                        <div class="fs-2 text-success opacity-75"><i class="bi bi-check-circle-fill"></i></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-white border rounded shadow-sm d-flex align-items-center justify-content-between h-100">
                        <div>
                            <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">Stock Discrepancies</small>
                            <h4 class="mb-0 fw-bold text-danger" id="recountDiffCount">0</h4>
                        </div>
                        <div class="fs-2 text-danger opacity-75"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    </div>
                </div>
            </div>

            <!-- Search & Controls Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1 flex-md-grow-0">
                    <div class="input-group shadow-sm" style="max-width: 320px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchRecount" class="form-control border-start-0 ps-0 bg-white fw-bold" placeholder="Search item name or code...">
                        <button type="button" class="btn btn-outline-secondary border-start-0 bg-white text-muted d-none" id="clearSearchRecount" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="btn-group shadow-sm" role="group" aria-label="Table View Mode">
                        <button type="button" class="btn btn-sm btn-outline-primary active fw-bold" id="btnViewPaged">
                            <i class="bi bi-list-ol me-1"></i>Paged (10)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="btnViewAll">
                            <i class="bi bi-list-check me-1"></i>View All
                        </button>
                    </div>
                </div>
                <div class="text-muted small fw-bold">
                    <i class="bi bi-person-badge me-1 text-primary"></i> Auditor: <strong class="text-dark"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></strong> <span class="badge bg-secondary ms-1"><?= strtoupper($role) ?></span>
                </div>
            </div>

            <!-- Warning Notice -->
            <div class="alert alert-warning px-3 py-2 mb-4 shadow-sm" style="border-left: 4px solid #ffc107;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Notice:</strong> Review physical counts carefully. Upon finalizing, system inventory stock balances will be reconciled and adjusted automatically.
            </div>

            <!-- Form -->
            <form id="physicalCountForm" method="POST" action="process/process.php" novalidate>
                <input type="hidden" name="action" value="submit_audit">
                
                <div class="table-responsive bg-white border rounded shadow-sm">
                    <table class="table table-hover align-middle mb-0 text-nowrap" id="recountTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="min-width: 200px;" class="py-3 px-3">Item Name</th>
                                <th class="text-center py-3">System Record</th>
                                <th class="text-center py-3" style="min-width: 240px;">Physical Count (This Week)</th>
                                <th class="text-center py-3" style="min-width: 140px;">Discrepancy (+/-)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $i => $item): ?>
                                <tr id="recountRow_<?= $i ?>">
                                    <td class="px-3" data-label="Item Name">
                                        <div class="text-end text-md-start">
                                            <span class="fw-bold text-dark d-block item-title"><?= htmlspecialchars($item['item_name']) ?></span>
                                            <small class="text-muted text-uppercase fw-bold item-code" style="font-size: 0.7rem;"><?= $item['item_code'] ?></small>
                                        </div>
                                        <input type="hidden" name="item_code[]" value="<?= $item['item_code'] ?>">
                                    </td>
                                    
                                    <td class="text-center bg-light" data-label="System Record">
                                        <div class="text-end text-md-center">
                                            <span class="fs-5 fw-bold text-secondary" id="sysQty_<?= $i ?>"><?= $item['quantity'] ?></span> 
                                            <small class="text-muted d-block item-unit" style="font-size: 0.75rem;"><?= $item['unit'] ?></small>
                                        </div>
                                        <input type="hidden" name="system_qty[]" value="<?= $item['quantity'] ?>">
                                    </td>
                                    
                                    <td data-label="Physical Count">
                                        <div class="input-group shadow-sm ms-auto mx-md-auto" style="min-width: 200px; max-width: 270px;">
                                            <button type="button" class="btn btn-outline-secondary fw-bold px-2.5" onclick="stepPhysQty(<?= $i ?>, -1)">-</button>
                                            <input type="number" class="form-control text-center fw-bold fs-5 text-primary phys-input" 
                                                   name="physical_qty[]" id="physInput_<?= $i ?>" data-index="<?= $i ?>" value="<?= $item['quantity'] ?>" 
                                                   required min="0" style="min-width: 60px;">
                                            <button type="button" class="btn btn-outline-secondary fw-bold px-2.5" onclick="stepPhysQty(<?= $i ?>, 1)">+</button>
                                            <span class="input-group-text bg-light text-muted fw-bold"><?= $item['unit'] ?></span>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center" data-label="Discrepancy">
                                        <span class="badge bg-success fs-6 w-100 py-2 shadow-sm text-uppercase diff-badge" id="diff_<?= $i ?>">
                                            <i class="bi bi-check-circle me-1"></i> Match
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 mb-3 p-3 bg-white border rounded shadow-sm">
                    <label class="form-label fw-bold small text-muted text-uppercase">Audit Remarks / Notes</label>
                    <textarea class="form-control fw-bold bg-light border-0" id="auditRemarks" name="remarks" rows="2" placeholder="Explain any missing items, damaged goods, or recounting notes..."></textarea>
                </div>
                
                <div class="text-end mt-4">
                    <button type="button" id="btnOpenRecountReview" class="btn btn-danger btn-lg w-100 w-md-auto px-5 fw-bold shadow-lg text-uppercase" style="letter-spacing: 1px;">
                        <i class="bi bi-shield-check me-2"></i>Review & Finalize Audit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RECOUNT CONFIRMATION & REVIEW MODAL -->
<div class="modal fade" id="confirmRecountModal" tabindex="-1" aria-labelledby="confirmRecountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="confirmRecountModalLabel">
                    <i class="bi bi-shield-check me-2 text-warning"></i>Confirm Inventory Reconciliation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-3">
                    <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning flex-shrink-0"></i>
                    <div>
                        <strong class="d-block text-dark">Important Action: Stock Level Overwrite</strong>
                        <span class="small text-secondary">Finalizing this physical recount will officially reconcile and adjust active system inventory levels. Please review the summary and any discrepancies below.</span>
                    </div>
                </div>

                <!-- Review Summary Cards -->
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2 bg-white rounded border text-center shadow-sm h-100">
                            <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem;">Total Counted</small>
                            <span class="fw-bold fs-5 text-dark" id="modalReviewTotal">0</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-white rounded border text-center shadow-sm h-100">
                            <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem;">In-Sync (Match)</small>
                            <span class="fw-bold fs-5 text-success" id="modalReviewMatches">0</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-white rounded border text-center shadow-sm h-100">
                            <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.68rem;">Discrepancies</small>
                            <span class="fw-bold fs-5 text-danger" id="modalReviewDiffs">0</span>
                        </div>
                    </div>
                </div>

                <!-- Discrepancies Breakdown Table / Notice -->
                <div class="mb-3">
                    <h6 class="fw-bold text-dark text-uppercase small mb-2 d-flex justify-content-between align-items-center">
                        <span>Items with Stock Adjustments</span>
                        <span class="badge bg-danger rounded-pill" id="modalReviewBadge">0 adjustments</span>
                    </h6>
                    <div class="table-responsive rounded border bg-white shadow-sm" style="max-height: 240px;">
                        <table class="table table-sm table-hover align-middle mb-0" id="modalDiscrepanciesTable">
                            <thead class="table-light small">
                                <tr>
                                    <th class="ps-3 py-2">Item</th>
                                    <th class="text-center py-2">System</th>
                                    <th class="text-center py-2">Physical</th>
                                    <th class="text-center pe-3 py-2">Adjustment</th>
                                </tr>
                            </thead>
                            <tbody id="modalDiscrepanciesBody">
                                <!-- Populated dynamically by audit.js -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Remarks Preview -->
                <div class="p-3 bg-white rounded border shadow-sm">
                    <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Audit Remarks / Notes</small>
                    <div class="text-dark small" id="modalReviewRemarks" style="font-style: italic;">None provided.</div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top-0 d-flex justify-content-between flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal">
                    <i class="bi bi-arrow-left me-1"></i>Back to Recount
                </button>
                <button type="button" class="btn btn-danger px-4 fw-bold shadow-sm" id="btnConfirmRecountSubmit">
                    <i class="bi bi-check-circle-fill me-1"></i>Confirm & Adjust Inventory
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 CDN for polished alerts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Audit Page Scripts -->
<script src="assets/js/audit.js?v=<?= time() ?>"></script>

<?php include 'layout/footer.php'; ?>
