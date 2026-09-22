<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// Restrict Requestor from viewing Materials Inventory
if ($role === 'requestor') {
    header("Location: requisitions");
    exit;
}

// Fetch Inventory Data
$search = $_GET['search'] ?? '';
$query = "SELECT i.*, COALESCE(u.reorder_level, 10) as reorder_level FROM inventory i LEFT JOIN units u ON i.unit = u.unit_name";
$params = [];
if ($search) {
    $query .= " WHERE (i.item_name LIKE :search OR i.item_code LIKE :search OR i.category LIKE :search OR i.unit LIKE :search)";
    $params[':search'] = "%$search%";
}
$query .= " ORDER BY i.last_updated DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pendingRSCount = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending Approval'")->fetchColumn();
$totalItems = count($items);
$totalValue = array_reduce($items, fn($carry, $item) => $carry + ($item['quantity'] * $item['unit_price']), 0);
$lowStockCount = count(array_filter($items, fn($item) => $item['quantity'] > 0 && $item['quantity'] <= (int) $item['reorder_level']));

// Calculate Percentages for the new UI Refresh
$lowStockPercentage = ($totalItems > 0) ? round(($lowStockCount / $totalItems) * 100) : 0;
$healthyStockPercentage = 100 - $lowStockPercentage;

// ==========================================
// FETCH BOTH UNITS AND CATEGORIES
// ==========================================
$dynamicUnits = $pdo->query("SELECT unit_name FROM units ORDER BY unit_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$dynamicCategories = $pdo->query("SELECT category_name FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Incoming Warehouse Supply ETAs
$todayStr = date('Y-m-d');
$incomingSupplyStmt = $pdo->prepare("
    SELECT p.*, s.company_name 
    FROM purchase_orders p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.status NOT IN ('Delivered', 'Delivered (Discrepancy)', 'Cancelled')
    ORDER BY 
        CASE 
            WHEN p.expected_delivery_date = ? THEN 1
            WHEN p.expected_delivery_date < ? THEN 2
            ELSE 3 
        END,
        p.expected_delivery_date ASC
    LIMIT 4
");
$incomingSupplyStmt->execute([$todayStr, $todayStr]);
$incomingSupplies = $incomingSupplyStmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<!-- Include External CSS -->
<link rel="stylesheet" href="assets/css/inventory.css">

<!-- Dynamic PHP Data injected to JS -->
<script> const inventoryData = <?= json_encode($items) ?>; </script>

<div class="container-fluid px-3 px-md-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <?php if (false): // Commented out: Total Inventory Value stat cards through Incoming Supply Deliveries ?>
        <!-- STAT CARDS -->
        <div class="row mb-4 g-3">
            <div class="col-12 col-md-4">
                <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0"
                    style="border-left: 4px solid var(--gb-blue) !important;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">Total Inventory Value</h6>
                            <h3 class="mb-0 fw-bold text-dark">₱<?= number_format($totalValue, 2) ?></h3>
                        </div>
                        <div class="fs-1 text-primary" style="color: var(--gb-blue) !important;"><i
                                class="bi bi-cash-stack"></i></div>
                    </div>
                    <div class="progress mt-2" style="height: 5px;">
                        <div class="progress-bar" role="progressbar" style="width: 100%; background-color: var(--gb-blue);">
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block fw-bold"><i class="bi bi-graph-up-arrow me-1 text-success"></i>
                        Across <?= $totalItems ?> items</small>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0"
                    style="border-left: 4px solid #dc3545 !important;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">Low Stock Alerts</h6>
                            <h3 class="mb-0 fw-bold text-danger"><?= $lowStockCount ?></h3>
                        </div>
                        <div class="fs-1 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                    <div class="progress mt-2" style="height: 5px;">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $lowStockPercentage ?>%">
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block fw-bold"><?= $lowStockPercentage ?>% of inventory requires
                        restock</small>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <?php if ($role === 'management' || $role === 'admin'): ?>
                    <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0"
                        style="border-left: 4px solid var(--gb-yellow); cursor: pointer;"
                        onclick="window.location.href='requisitions'">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">Action Required</h6>
                                <h3 class="mb-0 fw-bold text-dark"><?= $pendingRSCount ?> <span
                                        class="fs-6 text-muted fw-normal">Pending RS</span></h3>
                            </div>
                            <div class="fs-1 text-warning"><i class="bi bi-file-earmark-check"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 5px;">
                            <div class="progress-bar bg-warning" role="progressbar"
                                style="width: <?= $pendingRSCount > 0 ? '100' : '0' ?>%"></div>
                        </div>
                        <small class="text-muted mt-2 d-block fw-bold">Click here to review requests</small>
                    </div>
                <?php else: ?>
                    <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0"
                        style="border-left: 4px solid #198754 !important;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">Healthy Stock</h6>
                                <h3 class="mb-0 fw-bold text-success"><?= $healthyStockPercentage ?>%</h3>
                            </div>
                            <div class="fs-1 text-success"><i class="bi bi-shield-check"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 5px;">
                            <div class="progress-bar bg-success" role="progressbar"
                                style="width: <?= $healthyStockPercentage ?>%"></div>
                        </div>
                        <small class="text-muted mt-2 d-block fw-bold"><?= $totalItems - $lowStockCount ?> out of
                            <?= $totalItems ?> items are well-stocked</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- INCOMING SUPPLY ETAS & WAREHOUSE ARRIVALS -->
        <div class="card border-0 shadow-sm p-3 mb-4 bg-white rounded-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <div class="bg-primary text-white rounded-circle p-2 me-2 d-flex align-items-center justify-content-center"
                        style="width: 36px; height: 36px;">
                        <i class="bi bi-truck fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Incoming Supply Deliveries & Warehouse ETAs</h6>
                        <small class="text-muted" style="font-size:0.75rem;">Real-time tracking of supplier shipments
                            arriving at warehouse</small>
                    </div>
                </div>
                <a href="po" class="btn btn-sm btn-outline-primary fw-bold px-3" style="font-size:0.78rem;">
                    <i class="bi bi-box-arrow-up-right me-1"></i>View All POs
                </a>
            </div>

            <div class="row g-2">
                <?php if (count($incomingSupplies) > 0): ?>
                    <?php foreach ($incomingSupplies as $inPo): ?>
                        <?php
                        $etaStr = $inPo['expected_delivery_date'];
                        $etaBadgeClass = 'bg-secondary';
                        $etaText = 'ETA Not Set';
                        $iconClass = 'bi-calendar-event';

                        if (!empty($etaStr)) {
                            $daysDiff = (int) ((strtotime($etaStr) - strtotime($todayStr)) / 86400);
                            if ($daysDiff == 0) {
                                $etaBadgeClass = 'bg-warning text-dark';
                                $etaText = 'Arriving TODAY';
                                $iconClass = 'bi-truck-flatbed';
                            } elseif ($daysDiff < 0) {
                                $etaBadgeClass = 'bg-danger';
                                $etaText = 'Overdue by ' . abs($daysDiff) . 'd';
                                $iconClass = 'bi-exclamation-triangle-fill';
                            } else {
                                $etaBadgeClass = 'bg-success';
                                $etaText = 'In ' . $daysDiff . ' days (' . date('M d', strtotime($etaStr)) . ')';
                                $iconClass = 'bi-clock-history';
                            }
                        }
                        ?>
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="fw-bold text-primary"
                                            style="font-size:0.85rem;"><?= htmlspecialchars($inPo['po_no']) ?></span>
                                        <span class="badge <?= $etaBadgeClass ?>" style="font-size:0.68rem;">
                                            <i class="bi <?= $iconClass ?> me-1"></i><?= $etaText ?>
                                        </span>
                                    </div>
                                    <div class="text-dark fw-semibold small mb-1">
                                        <i
                                            class="bi bi-building me-1 text-muted"></i><?= htmlspecialchars($inPo['company_name'] ?: 'Supplier') ?>
                                    </div>
                                </div>
                                <div class="pt-2 border-top mt-2 d-flex justify-content-between align-items-center">
                                    <small class="text-muted" style="font-size:0.7rem;">Status: <strong
                                            class="text-dark"><?= htmlspecialchars($inPo['status']) ?></strong></small>
                                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                                        <button type="button" class="btn btn-xs btn-link text-primary p-0 text-decoration-none fw-bold"
                                            style="font-size:0.75rem;"
                                            onclick="openEditEtaModal(<?= $inPo['id'] ?>, '<?= $inPo['po_no'] ?>', '<?= $inPo['expected_delivery_date'] ?? '' ?>')">
                                            <i class="bi bi-pencil-square me-1"></i>ETA
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center text-muted py-3">
                        <small><i class="bi bi-check2-all text-success me-1"></i>No active pending supplier deliveries at this
                            time.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- INVENTORY TABLE -->
    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">

        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-xl-3 text-center text-xl-start">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Inventory</h4>
            </div>

            <div class="col-12 col-xl-9">
                <div
                    class="d-flex flex-wrap justify-content-start justify-content-xl-end align-items-center gap-2 w-100">

                    <!-- Search acts as order-1 -->
                    <form class="d-flex flex-grow-1 flex-md-grow-0 shadow-sm order-1"
                        style="max-width: 250px; min-width: 180px;">
                        <div class="input-group w-100">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i
                                    class="bi bi-search"></i></span>
                            <input type="text" id="searchInventory" class="form-control border-start-0 ps-0 bg-white"
                                placeholder="Search items...">
                        </div>
                    </form>

                    <!-- Filter is order-md-2 (hidden on mobile) -->
                    <div class="dropdown shadow-sm flex-grow-1 flex-md-grow-0 d-none d-md-block order-md-2">
                        <button
                            class="btn btn-white border w-100 text-start d-flex justify-content-between align-items-center fw-bold text-dark"
                            type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            style="min-width: 120px;">
                            <span><i class="bi bi-sliders text-primary me-2"></i>Filter</span>
                            <i class="bi bi-chevron-down ms-2 text-muted" style="font-size: 0.8rem;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow p-3"
                            style="min-width: 220px; border-radius: 10px;">
                            <li>
                                <h6 class="dropdown-header text-dark fw-bold px-1 mb-2">Show/Hide Columns</h6>
                            </li>
                            <li>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col1" value="1"
                                        checked>
                                    <label class="form-check-label ms-1" for="col1">Item Code</label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col2" value="2"
                                        checked>
                                    <label class="form-check-label ms-1" for="col2">Item Name</label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col3" value="3"
                                        checked>
                                    <label class="form-check-label ms-1" for="col3">Category</label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col4" value="4"
                                        checked>
                                    <label class="form-check-label ms-1" for="col4">Qty & Unit</label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col5" value="5"
                                        checked>
                                    <label class="form-check-label ms-1" for="col5">Status</label>
                                </div>
                            </li>
                            <?php if (in_array($role, ['admin', 'warehouse'])): ?>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input col-toggle" type="checkbox" id="col6" value="6"
                                            checked>
                                        <label class="form-check-label ms-1" for="col6">Actions (Buttons)</label>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <?php if ($role === 'admin'): ?>
                        <!-- Admin Direct Add Item Button -->
                        <button class="btn btn-brand fw-bold shadow-sm flex-grow-1 flex-md-grow-0 px-3 order-2 order-md-3"
                            data-bs-toggle="modal" data-bs-target="#itemModal" onclick="openAddModal()">
                            <i class="bi bi-plus-lg me-1"></i> Add Item
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-none d-md-block table-responsive inventory-table-wrapper border rounded shadow-sm bg-white">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="inventoryTable">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3">Item Code</th>
                        <th class="py-3">Item Name</th>
                        <th class="py-3">Category</th>
                        <th class="py-3">Qty & Unit</th>
                        <th class="py-3">Status</th>
                        <?php if (in_array($role, ['admin', 'warehouse'])): ?>
                            <th class="text-center py-3">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $qty = (int) $item['quantity'];
                            $reorderLevel = (int) $item['reorder_level'];
                            if ($qty <= 0) {
                                $statusText = 'Out of Stock';
                                $statusClass = 'bg-danger';
                            } elseif ($qty <= $reorderLevel) {
                                $statusText = 'Low Stock';
                                $statusClass = 'bg-warning text-dark';
                            } else {
                                $statusText = 'In Stock';
                                $statusClass = 'bg-success';
                            }
                            ?>
                            <tr class="item-row" id="inventory_row_<?= (int) $item['id'] ?>" data-status="<?= $statusText ?>">
                                <td class="fw-bold text-muted" data-label="Item Code">
                                    <?= htmlspecialchars($item['item_code']) ?></td>
                                <td class="fw-bold text-dark" data-label="Item Name"><?= htmlspecialchars($item['item_name']) ?>
                                </td>
                                <td data-label="Category"><span
                                        class="badge bg-secondary shadow-sm"><?= htmlspecialchars($item['category']) ?></span>
                                </td>

                                <td data-label="Qty & Unit" data-sort-value="<?= $qty ?>">
                                    <div>
                                        <span class="fw-bold fs-5 <?= $qty <= 0 ? 'text-danger' : 'text-dark' ?>"
                                            id="qty_<?= htmlspecialchars($item['item_code']) ?>"><?= $qty ?></span>
                                        <span class="text-muted small ms-1"><?= htmlspecialchars($item['unit']) ?></span>
                                    </div>
                                </td>

                                <td data-label="Status">
                                    <span class="badge <?= $statusClass ?> shadow-sm px-2 py-1"
                                        id="status_<?= htmlspecialchars($item['item_code']) ?>"><?= $statusText ?></span>
                                </td>

                                <?php if (in_array($role, ['admin', 'warehouse'])): ?>
                                    <td class="text-center" data-label="Actions">
                                        <button class="btn btn-sm btn-outline-secondary me-1 shadow-sm" title="Print QR Label"
                                            onclick="showItemQR('<?= $item['item_code'] ?>', '<?= addslashes($item['item_name']) ?>')"><i
                                                class="bi bi-qr-code"></i></button>
                                        <?php if ($role === 'admin'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1 shadow-sm"
                                                title="Edit Material" data-bs-toggle="modal" data-bs-target="#itemModal"
                                                onclick="openEditModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['item_code'])) ?>', '<?= htmlspecialchars(addslashes($item['item_name'])) ?>', '<?= htmlspecialchars(addslashes($item['category'])) ?>', <?= $qty ?>, '<?= htmlspecialchars(addslashes($item['unit'])) ?>', <?= (float) ($item['unit_price'] ?? 0) ?>, '<?= $statusText ?>')"><i
                                                    class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger shadow-sm"
                                                title="Delete Material"
                                                onclick="deleteInventoryItem(<?= (int) $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['item_code']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($item['item_name']), ENT_QUOTES, 'UTF-8') ?>')">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-records">
                            <td colspan="6" class="text-center py-5 text-muted"><i
                                    class="bi bi-folder-x fs-1 d-block mb-2"></i>No items found in inventory.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Phone Cards Container (<768px) -->
        <div class="d-block d-md-none pb-5 mb-4" id="inventoryMobileCards" role="region" aria-label="Mobile Inventory List">
            <?php if (count($items) > 0): ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $qty = (int) $item['quantity'];
                    $reorderLevel = (int) $item['reorder_level'];
                    if ($qty <= 0) {
                        $statusText = 'Out of Stock';
                        $statusClass = 'bg-danger';
                        $statusIcon = 'bi-x-circle-fill';
                    } elseif ($qty <= $reorderLevel) {
                        $statusText = 'Low Stock';
                        $statusClass = 'bg-warning text-dark';
                        $statusIcon = 'bi-exclamation-triangle-fill';
                    } else {
                        $statusText = 'In Stock';
                        $statusClass = 'bg-success';
                        $statusIcon = 'bi-check-circle-fill';
                    }
                    $unitPrice = (float) ($item['unit_price'] ?? 0);
                    ?>
                    <div class="card cims-mobile-card mb-3 shadow-sm border inv-card" 
                         id="inventory_card_<?= (int) $item['id'] ?>"
                         data-item-code="<?= htmlspecialchars($item['item_code']) ?>"
                         data-item-name="<?= htmlspecialchars($item['item_name']) ?>"
                         data-category="<?= htmlspecialchars($item['category']) ?>"
                         data-status="<?= $statusText ?>">
                        <div class="card-body p-3">
                            <!-- Card Header: Title, Item Code & Status Badge -->
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div class="min-w-0">
                                    <div class="d-flex align-items-center gap-1.5 mb-1">
                                        <i class="bi bi-box-seam text-primary" aria-hidden="true"></i>
                                        <h6 class="fw-bold mb-0 text-truncate text-dark"><?= htmlspecialchars($item['item_name']) ?></h6>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-1.5">
                                        <span class="badge bg-light text-secondary border font-monospace small px-2 py-1">
                                            <i class="bi bi-upc-scan me-1 text-muted" aria-hidden="true"></i><?= htmlspecialchars($item['item_code']) ?>
                                        </span>
                                        <?php if ($unitPrice > 0): ?>
                                            <span class="badge bg-light text-muted border small px-2 py-1">
                                                <i class="bi bi-tag-fill me-1 text-primary opacity-75" aria-hidden="true"></i>₱<?= number_format($unitPrice, 2) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge <?= $statusClass ?> shadow-sm px-2.5 py-1.5 flex-shrink-0 d-inline-flex align-items-center gap-1"
                                      id="mobile_status_<?= htmlspecialchars($item['item_code']) ?>">
                                    <i class="bi <?= $statusIcon ?>" aria-hidden="true"></i>
                                    <span><?= $statusText ?></span>
                                </span>
                            </div>

                            <!-- Card Middle: Category, Stock Qty & Unit -->
                            <div class="p-2.5 rounded bg-light border d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="text-muted small d-block mb-0.5">Category</span>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($item['category']) ?></span>
                                </div>
                                <div class="text-end">
                                    <span class="text-muted small d-block mb-0.5">On-Hand Stock</span>
                                    <span class="fw-bold fs-6 <?= $qty <= 0 ? 'text-danger' : 'text-dark' ?>"
                                          id="mobile_qty_<?= htmlspecialchars($item['item_code']) ?>"><?= $qty ?></span>
                                    <span class="text-muted small ms-1"><?= htmlspecialchars($item['unit']) ?></span>
                                </div>
                            </div>

                            <!-- Card Footer: Touch-Friendly Action Buttons (>=44px) -->
                            <?php if (in_array($role, ['admin', 'warehouse'])): ?>
                                <div class="row g-2 cims-mobile-actions">
                                    <?php if ($role === 'admin'): ?>
                                        <div class="col-4">
                                            <button type="button" class="btn btn-sm btn-outline-secondary w-100 shadow-sm d-flex align-items-center justify-content-center gap-1"
                                                    title="Print QR Label"
                                                    aria-label="Print QR Label for <?= htmlspecialchars($item['item_name']) ?>"
                                                    onclick="showItemQR('<?= $item['item_code'] ?>', '<?= addslashes($item['item_name']) ?>')">
                                                <i class="bi bi-qr-code" aria-hidden="true"></i>
                                                <span>QR</span>
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100 shadow-sm d-flex align-items-center justify-content-center gap-1"
                                                    title="Edit Material" 
                                                    aria-label="Edit Material <?= htmlspecialchars($item['item_name']) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#itemModal"
                                                    onclick="openEditModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['item_code'])) ?>', '<?= htmlspecialchars(addslashes($item['item_name'])) ?>', '<?= htmlspecialchars(addslashes($item['category'])) ?>', <?= $qty ?>, '<?= htmlspecialchars(addslashes($item['unit'])) ?>', <?= $unitPrice ?>, '<?= $statusText ?>')">
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                <span>Edit</span>
                                            </button>
                                        </div>
                                        <div class="col-2">
                                            <button type="button" class="btn btn-sm btn-outline-danger w-100 shadow-sm d-flex align-items-center justify-content-center"
                                                    title="Delete Material"
                                                    aria-label="Delete Material: <?= htmlspecialchars($item['item_name']) ?>"
                                                    onclick="deleteInventoryItem(<?= (int) $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['item_code']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($item['item_name']), ENT_QUOTES, 'UTF-8') ?>')">
                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    <?php else: /* warehouse role */ ?>
                                        <div class="col-12">
                                            <button type="button" class="btn btn-sm btn-outline-secondary w-100 shadow-sm d-flex align-items-center justify-content-center gap-2"
                                                    title="Print QR Label"
                                                    aria-label="Print QR Label for <?= htmlspecialchars($item['item_name']) ?>"
                                                    onclick="showItemQR('<?= $item['item_code'] ?>', '<?= addslashes($item['item_name']) ?>')">
                                                <i class="bi bi-qr-code" aria-hidden="true"></i>
                                                <span>Print QR Label</span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div id="noResultsInvMobile" class="alert alert-light text-center py-4 border shadow-sm my-2 text-muted" style="display: none;">
                <i class="bi bi-folder-x fs-2 d-block mb-1 text-secondary" aria-hidden="true"></i>
                No matching items found.
            </div>
        </div>
    </div>
</div>

<?php include 'components/inventory_modals.php'; ?>
<?php include 'layout/footer.php'; ?>