<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
if (!in_array($_SESSION['user_role'], ['admin', 'purchasing'])) {
    header("Location: index");
    exit;
}
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// Fetch all suppliers with live performance scoring
// Score = average of two metrics (each 0–100):
//   1. On-Time Rate    = (total POs - delayed POs) / total POs * 100
//   2. Accuracy Rate   = (total POs - discrepancy POs) / total POs * 100
$stmt = $pdo->query("
    SELECT
        s.*,
        COUNT(p.id)                                                               AS total_po,
        SUM(CASE WHEN p.status LIKE '%Delayed%' THEN 1 ELSE 0 END)               AS delayed_count,
        SUM(CASE WHEN p.status LIKE '%Discrepancy%' THEN 1 ELSE 0 END)           AS discrepancy_count,
        MIN(CASE WHEN p.status NOT IN ('Delivered', 'Delivered (Discrepancy)', 'Cancelled') AND p.expected_delivery_date IS NOT NULL THEN p.expected_delivery_date END) AS next_eta
    FROM suppliers s
    LEFT JOIN purchase_orders p ON p.supplier_id = s.id
    GROUP BY s.id
    ORDER BY s.company_name ASC
");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate status counts for live filtering
$activeCount = 0;
$inactiveCount = 0;
foreach ($suppliers as $s) {
    if (($s['status'] ?? '') === 'Active') {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
}

// Helper: compute the composite score for a supplier row
function calcPerformanceScore(array $sup): ?float
{
    if ((int) $sup['total_po'] === 0)
        return null; // No history yet
    $total = (int) $sup['total_po'];
    $onTime = ($total - (int) $sup['delayed_count']) / $total * 100;
    $accuracy = ($total - (int) $sup['discrepancy_count']) / $total * 100;
    return round(($onTime + $accuracy) / 2, 1);
}

include 'layout/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white rounded-3">
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-md-8 text-center text-md-start">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-buildings me-2 text-primary"></i>Suppliers</h4>
                <p class="text-muted small mb-0 mt-1">Vendor partners, contact persons, Viber logistics lines, and delivery reliability metrics.</p>
            </div>

            <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                <div class="col-12 col-md-4 text-md-end">
                    <button class="btn btn-brand shadow-sm w-100 w-md-auto fw-bold px-4 py-2" data-bs-toggle="modal"
                        data-bs-target="#supplierModal" onclick="openAddSupplierModal()">
                        <i class="bi bi-plus-lg me-1"></i> Add New Supplier
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action & Filter Bar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3 mb-4">
            <!-- Filter Pills -->
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 supplier-filter-btn active" data-filter="all" onclick="filterSuppliersTable('all', this)">
                    All Suppliers <span class="badge bg-secondary ms-1" id="badgeAllSuppliers"><?= count($suppliers) ?></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 supplier-filter-btn" data-filter="active" onclick="filterSuppliersTable('active', this)">
                    Active <span class="badge bg-success ms-1" id="badgeActiveSuppliers"><?= $activeCount ?></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 supplier-filter-btn" data-filter="inactive" onclick="filterSuppliersTable('inactive', this)">
                    Inactive <span class="badge bg-danger ms-1" id="badgeInactiveSuppliers"><?= $inactiveCount ?></span>
                </button>
            </div>

            <!-- Search Input -->
            <div class="input-group shadow-sm" style="max-width: 340px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="supplierSearchInput" class="form-control border-start-0 ps-0 bg-white fw-bold" placeholder="Search supplier, contact, code...">
                <button type="button" class="btn btn-outline-secondary border-start-0 bg-white text-muted d-none" id="clearSupplierSearch" title="Clear search">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <!-- DESKTOP DATA TABLE -->
        <div class="d-none d-md-block">
            <div class="table-responsive border rounded shadow-sm">
                <table class="table table-hover align-middle mb-0 text-nowrap" id="suppliersTable">
                    <thead class="table-dark">
                    <tr>
                        <th class="py-3">Supplier Code</th>
                        <th class="py-3">Company Name</th>
                        <th class="py-3">Contact Details</th>
                        <th class="py-3">Contact Number</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Next Supply ETA</th>
                        <th class="py-3">Performance</th>
                        <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                            <th class="text-center py-3">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $sup): ?>
                        <?php
                        $score = calcPerformanceScore($sup);
                        if ($score === null) {
                            $barColor = 'bg-secondary';
                            $tierLabel = 'New Supplier';
                            $tierBadge = 'bg-secondary';
                            $scoreText = '—';
                        } elseif ($score >= 90) {
                            $barColor = 'bg-success';
                            $tierLabel = 'Excellent';
                            $tierBadge = 'bg-success';
                            $scoreText = $score . '%';
                        } elseif ($score >= 70) {
                            $barColor = 'bg-warning';
                            $tierLabel = 'Average';
                            $tierBadge = 'bg-warning text-dark';
                            $scoreText = $score . '%';
                        } else {
                            $barColor = 'bg-danger';
                            $tierLabel = 'Poor';
                            $tierBadge = 'bg-danger';
                            $scoreText = $score . '%';
                        }

                        // Next ETA Badge
                        $supEtaBadge = '<span class="text-muted small">No Active Shipments</span>';
                        if (!empty($sup['next_eta'])) {
                            $nextEtaTs = strtotime($sup['next_eta']);
                            $todayTs = strtotime(date('Y-m-d'));
                            $daysDiff = (int) (($nextEtaTs - $todayTs) / 86400);

                            if ($daysDiff == 0) {
                                $supEtaBadge = '<span class="badge bg-warning text-dark shadow-sm"><i class="bi bi-truck-flatbed me-1"></i>Arriving TODAY</span>';
                            } elseif ($daysDiff < 0) {
                                $supEtaBadge = '<span class="badge bg-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i>Overdue (' . abs($daysDiff) . 'd)</span>';
                            } else {
                                $supEtaBadge = '<span class="badge bg-success shadow-sm"><i class="bi bi-calendar-check me-1"></i>' . date('M d, Y', $nextEtaTs) . ' (in ' . $daysDiff . 'd)</span>';
                            }
                        }
                        ?>
                        <tr id="supplier-row-<?= $sup['id'] ?>" class="supplier-row" data-status="<?= strtolower($sup['status'] ?? 'active') ?>">
                            <td class="text-muted fw-bold" data-label="Supplier Code">
                                <?= htmlspecialchars($sup['supplier_code']) ?>
                            </td>

                            <td class="fw-bold text-dark" data-label="Company Name">
                                <?= htmlspecialchars($sup['company_name']) ?>
                            </td>

                            <!-- Wrapped in span to prevent icons from breaking apart on mobile flexbox -->
                            <td data-label="Contact Details">
                                <span class="d-block text-dark"><i
                                        class="bi bi-person text-muted me-1"></i><?= htmlspecialchars($sup['contact_person']) ?></span>
                                <span class="d-block small text-muted"><i
                                        class="bi bi-envelope me-1"></i><?= htmlspecialchars($sup['email']) ?></span>
                            </td>

                            <td class="text-primary fw-bold" data-label="Contact Number">
                                <i class="bi bi-telephone text-muted me-1 d-none d-md-inline"></i><?= htmlspecialchars($sup['contact_number'] ?? '') ?>
                                <?php 
                                    $viberTarget = normalizeViberPhone($sup['contact_number'] ?? '');
                                    if ($viberTarget): 
                                ?>
                                    <a href="viber://chat?number=<?= urlencode($viberTarget) ?>" class="btn btn-sm btn-viber ms-2 px-2 py-1 shadow-sm fw-semibold" style="font-size: 0.78rem;" title="Chat via Viber (<?= htmlspecialchars($viberTarget) ?>)">
                                        <i class="fa-brands fa-viber me-1"></i>Viber
                                    </a>
                                <?php elseif (!empty($sup['contact_number'])): ?>
                                    <button type="button" class="btn btn-sm btn-light border text-muted ms-2 px-2 py-1 shadow-sm" style="font-size: 0.78rem;" disabled title="Mobile number starting with 09 or +63 required for Viber">
                                        <i class="fa-brands fa-viber me-1 text-muted"></i>Viber
                                    </button>
                                <?php endif; ?>
                            </td>

                            <td data-label="Status">
                                <?php if ($sup['status'] === 'Active'): ?>
                                    <span class="badge bg-success px-3 py-2 shadow-sm">ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge bg-danger px-3 py-2 shadow-sm">INACTIVE</span>
                                <?php endif; ?>
                            </td>

                            <td data-label="Next Supply ETA">
                                <?= $supEtaBadge ?>
                            </td>

                            <!-- ===== PERFORMANCE RANKING COLUMN ===== -->
                            <td data-label="Performance" style="min-width: 180px;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="flex-grow-1" style="min-width: 80px;">
                                        <div class="progress shadow-sm" style="height: 8px; border-radius: 6px;">
                                            <div class="progress-bar <?= $barColor ?>" role="progressbar"
                                                style="width: <?= $score !== null ? $score : 0 ?>%;"
                                                aria-valuenow="<?= $score !== null ? $score : 0 ?>" aria-valuemin="0"
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-1" style="font-size:0.7rem;">
                                            <?php if ($score !== null): ?>
                                                <?= (int) $sup['total_po'] ?>
                                                order<?= (int) $sup['total_po'] !== 1 ? 's' : '' ?>
                                            <?php else: ?>
                                                No orders yet
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?= $tierBadge ?> shadow-sm px-2 py-1 d-block mb-1"
                                            style="font-size:0.7rem;"><?= $tierLabel ?></span>
                                        <span class="fw-bold text-dark" style="font-size:0.85rem;"><?= $scoreText ?></span>
                                    </div>
                                    <?php if ((int) $sup['total_po'] > 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-sm fw-bold ms-1"
                                            style="font-size:0.7rem; padding: 3px 8px; border-radius: 6px;"
                                            title="View Delivery History" onclick="viewSupplierHistory(<?= $sup['id'] ?>)">
                                            <i class="bi bi-eye me-1"></i> View Details
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <!-- ===== END PERFORMANCE RANKING COLUMN ===== -->

                            <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                                <td class="text-center" data-label="Actions">
                                    <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-sm" data-bs-toggle="modal"
                                            data-bs-target="#supplierModal"
                                            onclick="openEditSupplierModal(<?= $sup['id'] ?>, <?= htmlspecialchars(json_encode($sup['supplier_code'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['company_name'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['contact_person'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['contact_number'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['email'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['address'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['status'] ?? '')) ?>)"
                                            aria-label="Edit Supplier <?= htmlspecialchars($sup['company_name']) ?>"
                                            title="Edit Supplier">
                                            <i class="bi bi-pencil-square"></i> <span class="d-none d-xl-inline ms-1">Edit</span>
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-danger shadow-sm"
                                            onclick="deleteSupplier(<?= $sup['id'] ?>, <?= htmlspecialchars(json_encode($sup['company_name'] ?? 'Supplier')) ?>, this)"
                                            aria-label="Delete Supplier <?= htmlspecialchars($sup['company_name']) ?>"
                                            title="Delete Supplier">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- END DESKTOP DATA TABLE -->

    <!-- MOBILE DIRECTORY CARDS (d-block d-md-none) -->
    <div class="d-block d-md-none pb-5 mb-4" id="suppliersMobileCards" role="region" aria-label="Suppliers Directory Cards">
        <?php if (empty($suppliers)): ?>
            <div class="text-center py-5 text-muted bg-light rounded-3 border">
                <i class="bi bi-buildings fs-1 d-block mb-2 text-secondary"></i>
                <h6 class="fw-bold mb-1">No Suppliers Found</h6>
                <p class="small mb-0">No supplier records have been added to the system yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($suppliers as $sup): ?>
                <?php
                $score = calcPerformanceScore($sup);
                if ($score === null) {
                    $barColor = 'bg-secondary';
                    $tierLabel = 'New Supplier';
                    $tierBadge = 'bg-secondary';
                    $scoreText = '—';
                } elseif ($score >= 90) {
                    $barColor = 'bg-success';
                    $tierLabel = 'Excellent';
                    $tierBadge = 'bg-success';
                    $scoreText = $score . '%';
                } elseif ($score >= 70) {
                    $barColor = 'bg-warning';
                    $tierLabel = 'Average';
                    $tierBadge = 'bg-warning text-dark';
                    $scoreText = $score . '%';
                } else {
                    $barColor = 'bg-danger';
                    $tierLabel = 'Poor';
                    $tierBadge = 'bg-danger';
                    $scoreText = $score . '%';
                }

                $supEtaBadge = '<span class="text-muted small">No Active Shipments</span>';
                if (!empty($sup['next_eta'])) {
                    $nextEtaTs = strtotime($sup['next_eta']);
                    $todayTs = strtotime(date('Y-m-d'));
                    $daysDiff = (int) (($nextEtaTs - $todayTs) / 86400);

                    if ($daysDiff == 0) {
                        $supEtaBadge = '<span class="badge bg-warning text-dark shadow-sm"><i class="bi bi-truck-flatbed me-1"></i>Arriving TODAY</span>';
                    } elseif ($daysDiff < 0) {
                        $supEtaBadge = '<span class="badge bg-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i>Overdue (' . abs($daysDiff) . 'd)</span>';
                    } else {
                        $supEtaBadge = '<span class="badge bg-success shadow-sm"><i class="bi bi-calendar-check me-1"></i>' . date('M d, Y', $nextEtaTs) . ' (in ' . $daysDiff . 'd)</span>';
                    }
                }

                $viberTarget = normalizeViberPhone($sup['contact_number'] ?? '');
                $searchString = strtolower(($sup['supplier_code'] ?? '') . ' ' . ($sup['company_name'] ?? '') . ' ' . ($sup['contact_person'] ?? '') . ' ' . ($sup['email'] ?? '') . ' ' . ($sup['contact_number'] ?? ''));
                ?>
                <div class="cims-mobile-card shadow-sm mb-3 position-relative" id="supplier-card-<?= $sup['id'] ?>" data-status="<?= strtolower($sup['status'] ?? 'active') ?>" data-search="<?= htmlspecialchars($searchString) ?>">
                    <!-- Card Header: Code, Name & Status -->
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div style="flex: 1 1 auto; min-width: 0;">
                            <div class="text-muted small mb-0.5" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.4px;">
                                <i class="bi bi-hash text-primary me-0.5"></i><?= htmlspecialchars($sup['supplier_code']) ?>
                                <?php if (!empty($sup['address'])): ?>
                                    <span class="text-muted ms-1 fw-normal text-truncate d-inline-block align-bottom" style="max-width: 180px;" title="<?= htmlspecialchars($sup['address']) ?>">
                                        <i class="bi bi-geo-alt me-0.5 text-danger"></i><?= htmlspecialchars($sup['address']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h6 class="fw-bold text-dark mb-0 text-break" style="line-height: 1.35; font-size: 1.05rem;">
                                <i class="bi bi-building me-1.5 text-primary"></i><?= htmlspecialchars($sup['company_name']) ?>
                            </h6>
                        </div>
                        <div class="flex-shrink-0 ms-2 text-end">
                            <?php if (($sup['status'] ?? '') === 'Active'): ?>
                                <span class="badge bg-success shadow-sm px-2.5 py-1.5" style="font-size: 0.72rem;">
                                    <i class="bi bi-check-circle-fill me-1"></i>ACTIVE
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger shadow-sm px-2.5 py-1.5" style="font-size: 0.72rem;">
                                    <i class="bi bi-x-circle-fill me-1"></i>INACTIVE
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Metadata Surface (Exact Audit.php Key-Value Layout) -->
                    <div class="bg-light border rounded-2 p-2.5 mb-2.5 small">
                        <!-- Contact Person -->
                        <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                            <span class="text-muted" style="font-size: 0.72rem; font-weight: 600;">
                                <i class="bi bi-person me-1 text-secondary"></i>Contact Person
                            </span>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($sup['contact_person'] ?: 'None Specified') ?></span>
                        </div>

                        <!-- Contact Number -->
                        <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                            <span class="text-muted" style="font-size: 0.72rem; font-weight: 600;">
                                <i class="bi bi-telephone me-1 text-secondary"></i>Contact Number
                            </span>
                            <div class="d-inline-flex align-items-center gap-1.5">
                                <?php if (!empty($sup['contact_number'])): ?>
                                    <a href="tel:<?= htmlspecialchars($sup['contact_number']) ?>" class="text-decoration-none fw-bold text-primary">
                                        <?= htmlspecialchars($sup['contact_number']) ?>
                                    </a>
                                    <?php if ($viberTarget): ?>
                                        <a href="viber://chat?number=<?= urlencode($viberTarget) ?>" class="badge text-white text-decoration-none px-2 py-0.5" style="background-color: #7360f2; font-size: 0.68rem; border-radius: 4px; display: inline-flex; align-items: center;" title="Chat via Viber">
                                            <i class="fa-brands fa-viber me-1"></i>Viber
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Email Address -->
                        <?php if (!empty($sup['email'])): ?>
                            <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                                <span class="text-muted" style="font-size: 0.72rem; font-weight: 600;">
                                    <i class="bi bi-envelope me-1 text-secondary"></i>Email Address
                                </span>
                                <a href="mailto:<?= htmlspecialchars($sup['email']) ?>" class="text-decoration-none text-muted text-truncate" style="max-width: 190px;">
                                    <?= htmlspecialchars($sup['email']) ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- Next Supply ETA -->
                        <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                            <span class="text-muted" style="font-size: 0.72rem; font-weight: 600;">
                                <i class="bi bi-truck me-1 text-secondary"></i>Next Supply ETA
                            </span>
                            <div><?= $supEtaBadge ?></div>
                        </div>

                        <!-- Performance -->
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted" style="font-size: 0.72rem; font-weight: 600;">
                                <i class="bi bi-speedometer2 me-1 text-secondary"></i>Performance
                            </span>
                            <div class="d-inline-flex align-items-center gap-1.5">
                                <span class="badge <?= $tierBadge ?> shadow-xs px-2 py-0.5" style="font-size: 0.68rem;"><?= $tierLabel ?></span>
                                <span class="fw-bold text-dark" style="font-size: 0.82rem;"><?= $scoreText ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Footer (Touch-safe >= 44px) -->
                    <div class="cims-mobile-actions d-flex gap-2">
                        <?php if ((int)$sup['total_po'] > 0): ?>
                            <button type="button" class="btn btn-outline-primary flex-fill fw-bold shadow-sm"
                                onclick="viewSupplierHistory(<?= $sup['id'] ?>)">
                                <i class="bi bi-clipboard2-data me-1.5"></i> View Delivery History
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-light border text-muted flex-fill fw-bold" disabled>
                                <i class="bi bi-clipboard2-data me-1.5"></i> No History
                            </button>
                        <?php endif; ?>

                        <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                            <button type="button" class="btn btn-outline-secondary px-3 fw-bold shadow-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#supplierModal"
                                onclick="openEditSupplierModal(<?= $sup['id'] ?>, <?= htmlspecialchars(json_encode($sup['supplier_code'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['company_name'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['contact_person'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['contact_number'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['email'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['address'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['status'] ?? '')) ?>)"
                                aria-label="Edit Supplier <?= htmlspecialchars($sup['company_name']) ?>"
                                title="Edit Supplier">
                                <i class="bi bi-pencil-square me-1"></i> Edit
                            </button>

                            <button type="button" class="btn btn-outline-danger px-3 fw-bold shadow-sm"
                                onclick="deleteSupplier(<?= $sup['id'] ?>, <?= htmlspecialchars(json_encode($sup['company_name'] ?? 'Supplier')) ?>, this)"
                                aria-label="Delete Supplier <?= htmlspecialchars($sup['company_name']) ?>"
                                title="Delete Supplier">
                                <i class="bi bi-trash3"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div id="suppliersMobileEmpty" class="text-center py-5 text-muted bg-light rounded-3 border d-none">
                <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                <h6 class="fw-bold mb-1">No Matching Suppliers</h6>
                <p class="small mb-0">Try adjusting your search query or status filter.</p>
            </div>
        <?php endif; ?>
    </div>
    <!-- END MOBILE DIRECTORY CARDS -->
    </div>
</div>

<!-- EXTERNAL MODAL -->
<?php include 'components/supplier_modal.php'; ?>

<!-- ==========================================
  MODAL: SUPPLIER DELIVERY HISTORY
=========================================== -->
<div class="modal fade" id="supplierHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-clipboard2-data me-2" style="color: var(--gb-yellow);"></i>
                    <span id="historyModalTitle">Delivery History</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-light-subtle">

                <!-- Loading State -->
                <div id="historyLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 mb-0 fw-bold small">Loading delivery records...</p>
                </div>

                <!-- Content (hidden until loaded) -->
                <div id="historyContent" class="d-none">

                    <!-- Summary Cards -->
                    <div class="p-3 pb-0">
                        <div class="row g-2 mb-3" id="historySummaryCards">
                            <!-- Populated via JS -->
                        </div>
                    </div>

                    <!-- Orders List -->
                    <div class="px-3 pb-3">
                        <h6 class="fw-bold text-muted text-uppercase small mb-2"><i
                                class="bi bi-list-check me-1"></i>Order-by-Order Breakdown</h6>
                        <div id="historyOrdersList">
                            <!-- Populated via JS -->
                        </div>
                        <div id="historyEmpty" class="text-center text-muted py-4 d-none">
                            <i class="bi bi-inbox" style="font-size:2rem;"></i>
                            <p class="mb-0 mt-2 fw-bold">No delivery records found.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary fw-bold px-4 py-2 w-100 w-sm-auto shadow-sm"
                    data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
  MODAL: PURCHASE ORDER QUICK VIEW (FROM SUPPLIER HISTORY)
=========================================== -->
<div class="modal fade" id="supplierPoDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background-color: var(--gb-dark, #0d1117);">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light d-inline-flex align-items-center gap-1 py-1 px-2 shadow-xs" onclick="backToSupplierHistory()" title="Back to Delivery History">
                        <i class="bi bi-arrow-left"></i>
                        <span class="d-none d-sm-inline">Back</span>
                    </button>
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="bi bi-file-earmark-text me-1 text-warning"></i>
                        <span id="supplierPoModalTitle">PO Details</span>
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light-subtle">
                <!-- Loading State -->
                <div id="supplierPoLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 mb-0 fw-bold small">Loading Purchase Order details...</p>
                </div>

                <!-- Content Container -->
                <div id="supplierPoContent" class="d-none">
                    <!-- PO Header Card -->
                    <div class="card border-0 shadow-sm rounded-3 mb-3 p-3 bg-white">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <h5 class="fw-bold text-dark mb-0 font-monospace" id="supPoNumberText">PO-00000000-000</h5>
                                    <span class="badge" id="supPoStatusBadge">Delivered</span>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-building me-1 text-primary"></i><span id="supPoSupplierName" class="fw-bold text-dark"></span>
                                    <span class="mx-1">•</span>
                                    <i class="bi bi-credit-card me-1 text-success"></i><span id="supPoTerms"></span>
                                </small>
                            </div>
                            <div class="text-sm-end">
                                <span class="d-block small text-muted"><i class="bi bi-calendar-event me-1"></i>Date: <strong id="supPoDateText" class="text-dark"></strong></span>
                                <span class="d-block small text-muted"><i class="bi bi-truck me-1"></i>ETA: <strong id="supPoEtaText" class="text-dark"></strong></span>
                            </div>
                        </div>
                        <div class="row g-2 pt-2 border-top mt-2 small text-muted">
                            <div class="col-12 col-sm-6">
                                <i class="bi bi-link-45deg me-1"></i>Linked RS: <span class="badge bg-light text-dark border" id="supPoRsNo"></span>
                            </div>
                            <div class="col-12 col-sm-6">
                                <i class="bi bi-folder2-open me-1"></i>Project: <strong id="supPoProjectName" class="text-dark"></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table Card -->
                    <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white overflow-hidden">
                        <div class="card-header bg-white border-bottom py-2 px-3">
                            <h6 class="fw-bold mb-0 small text-uppercase text-muted"><i class="bi bi-box-seam me-1 text-primary"></i>Ordered Line Items</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item Name & Code</th>
                                        <th class="text-center">Ordered</th>
                                        <th class="text-center">Received</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="supPoItemsTableBody">
                                    <!-- Populated via JS -->
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td colspan="4" class="text-end text-uppercase tfoot-label">Total Amount:</td>
                                        <td class="text-end text-primary fs-6 font-monospace tfoot-val" id="supPoTotalAmount">₱0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Remarks / Discrepancy Note (if present) -->
                    <div id="supPoRemarksCard" class="d-none mb-3">
                        <!-- Populated via JS -->
                    </div>

                    <!-- Proof of Delivery (if present) -->
                    <div id="supPoReceiptCard" class="d-none mb-3">
                        <!-- Populated via JS -->
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 d-flex flex-column flex-sm-row justify-content-between gap-2">
                <button type="button" class="btn btn-outline-secondary fw-bold px-3 py-2 w-100 w-sm-auto shadow-sm" onclick="backToSupplierHistory()">
                    <i class="bi bi-arrow-left me-1"></i>Back to Delivery History
                </button>
                <div class="d-flex gap-2 w-100 w-sm-auto justify-content-end">
                    <a href="#" id="btnOpenInPoModule" target="_blank" class="btn btn-outline-primary fw-bold px-3 py-2 flex-fill flex-sm-grow-0 shadow-sm text-center">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Open in PO Module
                    </a>
                    <button type="button" class="btn btn-secondary fw-bold px-4 py-2 flex-fill flex-sm-grow-0 shadow-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .history-card {
        border-radius: 10px;
        border: 1px solid #e9ecef;
        background: #fff;
        transition: box-shadow 0.2s;
    }

    .history-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .history-card .card-header-bar {
        height: 4px;
        border-radius: 10px 10px 0 0;
    }

    .stat-card {
        border-radius: 10px;
        padding: 12px 14px;
        text-align: center;
        border: 1px solid #e9ecef;
        background: #fff;
    }

    .stat-card .stat-number {
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.2;
    }

    .stat-card .stat-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        font-weight: 700;
        color: #6c757d;
        letter-spacing: 0.5px;
    }

    .item-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 20px;
        padding: 3px 10px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #495057;
        margin: 2px;
    }

    .discrepancy-detail {
        background: #fff5f5;
        border: 1px solid #fecaca;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 0.8rem;
        margin-top: 6px;
        color: #991b1b;
    }

    /* DARK MODE ADAPTIVE STYLES */
    [data-bs-theme="dark"] .stat-card {
        background-color: var(--gb-dark-surface, #161b22) !important;
        border-color: var(--gb-dark-border, #30363d) !important;
        color: var(--gb-dark-text-main, #f0f6fc) !important;
    }

    [data-bs-theme="dark"] .stat-card .stat-label {
        color: var(--gb-dark-text-muted, #8b949e) !important;
    }

    [data-bs-theme="dark"] .history-card {
        background-color: var(--gb-dark-surface, #161b22) !important;
        border-color: var(--gb-dark-border, #30363d) !important;
        color: var(--gb-dark-text-main, #f0f6fc) !important;
    }

    [data-bs-theme="dark"] .item-pill {
        background-color: var(--gb-dark-hover, #21262d) !important;
        border-color: var(--gb-dark-border, #30363d) !important;
        color: var(--gb-dark-text-main, #c9d1d9) !important;
    }

    [data-bs-theme="dark"] .item-pill .text-muted {
        color: var(--gb-dark-text-muted, #8b949e) !important;
    }

    [data-bs-theme="dark"] .discrepancy-detail {
        background-color: rgba(220, 53, 69, 0.15) !important;
        border-color: rgba(220, 53, 69, 0.35) !important;
        color: #f87171 !important;
    }

    /* PWA & Mobile Fullscreen Optimization */
    @media (max-width: 576px) {
        .modal-fullscreen-sm-down .modal-content {
            border-radius: 0 !important;
            min-height: 100vh;
            min-height: 100dvh;
        }
        .modal-fullscreen-sm-down .modal-header {
            padding-top: max(0.85rem, env(safe-area-inset-top));
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
            position: sticky;
            top: 0;
            z-index: 1055;
        }
        .modal-fullscreen-sm-down .modal-footer {
            padding-bottom: max(0.85rem, env(safe-area-inset-bottom));
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
            position: sticky;
            bottom: 0;
            background: #fff;
            z-index: 1055;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.04);
        }
        [data-bs-theme="dark"] .modal-fullscreen-sm-down .modal-footer {
            background: var(--gb-dark-surface, #161b22) !important;
        }
        /* Mobile-friendly stacked item cards in PO Details */
        #supplierPoDetailModal .table-responsive table thead {
            display: none;
        }
        #supplierPoDetailModal .table-responsive table tbody tr {
            display: block;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 10px;
            padding: 10px 12px;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        #supplierPoDetailModal .table-responsive table tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border: none;
            border-bottom: 1px dashed #f1f3f5;
        }
        #supplierPoDetailModal .table-responsive table tbody td:last-child {
            border-bottom: none;
        }
        #supplierPoDetailModal .table-responsive table tbody td::before {
            content: attr(data-label);
            font-weight: 700;
            color: #6c757d;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        #supplierPoDetailModal .table-responsive table tbody td[data-label="Item"] {
            display: block;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 6px;
            margin-bottom: 4px;
        }
        #supplierPoDetailModal .table-responsive table tbody td[data-label="Item"]::before {
            display: none;
        }
        #supplierPoDetailModal .table-responsive table tfoot tr {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        #supplierPoDetailModal .table-responsive table tfoot td {
            border: none;
            padding: 0;
        }
    }

    .po-touch-btn {
        transition: all 0.15s ease-in-out;
        min-height: 36px;
    }
    .po-touch-btn:active {
        transform: scale(0.97);
        background-color: #e9ecef;
    }
</style>

<script>
    function escapeHtmlSupplier(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    let currentSupplierHistoryId = null;

    window.viewSupplierHistory = function (supplierId) {
        currentSupplierHistoryId = supplierId;
        var modal = document.getElementById('supplierHistoryModal');
        var bsModal = bootstrap.Modal.getInstance(modal);
        if (!bsModal) bsModal = new bootstrap.Modal(modal);

        // Reset states
        document.getElementById('historyLoading').classList.remove('d-none');
        document.getElementById('historyContent').classList.add('d-none');
        document.getElementById('historyModalTitle').textContent = 'Delivery History';

        bsModal.show();

        // Fetch data
        var formData = new FormData();
        formData.append('action', 'fetch_supplier_delivery_history');
        formData.append('supplier_id', supplierId);

        fetch('process/process.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (data.status !== 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Delivery History',
                            text: data.message || 'Failed to load history.',
                            confirmButtonColor: '#0d6efd'
                        });
                    } else {
                        alert(data.message || 'Failed to load history.');
                    }
                    bsModal.hide();
                    return;
                }

                // Update title securely
                var companyName = data.supplier ? data.supplier.company_name : 'Supplier';
                document.getElementById('historyModalTitle').textContent = companyName + ' — Delivery History';

                // Build summary cards
                var s = data.summary || { total: 0, good: 0, discrepancies: 0, delayed: 0 };
                var cardsHtml = '';
                cardsHtml += '<div class="col-6 col-md-3"><div class="stat-card shadow-sm"><div class="stat-number text-primary">' + parseInt(s.total || 0, 10) + '</div><div class="stat-label">Total Orders</div></div></div>';
                cardsHtml += '<div class="col-6 col-md-3"><div class="stat-card shadow-sm" style="border-bottom: 3px solid #198754;"><div class="stat-number text-success">' + parseInt(s.good || 0, 10) + '</div><div class="stat-label"><i class="bi bi-check-circle-fill me-1"></i>Good Deliveries</div></div></div>';
                cardsHtml += '<div class="col-6 col-md-3"><div class="stat-card shadow-sm" style="border-bottom: 3px solid #dc3545;"><div class="stat-number text-danger">' + parseInt(s.discrepancies || 0, 10) + '</div><div class="stat-label"><i class="bi bi-exclamation-triangle-fill me-1"></i>Discrepancies</div></div></div>';
                cardsHtml += '<div class="col-6 col-md-3"><div class="stat-card shadow-sm" style="border-bottom: 3px solid #ffc107;"><div class="stat-number text-warning">' + parseInt(s.delayed || 0, 10) + '</div><div class="stat-label"><i class="bi bi-clock-fill me-1"></i>Delayed</div></div></div>';
                document.getElementById('historySummaryCards').innerHTML = cardsHtml;

                // Build orders list
                var orders = data.orders || [];
                if (orders.length === 0) {
                    document.getElementById('historyOrdersList').innerHTML = '';
                    document.getElementById('historyEmpty').classList.remove('d-none');
                } else {
                    document.getElementById('historyEmpty').classList.add('d-none');
                    var html = '';
                    for (var i = 0; i < orders.length; i++) {
                        var o = orders[i];
                        var barColor, icon, badgeClass, statusLabel;

                        if (o.classification === 'good') {
                            barColor = '#198754';
                            icon = 'bi-check-circle-fill text-success';
                            badgeClass = 'bg-success';
                            statusLabel = 'Delivered Successfully';
                        } else if (o.classification === 'discrepancy') {
                            barColor = '#dc3545';
                            icon = 'bi-exclamation-triangle-fill text-danger';
                            badgeClass = 'bg-danger';
                            statusLabel = 'Delivered with Discrepancy';
                        } else if (o.classification === 'delayed') {
                            barColor = '#ffc107';
                            icon = 'bi-clock-fill text-warning';
                            badgeClass = 'bg-warning text-dark';
                            statusLabel = 'Delayed';
                        } else if (o.classification === 'partial') {
                            barColor = '#0dcaf0';
                            icon = 'bi-box-arrow-in-down text-info';
                            badgeClass = 'bg-info text-dark';
                            statusLabel = o.status || 'Partially Delivered';
                        } else {
                            barColor = '#6c757d';
                            icon = 'bi-hourglass-split text-secondary';
                            badgeClass = 'bg-secondary';
                            statusLabel = o.status || 'In Progress';
                        }

                        html += '<div class="history-card mb-3 shadow-sm">';
                        html += '<div class="card-header-bar" style="background:' + barColor + ';"></div>';
                        html += '<div class="p-3">';

                        // Header row with responsive wrapping and touch-friendly clickable PO link
                        html += '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">';
                        html += '<div class="d-flex align-items-center gap-2">';
                        html += '<i class="bi ' + icon + ' fs-6"></i>';
                        html += '<button type="button" class="btn btn-light btn-sm border d-inline-flex align-items-center gap-1 fw-bold text-dark text-decoration-none shadow-xs px-2.5 py-1 hover-primary po-touch-btn" title="Click to view PO details popup" onclick="viewSupplierPoDetail(' + parseInt(o.id || 0, 10) + ', \'' + escapeHtmlSupplier(o.po_no) + '\')">';
                        html += '<span>' + escapeHtmlSupplier(o.po_no) + '</span>';
                        html += '<i class="bi bi-file-earmark-text text-primary small ms-1" style="font-size: 0.82rem;"></i>';
                        html += '</button>';
                        html += '<a href="po.php?search=' + encodeURIComponent(o.po_no || '') + '&open=1" class="btn btn-outline-secondary btn-sm p-1 d-inline-flex align-items-center justify-content-center shadow-xs" style="width: 32px; height: 32px;" title="Open in PO Module (New Tab)" target="_blank">';
                        html += '<i class="bi bi-box-arrow-up-right" style="font-size: 0.72rem;"></i>';
                        html += '</a>';
                        html += '</div>';

                        html += '<div class="d-flex align-items-center gap-2">';
                        html += '<small class="text-muted"><i class="bi bi-calendar3 me-1"></i>' + escapeHtmlSupplier(o.date) + '</small>';
                        html += '<span class="badge ' + badgeClass + ' px-2 py-1 shadow-sm" style="font-size:0.72rem;">' + escapeHtmlSupplier(statusLabel) + '</span>';
                        html += '</div>';
                        html += '</div>';

                        // Items list
                        html += '<div class="mt-2">';
                        if (o.items && o.items.length > 0) {
                            for (var j = 0; j < o.items.length; j++) {
                                var item = o.items[j];
                                html += '<span class="item-pill shadow-xs"><i class="bi bi-box-seam me-1 text-primary" style="font-size:0.68rem;"></i> ' + escapeHtmlSupplier(item.item_name) + ' <span class="text-muted fw-bold">×' + parseInt(item.expected_qty, 10) + '</span></span>';
                            }
                        } else {
                            html += '<span class="text-muted small fst-italic">No line items recorded</span>';
                        }
                        html += '</div>';

                        // Discrepancy / Delay details
                        if (o.classification === 'discrepancy' && o.delay_remarks) {
                            var remarks = o.delay_remarks;
                            var discMatch = remarks.match(/\[DELIVERY DISCREPANCY\]:\n([\s\S]*)/);
                            if (discMatch) {
                                var lines = discMatch[1].trim().split('\n');
                                html += '<div class="discrepancy-detail shadow-xs"><i class="bi bi-exclamation-circle-fill me-1"></i><strong>Mismatch Details:</strong><br>';
                                for (var k = 0; k < lines.length; k++) {
                                    if (lines[k].trim()) html += '<span class="d-block ms-2">' + escapeHtmlSupplier(lines[k].trim()) + '</span>';
                                }
                                html += '</div>';
                            } else {
                                html += '<div class="discrepancy-detail shadow-xs"><i class="bi bi-exclamation-circle-fill me-1"></i><strong>Remarks:</strong> ' + escapeHtmlSupplier(remarks) + '</div>';
                            }
                        } else if (o.classification === 'delayed' && o.delay_remarks) {
                            html += '<div class="discrepancy-detail shadow-xs" style="background:#fffbeb;border-color:#fde68a;color:#92400e;"><i class="bi bi-clock-history me-1"></i><strong>Delay Reason:</strong> ' + escapeHtmlSupplier(o.delay_remarks) + '</div>';
                        }

                        html += '</div></div>';
                    }
                    document.getElementById('historyOrdersList').innerHTML = html;
                }

                // Show content, hide loader
                document.getElementById('historyLoading').classList.add('d-none');
                document.getElementById('historyContent').classList.remove('d-none');
            })
            .catch(function (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Delivery History',
                        text: err.message || 'Error loading supplier history. Please try again.',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    alert(err.message || 'Error loading supplier history. Please try again.');
                }
                bsModal.hide();
            });
    };

    window.viewSupplierPoDetail = function (poId, poNo) {
        var histModalEl = document.getElementById('supplierHistoryModal');
        var histBsModal = bootstrap.Modal.getInstance(histModalEl);
        if (histBsModal) {
            histBsModal.hide();
        }

        var poModalEl = document.getElementById('supplierPoDetailModal');
        var poBsModal = bootstrap.Modal.getInstance(poModalEl);
        if (!poBsModal) poBsModal = new bootstrap.Modal(poModalEl);

        // Reset UI states
        document.getElementById('supplierPoLoading').classList.remove('d-none');
        document.getElementById('supplierPoContent').classList.add('d-none');
        document.getElementById('supplierPoModalTitle').textContent = poNo ? (poNo + ' — Details') : 'PO Details';
        document.getElementById('supPoNumberText').textContent = poNo || '—';
        document.getElementById('btnOpenInPoModule').href = 'po.php?search=' + encodeURIComponent(poNo || '') + '&open=1';

        poBsModal.show();

        var formData = new FormData();
        formData.append('action', 'fetch_po_details');
        if (poId) formData.append('po_id', poId);
        if (poNo) formData.append('po_no', poNo);

        fetch('process/process.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status !== 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'PO Details',
                            text: data.message || 'Failed to load purchase order details.',
                            confirmButtonColor: '#0d6efd'
                        });
                    } else {
                        alert(data.message || 'Failed to load purchase order details.');
                    }
                    poBsModal.hide();
                    return;
                }

                var po = data.po;
                document.getElementById('supplierPoModalTitle').textContent = (po.po_no || 'PO') + ' — Details';
                document.getElementById('supPoNumberText').textContent = po.po_no || '—';
                document.getElementById('supPoSupplierName').textContent = po.company_name || '—';
                document.getElementById('supPoTerms').textContent = po.payment_terms || 'Credit (30 Days Net)';
                document.getElementById('supPoDateText').textContent = data.formatted_date || po.created_at || '—';
                document.getElementById('supPoEtaText').textContent = data.formatted_eta || po.expected_delivery_date || 'Not Set';
                document.getElementById('supPoRsNo').textContent = po.rs_no || 'N/A';
                document.getElementById('supPoProjectName').textContent = po.project_name || 'Warehouse Restock';

                // Status badge
                var statusBadge = document.getElementById('supPoStatusBadge');
                var status = po.status || 'Generated';
                statusBadge.textContent = status;
                if (status === 'Delivered') {
                    statusBadge.className = 'badge bg-success shadow-sm';
                } else if (status.includes('Discrepancy')) {
                    statusBadge.className = 'badge bg-danger shadow-sm';
                } else if (status === 'Out for Delivery') {
                    statusBadge.className = 'badge bg-primary shadow-sm';
                } else if (status.includes('Delayed')) {
                    statusBadge.className = 'badge bg-warning text-dark shadow-sm';
                } else if (status === 'Viber Order Sent' || status === 'SMS Sent') {
                    statusBadge.className = 'badge text-white shadow-sm';
                    statusBadge.style.backgroundColor = '#7360f2';
                } else {
                    statusBadge.className = 'badge bg-secondary shadow-sm';
                }

                // Populate line items
                var items = data.items || [];
                var tbody = document.getElementById('supPoItemsTableBody');
                var rowsHtml = '';
                if (items.length === 0) {
                    rowsHtml = '<tr><td colspan="5" class="text-center text-muted py-3">No line items recorded for this order.</td></tr>';
                } else {
                    for (var i = 0; i < items.length; i++) {
                        var it = items[i];
                        var ordQty = parseInt(it.quantity || it.ordered_qty || 0, 10);
                        var recQty = parseInt(it.received_quantity || 0, 10);
                        var unitPrice = parseFloat(it.unit_price || 0);
                        var subtotal = parseFloat(it.subtotal || (ordQty * unitPrice));

                        var recBadgeClass = 'text-muted';
                        if (recQty >= ordQty && ordQty > 0) {
                            recBadgeClass = 'text-success fw-bold';
                        } else if (recQty > 0 && recQty < ordQty) {
                            recBadgeClass = 'text-warning fw-bold';
                        }

                        rowsHtml += '<tr class="sup-po-item-row">';
                        rowsHtml += '<td data-label="Item">';
                        rowsHtml += '<div class="fw-bold text-dark item-title">' + escapeHtmlSupplier(it.item_name) + '</div>';
                        rowsHtml += '<small class="text-muted font-monospace"><span class="item-code-badge">' + escapeHtmlSupplier(it.item_code) + '</span> • ' + escapeHtmlSupplier(it.unit || 'pcs') + '</small>';
                        rowsHtml += '</td>';
                        rowsHtml += '<td class="text-center fw-bold" data-label="Ordered">' + ordQty + '</td>';
                        rowsHtml += '<td class="text-center ' + recBadgeClass + '" data-label="Received">' + recQty + '</td>';
                        rowsHtml += '<td class="text-end font-monospace" data-label="Unit Price">₱' + unitPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>';
                        rowsHtml += '<td class="text-end fw-bold font-monospace text-dark" data-label="Subtotal">₱' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>';
                        rowsHtml += '</tr>';
                    }
                }
                tbody.innerHTML = rowsHtml;

                // Total
                var totalAmt = parseFloat(data.total_amount || 0);
                document.getElementById('supPoTotalAmount').textContent = '₱' + totalAmt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // Remarks / Discrepancies
                var remarksWrap = document.getElementById('supPoRemarksCard');
                if (po.delay_remarks && po.delay_remarks.trim() !== '') {
                    remarksWrap.classList.remove('d-none');
                    var isDiscrepancy = status.includes('Discrepancy');
                    var cardClass = isDiscrepancy ? 'border-danger-subtle bg-danger-subtle text-danger-emphasis' : 'border-warning-subtle bg-warning-subtle text-warning-emphasis';
                    var cardIcon = isDiscrepancy ? 'bi-exclamation-triangle-fill' : 'bi-clock-history';
                    remarksWrap.innerHTML = '<div class="card border p-3 rounded-3 shadow-xs ' + cardClass + '"><div class="d-flex align-items-center gap-2 mb-1 fw-bold small"><i class="bi ' + cardIcon + '"></i><span>Delivery Notice / Remarks:</span></div><div class="small" style="white-space: pre-wrap;">' + escapeHtmlSupplier(po.delay_remarks) + '</div></div>';
                } else {
                    remarksWrap.classList.add('d-none');
                    remarksWrap.innerHTML = '';
                }

                // Proof of delivery
                var receiptWrap = document.getElementById('supPoReceiptCard');
                if (po.proof_of_delivery_path && po.proof_of_delivery_path.trim() !== '') {
                    receiptWrap.classList.remove('d-none');
                    receiptWrap.innerHTML = '<div class="card border-0 bg-white p-3 rounded-3 shadow-sm"><div class="d-flex justify-content-between align-items-center"><div class="d-flex align-items-center gap-2"><i class="bi bi-file-earmark-check-fill text-success fs-5"></i><div><span class="fw-bold d-block small text-dark">Proof of Delivery / Delivery Receipt</span><span class="text-muted" style="font-size: 0.75rem;">Verified receipt document attached to this order</span></div></div><a href="' + escapeHtmlSupplier(po.proof_of_delivery_path) + '" target="_blank" class="btn btn-sm btn-outline-primary fw-bold"><i class="bi bi-eye me-1"></i>View File</a></div></div>';
                } else {
                    receiptWrap.classList.add('d-none');
                    receiptWrap.innerHTML = '';
                }

                document.getElementById('supplierPoLoading').classList.add('d-none');
                document.getElementById('supplierPoContent').classList.remove('d-none');
            })
            .catch(function (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'PO Details',
                        text: err.message || 'Error loading purchase order details.',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    alert(err.message || 'Error loading purchase order details.');
                }
                poBsModal.hide();
            });
    };

    window.backToSupplierHistory = function () {
        var poModalEl = document.getElementById('supplierPoDetailModal');
        var poBsModal = bootstrap.Modal.getInstance(poModalEl);
        if (poBsModal) poBsModal.hide();

        if (currentSupplierHistoryId) {
            window.viewSupplierHistory(currentSupplierHistoryId);
        } else {
            var histModalEl = document.getElementById('supplierHistoryModal');
            var histBsModal = bootstrap.Modal.getInstance(histModalEl) || new bootstrap.Modal(histModalEl);
            histBsModal.show();
        }
    };

    window.deleteSupplier = async function (supplierId, companyName, btnEl) {
        if (!supplierId) return;

        let confirmed = false;
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Delete Supplier?',
                html: 'Are you sure you want to delete <strong>' + escapeHtmlSupplier(companyName || 'this supplier') + '</strong>?<br><small class="text-muted">This action will remove the supplier record.</small>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Yes, Delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true
            });
            confirmed = result.isConfirmed;
        } else {
            confirmed = confirm('Are you sure you want to delete "' + (companyName || 'this supplier') + '"?');
        }

        if (!confirmed) return;

        var originalHtml = '';
        if (btnEl) {
            btnEl.disabled = true;
            originalHtml = btnEl.innerHTML;
            btnEl.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        }

        try {
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                            document.querySelector('input[name="csrf_token"]')?.value || '';

            var formData = new FormData();
            formData.append('action', 'delete_supplier');
            formData.append('id', supplierId);
            formData.append('csrf_token', csrfToken);

            var response = await fetch('process/process.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            });

            var data = await response.json();
            var isSuccess = data.success === true || data.status === 'success';

            if (isSuccess) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message || 'Supplier deleted successfully.',
                        timer: 1800,
                        showConfirmButton: false
                    });
                }

                // Smoothly fade out and remove both the row and the mobile card
                var row = document.getElementById('supplier-row-' + supplierId);
                var card = document.getElementById('supplier-card-' + supplierId);

                if (row) {
                    row.style.transition = 'all 0.35s ease-out';
                    row.style.opacity = '0';
                    row.style.transform = 'scale(0.95)';
                    setTimeout(function () { row.remove(); }, 350);
                }
                if (card) {
                    card.style.transition = 'all 0.35s ease-out';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(function () { card.remove(); }, 350);
                }

                setTimeout(function () {
                    var remainingRows = document.querySelectorAll('#suppliersTable tbody tr.supplier-row');
                    if (remainingRows.length === 0) {
                        var tbody = document.querySelector('#suppliersTable tbody');
                        if (tbody) {
                            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No suppliers found.</td></tr>';
                        }
                    }
                    if (typeof applySupplierFilters === 'function') {
                        applySupplierFilters();
                    }
                }, 400);
            } else {
                throw new Error(data.message || 'Failed to delete supplier.');
            }
        } catch (err) {
            console.error('Delete supplier error:', err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Cannot Delete Supplier',
                    text: err.message || 'An error occurred while deleting the supplier.',
                    confirmButtonColor: '#0d6efd'
                });
            } else {
                alert(err.message || 'An error occurred while deleting the supplier.');
            }
            if (btnEl) {
                btnEl.disabled = false;
                btnEl.innerHTML = originalHtml;
            }
        }
    };

    // Live search & status filtering for suppliers
    window.currentSupplierFilter = 'all';

    window.filterSuppliersTable = function(status, btnEl) {
        document.querySelectorAll('.supplier-filter-btn').forEach(function(b) {
            b.classList.remove('active', 'btn-dark', 'btn-success', 'btn-danger');
            var filter = b.getAttribute('data-filter');
            if (filter === 'active') {
                b.className = 'btn btn-sm btn-outline-success rounded-pill px-3 supplier-filter-btn';
            } else if (filter === 'inactive') {
                b.className = 'btn btn-sm btn-outline-danger rounded-pill px-3 supplier-filter-btn';
            } else {
                b.className = 'btn btn-sm btn-outline-dark rounded-pill px-3 supplier-filter-btn';
            }
        });

        if (btnEl) {
            btnEl.classList.add('active');
            if (status === 'active') {
                btnEl.className = 'btn btn-sm btn-success rounded-pill px-3 supplier-filter-btn active';
            } else if (status === 'inactive') {
                btnEl.className = 'btn btn-sm btn-danger rounded-pill px-3 supplier-filter-btn active';
            } else {
                btnEl.className = 'btn btn-sm btn-dark rounded-pill px-3 supplier-filter-btn active';
            }
        }

        window.currentSupplierFilter = status;
        applySupplierFilters();
    };

    window.applySupplierFilters = function() {
        var searchInput = document.getElementById('supplierSearchInput');
        var searchVal = (searchInput ? searchInput.value : '').trim().toLowerCase();
        var filterVal = window.currentSupplierFilter || 'all';

        // Filter Desktop Rows
        var rows = document.querySelectorAll('#suppliersTable tbody tr.supplier-row');
        var visibleDesktop = 0;
        rows.forEach(function(row) {
            var rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
            var rowText = (row.textContent || '').toLowerCase();

            var matchesFilter = (filterVal === 'all' || rowStatus === filterVal);
            var matchesSearch = (!searchVal || rowText.indexOf(searchVal) !== -1);

            if (matchesFilter && matchesSearch) {
                row.style.display = '';
                visibleDesktop++;
            } else {
                row.style.display = 'none';
            }
        });

        // Filter Mobile Cards
        var cards = document.querySelectorAll('#suppliersMobileCards .cims-mobile-card');
        var visibleMobile = 0;
        cards.forEach(function(card) {
            var cardStatus = (card.getAttribute('data-status') || '').toLowerCase();
            var cardSearch = (card.getAttribute('data-search') || card.textContent || '').toLowerCase();

            var matchesFilter = (filterVal === 'all' || cardStatus === filterVal);
            var matchesSearch = (!searchVal || cardSearch.indexOf(searchVal) !== -1);

            if (matchesFilter && matchesSearch) {
                card.style.display = '';
                visibleMobile++;
            } else {
                card.style.display = 'none';
            }
        });

        var emptyMobile = document.getElementById('suppliersMobileEmpty');
        if (emptyMobile) {
            emptyMobile.classList.toggle('d-none', visibleMobile > 0);
        }
    };

    // Initialize search listeners
    document.addEventListener('DOMContentLoaded', function() {
        var searchInput = document.getElementById('supplierSearchInput');
        var clearBtn = document.getElementById('clearSupplierSearch');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                if (clearBtn) {
                    clearBtn.classList.toggle('d-none', !this.value.trim());
                }
                applySupplierFilters();
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                clearBtn.classList.add('d-none');
                applySupplierFilters();
            });
        }
    });
</script>

<?php include 'layout/footer.php'; ?>