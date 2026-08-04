<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

// Allowed roles for this module
if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'management', 'warehouse'])) {
    header("Location: index");
    exit;
}
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// AUTO-PATCH DB: Ensures the PO table can handle SMS Status and Weather Delays!
try {
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN status VARCHAR(50) DEFAULT 'Generated'");
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN delay_remarks TEXT");
} catch (PDOException $e) { /* Columns already exist */
}

// Fetch Purchase Orders
$query = "
    SELECT p.*, s.company_name, s.contact_number, r.rs_no, r.project_name, u.name AS prepared_by_name 
    FROM purchase_orders p 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    LEFT JOIN requisitions r ON p.rs_id = r.id 
    LEFT JOIN users u ON p.prepared_by = u.id
    ORDER BY p.created_at DESC
";
$pos = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats
$totalPO = count($pos);
$pendingDelivery = count(array_filter($pos, fn($p) => in_array($p['status'], ['Generated', 'SMS Sent', 'Pending Delivery'])));
$delayedPO = count(array_filter($pos, fn($p) => strpos($p['status'], 'Delayed') !== false));

// Fetch suppliers, officers, and projects list for filter dropdowns
$suppliersList = $pdo->query("SELECT DISTINCT id, company_name FROM suppliers ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$officersList = $pdo->query("SELECT DISTINCT u.id, u.name FROM users u JOIN purchase_orders p ON p.prepared_by = u.id ORDER BY u.name ASC")->fetchAll(PDO::FETCH_ASSOC);
$projectsList = $pdo->query("SELECT DISTINCT project_name FROM requisitions WHERE project_name IS NOT NULL AND project_name != '' ORDER BY project_name ASC")->fetchAll(PDO::FETCH_COLUMN);

include 'layout/header.php';
?>

<!-- Premium Mobile Card Table CSS -->
<style>
    @media (max-width: 767.98px) {
        .table-responsive {
            overflow-x: hidden !important;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        #poTable {
            display: block;
            width: 100%;
            background: transparent !important;
        }

        #poTable thead {
            display: none;
        }

        #poTable tbody {
            display: block;
            width: 100%;
        }

        #poTable tbody tr {
            display: flex;
            flex-direction: column;
            border: 1px solid #e0e4e8;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #fff;
            padding: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }

        #poTable tbody td {
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
        #poTable tbody td:last-child {
            border-bottom: none;
            justify-content: center !important;
            gap: 8px;
            padding-top: 16px;
            margin-top: 4px;
            flex-wrap: wrap;
        }

        #poTable tbody td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            text-align: left;
            padding-right: 15px;
            flex-shrink: 0;
        }

        #poTable tbody td:last-child::before {
            display: none;
        }

        /* Receive Modal Table Mobile Stack */
        #receiveItemsTable {
            white-space: normal !important;
            background: transparent !important;
        }

        #receiveItemsTable thead {
            display: none;
        }

        #receiveItemsTable tbody tr {
            display: flex;
            flex-direction: column;
            border: 1px solid #e0e4e8;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #fff;
            padding: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }

        #receiveItemsTable tbody td {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            text-align: right;
            padding: 10px 4px;
            border: none;
            border-bottom: 1px dashed #e9ecef;
            white-space: normal !important;
            word-break: break-word;
            width: 100%;
        }

        #receiveItemsTable tbody td:last-child {
            border-bottom: none;
            align-items: center;
        }

        #receiveItemsTable tbody td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            text-align: left;
            padding-right: 15px;
            flex-shrink: 0;
            white-space: nowrap;
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

    <!-- PO Stats Cards (Premium Hover Effects applied via existing CSS) -->
    <div class="row mb-4 g-3">
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                style="border-left: 5px solid var(--gb-blue) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Total Purchase
                            Orders</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $totalPO ?></h3>
                    </div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue) !important; opacity: 0.8;"><i
                            class="bi bi-file-earmark-text-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                style="border-left: 5px solid var(--gb-yellow) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Pending Deliveries
                        </h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $pendingDelivery ?></h3>
                    </div>
                    <div class="fs-1 text-warning" style="opacity: 0.8;"><i class="bi bi-truck"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                style="border-left: 5px solid #dc3545 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Delayed Orders
                        </h6>
                        <h3 class="mb-0 fw-bold text-danger"><?= $delayedPO ?></h3>
                    </div>
                    <div class="fs-1 text-danger" style="opacity: 0.8;"><i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Datatable Card -->
    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white rounded-3">
        <!-- Main Datatable Top Header -->
        <div class="row align-items-center mb-3 g-2">
            <div class="col-12 col-md-5">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Purchase Orders</h4>
                <small class="text-muted">Manage, track deliveries, and view PO manifests</small>
            </div>

            <div class="col-12 col-md-7">
                <div class="d-flex flex-wrap justify-content-md-end align-items-center gap-2">
                    <!-- Search Bar -->
                    <div class="input-group shadow-sm flex-grow-1 flex-md-grow-0" style="max-width: 280px; min-width: 180px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchPo" class="form-control border-start-0 ps-0 bg-white fw-bold" placeholder="Search PO No, Supplier...">
                    </div>

                    <!-- Filter Toggle Button -->
                    <button class="btn btn-outline-secondary fw-bold shadow-sm d-flex align-items-center gap-1" type="button" data-bs-toggle="collapse" data-bs-target="#poFilterCollapse" aria-expanded="false" aria-controls="poFilterCollapse">
                        <i class="bi bi-funnel-fill text-primary"></i>
                        <span>Filter</span>
                        <span class="badge bg-primary rounded-pill ms-1 d-none" id="activeFilterBadge">0</span>
                    </button>

                    <!-- Create PO Button -->
                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                        <button class="btn btn-brand fw-bold text-nowrap shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#poModal">
                            <i class="bi bi-plus-lg me-1"></i> Create PO
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Collapsible Filter Options Panel -->
        <div class="collapse mb-3" id="poFilterCollapse">
            <div class="card card-body bg-light border-0 shadow-sm p-3 rounded-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0 small text-uppercase"><i class="bi bi-sliders me-1 text-primary"></i> Filter Purchase Orders</h6>
                    <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none fw-bold p-0" onclick="resetAllPoFilters()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset All Filters
                    </button>
                </div>
                <div class="row g-2">
                    <!-- 1. Created By Filter -->
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Created By Officer</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-person-fill text-primary"></i></span>
                            <select id="filterCreator" class="form-select bg-white fw-bold small">
                                <option value="all">All Officers</option>
                                <option value="me">👤 Created by Me</option>
                                <?php foreach ($officersList as $off): ?>
                                    <?php if ($off['id'] != $_SESSION['user_id']): ?>
                                        <option value="<?= $off['id'] ?>"><?= htmlspecialchars($off['name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 2. Supplier Filter -->
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Supplier</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-building text-info"></i></span>
                            <select id="filterSupplier" class="form-select bg-white fw-bold small">
                                <option value="all">All Suppliers</option>
                                <?php foreach ($suppliersList as $sup): ?>
                                    <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 3. Project / Destination Filter -->
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Project Destination</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-geo-alt-fill text-danger"></i></span>
                            <select id="filterProject" class="form-select bg-white fw-bold small">
                                <option value="all">All Destinations</option>
                                <option value="Warehouse Restock">📦 Warehouse Restock</option>
                                <?php foreach ($projectsList as $proj): ?>
                                    <option value="<?= htmlspecialchars($proj) ?>"><?= htmlspecialchars($proj) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 4. Order Status Filter -->
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Order Status</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-tag-fill text-success"></i></span>
                            <select id="filterStatus" class="form-select bg-white fw-bold small">
                                <option value="all">All Statuses</option>
                                <option value="Generated">Generated / Draft</option>
                                <option value="SMS Sent">SMS Sent</option>
                                <option value="Pending Delivery">Pending Delivery</option>
                                <option value="Delivered">Delivered (Complete)</option>
                                <option value="Delivered (Discrepancy)">Delivered (Discrepancy)</option>
                                <option value="Delayed">Delayed (All Reasons)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 5. Delivery Urgency / ETA Filter -->
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Logistics ETA Urgency</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-truck-flatbed text-primary"></i></span>
                            <select id="filterEtaUrgency" class="form-select bg-white fw-bold small">
                                <option value="all">All Deliveries</option>
                                <option value="today">🚚 Arriving Today</option>
                                <option value="overdue">⚠️ Overdue Deliveries</option>
                                <option value="upcoming">📅 Upcoming Deliveries</option>
                            </select>
                        </div>
                    </div>

                    <!-- 6. Date Created Filter -->
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Date Created</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-calendar-event text-warning"></i></span>
                            <input type="date" id="filterDate" class="form-control bg-white fw-bold small">
                            <button type="button" class="btn btn-outline-secondary" title="Clear Date" onclick="document.getElementById('filterDate').value=''; filterPoTable();">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive border rounded shadow-sm bg-white">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="poTable">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3">PO Number</th>
                        <th class="py-3">Date & Time Created</th>
                        <th class="py-3">Linked RS / Project</th>
                        <th class="py-3">Supplier</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Warehouse ETA</th>
                        <th class="text-center py-3">Logistics Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pos) > 0): ?>
                        <?php foreach ($pos as $po): ?>
                            <?php
                            $statusClass = 'bg-secondary';
                            if ($po['status'] === 'Generated')
                                $statusClass = 'bg-info text-dark';
                            if ($po['status'] === 'SMS Sent')
                                $statusClass = 'bg-info text-dark';
                            if ($po['status'] === 'Pending Delivery')
                                $statusClass = 'bg-warning text-dark';
                            if (strpos($po['status'], 'Delayed') !== false)
                                $statusClass = 'bg-danger';
                            if ($po['status'] === 'Delivered')
                                $statusClass = 'bg-success';
                            if ($po['status'] === 'Delivered (Discrepancy)')
                                $statusClass = 'bg-warning text-dark';

                            // Compute ETA Badges & Urgency Filter Attribute
                            $etaBadge = '<span class="text-muted small">Not Set</span>';
                            $etaDateStr = $po['expected_delivery_date'] ?? null;
                            $etaUrgencyVal = 'unset';

                            if (in_array($po['status'], ['Delivered', 'Delivered (Discrepancy)'])) {
                                $etaUrgencyVal = 'delivered';
                            }

                            if ($etaDateStr) {
                                $formattedEta = date('M d, Y', strtotime($etaDateStr));
                                if (in_array($po['status'], ['Delivered', 'Delivered (Discrepancy)'])) {
                                    $etaBadge = '<span class="badge bg-light text-muted border shadow-sm"><i class="bi bi-check2-circle me-1 text-success"></i>' . $formattedEta . '</span>';
                                } else {
                                    $todayTs = strtotime(date('Y-m-d'));
                                    $etaTs = strtotime($etaDateStr);
                                    $daysDiff = (int)(($etaTs - $todayTs) / 86400);

                                    if ($daysDiff == 0) {
                                        $etaBadge = '<span class="badge bg-warning text-dark shadow-sm"><i class="bi bi-truck-flatbed me-1"></i>Arriving Today</span>';
                                        $etaUrgencyVal = 'today';
                                    } elseif ($daysDiff < 0) {
                                        $overdueDays = abs($daysDiff);
                                        $etaBadge = '<span class="badge bg-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-1"></i>Overdue (' . $overdueDays . 'd)</span>';
                                        $etaUrgencyVal = 'overdue';
                                    } else {
                                        $etaBadge = '<span class="badge bg-success shadow-sm"><i class="bi bi-calendar-check me-1"></i>In ' . $daysDiff . 'd (' . date('M d', $etaTs) . ')</span>';
                                        $etaUrgencyVal = 'upcoming';
                                    }
                                }
                            }
                            ?>
                             <tr class="po-row" 
                                data-prepared-by="<?= htmlspecialchars($po['prepared_by'] ?? '') ?>" 
                                data-supplier-id="<?= htmlspecialchars($po['supplier_id'] ?? '') ?>" 
                                data-created-date="<?= !empty($po['created_at']) ? date('Y-m-d', strtotime($po['created_at'])) : '' ?>"
                                data-status="<?= htmlspecialchars($po['status'] ?? 'Generated') ?>"
                                data-project="<?= htmlspecialchars($po['project_name'] ?? 'Warehouse Restock') ?>"
                                data-eta-urgency="<?= $etaUrgencyVal ?>">
                                <td class="fw-bold text-dark po-no" data-label="PO Number"><?= htmlspecialchars($po['po_no']) ?>
                                </td>

                                <td data-label="Date & Time Created">
                                    <span class="d-block text-dark fw-semibold small">
                                        <i class="bi bi-calendar3 me-1 text-muted"></i><?= !empty($po['created_at']) ? date('M d, Y', strtotime($po['created_at'])) : 'N/A' ?>
                                    </span>
                                    <small class="text-muted d-block" style="font-size:0.73rem;">
                                        <i class="bi bi-clock me-1 text-primary"></i><?= !empty($po['created_at']) ? date('g:i A', strtotime($po['created_at'])) : '' ?>
                                    </small>
                                    <div class="mt-1 small fw-bold text-secondary" style="font-size: 0.78rem;" title="Created By Officer">
                                        <i class="bi bi-person-fill me-1 text-primary"></i><?= htmlspecialchars($po['prepared_by_name'] ?? 'System') ?>
                                    </div>
                                </td>

                                <td data-label="Linked RS / Project">
                                    <span class="d-block">
                                        <span
                                            class="badge bg-light text-dark border me-1 shadow-sm"><?= htmlspecialchars($po['rs_no']) ?></span>
                                        <small class="text-muted fw-bold"><?= htmlspecialchars($po['project_name']) ?></small>
                                    </span>
                                </td>

                                <td class="fw-bold text-primary po-supplier" data-label="Supplier">
                                    <span class="d-inline-flex align-items-center">
                                        <i
                                            class="bi bi-building me-2 text-muted"></i><?= htmlspecialchars($po['company_name']) ?>
                                    </span>
                                </td>

                                <td data-label="Status">
                                    <span class="badge <?= $statusClass ?> px-3 py-2 shadow-sm text-uppercase"
                                        id="status_<?= $po['id'] ?>">
                                        <?= htmlspecialchars($po['status'] ?? 'Generated') ?>
                                    </span>
                                    <?php if ($po['status'] === 'Delayed (Weather)'): ?>
                                        <small class="d-block text-danger mt-2 fw-bold"
                                            style="font-size: 0.75rem; white-space: normal;"><i
                                                class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($po['delay_remarks']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td data-label="Warehouse ETA">
                                    <div class="d-flex align-items-center gap-1">
                                        <?= $etaBadge ?>
                                        <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                                            <button type="button" class="btn btn-sm btn-link text-muted p-0 ms-1 text-decoration-none" title="Update ETA" onclick="openEditEtaModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>', '<?= $po['expected_delivery_date'] ?? '' ?>')">
                                                <i class="bi bi-pencil-square fs-6 text-primary"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="text-center" data-label="Actions">
                                    <?php if (in_array($role, ['admin', 'purchasing']) && !in_array($po['status'], ['Delivered', 'Delivered (Discrepancy)'])): ?>
                                        <button class="btn btn-sm btn-outline-success fw-bold shadow-sm me-1"
                                            id="smsBtn_<?= $po['id'] ?>"
                                            onclick="openSmsPreviewModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>', <?= (int) $po['supplier_id'] ?>, '<?= $po['contact_number'] ?>')">
                                            <i class="bi bi-chat-text-fill"></i> <span class="ms-1">SMS</span>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger fw-bold shadow-sm me-1"
                                            onclick="openDelayModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>', '<?= $po['expected_delivery_date'] ?? '' ?>')">
                                            <i class="bi bi-cloud-lightning-rain-fill"></i> <span class="ms-1">Delay</span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($role, ['admin', 'warehouse', 'purchasing']) && $po['status'] !== 'Delivered' && $po['status'] !== 'Delivered (Discrepancy)'): ?>
                                        <!-- RECEIVE ORDER (STOCK IN) -->
                                        <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm me-1"
                                            onclick="openReceiveModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>')">
                                            <i class="bi bi-box-arrow-in-down"></i> <span class="ms-1">Receive</span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!empty($po['proof_of_receipt'])): ?>
                                        <a href="<?= htmlspecialchars($po['proof_of_receipt']) ?>" target="_blank"
                                            class="btn btn-sm btn-outline-info fw-bold shadow-sm me-1" title="View Proof of Receipt">
                                            <i class="bi bi-paperclip"></i> <span class="ms-1">Receipt</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (in_array($role, ['admin', 'management', 'purchasing']) && $po['status'] === 'Delivered (Discrepancy)'): ?>
                                        <!-- VIEW DISCREPANCY BUTTON -->
                                        <button type="button" class="btn btn-sm btn-danger fw-bold shadow-sm me-1"
                                            title="View Discrepancy" data-pono="<?= htmlspecialchars($po['po_no']) ?>"
                                            data-remarks="<?= htmlspecialchars($po['delay_remarks'] ?? 'No remarks provided.') ?>"
                                            data-proof="<?= htmlspecialchars($po['proof_of_receipt'] ?? '') ?>"
                                            onclick="viewDiscrepancy(this)">
                                            <i class="bi bi-search"></i> <span class="ms-1">View Issue</span>
                                        </button>
                                    <?php endif; ?>

                                    <button class="btn btn-sm btn-outline-secondary fw-bold shadow-sm" title="View/Print Virtual PO Document"
                                        onclick="openPoPrintModal(<?= $po['id'] ?>)">
                                        <i class="bi bi-printer"></i> <span class="ms-1">Print</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="noResultsPoRow" style="display: none;">
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-funnel-fill fs-1 d-block mb-2 text-primary opacity-50"></i>
                                <span class="fw-bold">No Purchase Orders match your filter criteria.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted"><i
                                    class="bi bi-folder-x fs-1 d-block mb-2"></i>No Purchase Orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EXTERNAL MODALS -->
