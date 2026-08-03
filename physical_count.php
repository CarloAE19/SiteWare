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

            <!-- Search & Auditor Info Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div class="input-group shadow-sm flex-grow-1 flex-md-grow-0" style="max-width: 320px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchRecount" class="form-control border-start-0 ps-0 bg-white fw-bold" placeholder="Search item name or code...">
                </div>
                <div class="text-muted small fw-bold">
                    <i class="bi bi-person-badge me-1 text-primary"></i> Auditor: <strong class="text-dark"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></strong> <span class="badge bg-secondary ms-1"><?= strtoupper($role) ?></span>
                </div>
            </div>

            <!-- Warning Notice -->
            <div class="alert alert-warning px-3 py-2 mb-4 shadow-sm" style="border-left: 4px solid #ffc107;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Warning:</strong> Submitting this physical count will automatically reconcile and update system inventory stock levels.
            </div>

            <!-- Form -->
            <form method="POST" action="process/process.php" onsubmit="return confirm('CRITICAL: This will overwrite the system inventory with this week\'s physical count. Ensure all entries are correct before submitting.');">
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
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($item['item_name']) ?></span>
                                            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;"><?= $item['item_code'] ?></small>
                                        </div>
                                        <input type="hidden" name="item_code[]" value="<?= $item['item_code'] ?>">
                                    </td>
                                    
                                    <td class="text-center bg-light" data-label="System Record">
                                        <div class="text-end text-md-center">
                                            <span class="fs-5 fw-bold text-secondary" id="sysQty_<?= $i ?>"><?= $item['quantity'] ?></span> 
                                            <small class="text-muted d-block" style="font-size: 0.75rem;"><?= $item['unit'] ?></small>
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
                    <textarea class="form-control fw-bold bg-light border-0" name="remarks" rows="2" placeholder="Explain any missing items or damaged goods found during the recount..."></textarea>
                </div>
                
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-danger btn-lg w-100 w-md-auto px-5 fw-bold shadow-lg text-uppercase" style="letter-spacing: 1px;">
                        <i class="bi bi-save me-2"></i>Finalize Audit & Adjust Inventory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EXTERNAL MODAL -->
<?php include 'components/audit_modal.php'; ?>

<!-- Audit Page Scripts -->
<script src="assets/js/audit.js"></script>

<?php include 'layout/footer.php'; ?>
