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

<!-- Mobile Card Table CSS for Suppliers -->
<style>
    @media (max-width: 767.98px) {
        .table-responsive {
            overflow-x: hidden !important;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        #suppliersTable {
            display: block;
            width: 100%;
            background: transparent !important;
        }

        #suppliersTable thead {
            display: none;
        }

        #suppliersTable tbody {
            display: block;
            width: 100%;
        }

        #suppliersTable tbody tr {
            display: flex;
            flex-direction: column;
            border: 1px solid #e0e4e8;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #fff;
            padding: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }

        #suppliersTable tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-align: right;
            padding: 10px 4px;
            border: none;
            border-bottom: 1px dashed #e9ecef;
            white-space: normal !important;
            word-break: break-word;
        }

        /* Center the Actions button at the bottom of the card */
        #suppliersTable tbody td:last-child {
            border-bottom: none;
            justify-content: center !important;
            gap: 10px;
            padding-top: 16px;
            margin-top: 4px;
        }

        #suppliersTable tbody td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            text-align: left;
            padding-right: 15px;
            flex-shrink: 0;
        }

        #suppliersTable tbody td:last-child::before {
            display: none;
        }
    }
</style>

<div class="container-fluid px-3 px-md-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-md-8 text-center text-md-start">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-buildings me-2 text-primary"></i>Suppliers</h4>
            </div>

            <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                <div class="col-12 col-md-4 text-md-end">
                    <button class="btn btn-brand shadow-sm w-100 w-md-auto fw-bold px-4" data-bs-toggle="modal"
                        data-bs-target="#supplierModal" onclick="openAddSupplierModal()">
                        <i class="bi bi-plus-lg me-1"></i> Add New Supplier
                    </button>
                </div>
            <?php endif; ?>
        </div>

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
                        <tr>
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
                                <i class="bi bi-telephone text-muted me-1 d-none d-md-inline"></i><?= htmlspecialchars($sup['contact_number']) ?>
                                <?php if (!empty($sup['contact_number'])): 
                                    $cleanViberPhone = preg_replace('/[^0-9]/', '', $sup['contact_number']);
                                    if (strpos($cleanViberPhone, '09') === 0) { $cleanViberPhone = '63' . substr($cleanViberPhone, 1); }
                                    if (strpos($cleanViberPhone, '+') !== 0) { $cleanViberPhone = '+' . $cleanViberPhone; }
                                ?>
                                    <a href="viber://chat?number=<?= urlencode($cleanViberPhone) ?>" class="btn btn-sm btn-viber ms-2 px-2 py-1 shadow-sm fw-semibold" style="font-size: 0.78rem;" title="Chat via Viber">
                                        <i class="fa-brands fa-viber me-1"></i>Viber
                                    </a>
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
                                    <button class="btn btn-sm btn-outline-primary shadow-sm me-1" data-bs-toggle="modal"
                                        data-bs-target="#supplierModal"
                                        onclick="openEditSupplierModal(<?= $sup['id'] ?>, <?= htmlspecialchars(json_encode($sup['supplier_code'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['company_name'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['contact_person'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['contact_number'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['email'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['address'] ?? '')) ?>, <?= htmlspecialchars(json_encode($sup['status'] ?? '')) ?>)">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>

                                    <form method="POST" action="process/process.php" class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to completely delete this supplier?');">
                                        <input type="hidden" name="action" value="delete_supplier"><input type="hidden"
                                            name="id" value="<?= $sup['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm"><i
                                                class="bi bi-trash3"></i></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EXTERNAL MODAL -->
<?php include 'components/supplier_modal.php'; ?>

<!-- ==========================================
  MODAL: SUPPLIER DELIVERY HISTORY
=========================================== -->
<div class="modal fade" id="supplierHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-clipboard2-data me-2" style="color: var(--gb-yellow);"></i>
                    <span id="historyModalTitle">Delivery History</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">

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
                <button type="button" class="btn btn-secondary fw-bold px-4 shadow-sm"
                    data-bs-dismiss="modal">Close</button>
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
</style>

