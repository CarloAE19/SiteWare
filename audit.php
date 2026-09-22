<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once 'Connection/db.php';

$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['admin', 'management', 'warehouse'])) {
    header("Location: dashboard");
    exit;
}

$audits = $pdo->query("SELECT a.*, u.name as auditor_name FROM inventory_audits a LEFT JOIN users u ON a.conducted_by = u.id ORDER BY a.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$auditItemsData = $pdo->query("SELECT ai.*, i.item_name, i.unit FROM audit_items ai LEFT JOIN inventory i ON ai.item_code = i.item_code")->fetchAll(PDO::FETCH_ASSOC);
$groupedAuditItems = [];
foreach($auditItemsData as $item) { $groupedAuditItems[$item['audit_id']][] = $item; }

$totalAudits = count($audits);
$matchingAudits = 0;
$discrepancyAudits = 0;
foreach ($audits as $a) {
    if ((int)$a['total_discrepancy_items'] > 0) {
        $discrepancyAudits++;
    } else {
        $matchingAudits++;
    }
}

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
        <div>
            <?php if (in_array($role, ['admin', 'warehouse'])): ?>
                <a href="physical_count" class="btn btn-danger fw-bold shadow-sm px-3 py-2">
                    <i class="bi bi-calculator me-1"></i> Perform Physical Count
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Live Summary Statistics Toolbar -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="p-3 bg-white border rounded shadow-sm d-flex align-items-center justify-content-between h-100">
                <div>
                    <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">Total Audits Conducted</small>
                    <h4 class="mb-0 fw-bold text-dark"><?= $totalAudits ?> <small class="text-muted fs-6">Audits</small></h4>
                </div>
                <div class="fs-2 text-primary opacity-75"><i class="bi bi-clock-history"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="p-3 bg-white border rounded shadow-sm d-flex align-items-center justify-content-between h-100">
                <div>
                    <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">100% Matching Recounts</small>
                    <h4 class="mb-0 fw-bold text-success"><?= $matchingAudits ?> <small class="text-muted fs-6">Clean</small></h4>
                </div>
                <div class="fs-2 text-success opacity-75"><i class="bi bi-check-circle-fill"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="p-3 bg-white border rounded shadow-sm d-flex align-items-center justify-content-between h-100">
                <div>
                    <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">Audits with Discrepancies</small>
                    <h4 class="mb-0 fw-bold text-danger"><?= $discrepancyAudits ?> <small class="text-muted fs-6">Adjusted</small></h4>
                </div>
                <div class="fs-2 text-danger opacity-75"><i class="bi bi-exclamation-triangle-fill"></i></div>
            </div>
        </div>
    </div>

    <!-- AUDIT HISTORY TABLE CARD -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-3 p-md-4 bg-light">
            
            <!-- Search & Controls Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div class="input-group shadow-sm flex-grow-1 flex-md-grow-0" style="max-width: 340px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchAuditHistory" class="form-control border-start-0 ps-0 bg-white fw-bold" placeholder="Search audit period, auditor...">
                    <button type="button" class="btn btn-outline-secondary border-start-0 bg-white text-muted d-none" id="clearSearchHistory" title="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="text-muted small fw-bold">
                    <i class="bi bi-journal-text me-1 text-primary"></i> Total Recorded: <strong class="text-dark"><?= $totalAudits ?></strong>
                </div>
            </div>

            <!-- DESKTOP AUDIT TABLE -->
            <div class="d-none d-md-block">
                <div class="table-responsive bg-white border rounded shadow-sm">
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
                                                <i class="bi bi-person-badge me-2 text-muted"></i><?= htmlspecialchars($audit['auditor_name'] ?? 'Staff') ?>
                                            </span>
                                        </td>
                                        
                                        <td class="text-muted fw-bold small" data-label="Date Completed"><?= date('M d, Y h:i A', strtotime($audit['created_at'])) ?></td>
                                        <td data-label="Discrepancies">
                                            <?php if((int)$audit['total_discrepancy_items'] > 0): ?>
                                                <span class="badge bg-danger shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $audit['total_discrepancy_items'] ?> Items Adjusted</span>
                                            <?php else: ?>
                                                <span class="badge bg-success shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-check-circle-fill me-1"></i>Match</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" data-label="Actions">
                                            <?php $itemsJson = htmlspecialchars(json_encode($groupedAuditItems[$audit['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary fw-bold shadow-sm btn-view-audit" 
                                                    data-month="<?= htmlspecialchars($audit['audit_month'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-remarks="<?= htmlspecialchars($audit['remarks'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                    data-items="<?= $itemsJson ?>"
                                                    onclick="viewAuditDetailsFromBtn(this)">
                                                <i class="bi bi-eye me-1"></i> View Trail
                                            </button>
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
            <!-- END DESKTOP AUDIT TABLE -->

            <!-- MOBILE AUDIT CARDS -->
            <div class="d-block d-md-none" id="auditMobileCards" role="region" aria-label="Audit Trail Cards">
                <?php if(count($audits) > 0): ?>
                    <?php foreach ($audits as $audit): ?>
                        <?php
                        $hasDiscrepancy = ((int)$audit['total_discrepancy_items'] > 0);
                        $itemsJson = htmlspecialchars(json_encode($groupedAuditItems[$audit['id']] ?? []), ENT_QUOTES, 'UTF-8');
                        $searchString = strtolower(($audit['audit_month'] ?? '') . ' ' . ($audit['auditor_name'] ?? '') . ' ' . ($audit['remarks'] ?? ''));
                        ?>
                        <div class="cims-mobile-card shadow-sm mb-3 position-relative" data-search="<?= htmlspecialchars($searchString) ?>">
                            <!-- Card Header: Audit Month & Discrepancy Status -->
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div style="flex: 1 1 auto; min-width: 0;">
                                    <div class="text-muted small mb-0.5" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.4px;">
                                        <i class="bi bi-calendar-event me-1 text-primary"></i>Audit Period
                                    </div>
                                    <h6 class="fw-bold text-dark mb-0 text-break" style="line-height: 1.35; font-size: 1.05rem;">
                                        <?= htmlspecialchars($audit['audit_month']) ?>
                                    </h6>
                                </div>
                                <div class="flex-shrink-0 ms-2 text-end">
                                    <?php if ($hasDiscrepancy): ?>
                                        <span class="badge bg-danger shadow-sm px-2.5 py-1.5" style="font-size: 0.72rem;">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= (int)$audit['total_discrepancy_items'] ?> Adjusted
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success shadow-sm px-2.5 py-1.5" style="font-size: 0.72rem;">
                                            <i class="bi bi-check-circle-fill me-1"></i>100% Match
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Metadata Surface -->
                            <div class="bg-light border rounded-2 p-2.5 mb-2.5 small">
                                <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                                    <span class="text-muted" style="font-size: 0.72rem; font-weight: 600;">
                                        <i class="bi bi-person-badge me-1 text-secondary"></i>Auditor
                                    </span>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($audit['auditor_name'] ?? 'Staff') ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted" style="font-size: 0.72rem; font-weight: 600;">
                                        <i class="bi bi-clock-history me-1 text-secondary"></i>Date Logged
                                    </span>
                                    <span class="text-dark fw-bold" style="font-size: 0.75rem;"><?= date('M d, Y h:i A', strtotime($audit['created_at'])) ?></span>
                                </div>
                            </div>

                            <!-- Action Button (Touch-safe >= 44px) -->
                            <div class="cims-mobile-actions">
                                <button type="button" class="btn btn-outline-primary w-100 fw-bold shadow-sm btn-view-audit d-flex align-items-center justify-content-center"
                                        data-month="<?= htmlspecialchars($audit['audit_month'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-remarks="<?= htmlspecialchars($audit['remarks'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-items="<?= $itemsJson ?>"
                                        onclick="viewAuditDetailsFromBtn(this)">
                                    <i class="bi bi-eye me-2"></i> View Audit Trail Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div id="auditMobileEmpty" class="text-center py-5 text-muted bg-white rounded-3 border shadow-sm d-none">
                        <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                        <h6 class="fw-bold mb-1">No Matching Audits</h6>
                        <p class="small mb-0">Try searching for a different month or auditor name.</p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted bg-white rounded-3 border shadow-sm">
                        <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary"></i>
                        <h6 class="fw-bold mb-1">No Audit History Found</h6>
                        <p class="small mb-0">No weekly audit logs have been recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
            <!-- END MOBILE AUDIT CARDS -->

            <!-- SHARED PAGINATION CONTAINER -->
            <div id="historyPaginationWrapper" class="mt-2"></div>
        </div>
    </div>
</div>

<!-- EXTERNAL MODAL -->
<?php include 'components/audit_modal.php'; ?>

<!-- Audit Page Scripts -->
<script src="assets/js/audit.js?v=<?= time() ?>"></script>

<?php include 'layout/footer.php'; ?>
