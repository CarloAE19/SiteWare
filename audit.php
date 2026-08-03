<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];
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

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-clipboard-check me-2 text-primary"></i>Weekly Audit History</h4>
            <small class="text-muted fw-semibold">Past weekly physical count logs, auditor trails, and stock discrepancy records</small>
        </div>
    </div>

    <!-- AUDIT HISTORY TABLE CARD -->
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

<!-- EXTERNAL MODAL -->
<?php include 'components/audit_modal.php'; ?>

<!-- Audit Page Scripts -->
<script src="assets/js/audit.js"></script>

<?php include 'layout/footer.php'; ?>
