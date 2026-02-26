<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// Fetch current inventory for the Recount Form
$inventory = $pdo->query("SELECT item_code, item_name, quantity, unit FROM inventory ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Audit History for the logs
$query = "SELECT a.*, u.name as auditor_name FROM inventory_audits a LEFT JOIN users u ON a.conducted_by = u.id ORDER BY a.created_at DESC";
$audits = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch all audit items for the View Details modal
$auditItemsData = $pdo->query("SELECT ai.*, i.item_name, i.unit FROM audit_items ai LEFT JOIN inventory i ON ai.item_code = i.item_code")->fetchAll(PDO::FETCH_ASSOC);
$groupedAuditItems = [];
foreach($auditItemsData as $item) {
    $groupedAuditItems[$item['audit_id']][] = $item;
}

include 'layout/header.php';
?>

<div class="container-fluid px-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-clipboard-check me-2 text-primary"></i>Monthly Physical Recount</h4>
            <small class="text-muted">Reconcile System Data vs. Physical Warehouse Stock</small>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" id="auditTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold text-dark" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab"><i class="bi bi-clock-history me-1"></i> Audit History (Logs)</button>
        </li>
        <?php if (in_array($role, ['warehouse', 'admin'])): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold text-primary" id="recount-tab" data-bs-toggle="tab" data-bs-target="#recount" type="button" role="tab"><i class="bi bi-calculator me-1"></i> Perform Physical Count</button>
        </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content" id="auditTabsContent">
        
        <!-- TAB 1: AUDIT HISTORY -->
        <div class="tab-pane fade show active" id="history" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Audit Month</th><th>Conducted By</th><th>Date Completed</th><th>Discrepancies</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            <?php if(count($audits) > 0): ?>
                                <?php foreach ($audits as $audit): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($audit['audit_month']) ?></td>
                                        <td><i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($audit['auditor_name']) ?></td>
                                        <td class="text-muted small"><?= date('M d, Y h:i A', strtotime($audit['created_at'])) ?></td>
                                        <td>
                                            <?php if($audit['total_discrepancy_items'] > 0): ?>
                                                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= $audit['total_discrepancy_items'] ?> Items Missing</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Perfect Match</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php $itemsJson = htmlspecialchars(json_encode($groupedAuditItems[$audit['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewAuditDetails('<?= $audit['audit_month'] ?>', '<?= addslashes($audit['remarks']) ?>', '<?= $itemsJson ?>')"><i class="bi bi-eye"></i> View Trail</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No audit history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: PERFORM RECOUNT (WAREHOUSE ONLY) -->
        <?php if (in_array($role, ['warehouse', 'admin'])): ?>
        <div class="tab-pane fade" id="recount" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-body bg-light">
                    <div class="alert alert-info border-info d-flex align-items-center">
                        <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Internal Audit Instructions:</strong> Physically count the items on the shelves. Enter the actual physical quantity in the inputs below. The system will automatically calculate shortages and update the master inventory.
                        </div>
                    </div>

                    <form method="POST" action="process/process.php" onsubmit="return confirm('Are you sure? This will overwrite the current system inventory with your physical counts.');">
                        <input type="hidden" name="action" value="submit_audit">
                        
                        <div class="table-responsive bg-white border rounded">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Item Name</th>
                                        <th class="text-center" style="width: 15%">System Record</th>
                                        <th class="text-center" style="width: 25%">Physical Count (Actual)</th>
                                        <th class="text-center" style="width: 20%">Discrepancy (+/-)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory as $i => $item): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <?= htmlspecialchars($item['item_name']) ?> <small class="text-muted d-block"><?= $item['item_code'] ?></small>
                                                <input type="hidden" name="item_code[]" value="<?= $item['item_code'] ?>">
                                            </td>
                                            <td class="text-center">
                                                <span class="fs-5 fw-bold text-secondary" id="sysQty_<?= $i ?>"><?= $item['quantity'] ?></span> <small><?= $item['unit'] ?></small>
                                                <input type="hidden" name="system_qty[]" value="<?= $item['quantity'] ?>">
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <input type="number" class="form-control text-center fw-bold fs-5 text-primary phys-input" name="physical_qty[]" data-index="<?= $i ?>" value="<?= $item['quantity'] ?>" required min="0">
                                                    <span class="input-group-text bg-light"><?= $item['unit'] ?></span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary fs-6 w-75 p-2 diff-badge" id="diff_<?= $i ?>">0 Match</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 mb-3">
                            <label class="form-label fw-bold">Audit Remarks / Notes</label>
                            <textarea class="form-control" name="remarks" rows="2" placeholder="Explain any major missing items or damaged goods found during the recount..."></textarea>
                        </div>
                        
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-danger btn-lg px-5 fw-bold"><i class="bi bi-save me-2"></i>Finalize Audit & Adjust Inventory</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- AUDIT DETAILS MODAL -->
<div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-file-earmark-ruled me-2" style="color: var(--gb-yellow);"></i>Audit Trail Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <h5 class="fw-bold text-primary mb-3" id="modalAuditMonth">Month</h5>
                
                <div class="table-responsive bg-white border rounded">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light"><tr><th>Item Code</th><th>Item Name</th><th class="text-center">System Qty</th><th class="text-center">Physical Qty</th><th class="text-center">Discrepancy</th></tr></thead>
                        <tbody id="auditModalBody"></tbody>
                    </table>
                </div>
                
                <div class="mt-3"><h6 class="fw-bold mb-1">Remarks:</h6><p class="text-muted small border p-2 bg-white rounded" id="modalAuditRemarks">No remarks.</p></div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-Calculate Discrepancy Live
document.querySelectorAll('.phys-input').forEach(input => {
    input.addEventListener('input', function() {
        let index = this.getAttribute('data-index');
        let sysQty = parseInt(document.getElementById('sysQty_' + index).innerText);
        let physQty = parseInt(this.value) || 0;
        let diff = physQty - sysQty;
        
        let badge = document.getElementById('diff_' + index);
        if (diff < 0) {
            badge.className = 'badge bg-danger fs-6 w-75 p-2 diff-badge';
            badge.innerHTML = diff + ' Missing';
        } else if (diff > 0) {
            badge.className = 'badge bg-info text-dark fs-6 w-75 p-2 diff-badge';
            badge.innerHTML = '+' + diff + ' Surplus';
        } else {
            badge.className = 'badge bg-secondary fs-6 w-75 p-2 diff-badge';
            badge.innerHTML = '0 Match';
        }
    });
});

function viewAuditDetails(month, remarks, itemsJson) {
    document.getElementById('modalAuditMonth').innerText = "Audit: " + month;
    document.getElementById('modalAuditRemarks').innerText = remarks ? remarks : 'No notes provided.';
    
    let tbody = document.getElementById('auditModalBody');
    tbody.innerHTML = '';
    
    let items = JSON.parse(itemsJson);
    items.forEach(item => {
        let diffColor = item.discrepancy < 0 ? 'text-danger fw-bold' : (item.discrepancy > 0 ? 'text-info fw-bold' : 'text-muted');
        let diffText = item.discrepancy > 0 ? '+' + item.discrepancy : item.discrepancy;
        
        tbody.innerHTML += `
            <tr>
                <td class="text-muted small">${item.item_code}</td>
                <td class="fw-bold">${item.item_name}</td>
                <td class="text-center">${item.system_qty}</td>
                <td class="text-center fw-bold">${item.physical_qty}</td>
                <td class="text-center ${diffColor}">${diffText}</td>
            </tr>
        `;
    });
    
    new bootstrap.Modal(document.getElementById('auditModal')).show();
}
</script>

<?php include 'layout/footer.php'; ?>