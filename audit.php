<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];
$inventory = $pdo->query("SELECT item_code, item_name, quantity, unit FROM inventory ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$audits = $pdo->query("SELECT a.*, u.name as auditor_name FROM inventory_audits a LEFT JOIN users u ON a.conducted_by = u.id ORDER BY a.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$auditItemsData = $pdo->query("SELECT ai.*, i.item_name, i.unit FROM audit_items ai LEFT JOIN inventory i ON ai.item_code = i.item_code")->fetchAll(PDO::FETCH_ASSOC);
$groupedAuditItems = [];
foreach($auditItemsData as $item) { $groupedAuditItems[$item['audit_id']][] = $item; }

include 'layout/header.php';
?>

<!-- Audit Page Styles -->
<link rel="stylesheet" href="assets/css/audit.css">


<div class="container-fluid px-3 px-md-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-clipboard-check me-2 text-primary"></i>Weekly Physical Recount</h4>
        </div>
    </div>

    <!-- Premium Styled Tabs -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 shadow-sm border d-inline-flex" id="auditTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold px-4" id="history-tab" data-bs-toggle="pill" data-bs-target="#history" type="button" role="tab">
                <i class="bi bi-clock-history me-1"></i> Audit History <span class="d-none d-sm-inline">(Weekly Logs)</span>
            </button>
        </li>
        <?php if (in_array($role, ['warehouse', 'admin'])): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold px-4" id="recount-tab" data-bs-toggle="pill" data-bs-target="#recount" type="button" role="tab">
                <i class="bi bi-calculator me-1"></i> Perform <span class="d-none d-sm-inline">Physical</span> Count
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content" id="auditTabsContent">
        <!-- ==========================================
          TAB 1: AUDIT HISTORY
        =========================================== -->
        <div class="tab-pane fade show active" id="history" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0 table-responsive border rounded bg-white">
                    <table class="table table-hover align-middle mb-0 text-nowrap" id="historyTable">
                        <thead class="table-dark">
                            <tr>
                                <th class="py-3 px-3">Audit Month</th>
                                <th class="py-3">Conducted By</th>
                                <th class="py-3">Date Completed</th>
                                <th class="py-3">Discrepancies</th>
                                <th class="text-center py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($audits) > 0): ?>
                                <?php foreach ($audits as $audit): ?>
                                    <tr>
                                        <td class="fw-bold text-primary px-3" data-label="Audit Month"><?= htmlspecialchars($audit['audit_month']) ?></td>
                                        
                                        <!-- FIX: Added d-inline-flex to lock the icon and text together -->
                                        <td data-label="Conducted By">
                                            <span class="d-inline-flex align-items-center text-dark fw-bold">
                                                <i class="bi bi-person-badge me-2 text-muted"></i><?= htmlspecialchars($audit['auditor_name']) ?>
                                            </span>
                                        </td>
                                        
                                        <td class="text-muted fw-bold small" data-label="Date Completed"><?= date('M d, Y h:i A', strtotime($audit['created_at'])) ?></td>
                                        <td data-label="Discrepancies">
                                            <?php if($audit['total_discrepancy_items'] > 0): ?>
                                                <span class="badge bg-danger shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $audit['total_discrepancy_items'] ?> Items Adjusted</span>
                                            <?php else: ?>
                                                <span class="badge bg-success shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-check-circle-fill me-1"></i>Match</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" data-label="Actions">
                                            <?php $itemsJson = htmlspecialchars(json_encode($groupedAuditItems[$audit['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>
                                            <button class="btn btn-sm btn-outline-secondary fw-bold shadow-sm" onclick="viewAuditDetails('<?= $audit['audit_month'] ?>', '<?= addslashes($audit['remarks']) ?>', '<?= $itemsJson ?>')"><i class="bi bi-eye"></i> View Trail</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 d-block mb-2"></i>No audit history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==========================================
          TAB 2: PERFORM RECOUNT
        =========================================== -->
        <?php if (in_array($role, ['warehouse', 'admin'])): ?>
        <div class="tab-pane fade" id="recount" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body bg-light p-3 p-md-4">
                    
                    <div class="alert alert-warning px-3 py-2 mb-4 shadow-sm" style="border-left: 4px solid #ffc107;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Warning:</strong> Submitting this form will automatically overwrite the main inventory with your weekly physical counts.
                    </div>

                    <form method="POST" action="process/process.php" onsubmit="return confirm('CRITICAL: This will overwrite the system inventory with this week\'s physical count. Ensure all entries are correct before submitting.');">
                        <input type="hidden" name="action" value="submit_audit">
                        
                        <div class="table-responsive bg-white border rounded shadow-sm">
                            <table class="table table-hover align-middle mb-0 text-nowrap" id="recountTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="min-width: 200px;" class="py-3 px-3">Item Name</th>
                                        <th class="text-center py-3">System Record</th>
                                        <th class="text-center py-3" style="min-width: 220px;">Physical Count (This Week)</th>
                                        <th class="text-center py-3" style="min-width: 140px;">Discrepancy (+/-)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory as $i => $item): ?>
                                        <tr>
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
                                                <div class="input-group shadow-sm ms-auto mx-md-auto" style="min-width: 180px; max-width: 250px;">
                                                    <input type="number" class="form-control text-center fw-bold fs-5 text-primary phys-input" 
                                                           name="physical_qty[]" data-index="<?= $i ?>" value="<?= $item['quantity'] ?>" 
                                                           required min="0" style="min-width: 70px;">
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
        <?php endif; ?>
    </div>
</div>

<!-- EXTERNAL MODAL -->
<?php include 'components/audit_modal.php'; ?>

<!-- Audit Page Scripts -->
<script src="assets/js/audit.js"></script>

<?php include 'layout/footer.php'; ?>