<script>
    window.viewSupplierHistory = function (supplierId) {
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
                    alert(data.message || 'Failed to load history.');
                    bsModal.hide();
                    return;
                }

                // Update title
                document.getElementById('historyModalTitle').textContent = data.supplier.company_name + ' — Delivery History';

                // Build summary cards
                var s = data.summary;
                var cardsHtml = '';
                cardsHtml += '<div class="col-6 col-md-3"><div class="stat-card"><div class="stat-number text-primary">' + s.total + '</div><div class="stat-label">Total Orders</div></div></div>';
                cardsHtml += '<div class="col-6 col-md-3"><div class="stat-card" style="border-bottom: 3px solid #198754;"><div class="stat-number text-success">' + s.good + '</div><div class="stat-label"><i class="bi bi-check-circle-fill me-1"></i>Good Deliveries</div></div></div>';
                cardsHtml += '<div class="col-6 col-md-3"><div class="stat-card" style="border-bottom: 3px solid #dc3545;"><div class="stat-number text-danger">' + s.discrepancies + '</div><div class="stat-label"><i class="bi bi-exclamation-triangle-fill me-1"></i>Discrepancies</div></div></div>';
                cardsHtml += '<div class="col-6 col-md-3"><div class="stat-card" style="border-bottom: 3px solid #ffc107;"><div class="stat-number text-warning">' + s.delayed + '</div><div class="stat-label"><i class="bi bi-clock-fill me-1"></i>Delayed</div></div></div>';
                document.getElementById('historySummaryCards').innerHTML = cardsHtml;

                // Build orders list
                var orders = data.orders;
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
                        } else {
                            barColor = '#6c757d';
                            icon = 'bi-hourglass-split text-secondary';
                            badgeClass = 'bg-secondary';
                            statusLabel = o.status;
                        }

                        html += '<div class="history-card mb-2 shadow-sm">';
                        html += '<div class="card-header-bar" style="background:' + barColor + ';"></div>';
                        html += '<div class="p-3">';

                        // Header row
                        html += '<div class="d-flex justify-content-between align-items-center mb-2">';
                        html += '<div><i class="bi ' + icon + ' me-2"></i><span class="fw-bold text-dark">' + o.po_no + '</span></div>';
                        html += '<div class="d-flex align-items-center gap-2">';
                        html += '<small class="text-muted"><i class="bi bi-calendar3 me-1"></i>' + o.date + '</small>';
                        html += '<span class="badge ' + badgeClass + ' px-2 py-1 shadow-sm" style="font-size:0.7rem;">' + statusLabel + '</span>';
                        html += '</div></div>';

                        // Items
                        html += '<div class="mt-1">';
                        for (var j = 0; j < o.items.length; j++) {
                            var item = o.items[j];
                            html += '<span class="item-pill"><i class="bi bi-box-seam" style="font-size:0.65rem;"></i> ' + item.item_name + ' <span class="text-muted">×' + item.expected_qty + '</span></span>';
                        }
                        html += '</div>';

                        // Discrepancy/Delay details
                        if (o.classification === 'discrepancy' && o.delay_remarks) {
                            var remarks = o.delay_remarks;
                            // Extract just the discrepancy items
                            var discMatch = remarks.match(/\[DELIVERY DISCREPANCY\]:\n([\s\S]*)/);
                            if (discMatch) {
                                var lines = discMatch[1].trim().split('\n');
                                html += '<div class="discrepancy-detail"><i class="bi bi-exclamation-circle-fill me-1"></i><strong>Mismatch Details:</strong><br>';
                                for (var k = 0; k < lines.length; k++) {
                                    if (lines[k].trim()) html += '<span class="d-block ms-2">' + lines[k].trim() + '</span>';
                                }
                                html += '</div>';
                            }
                        } else if (o.classification === 'delayed' && o.delay_remarks) {
                            html += '<div class="discrepancy-detail" style="background:#fffbeb;border-color:#fde68a;color:#92400e;"><i class="bi bi-clock-history me-1"></i><strong>Delay Reason:</strong> ' + o.delay_remarks + '</div>';
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
                alert(err.message || 'Error loading supplier history. Please try again.');
                bsModal.hide();
            });
    }
</script>

<?php include 'layout/footer.php'; ?>