<?php include 'components/po_modal.php'; ?>

<!-- SPA-PROOF JAVASCRIPT LOGIC -->
<script>
    // ==========================================
    // NEW: FETCH RS ITEMS & SUPPLIER HISTORY
    // ==========================================
    document.addEventListener('DOMContentLoaded', function () {
        const rsSelect = document.getElementById('poRsSelect');
        if (rsSelect) {
            rsSelect.addEventListener('change', async function () {
                const rsId = this.value;
                if (!rsId) return;

                const container = document.getElementById('rsItemsPreviewContainer');
                const tbody = document.getElementById('rsItemsPreviewBody');

                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div> Loading items...</td></tr>';
                container.classList.remove('d-none');

                let formData = new FormData();
                formData.append('action', 'fetch_rs_with_history');
                formData.append('rs_id', rsId);

                try {
                    const response = await fetch('process/process.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.status === 'success') {
                        tbody.innerHTML = '';
                        if (data.items.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2"><i class="bi bi-info-circle me-1"></i> No items found.</td></tr>';
                            return;
                        }
                        data.items.forEach(item => {
                            const tr = document.createElement('tr');
                            const supplierText = item.last_purchased ?
                                `<span class="text-primary fw-bold" style="font-size: 0.8rem;">${item.last_supplier} <br><small class="text-muted fw-normal">${item.last_purchased}</small></span>` :
                                `${item.last_supplier}`;

                            tr.innerHTML = `
                            <td class="fw-bold text-dark text-wrap">${item.item_name}</td>
                            <td class="text-center fw-bold text-danger">${item.quantity}</td>
                            <td>${supplierText}</td>
                        `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-2">Error: ${data.message}</td></tr>`;
                    }
                } catch (e) {
                    tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-2">Network Error: Could not fetch RS items.</td></tr>`;
                }
            });
        }
    });

    // ==========================================
    // NEW: RECEIVE MODAL LOGIC (Discrepancy Checks & Price Entry)
    // ==========================================
    window.openReceiveModal = async function (id, po_no) {
        document.getElementById('receivePoId').value = id;
        document.getElementById('receivePoNo').value = po_no;

        const tbody = document.getElementById('receiveItemsBody');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><div class="spinner-border text-success spinner-border-sm me-2"></div> Fetching Manifest...</td></tr>';

        var myModalEl = document.getElementById('receiveModal');
        var receiveModal = bootstrap.Modal.getInstance(myModalEl);
        if (!receiveModal) receiveModal = new bootstrap.Modal(myModalEl);
        receiveModal.show();

        let formData = new FormData();
        formData.append('action', 'fetch_po_items');
        formData.append('po_id', id);

        try {
            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                tbody.innerHTML = '';
                if (data.items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No items linked to this manifest.</td></tr>';
                    document.getElementById('confirmReceiveBtn').disabled = true;
                    return;
                }

                document.getElementById('confirmReceiveBtn').disabled = false;

                data.items.forEach(item => {
                    const tr = document.createElement('tr');
                    const initialPrice = parseFloat(item.unit_price || 0).toFixed(2);
                    const initialQty = parseInt(item.expected_qty || 0);
                    const initialSubtotal = (initialQty * parseFloat(initialPrice)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    tr.innerHTML = `
                    <td class="fw-bold text-muted" style="font-size: 0.8rem;" data-label="Item Code">
                        ${item.item_code}
                        <input type="hidden" name="item_codes[]" value="${item.item_code}">
                        <input type="hidden" name="expected_qtys[]" value="${item.expected_qty}">
                    </td>
                    <td class="fw-bold text-dark text-wrap" data-label="Item Name">${item.item_name}</td>
                    <td class="text-center fw-bold text-primary fs-6" data-label="Expected Qty">${item.expected_qty}</td>
                    <td class="text-center align-middle" data-label="Actual Received">
                        <input type="number" name="actual_qtys[]" class="form-control form-control-sm text-center fw-bold text-success border-success shadow-sm ms-auto actual-qty-input" 
                            style="max-width: 90px; font-size: 1rem; height: 35px;" value="${item.expected_qty}" min="0" onclick="this.select()" onfocus="this.select()" required>
                    </td>
                    <td class="text-center align-middle" data-label="Unit Price (₱)">
                        <input type="number" step="0.01" name="unit_prices[]" class="form-control form-control-sm text-center fw-bold text-primary border-primary shadow-sm ms-auto unit-price-input" 
                            style="max-width: 110px; font-size: 1rem; height: 35px;" value="${initialPrice}" min="0" onclick="this.select()" onfocus="this.select()" required>
                    </td>
                    <td class="text-end fw-bold text-dark align-middle subtotal-val" data-label="Subtotal">
                        ₱${initialSubtotal}
                    </td>
                `;
                    tbody.appendChild(tr);

                    const qtyInput = tr.querySelector('.actual-qty-input');
                    const priceInput = tr.querySelector('.unit-price-input');
                    const subtotalTd = tr.querySelector('.subtotal-val');

                    const updateSubtotal = () => {
                        const q = parseFloat(qtyInput.value) || 0;
                        const p = parseFloat(priceInput.value) || 0;
                        subtotalTd.textContent = '₱' + (q * p).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    };

                    qtyInput.addEventListener('input', updateSubtotal);
                    priceInput.addEventListener('input', updateSubtotal);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">Error: ${data.message}</td></tr>`;
            }
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">Network Error: Could not load the manifest.</td></tr>`;
        }
    }

    window.viewDiscrepancy = function (btnElem) {
        document.getElementById('discPoNo').innerText = btnElem.getAttribute('data-pono');

        let rawText = btnElem.getAttribute('data-remarks');
        rawText = rawText.replace(/\[DELIVERY DISCREPANCY\]:/g, '<span class="d-block text-danger fw-bold mb-1 border-bottom border-danger border-opacity-25 pb-2"><i class="bi bi-x-circle-fill me-1"></i> DELIVERY DISCREPANCY ISSUES</span>');
        document.getElementById('discRemarks').innerHTML = rawText;

        const proofPath = btnElem.getAttribute('data-proof');
        const proofContainer = document.getElementById('discProofContainer');
        if (proofContainer) {
            if (proofPath && proofPath.trim() !== '') {
                proofContainer.classList.remove('d-none');
                proofContainer.innerHTML = `
                    <div class="alert alert-info py-2 px-3 mb-0 d-flex justify-content-between align-items-center">
                        <span class="small fw-bold"><i class="bi bi-paperclip me-1"></i> Proof of Receipt Attached</span>
                        <a href="${proofPath}" target="_blank" class="btn btn-sm btn-info text-white fw-bold"><i class="bi bi-box-arrow-up-right me-1"></i> View Receipt File</a>
                    </div>
                `;
            } else {
                proofContainer.classList.add('d-none');
                proofContainer.innerHTML = '';
            }
        }

        var myModalEl = document.getElementById('discrepancyModal');
        var discModal = bootstrap.Modal.getInstance(myModalEl);
        if (!discModal) discModal = new bootstrap.Modal(myModalEl);
        discModal.show();
    }

    // ==========================================
    // CAMERA PHOTO CAPTURE LOGIC
    // ==========================================
    window.stopReceiptCamera = function () {
        const video = document.getElementById('receiptCameraVideo');
        if (video) {
            if (video.srcObject) {
                try {
                    const tracks = video.srcObject.getTracks();
                    tracks.forEach(track => {
                        track.stop();
                        track.enabled = false;
                    });
                } catch (e) {}
                video.srcObject = null;
            }
            try { video.pause(); } catch (e) {}
            video.classList.add('d-none');
        }

        if (window.receiptStream) {
            try {
                const tracks = window.receiptStream.getTracks();
                tracks.forEach(track => {
                    track.stop();
                    track.enabled = false;
                });
            } catch (e) {}
            window.receiptStream = null;
        }

        const captureBtn = document.getElementById('captureReceiptBtn');
        if (captureBtn) captureBtn.classList.add('d-none');
        const retakeBtn = document.getElementById('retakeReceiptBtn');
        if (retakeBtn) retakeBtn.classList.add('d-none');
        const previewImg = document.getElementById('receiptCapturedImage');
        if (previewImg) previewImg.classList.add('d-none');
        const openBtn = document.getElementById('openCameraBtn');
        if (openBtn) openBtn.classList.remove('d-none');
        const hiddenInput = document.getElementById('capturedProofBase64');
        if (hiddenInput) hiddenInput.value = '';
    };

    window.startReceiptCamera = async function () {
        // Always stop existing camera stream first
        window.stopReceiptCamera();

        const video = document.getElementById('receiptCameraVideo');
        const captureBtn = document.getElementById('captureReceiptBtn');
        const retakeBtn = document.getElementById('retakeReceiptBtn');
        const previewImg = document.getElementById('receiptCapturedImage');
        const openBtn = document.getElementById('openCameraBtn');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert("Camera access is not supported on this browser or device.");
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            window.receiptStream = stream;
            video.srcObject = stream;
            video.classList.remove('d-none');
            captureBtn.classList.remove('d-none');
            if (openBtn) openBtn.classList.add('d-none');
            retakeBtn.classList.add('d-none');
            previewImg.classList.add('d-none');
        } catch (err) {
            alert("Unable to access camera: " + err.message);
        }
    };

    window.takeReceiptPhoto = function () {
        const video = document.getElementById('receiptCameraVideo');
        const canvas = document.getElementById('receiptCameraCanvas');
        const previewImg = document.getElementById('receiptCapturedImage');
        const hiddenInput = document.getElementById('capturedProofBase64');
        const captureBtn = document.getElementById('captureReceiptBtn');
        const retakeBtn = document.getElementById('retakeReceiptBtn');

        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        hiddenInput.value = dataUrl;
        previewImg.src = dataUrl;
        previewImg.classList.remove('d-none');

        // Stop video stream after taking snapshot
        if (video.srcObject) {
            try {
                video.srcObject.getTracks().forEach(track => {
                    track.stop();
                    track.enabled = false;
                });
            } catch (e) {}
            video.srcObject = null;
        }
        if (window.receiptStream) {
            try {
                window.receiptStream.getTracks().forEach(track => {
                    track.stop();
                    track.enabled = false;
                });
            } catch (e) {}
            window.receiptStream = null;
        }

        video.classList.add('d-none');
        captureBtn.classList.add('d-none');
        retakeBtn.classList.remove('d-none');
    };

    document.addEventListener('DOMContentLoaded', function () {
        const receiveModalEl = document.getElementById('receiveModal');
        if (receiveModalEl) {
            receiveModalEl.addEventListener('hide.bs.modal', function () {
                window.stopReceiptCamera();
            });
            receiveModalEl.addEventListener('hidden.bs.modal', function () {
                window.stopReceiptCamera();
            });
        }
    });

    // ==========================================
    // VIRTUAL PO DOCUMENT & PRINT LOGIC
    // ==========================================
    window.openPoPrintModal = async function(poId) {
        const spinner = document.getElementById('poPrintLoadingSpinner');
        const paper = document.getElementById('poPrintPaper');
        if (spinner) spinner.classList.remove('d-none');
        if (paper) paper.classList.add('d-none');

        var myModalEl = document.getElementById('poPrintModal');
        var printModal = bootstrap.Modal.getInstance(myModalEl);
        if (!printModal) printModal = new bootstrap.Modal(myModalEl);
        printModal.show();

        let formData = new FormData();
        formData.append('action', 'fetch_po_details');
        formData.append('po_id', poId);

        try {
            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                const po = data.po;
                document.getElementById('printPoNo').innerText = po.po_no;
                document.getElementById('printPoStatus').innerText = po.status || 'Generated';
                document.getElementById('printPoDate').innerText = data.formatted_date;
                document.getElementById('printRsNo').innerText = po.rs_no || 'N/A';
                document.getElementById('printProjectName').innerText = po.project_name || 'Warehouse Restock';
                document.getElementById('printPoEta').innerText = data.formatted_eta;
                document.getElementById('printPreparedBy').innerText = po.prepared_by_name || 'Purchasing Department';

                document.getElementById('printSupplierName').innerText = po.company_name || 'N/A';
                document.getElementById('printSupplierContact').innerText = 'Attn: ' + (po.contact_person || 'N/A');
                document.getElementById('printSupplierPhone').innerText = 'Phone: ' + (po.contact_number || 'N/A');
                document.getElementById('printSupplierAddress').innerText = po.supplier_address || '';

                // Render Itemized Table
                const tbody = document.getElementById('printPoItemsBody');
                tbody.innerHTML = '';
                if (data.items && data.items.length > 0) {
                    data.items.forEach((item, index) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="text-center font-monospace">${index + 1}</td>
                            <td class="fw-bold text-muted">${item.item_code}</td>
                            <td class="fw-bold text-dark">${item.item_name} <span class="text-muted fw-normal">(${item.unit || 'units'})</span></td>
                            <td class="text-center fw-bold text-primary">${item.quantity}</td>
                            <td class="text-end">₱${parseFloat(item.unit_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            <td class="text-end fw-bold">₱${parseFloat(item.subtotal || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No items listed.</td></tr>';
                }

                document.getElementById('printPoTotalValue').innerText = '₱' + parseFloat(data.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                // Logistics / Discrepancy Remarks
                const remarksSec = document.getElementById('printRemarksSection');
                const remarksText = document.getElementById('printPoRemarks');
                if (po.delay_remarks && po.delay_remarks.trim() !== '') {
                    remarksSec.classList.remove('d-none');
                    remarksText.innerText = po.delay_remarks;
                } else {
                    remarksSec.classList.add('d-none');
                }

                if (spinner) spinner.classList.add('d-none');
                if (paper) paper.classList.remove('d-none');
            } else {
                alert("Failed to load PO document: " + data.message);
                if (printModal) printModal.hide();
            }
        } catch (e) {
            alert("Network error: Could not fetch PO document details.");
            if (printModal) printModal.hide();
        }
    };

    window.printPoDocument = function() {
        const printContent = document.getElementById('poPrintPaper').innerHTML;
        const originalBody = document.body.innerHTML;

        const printStyles = `
            <style>
                @media print {
                    * {
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                        color-adjust: exact !important;
                    }
                    .table-dark, thead.table-dark, thead.table-dark tr, thead.table-dark th {
                        background-color: #212529 !important;
                        color: #ffffff !important;
                    }
                }
                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .table-dark, thead.table-dark, thead.table-dark tr, thead.table-dark th {
                    background-color: #212529 !important;
                    color: #ffffff !important;
                }
            </style>
        `;

        document.body.innerHTML = `
            ${printStyles}
            <div style="padding: 40px; background: #fff;">
                ${printContent}
            </div>
        `;
        window.print();
        document.body.innerHTML = originalBody;
        window.location.reload();
    };

    // Multi-Criteria Table Filtering (Search, Officer, Supplier, Project, Status, Urgency, Date)
    window.filterPoTable = function () {
        const searchInput = document.getElementById('searchPo');
        const creatorSelect = document.getElementById('filterCreator');
        const supplierSelect = document.getElementById('filterSupplier');
        const projectSelect = document.getElementById('filterProject');
        const statusSelect = document.getElementById('filterStatus');
        const urgencySelect = document.getElementById('filterEtaUrgency');
        const dateInput = document.getElementById('filterDate');

        const searchTerm = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const creatorVal = creatorSelect ? creatorSelect.value : 'all';
        const supplierVal = supplierSelect ? supplierSelect.value : 'all';
        const projectVal = projectSelect ? projectSelect.value : 'all';
        const statusVal = statusSelect ? statusSelect.value : 'all';
        const urgencyVal = urgencySelect ? urgencySelect.value : 'all';
        const dateVal = dateInput ? dateInput.value : '';

        let visibleCount = 0;
        const currentUserId = '<?= (string)$_SESSION['user_id'] ?>';

        document.querySelectorAll('.po-row').forEach(row => {
            const no = (row.querySelector('.po-no')?.textContent || '').toLowerCase();
            const sup = (row.querySelector('.po-supplier')?.textContent || '').toLowerCase();
            const rowCreator = row.getAttribute('data-prepared-by') || '';
            const rowSupplier = row.getAttribute('data-supplier-id') || '';
            const rowDate = row.getAttribute('data-created-date') || '';
            const rowStatus = row.getAttribute('data-status') || '';
            const rowProject = row.getAttribute('data-project') || '';
            const rowUrgency = row.getAttribute('data-eta-urgency') || '';

            const matchesSearch = !searchTerm || no.includes(searchTerm) || sup.includes(searchTerm);

            let matchesCreator = true;
            if (creatorVal === 'me') {
                matchesCreator = (rowCreator === currentUserId);
            } else if (creatorVal !== 'all') {
                matchesCreator = (rowCreator === creatorVal);
            }

            const matchesSupplier = (supplierVal === 'all') || (rowSupplier === supplierVal);
            const matchesProject = (projectVal === 'all') || (rowProject === projectVal);

            let matchesStatus = true;
            if (statusVal === 'Delayed') {
                matchesStatus = rowStatus.includes('Delayed');
            } else if (statusVal !== 'all') {
                matchesStatus = (rowStatus === statusVal);
            }

            const matchesUrgency = (urgencyVal === 'all') || (rowUrgency === urgencyVal);
            const matchesDate = !dateVal || (rowDate === dateVal);

            if (matchesSearch && matchesCreator && matchesSupplier && matchesProject && matchesStatus && matchesUrgency && matchesDate) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Active Filter Badge Count Update
        let activeFilterCount = 0;
        if (creatorVal !== 'all') activeFilterCount++;
        if (supplierVal !== 'all') activeFilterCount++;
        if (projectVal !== 'all') activeFilterCount++;
        if (statusVal !== 'all') activeFilterCount++;
        if (urgencyVal !== 'all') activeFilterCount++;
        if (dateVal !== '') activeFilterCount++;

        const badge = document.getElementById('activeFilterBadge');
        if (badge) {
            if (activeFilterCount > 0) {
                badge.innerText = activeFilterCount;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }

        const noResultsRow = document.getElementById('noResultsPoRow');
        if (noResultsRow) {
            noResultsRow.style.display = (visibleCount === 0) ? '' : 'none';
        }
    };

    window.resetAllPoFilters = function () {
        const creatorSelect = document.getElementById('filterCreator');
        const supplierSelect = document.getElementById('filterSupplier');
        const projectSelect = document.getElementById('filterProject');
        const statusSelect = document.getElementById('filterStatus');
        const urgencySelect = document.getElementById('filterEtaUrgency');
        const dateInput = document.getElementById('filterDate');

        if (creatorSelect) creatorSelect.value = 'all';
        if (supplierSelect) supplierSelect.value = 'all';
        if (projectSelect) projectSelect.value = 'all';
        if (statusSelect) statusSelect.value = 'all';
        if (urgencySelect) urgencySelect.value = 'all';
        if (dateInput) dateInput.value = '';

        window.filterPoTable();
    };

    window.initPoSearch = function () {
        const searchPo = document.getElementById('searchPo');
        const filterCreator = document.getElementById('filterCreator');
        const filterSupplier = document.getElementById('filterSupplier');
        const filterProject = document.getElementById('filterProject');
        const filterStatus = document.getElementById('filterStatus');
        const filterEtaUrgency = document.getElementById('filterEtaUrgency');
        const filterDate = document.getElementById('filterDate');

        if (searchPo) searchPo.onkeyup = window.filterPoTable;
        if (filterCreator) filterCreator.onchange = window.filterPoTable;
        if (filterSupplier) filterSupplier.onchange = window.filterPoTable;
        if (filterProject) filterProject.onchange = window.filterPoTable;
        if (filterStatus) filterStatus.onchange = window.filterPoTable;
        if (filterEtaUrgency) filterEtaUrgency.onchange = window.filterPoTable;
        if (filterDate) filterDate.onchange = window.filterPoTable;
    };
    window.initPoSearch();

    // Make sure openSmsPreviewModal is attached to window for SPA compatibility
    window.openSmsPreviewModal = async function (poId, poNo, supplierId, phone) {
        document.getElementById('smsPoId').value = poId;
        document.getElementById('smsPoNo').value = poNo;
        document.getElementById('smsPhone').value = phone || '';

        const supplierSelect = document.getElementById('smsSupplierSelect');
        if (supplierSelect) {
            supplierSelect.value = supplierId;
        }

        const tbody = document.getElementById('smsItemsBody');
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div> Loading items...</td></tr>';
        document.getElementById('smsMessageText').value = 'Loading SMS message template...';

        var myModalEl = document.getElementById('smsPreviewModal');
        var smsModal = bootstrap.Modal.getInstance(myModalEl);
        if (!smsModal) smsModal = new bootstrap.Modal(myModalEl);
        smsModal.show();

        let formData = new FormData();
        formData.append('action', 'fetch_po_sms_preview');
        formData.append('po_id', poId);

        try {
            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                tbody.innerHTML = '';
                if (data.items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-2">No items found.</td></tr>';
                } else {
                    data.items.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="fw-bold text-dark text-wrap">${item.item_name}</td>
                            <td class="text-center fw-bold text-danger">${item.quantity}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                const msg = `Genetian Builders Construction PO: ${poNo}\nItems to purchase:\n${data.item_list}If you have any concerns or clarifications text or email here`;
                document.getElementById('smsMessageText').value = msg;

                // Load recent SMS history for this supplier / PO
                loadPoSmsConversation(poId, phone, supplierId);
            } else {
                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-2">Failed to load items.</td></tr>';
                document.getElementById('smsMessageText').value = 'Error loading items template.';
            }
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-2">Network error.</td></tr>';
            document.getElementById('smsMessageText').value = 'Network error loading template.';
        }
    };

    async function loadPoSmsConversation(poId, phone, supplierId) {
        const section = document.getElementById('smsPoConversationSection');
        const container = document.getElementById('smsPoConversationThread');
        if (!section || !container) return;

        section.classList.remove('d-none');
        container.innerHTML = '<div class="text-center text-muted py-2"><span class="spinner-border spinner-border-sm me-2"></span>Loading SMS thread...</div>';

        try {
            const formData = new FormData();
            formData.append('action', 'fetch_sms_messages');
            formData.append('po_id', poId);
            if (phone) formData.append('sender_number', phone);
            if (supplierId) formData.append('supplier_id', supplierId);

            const res = await fetch('process/process.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.status === 'success' && data.messages && data.messages.length > 0) {
                let html = '';
                data.messages.forEach(m => {
                    const isInbound = m.direction === 'inbound';
                    const badge = isInbound ? '<span class="badge bg-primary me-1">Supplier Reply</span>' : '<span class="badge bg-success me-1">Sent PO SMS</span>';
                    html += `
                        <div class="mb-2 pb-2 border-bottom">
                            <div class="d-flex justify-content-between text-muted small mb-1">
                                <span>${badge} <strong>${isInbound ? (m.company_name || m.sender_number) : 'CIMS'}</strong></span>
                                <span>${m.created_at}</span>
                            </div>
                            <div class="text-dark">${escapeSmsHtml(m.message_text)}</div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-muted text-center py-2">No previous SMS replies from this supplier yet.</div>';
            }
        } catch (err) {
            container.innerHTML = '<div class="text-muted text-center py-2">Could not load conversation history.</div>';
        }
    }

    // Attach form and select change listeners
    document.addEventListener('DOMContentLoaded', function () {
        const smsForm = document.getElementById('smsPreviewForm');
        if (smsForm) {
            smsForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                const poId = document.getElementById('smsPoId').value;
                const poNo = document.getElementById('smsPoNo').value;
                const phone = document.getElementById('smsPhone').value;
                const supplierId = document.getElementById('smsSupplierSelect').value;
                const message = document.getElementById('smsMessageText').value;

                if (!phone || phone.trim() === '') {
                    alert("Please specify a valid recipient phone number.");
                    return;
                }

                const submitBtn = document.getElementById('sendSmsSubmitBtn');
                const originalHtml = submitBtn.innerHTML;

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';

                const tableBtn = document.getElementById('smsBtn_' + poId);
                let tableBtnHtml = '';
                if (tableBtn) {
                    tableBtnHtml = tableBtn.innerHTML;
                    tableBtn.disabled = true;
                    tableBtn.classList.replace('btn-outline-success', 'btn-success');
                    tableBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                }

                let formData = new FormData();
                formData.append('action', 'send_po_sms');
                formData.append('po_id', poId);
                formData.append('po_no', poNo);
                formData.append('supplier_id', supplierId);
                formData.append('contact_number', phone);
                formData.append('message', message);

                try {
                    const response = await fetch('process/process.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.status === 'success') {
                        new Audio('assets/sounds/success.mp3').play().catch(e => { });

                        var myModalEl = document.getElementById('smsPreviewModal');
                        var smsModal = bootstrap.Modal.getInstance(myModalEl);
                        if (smsModal) smsModal.hide();

                        alert("SMS sent successfully to supplier!");

                        if (tableBtn) {
                            tableBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
                            tableBtn.disabled = false;
                        }
                        const statusBadge = document.getElementById('status_' + poId);
                        if (statusBadge) {
                            statusBadge.className = 'badge bg-success px-3 py-2 shadow-sm text-uppercase';
                            statusBadge.innerText = 'SMS Sent';
                        }

                        window.location.reload();
                    } else {
                        alert("Error sending SMS: " + data.message);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                        if (tableBtn) {
                            tableBtn.disabled = false;
                            tableBtn.innerHTML = tableBtnHtml;
                            tableBtn.classList.replace('btn-success', 'btn-outline-success');
                        }
                    }
                } catch (err) {
                    alert("Network Error: Could not connect to SMS Gateway.");
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                    if (tableBtn) {
                        tableBtn.disabled = false;
                        tableBtn.innerHTML = tableBtnHtml;
                        tableBtn.classList.replace('btn-success', 'btn-outline-success');
                    }
                }
            });
        }

        const smsSupplierSelect = document.getElementById('smsSupplierSelect');
        if (smsSupplierSelect) {
            smsSupplierSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const phone = selectedOption.getAttribute('data-phone');
                document.getElementById('smsPhone').value = phone || '';
            });
        }

        // Check URL parameters for shortcut auto-open modals
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'new') {
            const poModalEl = document.getElementById('poModal');
            if (poModalEl) {
                new bootstrap.Modal(poModalEl).show();
            }
        }
    });
</script>

<?php include 'layout/footer.php'; ?>