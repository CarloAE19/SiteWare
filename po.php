<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

// Allowed roles for this module
if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'management', 'warehouse'])) {
    header("Location: requisitions");
    exit;
}
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// AUTO-PATCH DB: Ensures the PO table can handle SMS Status and Weather Delays!
try {
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN status VARCHAR(50) DEFAULT 'Generated'");
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN delay_remarks TEXT");
    $pdo->exec("UPDATE purchase_orders SET status = 'Viber Order Sent' WHERE status = 'SMS Sent'");
    try {
        $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN payment_terms VARCHAR(100) DEFAULT 'Credit (30 Days Net)'");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE requisitions ADD COLUMN approved_by INT NULL AFTER status");
    } catch (PDOException $e) {}

    // Clean existing duplicated discrepancy records in delay_remarks if present
    $dupPos = $pdo->query("SELECT id, delay_remarks FROM purchase_orders WHERE delay_remarks LIKE '%[DELIVERY DISCREPANCY]%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dupPos as $dupPo) {
        $normalized = str_replace(["\r\n", "\r"], "\n", $dupPo['delay_remarks'] ?? '');
        $parts = explode('[DELIVERY DISCREPANCY]:', $normalized);
        $unique = [];
        $preface = trim($parts[0]);
        for ($i = 1; $i < count($parts); $i++) {
            $t = trim($parts[$i]);
            $normT = preg_replace('/\s+/', ' ', $t);
            $alreadyExists = false;
            foreach ($unique as $u) {
                if (preg_replace('/\s+/', ' ', $u) === $normT) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (!empty($t) && !$alreadyExists) {
                $unique[] = $t;
            }
        }
        if (!empty($unique)) {
            $cleanedText = (!empty($preface) ? $preface . "\n\n" : "") . implode("\n\n[DELIVERY DISCREPANCY]:\n", array_map(fn($u) => "[DELIVERY DISCREPANCY]:\n" . $u, $unique));
            $cleanedText = str_replace("[DELIVERY DISCREPANCY]:\n[DELIVERY DISCREPANCY]:", "[DELIVERY DISCREPANCY]:", $cleanedText);
            if ($cleanedText !== $dupPo['delay_remarks']) {
                $cleanStmt = $pdo->prepare("UPDATE purchase_orders SET delay_remarks = ? WHERE id = ?");
                $cleanStmt->execute([$cleanedText, $dupPo['id']]);
            }
        }
    }
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
$pendingDelivery = count(array_filter($pos, fn($p) => in_array($p['status'], ['Generated', 'Viber Order Sent', 'Out for Delivery', 'Pending Delivery', 'Partially Delivered', 'Partially Received'])));
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
        .po-main-table-wrap,
        .table-responsive:has(#poTable) {
            overflow-x: hidden !important;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }

    /* ==========================================
       Virtual Purchase Order Document (PC & Mobile Adaptive)
    ========================================== */
    .po-half-a4-paper {
        max-width: 780px !important;
        width: 100% !important;
        margin: 0 auto !important;
        background: #ffffff !important;
        border: 1px solid #dcdfe4 !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
        position: relative;
        box-sizing: border-box;
    }

    #poPrintPaper .po-paper-table-wrap {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
        border: 1px solid #dee2e6 !important;
        background: #ffffff !important;
    }

    .po-doc-table {
        table-layout: auto;
        width: 100% !important;
    }

    /* Desktop View (PC / Laptop): Fills modal-lg naturally with generous spacing */
    @media (min-width: 768px) {
        .po-half-a4-paper {
            max-width: 780px !important;
            padding: 1.5rem 1.75rem !important;
        }

        .po-doc-logo {
            height: 46px !important;
        }

        .po-doc-brand {
            font-size: 1.20rem !important;
        }

        .po-doc-subbrand {
            font-size: 0.74rem !important;
        }

        .po-doc-manifest-lbl {
            font-size: 0.68rem !important;
        }

        .po-doc-status {
            font-size: 0.72rem !important;
            padding: 3px 8px !important;
        }

        .po-doc-pono {
            font-size: 1.10rem !important;
        }

        .po-meta-grid {
            font-size: 0.80rem !important;
            padding: 8px 12px !important;
        }

        .po-doc-table th,
        .po-doc-table td {
            padding: 6px 8px !important;
            font-size: 0.80rem !important;
        }
    }

    /* Mobile Phone View: Authentic Half-A4 (A5) voucher proportions */
    @media (max-width: 767.98px) {
        #poPrintModal .modal-dialog {
            margin: 0.5rem auto !important;
            max-width: 100% !important;
        }

        #poPrintDocumentBody {
            padding: 0.45rem !important;
        }

        .po-half-a4-paper {
            max-width: 595px !important;
            padding: 0.60rem 0.65rem !important;
            border-radius: 6px !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06) !important;
        }

        .po-doc-brand {
            font-size: 0.98rem !important;
        }

        .po-doc-subbrand {
            font-size: 0.62rem !important;
        }

        .po-doc-manifest-lbl {
            font-size: 0.56rem !important;
        }

        .po-doc-status {
            font-size: 0.64rem !important;
            padding: 2px 6px !important;
        }

        .po-doc-pono {
            font-size: 0.92rem !important;
            white-space: nowrap !important;
        }

        .po-meta-grid {
            font-size: 0.72rem !important;
            padding: 4px 6px !important;
        }

        .po-doc-table th,
        .po-doc-table td {
            padding: 3px 3px !important;
            font-size: 0.70rem !important;
        }
    }

    .po-no-link {
        transition: color 0.15s ease, text-decoration 0.15s ease;
        cursor: pointer;
    }
    .po-no-link:hover {
        color: #0d6efd !important;
        text-decoration: underline !important;
    }

    /* PO Modal Tab Navigation & Polish */
    #poDetailsTab .nav-link {
        color: #475569;
        border-radius: 6px;
        font-size: 0.82rem;
        transition: all 0.15s ease-in-out;
    }
    #poDetailsTab .nav-link:hover {
        color: #0f172a;
        background-color: #f1f5f9;
    }
    #poDetailsTab .nav-link.active {
        color: #fff;
        background-color: #0d6efd;
        box-shadow: 0 2px 6px rgba(13, 110, 253, 0.25);
    }
    @media (max-width: 767.98px) {
        #poDetailsTab .nav-link {
            font-size: 0.72rem !important;
            padding: 6px 4px !important;
        }
    }

    /* PO Fulfillment Timeline Styles */
    .po-timeline {
        position: relative;
        padding-left: 32px;
        margin-top: 8px;
        margin-bottom: 8px;
    }
    .po-timeline::before {
        content: '';
        position: absolute;
        top: 6px;
        bottom: 6px;
        left: 12px;
        width: 2px;
        background-color: #e2e8f0;
    }
    .po-timeline-step {
        position: relative;
        margin-bottom: 18px;
    }
    .po-timeline-step:last-child {
        margin-bottom: 0;
    }
    .po-timeline-node {
        position: absolute;
        left: -32px;
        top: 2px;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background-color: #fff;
        border: 2px solid #94a3b8;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        z-index: 2;
        transition: all 0.2s ease;
    }
    .po-timeline-node.completed {
        background-color: #10b981;
        border-color: #10b981;
        color: #fff;
    }
    .po-timeline-node.active {
        background-color: #0284c7;
        border-color: #0284c7;
        color: #fff;
        box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.25);
        animation: timelinePulse 2s infinite;
    }
    .po-timeline-node.warning {
        background-color: #f59e0b;
        border-color: #f59e0b;
        color: #fff;
    }
    .po-timeline-node.muted {
        background-color: #f8fafc;
        border-color: #cbd5e1;
        color: #94a3b8;
    }
    @keyframes timelinePulse {
        0% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.4); }
        70% { box-shadow: 0 0 0 7px rgba(2, 132, 199, 0); }
        100% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
    }
    .po-timeline-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
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

        /* Interactive KPI Filter Tiles */
        .po-filter-tile {
            cursor: pointer;
            transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s ease, border-color 0.18s ease;
            user-select: none;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            position: relative;
        }
        .po-filter-tile:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08) !important;
        }
        .po-filter-tile:active {
            transform: scale(0.98);
        }
        .po-filter-tile.active-filter {
            box-shadow: 0 0 0 2px var(--gb-blue, #0033CC), 0 8px 20px rgba(0, 51, 204, 0.12) !important;
            background-color: #f8fafc !important;
        }
        .po-filter-tile[data-filter="pending"].active-filter {
            box-shadow: 0 0 0 2px var(--gb-yellow, #ffc107), 0 8px 20px rgba(255, 193, 7, 0.2) !important;
        }
        .po-filter-tile[data-filter="delayed"].active-filter {
            box-shadow: 0 0 0 2px #dc3545, 0 8px 20px rgba(220, 53, 69, 0.2) !important;
        }

        /* 3-Dots Logistics Action Dropdown */
        .dropdown-item {
            transition: background-color 0.15s ease, color 0.15s ease;
            font-size: 0.83rem;
            cursor: pointer;
        }
        .dropdown-item:hover {
            background-color: #f1f5f9;
        }
        .dropdown-item:active {
            background-color: #e2e8f0;
        }
        .dropdown-item.text-danger:hover {
            background-color: #fee2e2 !important;
            color: #dc2626 !important;
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

    <!-- PO Stats Cards (Interactive Filter Tiles) -->
    <div class="row mb-4 g-3">
        <div class="col-12 col-md-4">
            <div class="card stat-card po-filter-tile active-filter bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                data-filter="all" role="button" tabindex="0" title="Click to view all purchase orders"
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
            <div class="card stat-card po-filter-tile bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                data-filter="pending" role="button" tabindex="0" title="Click to filter Pending Deliveries"
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
            <div class="card stat-card po-filter-tile bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                data-filter="delayed" role="button" tabindex="0" title="Click to filter Delayed Orders"
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
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Purchase
                    Orders</h4>
                <small class="text-muted">Manage, track deliveries, and view PO manifests</small>
            </div>

            <div class="col-12 col-md-7">
                <div class="d-flex flex-wrap justify-content-md-end align-items-center gap-2">
                    <!-- Search Bar -->
                    <div class="input-group shadow-sm flex-grow-1 flex-md-grow-0"
                        style="max-width: 280px; min-width: 180px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i
                                class="bi bi-search"></i></span>
                        <input type="text" id="searchPo" class="form-control border-start-0 ps-0 bg-white fw-bold"
                            placeholder="Search PO No, Supplier...">
                    </div>

                    <!-- Filter Toggle Button -->
                    <button class="btn btn-outline-secondary fw-bold shadow-sm d-flex align-items-center gap-1"
                        type="button" data-bs-toggle="collapse" data-bs-target="#poFilterCollapse" aria-expanded="false"
                        aria-controls="poFilterCollapse">
                        <i class="bi bi-funnel-fill text-primary"></i>
                        <span>Filter</span>
                        <span class="badge bg-primary rounded-pill ms-1 d-none" id="activeFilterBadge">0</span>
                    </button>

                    <!-- Create PO Button -->
                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                        <button class="btn btn-brand fw-bold text-nowrap shadow-sm px-3" data-bs-toggle="modal"
                            data-bs-target="#poModal">
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
                    <h6 class="fw-bold text-dark mb-0 small text-uppercase"><i
                            class="bi bi-sliders me-1 text-primary"></i> Filter Purchase Orders</h6>
                    <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none fw-bold p-0"
                        onclick="resetAllPoFilters()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset All Filters
                    </button>
                </div>
                <div class="row g-2">
                    <!-- 1. Created By Filter -->
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Created By
                            Officer</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i
                                    class="bi bi-person-fill text-primary"></i></span>
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
                            <span class="input-group-text bg-white text-muted"><i
                                    class="bi bi-building text-info"></i></span>
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
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Project
                            Destination</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i
                                    class="bi bi-geo-alt-fill text-danger"></i></span>
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
                            <span class="input-group-text bg-white text-muted"><i
                                    class="bi bi-tag-fill text-success"></i></span>
                            <select id="filterStatus" class="form-select bg-white fw-bold small">
                                <option value="all">All Statuses</option>
                                <option value="Generated">Generated / Draft</option>
                                <option value="Viber Order Sent">Viber Order Sent</option>
                                <option value="Out for Delivery">🚚 Out for Delivery</option>
                                <option value="Pending Delivery">Pending Delivery</option>
                                <option value="Partially Delivered">Partially Delivered</option>
                                <option value="Delivered">Delivered (Complete)</option>
                                <option value="Delivered (Discrepancy)">Delivered (Discrepancy)</option>
                                <option value="Delayed">Delayed (All Reasons)</option>
                                <option value="Cancelled">Cancelled / Voided</option>
                            </select>
                        </div>
                    </div>

                    <!-- 5. Delivery Urgency / ETA Filter -->
                    <div class="col-12 col-sm-6 col-md-4">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Logistics ETA
                            Urgency</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i
                                    class="bi bi-truck-flatbed text-primary"></i></span>
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
                            <span class="input-group-text bg-white text-muted"><i
                                    class="bi bi-calendar-event text-warning"></i></span>
                            <input type="date" id="filterDate" class="form-control bg-white fw-bold small">
                            <button type="button" class="btn btn-outline-secondary" title="Clear Date"
                                onclick="document.getElementById('filterDate').value=''; filterPoTable();">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive border rounded shadow-sm bg-white po-main-table-wrap">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="poTable">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3" style="min-width: 140px;">PO Number</th>
                        <th class="py-3">Date & Time Created</th>
                        <th class="py-3">Linked RS / Project</th>
                        <th class="py-3">Supplier</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3">Warehouse ETA</th>
                        <th class="text-center py-3" style="width: 180px; min-width: 170px;">Logistics Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pos) > 0): ?>
                        <?php foreach ($pos as $po): ?>
                            <?php
                            $displayStatus = $po['status'] ?? 'Generated';
                            if ($displayStatus === 'SMS Sent') {
                                $displayStatus = 'Viber Order Sent';
                            }
                            if ($displayStatus === 'Partially Received') {
                                $displayStatus = 'Partially Delivered';
                            }
                            $statusClass = 'bg-secondary';
                            if ($displayStatus === 'Generated')
                                $statusClass = 'bg-info text-dark';
                            if ($displayStatus === 'Viber Order Sent')
                                $statusClass = 'bg-viber text-white';
                            if ($displayStatus === 'Out for Delivery')
                                $statusClass = 'bg-primary text-white shadow-sm';
                            if ($displayStatus === 'Pending Delivery')
                                $statusClass = 'bg-warning text-dark';
                            if (strpos($displayStatus, 'Delayed') !== false)
                                $statusClass = 'bg-danger';
                            if ($displayStatus === 'Partially Delivered' || $displayStatus === 'Partially Received')
                                $statusClass = 'bg-warning text-dark border border-warning shadow-sm';
                            if ($displayStatus === 'Delivered')
                                $statusClass = 'bg-success';
                            if ($displayStatus === 'Delivered (Discrepancy)')
                                $statusClass = 'bg-warning text-dark';
                            if ($displayStatus === 'Cancelled')
                                $statusClass = 'bg-dark text-white border border-secondary shadow-sm';

                            // Compute ETA Badges & Urgency Filter Attribute
                            $etaBadge = '<span class="text-muted small">Not Set</span>';
                            $etaDateStr = $po['expected_delivery_date'] ?? null;
                            $etaUrgencyVal = 'unset';

                            if (in_array($po['status'], ['Delivered', 'Delivered (Discrepancy)'])) {
                                $etaUrgencyVal = 'delivered';
                            } elseif ($po['status'] === 'Cancelled') {
                                $etaUrgencyVal = 'cancelled';
                            }

                            if ($po['status'] === 'Cancelled') {
                                $etaBadge = '<span class="badge bg-secondary text-white-50 shadow-sm"><i class="bi bi-slash-circle me-1"></i>Voided</span>';
                            } elseif ($etaDateStr) {
                                $formattedEta = date('M d, Y', strtotime($etaDateStr));
                                if (in_array($po['status'], ['Delivered', 'Delivered (Discrepancy)'])) {
                                    $etaBadge = '<span class="badge bg-light text-muted border shadow-sm"><i class="bi bi-check2-circle me-1 text-success"></i>' . $formattedEta . '</span>';
                                } else {
                                    $todayTs = strtotime(date('Y-m-d'));
                                    $etaTs = strtotime($etaDateStr);
                                    $daysDiff = (int) (($etaTs - $todayTs) / 86400);

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
                            <tr class="po-row" data-prepared-by="<?= htmlspecialchars($po['prepared_by'] ?? '') ?>"
                                data-supplier-id="<?= htmlspecialchars($po['supplier_id'] ?? '') ?>"
                                data-created-date="<?= !empty($po['created_at']) ? date('Y-m-d', strtotime($po['created_at'])) : '' ?>"
                                data-status="<?= htmlspecialchars($po['status'] ?? 'Generated') ?>"
                                data-project="<?= htmlspecialchars($po['project_name'] ?? 'Warehouse Restock') ?>"
                                data-eta-urgency="<?= $etaUrgencyVal ?>">
                                <td class="fw-bold text-dark po-no" data-label="PO Number">
                                    <a href="javascript:void(0)" class="text-dark text-decoration-none po-no-link d-inline-flex align-items-center gap-1"
                                        title="Click to view details for <?= htmlspecialchars($po['po_no']) ?>"
                                        onclick="openPoPrintModal(<?= $po['id'] ?>)">
                                        <span><?= htmlspecialchars($po['po_no']) ?></span>
                                        <i class="bi bi-box-arrow-up-right text-muted small" style="font-size: 0.70rem;"></i>
                                    </a>
                                </td>

                                <td data-label="Date & Time Created">
                                    <span class="d-block text-dark fw-semibold small">
                                        <i
                                            class="bi bi-calendar3 me-1 text-muted"></i><?= !empty($po['created_at']) ? date('M d, Y', strtotime($po['created_at'])) : 'N/A' ?>
                                    </span>
                                    <small class="text-muted d-block" style="font-size:0.73rem;">
                                        <i
                                            class="bi bi-clock me-1 text-primary"></i><?= !empty($po['created_at']) ? date('g:i A', strtotime($po['created_at'])) : '' ?>
                                    </small>
                                    <div class="mt-1 small fw-bold text-secondary" style="font-size: 0.78rem;"
                                        title="Created By Officer">
                                        <i
                                            class="bi bi-person-fill me-1 text-primary"></i><?= htmlspecialchars($po['prepared_by_name'] ?? 'System') ?>
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
                                    <?php 
                                    $terms = $po['payment_terms'] ?? 'Credit (30 Days Net)';
                                    $isCredit = stripos($terms, 'Credit') !== false || stripos($terms, 'Account') !== false;
                                    $termBadgeClass = $isCredit ? 'bg-primary-subtle text-primary border-primary-subtle' : 'bg-success-subtle text-success border-success-subtle';
                                    $termIcon = $isCredit ? 'bi-credit-card' : 'bi-cash-stack';
                                    ?>
                                    <div class="mt-1">
                                        <span class="badge <?= $termBadgeClass ?> border px-2 py-0.5 fw-semibold" style="font-size: 0.68rem;">
                                            <i class="bi <?= $termIcon ?> me-1"></i><?= htmlspecialchars($terms) ?>
                                        </span>
                                    </div>
                                </td>

                                <td data-label="Status">
                                    <span class="badge <?= $statusClass ?> px-3 py-2 shadow-sm text-uppercase"
                                        id="status_<?= $po['id'] ?>">
                                        <?php if ($displayStatus === 'Out for Delivery'): ?>
                                            <i class="bi bi-truck me-1"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($displayStatus) ?>
                                    </span>
                                    <?php if ($po['status'] === 'Delayed (Weather)'): ?>
                                        <small class="d-block text-danger mt-2 fw-bold"
                                            style="font-size: 0.75rem; white-space: normal;"><i
                                                class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($po['delay_remarks']) ?></small>
                                    <?php elseif ($po['status'] === 'Cancelled'): ?>
                                        <small class="d-block text-muted mt-1 fw-bold"
                                            style="font-size: 0.72rem; white-space: normal;"><i
                                                class="bi bi-slash-circle me-1 text-danger"></i>Voided Order</small>
                                    <?php endif; ?>
                                </td>

                                <td data-label="Warehouse ETA">
                                    <div class="d-flex align-items-center gap-1">
                                        <?= $etaBadge ?>
                                        <?php if (in_array($role, ['admin', 'purchasing']) && $po['status'] !== 'Cancelled'): ?>
                                            <button type="button"
                                                class="btn btn-sm btn-link text-muted p-0 ms-1 text-decoration-none"
                                                title="Update ETA"
                                                onclick="openEditEtaModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>', '<?= $po['expected_delivery_date'] ?? '' ?>')">
                                                <i class="bi bi-pencil-square fs-6 text-primary"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="text-center text-nowrap" data-label="Actions">
                                    <?php
                                    $receiptFile = !empty($po['proof_of_receipt']) ? basename($po['proof_of_receipt']) : '';
                                    $secureReceiptUrl = $receiptFile ? ('secure-image?type=receipts&file=' . urlencode($receiptFile)) : '';
                                    $canManageLogistics = in_array($role, ['admin', 'purchasing']) && !in_array($po['status'], ['Delivered', 'Delivered (Discrepancy)', 'Cancelled']);
                                    $canReceive = in_array($role, ['admin', 'warehouse', 'purchasing']) && !in_array($po['status'], ['Delivered', 'Delivered (Discrepancy)', 'Cancelled']);
                                    ?>

                                    <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                        <!-- 1. PRIMARY OPERATIONAL ACTION BUTTON (Dynamic by Lifecycle) -->
                                        <?php if ($canReceive && in_array($po['status'], ['Generated', 'Viber Order Sent'])): ?>
                                            <!-- Out for Delivery is the next milestone -->
                                            <button type="button" class="btn btn-sm btn-primary fw-bold shadow-sm primary-action-btn-<?= $po['id'] ?>"
                                                style="min-width: 110px;"
                                                title="Mark Order as Out for Delivery (In Transit)"
                                                onclick="markPoOutForDelivery(<?= $po['id'] ?>, '<?= htmlspecialchars($po['po_no'], ENT_QUOTES) ?>')">
                                                <i class="bi bi-truck"></i> <span class="ms-1">Out for Delivery</span>
                                            </button>
                                        <?php elseif ($canReceive && in_array($po['status'], ['Out for Delivery', 'Pending Delivery'])): ?>
                                            <!-- En Route / Arrival: Receive is the primary milestone -->
                                            <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm primary-action-btn-<?= $po['id'] ?>"
                                                style="min-width: 110px;"
                                                title="Receive Order & Ingest Inventory"
                                                onclick="openReceiveModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>')">
                                                <i class="bi bi-box-arrow-in-down"></i> <span class="ms-1">Receive</span>
                                            </button>
                                        <?php elseif (in_array($role, ['admin', 'management', 'purchasing', 'warehouse']) && in_array($po['status'], ['Delivered (Discrepancy)', 'Partially Delivered', 'Partially Received'])):
                                            $isPartial = in_array($po['status'], ['Partially Delivered', 'Partially Received']);
                                            $btnClass = $isPartial ? 'btn-outline-warning text-dark' : 'btn-danger';
                                            $btnIcon = $isPartial ? 'bi-clock-history' : 'bi-search';
                                            $btnText = $isPartial ? 'Delivery Log' : 'View Issue';
                                            ?>
                                            <button type="button" class="btn btn-sm <?= $btnClass ?> fw-bold shadow-sm"
                                                style="min-width: 110px;"
                                                title="View Delivery History & Discrepancy Remarks" 
                                                data-pono="<?= htmlspecialchars($po['po_no']) ?>"
                                                data-poid="<?= (int)$po['id'] ?>"
                                                data-status="<?= htmlspecialchars($po['status']) ?>"
                                                data-remarks="<?= htmlspecialchars($po['delay_remarks'] ?? 'No delivery remarks logged yet.') ?>"
                                                data-proof="<?= htmlspecialchars($secureReceiptUrl) ?>" 
                                                onclick="viewDiscrepancy(this)">
                                                <i class="bi <?= $btnIcon ?>"></i> <span class="ms-1"><?= $btnText ?></span>
                                            </button>
                                        <?php elseif ($po['status'] === 'Cancelled'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-dark fw-bold shadow-sm"
                                                style="min-width: 110px;"
                                                title="View Cancellation Audit Log" 
                                                data-pono="<?= htmlspecialchars($po['po_no']) ?>"
                                                data-poid="<?= (int)$po['id'] ?>"
                                                data-status="<?= htmlspecialchars($po['status']) ?>"
                                                data-remarks="<?= htmlspecialchars($po['delay_remarks'] ?? 'No cancellation remarks recorded.') ?>"
                                                data-proof="" 
                                                onclick="viewDiscrepancy(this)">
                                                <i class="bi bi-file-earmark-medical"></i> <span class="ms-1">Audit Log</span>
                                            </button>
                                        <?php else: ?>
                                            <!-- Standard Inspection: View Details -->
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold shadow-sm"
                                                style="min-width: 110px;"
                                                title="View Purchase Order Details" aria-label="View Details for <?= htmlspecialchars($po['po_no']) ?>"
                                                onclick="openPoPrintModal(<?= $po['id'] ?>)">
                                                <i class="bi bi-eye"></i> <span class="ms-1">View Details</span>
                                            </button>
                                        <?php endif; ?>

                                        <!-- 2. STANDARDIZED 3-DOTS MORE ACTIONS DROPDOWN -->
                                        <div class="dropdown d-inline-block">
                                            <button type="button" 
                                                class="btn btn-sm btn-outline-secondary fw-bold shadow-sm px-2"
                                                data-bs-toggle="dropdown" 
                                                aria-expanded="false"
                                                title="More Actions">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-2" style="font-size: 0.85rem; min-width: 215px; border-radius: 10px; z-index: 1060;">
                                                <li class="dropdown-header text-uppercase text-muted fw-bold py-1 px-3" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                                                    <i class="bi bi-gear me-1"></i> Order Actions
                                                </li>
                                                <li>
                                                    <button type="button" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2"
                                                        onclick="openPoPrintModal(<?= $po['id'] ?>)">
                                                        <i class="bi bi-eye text-primary fs-6" style="width: 18px;"></i>
                                                        <span>View PO Details</span>
                                                    </button>
                                                </li>
                                                <li>
                                                    <button type="button" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2"
                                                        onclick="directPrintPo(<?= $po['id'] ?>)">
                                                        <i class="bi bi-printer text-secondary fs-6" style="width: 18px;"></i>
                                                        <span>Print PO Manifest</span>
                                                    </button>
                                                </li>

                                                <?php if ($canReceive): ?>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <?php if (in_array($po['status'], ['Generated', 'Viber Order Sent'])): ?>
                                                        <li>
                                                            <button type="button" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-success fw-semibold"
                                                                onclick="openReceiveModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>')">
                                                                <i class="bi bi-box-arrow-in-down fs-6" style="width: 18px;"></i>
                                                                <span>Receive Order (Stock In)</span>
                                                            </button>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if (in_array($po['status'], ['Generated', 'Viber Order Sent', 'Pending Delivery'])): ?>
                                                        <li class="out-for-delivery-item-<?= $po['id'] ?>">
                                                            <button type="button" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-primary fw-semibold"
                                                                onclick="markPoOutForDelivery(<?= $po['id'] ?>, '<?= htmlspecialchars($po['po_no'], ENT_QUOTES) ?>')">
                                                                <i class="bi bi-truck fs-6" style="width: 18px;"></i>
                                                                <span>Mark Out for Delivery</span>
                                                            </button>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php if ($canManageLogistics): ?>
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2"
                                                            onclick="openViberPreviewModal(<?= $po['id'] ?>, '<?= htmlspecialchars($po['po_no'], ENT_QUOTES) ?>', <?= (int) $po['supplier_id'] ?>, '<?= htmlspecialchars($po['contact_number'] ?? '', ENT_QUOTES) ?>')">
                                                            <i class="fa-brands fa-viber fs-6" style="color: #7360f2; width: 18px;"></i>
                                                            <span>Send via Viber</span>
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2"
                                                            onclick="openDelayModal(<?= $po['id'] ?>, '<?= htmlspecialchars($po['po_no'], ENT_QUOTES) ?>', '<?= htmlspecialchars($po['expected_delivery_date'] ?? '', ENT_QUOTES) ?>')">
                                                            <i class="bi bi-cloud-lightning-rain-fill text-warning fs-6" style="width: 18px;"></i>
                                                            <span>Report Delay / Weather</span>
                                                        </button>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if (in_array($role, ['admin', 'warehouse', 'purchasing']) && $po['status'] !== 'Cancelled'): ?>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <!-- Upload / Attach Delivery Receipt (Post-Delivery & Active) -->
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-primary fw-semibold"
                                                            onclick="openUploadReceiptModal(<?= $po['id'] ?>, '<?= htmlspecialchars($po['po_no'], ENT_QUOTES) ?>', '<?= htmlspecialchars($po['company_name'] ?? '', ENT_QUOTES) ?>', '<?= !empty($receiptFile) ? 1 : 0 ?>')">
                                                            <i class="bi bi-cloud-arrow-up fs-6" style="width: 18px;"></i>
                                                            <span id="uploadReceiptMenuText_<?= $po['id'] ?>"><?= !empty($receiptFile) ? 'Update / Replace Receipt' : 'Attach Delivery Receipt' ?></span>
                                                        </button>
                                                    </li>
                                                <?php endif; ?>

                                                <!-- View Proof of Receipt (Dynamic Container) -->
                                                <li id="receiptViewItemWrap_<?= $po['id'] ?>" class="<?= empty($secureReceiptUrl) ? 'd-none' : '' ?>">
                                                    <a id="receiptViewLink_<?= $po['id'] ?>" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-info fw-semibold" href="<?= htmlspecialchars($secureReceiptUrl ?: '#') ?>"
                                                        onclick="event.preventDefault(); window.openPhotoWindow(this.getAttribute('href'));">
                                                        <i class="bi bi-paperclip fs-6" style="width: 18px;"></i>
                                                        <span>View Proof of Receipt</span>
                                                    </a>
                                                </li>

                                                <?php if (!empty($po['delay_remarks'])): ?>
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2"
                                                            data-pono="<?= htmlspecialchars($po['po_no']) ?>"
                                                            data-poid="<?= (int)$po['id'] ?>"
                                                            data-status="<?= htmlspecialchars($po['status']) ?>"
                                                            data-remarks="<?= htmlspecialchars($po['delay_remarks']) ?>"
                                                            data-proof="<?= htmlspecialchars($secureReceiptUrl) ?>" 
                                                            onclick="viewDiscrepancy(this)">
                                                            <i class="bi bi-clock-history text-secondary fs-6" style="width: 18px;"></i>
                                                            <span>Delivery & Issue Log</span>
                                                        </button>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if ($canManageLogistics): ?>
                                                    <li>
                                                        <button type="button" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 text-danger"
                                                            onclick="openCancelPoModal(<?= $po['id'] ?>, '<?= htmlspecialchars($po['po_no'], ENT_QUOTES) ?>', '<?= htmlspecialchars($po['company_name'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($po['rs_no'] ?? '', ENT_QUOTES) ?>')">
                                                            <i class="bi bi-slash-circle fs-6 text-danger" style="width: 18px;"></i>
                                                            <span class="fw-semibold">Void / Cancel PO</span>
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
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
    window.initPoRsPreview = function () {
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
    };
    window.initPoRsPreview();

    // ==========================================
    // NEW: RECEIVE MODAL LOGIC (Discrepancy Checks & Price Entry)
    // ==========================================
    window.openReceiveModal = async function (id, po_no) {
        document.getElementById('receivePoId').value = id;
        document.getElementById('receivePoNo').value = po_no;

        // Reset proof file input & camera state
        const fileInput = document.getElementById('proofOfReceiptFileInput');
        if (fileInput) fileInput.value = '';
        const base64Input = document.getElementById('capturedProofBase64');
        if (base64Input) base64Input.value = '';

        // Reset tab to Upload Image by default
        const uploadTabBtn = document.getElementById('upload-receipt-tab');
        if (uploadTabBtn && typeof bootstrap !== 'undefined') {
            const tab = bootstrap.Tab.getOrCreateInstance(uploadTabBtn);
            tab.show();
        }
        if (typeof window.stopReceiptCamera === 'function') {
            window.stopReceiptCamera();
        }

        const tbody = document.getElementById('receiveItemsBody');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><div class="spinner-border text-success spinner-border-sm me-2"></div> Fetching Manifest...</td></tr>';

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
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No items linked to this manifest.</td></tr>';
                    document.getElementById('confirmReceiveBtn').disabled = true;
                    return;
                }

                document.getElementById('confirmReceiveBtn').disabled = false;

                data.items.forEach(item => {
                    const tr = document.createElement('tr');
                    const initialPrice = parseFloat(item.unit_price || 0).toFixed(2);
                    const orderedQty = parseInt(item.ordered_qty || item.expected_qty || 0);
                    const receivedQty = parseInt(item.received_quantity || 0);
                    const remainingQty = parseInt(item.remaining_qty !== undefined ? item.remaining_qty : (orderedQty - receivedQty));
                    const isAlreadyCompleted = (remainingQty <= 0);
                    const defaultReceiveToday = isAlreadyCompleted ? 0 : remainingQty;
                    const initialSubtotal = (defaultReceiveToday * parseFloat(initialPrice)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    tr.innerHTML = `
                        <td data-label="Item Description">
                            <div class="fw-bold text-dark text-wrap">${item.item_name}</div>
                            <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.72rem;">${item.item_code}</span>
                            <input type="hidden" name="item_codes[]" value="${item.item_code}">
                            <input type="hidden" name="expected_qtys[]" value="${remainingQty}">
                        </td>
                        <td class="text-center fw-semibold text-secondary" data-label="Ordered">
                            ${orderedQty} <small class="text-muted">${item.unit || ''}</small>
                        </td>
                        <td class="text-center fw-semibold text-info" data-label="Prior Recv">
                            ${receivedQty}
                        </td>
                        <td class="text-center fw-bold ${remainingQty > 0 ? 'text-primary' : 'text-muted'}" data-label="Remaining">
                            <span class="badge ${remainingQty > 0 ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-light text-muted'} px-2 py-1">${remainingQty}</span>
                        </td>
                        <td class="text-center align-middle" data-label="Receive Today">
                            ${isAlreadyCompleted ? `
                                <input type="number" name="actual_qtys[]" class="form-control form-control-sm text-center bg-light text-muted actual-qty-input" 
                                    value="0" readonly style="max-width: 90px; font-size: 0.95rem; height: 35px; margin: 0 auto;">
                            ` : `
                                <input type="number" name="actual_qtys[]" class="form-control form-control-sm text-center fw-bold text-success border-success shadow-sm actual-qty-input" 
                                    style="max-width: 90px; font-size: 1rem; height: 35px; margin: 0 auto;" value="${defaultReceiveToday}" min="0" max="${remainingQty}" data-remaining="${remainingQty}" onclick="this.select()" onfocus="this.select()" required>
                            `}
                        </td>
                        <td class="text-center align-middle" data-label="Unit Price (₱)">
                            <input type="number" step="0.01" name="unit_prices[]" class="form-control form-control-sm text-center fw-bold text-primary border-primary shadow-sm unit-price-input" 
                                style="max-width: 105px; font-size: 0.95rem; height: 35px; margin: 0 auto;" value="${initialPrice}" min="0" onclick="this.select()" onfocus="this.select()" required>
                        </td>
                        <td class="text-center align-middle" data-label="Status / Remainder">
                            <div class="disposition-wrapper">
                                ${isAlreadyCompleted ? `
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-2 w-100" style="font-size: 0.75rem;"><i class="bi bi-check-circle-fill me-1"></i>Fulfilled</span>
                                    <input type="hidden" name="item_dispositions[]" value="to_follow">
                                ` : `
                                    <div class="full-delivery-badge">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-2 w-100" style="font-size: 0.75rem;"><i class="bi bi-check2-circle me-1"></i>Full Delivery</span>
                                        <input type="hidden" name="item_dispositions[]" class="disposition-input" value="to_follow">
                                    </div>
                                    <div class="partial-delivery-select d-none">
                                        <select class="form-select form-select-sm fw-bold border-warning shadow-sm disposition-dropdown" style="font-size: 0.78rem;">
                                            <option value="to_follow" selected>🟡 To Follow (Supplier Pending)</option>
                                            <option value="sold_out">🔴 Sold Out (Supplier Cancelled)</option>
                                        </select>
                                    </div>
                                `}
                            </div>
                        </td>
                        <td class="text-end fw-bold text-dark align-middle subtotal-val" data-label="Batch Subtotal">
                            ₱${initialSubtotal}
                        </td>
                    `;
                    tbody.appendChild(tr);

                    const qtyInput = tr.querySelector('.actual-qty-input');
                    const priceInput = tr.querySelector('.unit-price-input');
                    const subtotalTd = tr.querySelector('.subtotal-val');
                    const fullBadge = tr.querySelector('.full-delivery-badge');
                    const partialSelect = tr.querySelector('.partial-delivery-select');
                    const dispInput = tr.querySelector('.disposition-input');
                    const dispSelect = tr.querySelector('.disposition-dropdown');

                    const updateRowState = () => {
                        let q = parseInt(qtyInput.value) || 0;
                        const maxQ = parseInt(qtyInput.getAttribute('data-remaining') || 0);

                        if (q < 0) { q = 0; qtyInput.value = 0; }
                        if (maxQ > 0 && q > maxQ) { 
                            q = maxQ; 
                            qtyInput.value = maxQ; 
                        }

                        const p = parseFloat(priceInput.value) || 0;
                        subtotalTd.textContent = '₱' + (q * p).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        if (!isAlreadyCompleted && fullBadge && partialSelect) {
                            if (q >= maxQ) {
                                fullBadge.classList.remove('d-none');
                                partialSelect.classList.add('d-none');
                                if (dispInput) {
                                    dispInput.name = 'item_dispositions[]';
                                    dispInput.value = 'to_follow';
                                }
                                if (dispSelect) dispSelect.removeAttribute('name');
                            } else {
                                fullBadge.classList.add('d-none');
                                partialSelect.classList.remove('d-none');
                                if (dispInput) dispInput.removeAttribute('name');
                                if (dispSelect) {
                                    dispSelect.name = 'item_dispositions[]';
                                }
                            }
                        }
                        updateGrandTotal();
                    };

                    if (qtyInput && !isAlreadyCompleted) {
                        qtyInput.addEventListener('input', updateRowState);
                    }
                    if (priceInput) {
                        priceInput.addEventListener('input', updateRowState);
                    }
                });

                function updateGrandTotal() {
                    let sum = 0;
                    tbody.querySelectorAll('tr').forEach(r => {
                        const q = parseFloat(r.querySelector('.actual-qty-input')?.value || 0);
                        const p = parseFloat(r.querySelector('.unit-price-input')?.value || 0);
                        sum += (q * p);
                    });
                    const batchTotalEl = document.getElementById('receiveBatchTotalVal');
                    if (batchTotalEl) {
                        batchTotalEl.textContent = '₱' + sum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                }
                updateGrandTotal();
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-3">Error: ${data.message}</td></tr>`;
            }
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-3">Network Error: Could not load the manifest.</td></tr>`;
        }
    }

    window.viewDiscrepancy = function (btnElem) {
        const poNo = btnElem.getAttribute('data-pono') || '';
        const poStatus = btnElem.getAttribute('data-status') || '';
        const poId = btnElem.getAttribute('data-poid') || '';
        const rawText = (btnElem.getAttribute('data-remarks') || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();

        // 1. Update Header Information
        const poNoElem = document.getElementById('discPoNo');
        if (poNoElem) poNoElem.innerText = poNo;

        const isPartial = (poStatus === 'Partially Delivered' || poStatus === 'Partially Received');
        const isDiscrepancy = (poStatus === 'Delivered (Discrepancy)');

        // Header Styling
        const modalCard = document.getElementById('discModalCard');
        const modalIconWrap = document.getElementById('discModalIconWrap');
        const modalIcon = document.getElementById('discModalIcon');
        const modalTitle = document.getElementById('discModalTitle');
        const modalSubtitle = document.getElementById('discModalSubtitle');
        const statusBadge = document.getElementById('discPoStatusBadge');

        if (isPartial) {
            if (modalCard) modalCard.style.setProperty('border-top', '4px solid #ffc107', 'important');
            if (modalIconWrap) {
                modalIconWrap.className = 'rounded-circle p-2 bg-warning-subtle text-warning d-flex align-items-center justify-content-center';
            }
            if (modalIcon) modalIcon.className = 'bi bi-clock-history fs-5';
            if (modalTitle) modalTitle.innerText = 'Delivery Intake & Audit History';
            if (modalSubtitle) modalSubtitle.innerText = 'Multi-Stage Fulfillment Timeline (Remaining Items to Follow)';
            if (statusBadge) {
                statusBadge.className = 'badge bg-warning text-dark border border-warning shadow-sm';
                statusBadge.innerHTML = '<i class="bi bi-pie-chart-fill me-1"></i> Partially Delivered';
            }
        } else if (isDiscrepancy) {
            if (modalCard) modalCard.style.setProperty('border-top', '4px solid #dc3545', 'important');
            if (modalIconWrap) {
                modalIconWrap.className = 'rounded-circle p-2 bg-danger-subtle text-danger d-flex align-items-center justify-content-center';
            }
            if (modalIcon) modalIcon.className = 'bi bi-exclamation-octagon-fill fs-5';
            if (modalTitle) modalTitle.innerText = 'Delivery & Discrepancy Log';
            if (modalSubtitle) modalSubtitle.innerText = 'Order Finalized with Supplier Shortages / Cancellations';
            if (statusBadge) {
                statusBadge.className = 'badge bg-danger text-white shadow-sm';
                statusBadge.innerHTML = '<i class="bi bi-x-octagon-fill me-1"></i> Delivered (Discrepancy)';
            }
        } else {
            if (modalCard) modalCard.style.setProperty('border-top', '4px solid #0d6efd', 'important');
            if (modalIconWrap) {
                modalIconWrap.className = 'rounded-circle p-2 bg-primary-subtle text-primary d-flex align-items-center justify-content-center';
            }
            if (modalIcon) modalIcon.className = 'bi bi-card-checklist fs-5';
            if (modalTitle) modalTitle.innerText = 'Order Delivery Manifest';
            if (modalSubtitle) modalSubtitle.innerText = 'Receipt and intake verification log';
            if (statusBadge) {
                statusBadge.className = 'badge bg-secondary text-white';
                statusBadge.innerText = poStatus || 'Pending';
            }
        }

        // 2. Action buttons in modal footer (Quick "Receive Next Batch" button if still open)
        const actionBtnContainer = document.getElementById('discActionButtons');
        if (actionBtnContainer) {
            if (isPartial && poId) {
                actionBtnContainer.innerHTML = `
                    <button type="button" class="btn btn-success fw-bold px-4 shadow-sm" onclick="bootstrap.Modal.getInstance(document.getElementById('discrepancyModal'))?.hide(); openReceiveModal(${poId}, '${poNo}');">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Receive Next Batch
                    </button>
                `;
            } else {
                actionBtnContainer.innerHTML = '';
            }
        }

        // 3. Raw Log Container
        const rawLogElem = document.getElementById('discRawLog');
        if (rawLogElem) {
            rawLogElem.innerText = rawText || 'No log entries recorded.';
        }

        // 4. Parse Batches and Structured Logs
        const timelineContainer = document.getElementById('discTimelineContainer');
        const batchCountBadge = document.getElementById('discBatchCountBadge');
        if (!timelineContainer) return;

        timelineContainer.innerHTML = '';

        if (!rawText) {
            timelineContainer.innerHTML = `
                <div class="card border-0 shadow-sm p-4 text-center text-muted rounded-3 bg-white">
                    <i class="bi bi-inbox fs-2 d-block mb-1 text-secondary opacity-50"></i>
                    <span>No delivery logs or activity remarks recorded for this purchase order.</span>
                </div>
            `;
            if (batchCountBadge) batchCountBadge.innerText = '0 Records';
        } else {
            // Check if there are structured delivery batches: [DELIVERY BATCH — ...]
            const batchRegex = /\[DELIVERY BATCH\s*—\s*([^\]]+)\]:\s*([\s\S]*?)(?=(?:\[DELIVERY BATCH|\[DELIVERY DISCREPANCY|$))/gi;
            const batches = [];
            let match;

            while ((match = batchRegex.exec(rawText)) !== null) {
                batches.push({
                    meta: match[1].trim(), // e.g. "Sep 07, 2026 5:29 PM by Angelo Carlo Pedrosa"
                    content: match[2].trim()
                });
            }

            if (batches.length > 0) {
                if (batchCountBadge) {
                    batchCountBadge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1';
                    batchCountBadge.innerText = `${batches.length} Delivery ${batches.length === 1 ? 'Batch' : 'Batches'}`;
                }

                batches.forEach((batch, index) => {
                    const batchNum = index + 1;
                    let datePart = batch.meta;
                    let officerPart = 'Warehouse Officer';
                    if (batch.meta.includes(' by ')) {
                        const parts = batch.meta.split(' by ');
                        datePart = parts[0].trim();
                        officerPart = parts[1].trim();
                    }

                    const lines = batch.content.split('\n').filter(l => l.trim().length > 0);

                    let itemsHtml = '';
                    lines.forEach(line => {
                        const rawTrimmed = line.trim().replace(/^-\s*/, '');

                        // Regex to parse structured line:
                        // e.g. "Solar Panel [Code: ITM-5616]: Received 10 units today (Total: 10/15) @ ₱150.00 ⏳ [5 Remainder To Follow from Supplier]"
                        const parsedMatch = rawTrimmed.match(/^(.+?)\s*\[Code:\s*([^\]]+)\]:\s*Received\s*([\d\.,]+)\s*(?:units\s*)?today\s*\(Total:\s*([\d\.,]+)\/([\d\.,]+)\)(.*)$/i);

                        if (parsedMatch) {
                            const itemName = parsedMatch[1].trim();
                            const itemCode = parsedMatch[2].trim();
                            const todayQty = parsedMatch[3].trim();
                            const totalRecv = parseFloat(parsedMatch[4].replace(/,/g, '')) || 0;
                            const orderedQty = parseFloat(parsedMatch[5].replace(/,/g, '')) || 1;
                            const extraInfo = parsedMatch[6] || '';

                            const pct = Math.min(100, Math.round((totalRecv / orderedQty) * 100));

                            // Extract unit price if present
                            const priceMatch = extraInfo.match(/@\s*(₱[\d\.,]+)/);
                            const unitPriceStr = priceMatch ? priceMatch[1] : '';

                            // Status Disposition Pill (Clean, concise, and non-wrapping)
                            let statusPill = '';
                            if (extraInfo.includes('SOLD OUT')) {
                                const cancelMatch = extraInfo.match(/SOLD OUT\s*-\s*(\d+)/i);
                                const cancelQty = cancelMatch ? cancelMatch[1] : (orderedQty - totalRecv);
                                statusPill = `<span class="badge bg-danger text-white shadow-sm px-2.5 py-1.5 text-nowrap"><i class="bi bi-x-octagon-fill me-1"></i>${cancelQty} Sold Out</span>`;
                            } else if (extraInfo.includes('Remainder To Follow') || extraInfo.includes('to follow')) {
                                const followMatch = extraInfo.match(/\[(\d+)\s*Remainder To Follow/i);
                                const followQty = followMatch ? followMatch[1] : (orderedQty - totalRecv);
                                statusPill = `<span class="badge bg-warning text-dark border border-warning shadow-sm px-2.5 py-1.5 text-nowrap"><i class="bi bi-hourglass-split me-1 text-dark"></i>${followQty} To Follow</span>`;
                            } else if (extraInfo.includes('[COMPLETE]') || totalRecv >= orderedQty) {
                                statusPill = `<span class="badge bg-success text-white shadow-sm px-2.5 py-1.5 text-nowrap"><i class="bi bi-check2-circle me-1"></i>100% Fulfilled</span>`;
                            }

                            itemsHtml += `
                                <div class="p-2.5 p-md-3 rounded-3 bg-light border mb-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2.5">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="bg-white rounded-circle p-2 border shadow-sm text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                            <i class="bi bi-box-seam fs-6"></i>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="fw-bold text-dark fs-6 mb-0">${itemName}</span>
                                                <span class="badge bg-white text-secondary border font-monospace shadow-sm" style="font-size: 0.72rem;">${itemCode}</span>
                                            </div>
                                            ${unitPriceStr ? `<small class="text-muted d-block" style="font-size: 0.75rem;">Unit Price: <strong class="text-primary">${unitPriceStr}</strong></small>` : ''}
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center justify-content-md-end gap-2 flex-shrink-0">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 fw-bold text-nowrap" style="font-size: 0.80rem;">
                                            <i class="bi bi-plus-circle me-1"></i>+${todayQty} units
                                        </span>
                                        <span class="badge bg-white text-secondary border px-2.5 py-1.5 fw-semibold shadow-sm text-nowrap" style="font-size: 0.80rem;" title="Fulfilled Progress">
                                            <i class="bi bi-pie-chart me-1 text-muted"></i>Total: ${totalRecv}/${orderedQty} (${pct}%)
                                        </span>
                                        ${statusPill}
                                    </div>
                                </div>
                            `;
                        } else {
                            // Fallback for unstructured lines
                            let statusPill = '';
                            let cleanLine = rawTrimmed;

                            if (cleanLine.includes('SOLD OUT')) {
                                statusPill = `<span class="badge bg-danger text-white shadow-sm px-2 py-1"><i class="bi bi-x-circle-fill me-1"></i> Sold Out (Cancelled)</span>`;
                            } else if (cleanLine.includes('Remainder To Follow') || cleanLine.includes('to follow')) {
                                statusPill = `<span class="badge bg-warning text-dark border border-warning shadow-sm px-2 py-1"><i class="bi bi-hourglass-split me-1"></i> To Follow (Backordered)</span>`;
                            } else if (cleanLine.includes('[COMPLETE]')) {
                                statusPill = `<span class="badge bg-success text-white shadow-sm px-2 py-1"><i class="bi bi-check2-circle me-1"></i> Fulfilled (100%)</span>`;
                            }

                            cleanLine = cleanLine.replace(/⏳\s*\[[^\]]+\]/g, '')
                                                 .replace(/⚠️\s*\[[^\]]+\]/g, '')
                                                 .replace(/✅\s*\[[^\]]+\]/g, '')
                                                 .trim();

                            itemsHtml += `
                                <div class="p-2.5 rounded-2 bg-light border mb-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                    <div class="text-dark fw-semibold" style="font-size: 0.88rem;">
                                        <i class="bi bi-box-seam me-1.5 text-primary"></i> ${cleanLine}
                                    </div>
                                    <div class="flex-shrink-0">
                                        ${statusPill}
                                    </div>
                                </div>
                            `;
                        }
                    });

                    const isLatest = (index === batches.length - 1);
                    const batchBadgeClass = isLatest ? 'bg-primary' : 'bg-dark';

                    const batchCard = document.createElement('div');
                    batchCard.className = 'card border-0 shadow-sm rounded-3 overflow-hidden';
                    batchCard.innerHTML = `
                        <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge ${batchBadgeClass} text-white fw-bold px-2 py-1">Batch #${batchNum}</span>
                                <span class="fw-bold text-dark small"><i class="bi bi-calendar-event me-1 text-muted"></i> ${datePart}</span>
                                ${isLatest ? '<span class="badge bg-success-subtle text-success border border-success-subtle small px-1.5 py-0.5">Latest Intake</span>' : ''}
                            </div>
                            <span class="badge bg-light text-secondary border small">
                                <i class="bi bi-person-check-fill me-1 text-primary"></i> ${officerPart}
                            </span>
                        </div>
                        <div class="card-body p-3 bg-white">
                            <div class="d-flex flex-column">
                                ${itemsHtml}
                            </div>
                        </div>
                    `;
                    timelineContainer.appendChild(batchCard);
                });
            } else {
                // Legacy Discrepancy or Plain Text handling
                if (batchCountBadge) {
                    batchCountBadge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1';
                    batchCountBadge.innerText = 'Legacy Discrepancy Log';
                }

                const legacyParts = rawText.split(/\[DELIVERY DISCREPANCY\]:/i).filter(p => p.trim().length > 0);
                if (legacyParts.length > 0) {
                    legacyParts.forEach((part, idx) => {
                        const card = document.createElement('div');
                        card.className = 'card border-0 shadow-sm rounded-3 overflow-hidden mb-2';
                        card.innerHTML = `
                            <div class="card-header bg-danger-subtle text-danger border-bottom border-danger-subtle py-2 px-3 fw-bold small">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Discrepancy Record #${idx + 1}
                            </div>
                            <div class="card-body p-3 bg-white text-dark" style="font-size: 0.9rem; line-height: 1.6; white-space: pre-wrap;">${part.trim()}</div>
                        `;
                        timelineContainer.appendChild(card);
                    });
                } else {
                    const card = document.createElement('div');
                    card.className = 'card border-0 shadow-sm rounded-3 overflow-hidden';
                    card.innerHTML = `
                        <div class="card-body p-3 bg-white text-dark" style="font-size: 0.9rem; line-height: 1.6; white-space: pre-wrap;">${rawText}</div>
                    `;
                    timelineContainer.appendChild(card);
                }
            }
        }

        // 5. Proof of Receipt File Link
        let proofPath = btnElem.getAttribute('data-proof');
        if (proofPath && proofPath.includes('uploads/receipts/')) {
            const filename = proofPath.split('/').pop();
            proofPath = `secure-image?type=receipts&file=${encodeURIComponent(filename)}`;
        }

        const proofContainer = document.getElementById('discProofContainer');
        if (proofContainer) {
            if (proofPath && proofPath.trim() !== '') {
                proofContainer.classList.remove('d-none');
                proofContainer.innerHTML = `
                    <div class="card border-0 shadow-sm rounded-3 bg-white p-3 border-start border-4 border-info">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="p-2 bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="bi bi-paperclip fs-5"></i>
                                </div>
                                <div>
                                    <span class="fw-bold text-dark d-block small">Proof of Receipt Attached</span>
                                    <small class="text-muted">Delivery Order / Live Camera Physical Verification</small>
                                </div>
                            </div>
                            <a href="${proofPath}" onclick="event.preventDefault(); window.openPhotoWindow('${proofPath}');" class="btn btn-sm btn-info text-white fw-bold shadow-sm px-3">
                                <i class="bi bi-box-arrow-up-right me-1"></i> View Attached Receipt
                            </a>
                        </div>
                    </div>
                `;
            } else {
                proofContainer.classList.add('d-none');
                proofContainer.innerHTML = '';
            }
        }

        // Open Modal
        var myModalEl = document.getElementById('discrepancyModal');
        var discModal = bootstrap.Modal.getInstance(myModalEl);
        if (!discModal) discModal = new bootstrap.Modal(myModalEl);
        discModal.show();
    };

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
                } catch (e) { }
                video.srcObject = null;
            }
            try { video.pause(); } catch (e) { }
            video.classList.add('d-none');
        }

        if (window.receiptStream) {
            try {
                const tracks = window.receiptStream.getTracks();
                tracks.forEach(track => {
                    track.stop();
                    track.enabled = false;
                });
            } catch (e) { }
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
            } catch (e) { }
            video.srcObject = null;
        }
        if (window.receiptStream) {
            try {
                window.receiptStream.getTracks().forEach(track => {
                    track.stop();
                    track.enabled = false;
                });
            } catch (e) { }
            window.receiptStream = null;
        }

        video.classList.add('d-none');
        captureBtn.classList.add('d-none');
        retakeBtn.classList.remove('d-none');
    };

    document.addEventListener('DOMContentLoaded', function () {
        const receiveModalEl = document.getElementById('receiveModal');
        const receiveForm = document.getElementById('receiveForm');

        if (receiveModalEl) {
            // Accessibility: Auto-focus the first editable quantity field
            receiveModalEl.addEventListener('shown.bs.modal', function () {
                const firstQty = receiveModalEl.querySelector('.actual-qty-input:not([readonly])');
                if (firstQty) {
                    firstQty.focus();
                    firstQty.select();
                }
            });

            // Modal Lifecycle: Graceful camera cleanup and state teardown
            receiveModalEl.addEventListener('hide.bs.modal', function () {
                if (typeof window.stopReceiptCamera === 'function') {
                    window.stopReceiptCamera();
                }
            });

            receiveModalEl.addEventListener('hidden.bs.modal', function () {
                if (typeof window.stopReceiptCamera === 'function') {
                    window.stopReceiptCamera();
                }
                const fileInput = document.getElementById('proofOfReceiptFileInput');
                if (fileInput) fileInput.value = '';
                const base64Input = document.getElementById('capturedProofBase64');
                if (base64Input) base64Input.value = '';
                const previewImg = document.getElementById('receiptPhotoPreview');
                if (previewImg) {
                    previewImg.src = '';
                    previewImg.classList.add('d-none');
                }
                const retakeBtn = document.getElementById('retakeReceiptPhotoBtn');
                if (retakeBtn) retakeBtn.classList.add('d-none');
            });
        }

        // Standard AJAX Modal Submission Pattern (cims-modal-ajax-handler & quality-standards)
        if (receiveForm) {
            receiveForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                // 1. Client-side form validity check
                if (!receiveForm.checkValidity()) {
                    receiveForm.reportValidity();
                    return;
                }

                const tbody = document.getElementById('receiveItemsBody');
                const qtyInputs = tbody ? tbody.querySelectorAll('.actual-qty-input') : [];
                if (!tbody || qtyInputs.length === 0) {
                    const msg = 'No manifest items found to receive.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'Empty Manifest', text: msg });
                    } else {
                        alert(msg);
                    }
                    return;
                }

                // Defensive check: non-negative and bounds validation
                let totalBatchQty = 0;
                let hasNegative = false;
                let hasOverQty = false;

                qtyInputs.forEach(input => {
                    const val = parseInt(input.value) || 0;
                    const max = parseInt(input.getAttribute('data-remaining') || input.getAttribute('max') || 0);
                    if (val < 0) hasNegative = true;
                    if (max > 0 && val > max) hasOverQty = true;
                    totalBatchQty += val;
                });

                if (hasNegative) {
                    const msg = 'Received quantities cannot be negative.';
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Invalid Quantity', text: msg });
                    else alert(msg);
                    return;
                }

                if (hasOverQty) {
                    const msg = 'One or more items exceed the maximum remaining quantity permitted.';
                    if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Quantity Exceeded', text: msg });
                    else alert(msg);
                    return;
                }

                // If 0 units received across entire shipment, confirm explicit user intent
                if (totalBatchQty === 0) {
                    if (typeof Swal !== 'undefined') {
                        const confirmZero = await Swal.fire({
                            icon: 'question',
                            title: 'Zero Units Arrived?',
                            text: 'You have entered 0 units received today for all items. Proceed only if recording non-delivery or supplier cancellation.',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, Proceed',
                            cancelButtonText: 'Cancel'
                        });
                        if (!confirmZero.isConfirmed) return;
                    } else {
                        if (!confirm('You entered 0 units received today. Proceed?')) return;
                    }
                }

                // 2. Prevent duplicate submits & display loading state
                const submitBtn = document.getElementById('confirmReceiveBtn');
                const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '<i class="bi bi-check2-all me-1"></i>Confirm & Stock In';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Stocking In...';
                }

                const cancelBtns = receiveModalEl ? receiveModalEl.querySelectorAll('[data-bs-dismiss="modal"]') : [];
                cancelBtns.forEach(btn => btn.disabled = true);

                try {
                    const formData = new FormData(receiveForm);

                    // Pre-compress photo proof if an image file was selected
                    const proofFileInput = document.getElementById('proofOfReceiptFileInput');
                    if (proofFileInput && proofFileInput.files && proofFileInput.files.length > 0) {
                        let pFile = proofFileInput.files[0];
                        if (pFile.type && pFile.type.startsWith('image/') && typeof window.compressImageFile === 'function') {
                            if (submitBtn) {
                                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Optimizing Photo...';
                            }
                            try {
                                pFile = await window.compressImageFile(pFile, { maxDimension: 1920, quality: 0.82 });
                                formData.set('proof_of_receipt', pFile);
                            } catch (compErr) {
                                console.warn('[CIMS] Proof compression fallback:', compErr);
                            }
                            if (submitBtn) {
                                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Stocking In...';
                            }
                        }
                    }

                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    if (csrfToken && !formData.has('csrf_token')) {
                        formData.append('csrf_token', csrfToken);
                    }

                    const response = await fetch('process/process.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-Token': csrfToken
                        }
                    });

                    const rawText = await response.text();
                    let result = null;
                    try {
                        result = JSON.parse(rawText);
                    } catch (jsonErr) {
                        console.error('Non-JSON server response:', rawText);
                        throw new Error('Server returned an unexpected response. Please try again.');
                    }

                    const isSuccess = result && (result.success === true || result.status === 'success');

                    if (isSuccess) {
                        // Stop camera if running
                        if (typeof window.stopReceiptCamera === 'function') {
                            window.stopReceiptCamera();
                        }

                        // Hide modal instance
                        if (receiveModalEl) {
                            const modalInstance = bootstrap.Modal.getInstance(receiveModalEl);
                            if (modalInstance) modalInstance.hide();
                        }

                        // Success notification via SweetAlert2
                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'success',
                                title: 'Stock In Recorded!',
                                text: result.message || 'Delivery successfully processed and inventory updated.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            alert(result.message || 'Delivery successfully processed.');
                        }

                        // Refresh table / page state
                        window.location.reload();
                    } else {
                        throw new Error(result?.message || 'Failed to process Stock In.');
                    }
                } catch (error) {
                    console.error('Stock In Error:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Stock In Failed',
                            text: error.message || 'Failed to process delivery.'
                        });
                    } else {
                        alert(error.message || 'Failed to process delivery.');
                    }
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHtml;
                    }
                    cancelBtns.forEach(btn => btn.disabled = false);
                }
            });
        }
    });

    // ==========================================
    // VIRTUAL PO DOCUMENT & PRINT LOGIC
    // ==========================================
    window.currentPoModalData = null;

    window.triggerPoReceiptModal = function (hasExisting) {
        if (!window.currentPoModalData) return;
        const po = window.currentPoModalData;
        const isExisting = hasExisting !== undefined ? hasExisting : Boolean(po.proof_of_receipt && po.proof_of_receipt.trim() !== '');
        openUploadReceiptModal(po.id, po.po_no, po.company_name, isExisting ? 1 : 0);
    };

    window.openPoPrintModal = async function (poId) {
        const spinner = document.getElementById('poPrintLoadingSpinner');
        const modalContent = document.getElementById('poPrintModalContent');
        const paper = document.getElementById('poPrintPaper');
        if (spinner) spinner.classList.remove('d-none');
        if (modalContent) modalContent.classList.add('d-none');
        if (paper) paper.classList.add('d-none');

        // Always activate Tab 1 (PO Document) when opening modal
        const docTabBtn = document.getElementById('poTabDocBtn');
        if (docTabBtn && typeof bootstrap !== 'undefined') {
            const tabInstance = bootstrap.Tab.getInstance(docTabBtn) || new bootstrap.Tab(docTabBtn);
            tabInstance.show();
        }

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
                window.currentPoModalData = po;

                // Update Header PO badge
                const headerPoNo = document.getElementById('poModalHeaderPoNo');
                if (headerPoNo) headerPoNo.innerText = po.po_no || ('PO-' + po.id);

                document.getElementById('printPoNo').innerText = po.po_no;
                let docStatus = po.status || 'Generated';
                if (docStatus === 'Viber Order Sent' || docStatus === 'SMS Sent') {
                    docStatus = 'Order Sent';
                }
                const poStatusBadge = document.getElementById('printPoStatus');
                if (poStatusBadge) {
                    poStatusBadge.innerText = docStatus;
                    if (docStatus === 'Out for Delivery') {
                        poStatusBadge.className = 'badge bg-primary px-2 py-0.5 text-uppercase po-doc-status';
                    } else if (docStatus === 'Delivered') {
                        poStatusBadge.className = 'badge bg-success px-2 py-0.5 text-uppercase po-doc-status';
                    } else if (docStatus === 'Order Sent') {
                        poStatusBadge.className = 'badge bg-secondary px-2 py-0.5 text-uppercase po-doc-status';
                    } else {
                        poStatusBadge.className = 'badge bg-dark px-2 py-0.5 text-uppercase po-doc-status';
                    }
                }
                document.getElementById('printPoDate').innerText = data.formatted_date;
                document.getElementById('printRsNo').innerText = po.rs_no || 'N/A';
                document.getElementById('printProjectName').innerText = po.project_name || 'Warehouse Restock';
                const poTermsEl = document.getElementById('printPoTerms');
                if (poTermsEl) {
                    poTermsEl.innerText = po.payment_terms || 'Credit (30 Days Net)';
                }
                document.getElementById('printPoEta').innerText = data.formatted_eta;
                document.getElementById('printPreparedBy').innerText = po.prepared_by_name || 'Purchasing Department';
                const prepSigWrap = document.getElementById('preparedSigImgWrap');
                const prepSigImg = document.getElementById('printPreparedSigImg');
                const prepSig = po.prepared_signature || po.prepared_user_sig;
                if (prepSigWrap && prepSigImg) {
                    if (prepSig && prepSig.trim() !== '') {
                        const filename = prepSig.split('/').pop();
                        prepSigImg.src = `secure_image.php?type=signatures&file=${encodeURIComponent(filename)}&t=${Date.now()}`;
                        prepSigWrap.classList.remove('d-none');
                    } else {
                        prepSigWrap.classList.add('d-none');
                    }
                }

                const appByElem = document.getElementById('printApprovedBy');
                if (appByElem) {
                    appByElem.innerText = po.approved_by_name || 'Management / Supplier Authorization';
                }
                const appSigWrap = document.getElementById('approvedSigImgWrap');
                const appSigImg = document.getElementById('printApprovedSigImg');
                const appSig = po.approved_signature || po.approved_user_sig;
                if (appSigWrap && appSigImg) {
                    if (appSig && appSig.trim() !== '') {
                        const filename = appSig.split('/').pop();
                        appSigImg.src = `secure_image.php?type=signatures&file=${encodeURIComponent(filename)}&t=${Date.now()}`;
                        appSigWrap.classList.remove('d-none');
                    } else {
                        appSigWrap.classList.add('d-none');
                    }
                }

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
                        const recvQty = parseInt(item.received_quantity || 0);
                        const ordQty = parseInt(item.quantity || 0);
                        let fulfillmentBadge = '';
                        if (recvQty > 0 || item.item_status) {
                            if (item.item_status === 'Complete' || recvQty >= ordQty) {
                                fulfillmentBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-1" style="font-size: 0.64rem;"><i class="bi bi-check2-circle me-1"></i>Delivered (${recvQty}/${ordQty})</span>`;
                            } else if (item.item_status === 'Sold Out') {
                                fulfillmentBadge = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0 px-1" style="font-size: 0.64rem;"><i class="bi bi-x-circle me-1"></i>Sold Out (${recvQty}/${ordQty})</span>`;
                            } else {
                                fulfillmentBadge = `<span class="badge bg-warning-subtle text-dark border border-warning-subtle py-0 px-1" style="font-size: 0.64rem;"><i class="bi bi-pie-chart-fill me-1"></i>Recv'd ${recvQty}/${ordQty} (${item.remaining_qty || (ordQty - recvQty)} to follow)</span>`;
                            }
                        }

                        tr.innerHTML = `
                            <td class="text-center font-monospace py-1 px-1">${index + 1}</td>
                            <td class="fw-bold text-muted py-1 px-1 font-monospace text-nowrap" style="font-size: 0.70rem; white-space: nowrap !important; word-break: keep-all !important;">${item.item_code}</td>
                            <td class="py-1 px-1 text-wrap" style="min-width: 85px;">
                                <div class="fw-bold text-dark" style="font-size: 0.74rem; line-height: 1.15;">${item.item_name} <span class="text-muted fw-normal" style="font-size: 0.68rem;">(${item.unit || 'units'})</span></div>
                                ${fulfillmentBadge ? `<div class="mt-0.5">${fulfillmentBadge}</div>` : ''}
                            </td>
                            <td class="text-center fw-bold text-primary py-1 px-1" style="font-size: 0.74rem;">${item.quantity}</td>
                            <td class="text-end py-1 px-1 text-nowrap" style="font-size: 0.72rem;">₱${parseFloat(item.unit_price || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            <td class="text-end fw-bold py-1 px-1 text-nowrap" style="font-size: 0.74rem;">₱${parseFloat(item.subtotal || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No items listed.</td></tr>';
                }

                document.getElementById('printPoTotalValue').innerText = '₱' + parseFloat(data.total_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // Logistics / Discrepancy Remarks
                const remarksSec = document.getElementById('printRemarksSection');
                const remarksText = document.getElementById('printPoRemarks');
                if (po.delay_remarks && po.delay_remarks.trim() !== '') {
                    remarksSec.classList.remove('d-none');
                    let rawRemarks = po.delay_remarks.trim();
                    const parts = rawRemarks.split('[DELIVERY DISCREPANCY]:');
                    const uniqueBlocks = [];
                    parts.forEach(part => {
                        const trimmed = part.trim();
                        if (trimmed && !uniqueBlocks.includes(trimmed)) {
                            uniqueBlocks.push(trimmed);
                        }
                    });

                    if (uniqueBlocks.length > 0) {
                        remarksText.innerText = uniqueBlocks.map(b => '[DELIVERY DISCREPANCY]:\n' + b).join('\n\n');
                    } else {
                        remarksText.innerText = rawRemarks;
                    }
                } else {
                    remarksSec.classList.add('d-none');
                }

                // Cryptographic Verification QR Code & Document Digest
                const poDocHashElem = document.getElementById('printPoDocHash');
                if (poDocHashElem) {
                    if (po.document_hash) {
                        poDocHashElem.innerText = po.document_hash.substring(0, 14) + '...' + po.document_hash.substring(po.document_hash.length - 8);
                        poDocHashElem.title = po.document_hash;
                    } else {
                        poDocHashElem.innerText = 'SHA256:AUTHENTICATED';
                    }
                }

                const poQrImg = document.getElementById('printPoQrCode');
                if (poQrImg) {
                    const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                    const verifyUrl = `${window.location.origin}${basePath}/verify?type=po&ref=${encodeURIComponent(po.po_no)}`;
                    poQrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(verifyUrl)}`;
                }

                // ==========================================
                // POPULATE TAB 2: ATTACHED DELIVERY RECEIPT
                // ==========================================
                const receiptBadge = document.getElementById('poReceiptTabBadge');
                const attachedView = document.getElementById('poReceiptAttachedView');
                const emptyView = document.getElementById('poReceiptEmptyView');
                const attachBtn = document.getElementById('poModalAttachReceiptBtn');

                if (po.proof_of_receipt && po.proof_of_receipt.trim() !== '') {
                    const filename = po.proof_of_receipt.split('/').pop().split('\\').pop();
                    const secureUrl = `secure_image.php?type=receipts&file=${encodeURIComponent(filename)}&t=${Date.now()}`;
                    const ext = filename.split('.').pop().toUpperCase();
                    const isPdf = ext === 'PDF';

                    if (receiptBadge) {
                        receiptBadge.className = 'badge rounded-pill bg-success ms-1';
                        receiptBadge.innerHTML = '<i class="bi bi-check2"></i> Attached';
                    }

                    if (attachedView) attachedView.classList.remove('d-none');
                    if (emptyView) emptyView.classList.add('d-none');

                    const extBadge = document.getElementById('poReceiptFileExtBadge');
                    if (extBadge) extBadge.innerText = ext;

                    const extLink = document.getElementById('poReceiptExternalLink');
                    if (extLink) extLink.href = secureUrl;

                    const imgEl = document.getElementById('poReceiptImagePreview');
                    const pdfWrap = document.getElementById('poReceiptPdfWrap');
                    const pdfFrame = document.getElementById('poReceiptPdfFrame');

                    if (isPdf) {
                        if (imgEl) imgEl.classList.add('d-none');
                        if (pdfWrap) pdfWrap.classList.remove('d-none');
                        if (pdfFrame) pdfFrame.src = secureUrl;
                    } else {
                        if (pdfWrap) pdfWrap.classList.add('d-none');
                        if (pdfFrame) pdfFrame.src = '';
                        if (imgEl) {
                            imgEl.src = secureUrl;
                            imgEl.classList.remove('d-none');
                        }
                    }

                    const metaEl = document.getElementById('poReceiptUploadMeta');
                    const notesBox = document.getElementById('poReceiptNotesBox');
                    const notesText = document.getElementById('poReceiptNotesText');

                    let receiptMetaStr = '<i class="bi bi-file-earmark-arrow-up me-1"></i>Document archived in secure storage';
                    let receiptNotesStr = '';

                    if (po.delay_remarks) {
                        const m = po.delay_remarks.match(/\[RECEIPT\s+(?:ATTACHED|UPDATED)\s+[—\-]\s+([^\]]+)\]/i);
                        if (m && m[1]) {
                            receiptMetaStr = '<i class="bi bi-person-check me-1"></i>Uploaded: ' + m[1];
                        }
                        const noteMatch = po.delay_remarks.match(/Reference \/ Notes:\s*([^\n\r]+)/i);
                        if (noteMatch && noteMatch[1]) {
                            receiptNotesStr = noteMatch[1].trim();
                        }
                    }
                    if (metaEl) metaEl.innerHTML = receiptMetaStr;
                    if (notesBox && notesText) {
                        if (receiptNotesStr) {
                            notesText.innerText = receiptNotesStr;
                            notesBox.classList.remove('d-none');
                        } else {
                            notesBox.classList.add('d-none');
                        }
                    }

                    if (attachBtn) {
                        attachBtn.classList.remove('d-none');
                        attachBtn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Replace Receipt';
                    }
                } else {
                    if (receiptBadge) {
                        receiptBadge.className = 'badge rounded-pill bg-secondary ms-1';
                        receiptBadge.innerText = 'None';
                    }
                    if (attachedView) attachedView.classList.add('d-none');
                    if (emptyView) emptyView.classList.remove('d-none');
                    if (attachBtn) {
                        attachBtn.classList.remove('d-none');
                        attachBtn.innerHTML = '<i class="bi bi-paperclip me-1"></i> Attach Receipt';
                    }
                }

                // ==========================================
                // POPULATE TAB 3: FULFILLMENT TIMELINE
                // ==========================================
                const tlStatusBadge = document.getElementById('poTimelineStatusBadge');
                if (tlStatusBadge) {
                    tlStatusBadge.innerText = po.status || 'Generated';
                    tlStatusBadge.className = 'badge px-2 py-1 mt-0.5 ' + (
                        po.status === 'Delivered' ? 'bg-success' :
                        po.status === 'Out for Delivery' ? 'bg-primary' :
                        po.status === 'Cancelled' ? 'bg-danger' :
                        'bg-secondary'
                    );
                }
                const tlSupplier = document.getElementById('poTimelineSupplier');
                if (tlSupplier) tlSupplier.innerText = po.company_name || 'N/A';
                const tlRsNo = document.getElementById('poTimelineRsNo');
                if (tlRsNo) tlRsNo.innerText = po.rs_no || 'N/A';
                const tlEta = document.getElementById('poTimelineEta');
                if (tlEta) tlEta.innerText = data.formatted_eta || 'Not Set';

                if (typeof window.renderPoTimeline === 'function') {
                    window.renderPoTimeline(po, data);
                }

                if (spinner) spinner.classList.add('d-none');
                if (modalContent) modalContent.classList.remove('d-none');
                if (paper) paper.classList.remove('d-none');
            } else {
                alert("Failed to load PO details: " + data.message);
                if (printModal) printModal.hide();
            }
        } catch (e) {
            console.error('Fetch PO details error:', e);
            alert("Network error: Could not fetch PO document details.");
            if (printModal) printModal.hide();
        }
    };

    // ==========================================
    // RENDER PO TIMELINE & AUDIT TRAIL (TAB 3)
    // ==========================================
    window.renderPoTimeline = function (po, data) {
        const container = document.getElementById('poTimelineContainer');
        if (!container) return;

        const status = po.status || 'Generated';
        const isCancelled = status === 'Cancelled';
        const isDelivered = status === 'Delivered';
        const isOutForDelivery = status === 'Out for Delivery';
        const isViberSent = status === 'Viber Order Sent' || isOutForDelivery || isDelivered;
        const hasReceipt = Boolean(po.proof_of_receipt && po.proof_of_receipt.trim() !== '');

        let outForDeliveryNote = '';
        let outForDeliveryMeta = '';
        let receiptMeta = '';
        const delayIncidents = [];

        if (po.delay_remarks) {
            const lines = po.delay_remarks.split('\n');
            let currentBlock = '';
            let currentHeader = '';

            lines.forEach(line => {
                const trimmed = line.trim();
                if (trimmed.startsWith('[') && trimmed.includes(']')) {
                    if (currentHeader && currentBlock) {
                        processRemarkBlock(currentHeader, currentBlock);
                    }
                    currentHeader = trimmed;
                    currentBlock = '';
                } else if (trimmed) {
                    currentBlock += (currentBlock ? '\n' : '') + trimmed;
                }
            });
            if (currentHeader && currentBlock) {
                processRemarkBlock(currentHeader, currentBlock);
            }
        }

        function processRemarkBlock(header, content) {
            if (header.includes('OUT FOR DELIVERY')) {
                outForDeliveryMeta = header.replace(/^\[|\]$/g, '');
                outForDeliveryNote = content;
            } else if (header.includes('RECEIPT')) {
                receiptMeta = header.replace(/^\[|\]$/g, '');
            } else if (header.includes('DELIVERY DISCREPANCY') || header.includes('DELAY ALERT') || header.includes('PO VOIDED') || header.includes('ETA UPDATED')) {
                delayIncidents.push({ header: header.replace(/^\[|\]$/g, ''), content: content });
            }
        }

        const steps = [
            {
                title: 'Purchase Order Created & Authorized',
                icon: 'bi-file-earmark-check-fill',
                nodeClass: 'completed',
                date: data.formatted_date,
                subtitle: `Prepared by <strong>${po.prepared_by_name || 'Purchasing'}</strong> &bull; Authorized by <strong>${po.approved_by_name || 'Management'}</strong>`,
                extra: `Linked Requisition: <span class="badge bg-light text-primary border">${po.rs_no || 'N/A'}</span> &bull; Project: <em>${po.project_name || 'Warehouse Restock'}</em>`
            },
            {
                title: 'Supplier Order Transmitted',
                icon: 'bi-send-check-fill',
                nodeClass: isCancelled ? 'muted' : (isViberSent ? 'completed' : 'muted'),
                date: isViberSent ? 'Dispatched to Supplier' : 'Pending Transmission',
                subtitle: `Transmitted to <strong>${po.company_name || 'Supplier'}</strong> (${po.contact_person || 'Representative'})`,
                extra: `Expected Delivery Target (ETA): <span class="fw-bold text-dark">${data.formatted_eta || 'Not Set'}</span>`
            },
            {
                title: 'Out for Delivery / In Transit',
                icon: 'bi-truck',
                nodeClass: isCancelled ? 'muted' : (isDelivered ? 'completed' : (isOutForDelivery ? 'active' : 'muted')),
                date: isOutForDelivery ? 'In Transit Right Now' : (isDelivered ? 'Transit Completed' : 'Awaiting Supplier Dispatch'),
                subtitle: outForDeliveryMeta ? `<i class="bi bi-clock me-1"></i>${outForDeliveryMeta}` : (isOutForDelivery ? 'Dispatched by supplier and currently en route to warehouse.' : 'Shipment has not been marked out for delivery yet.'),
                extra: outForDeliveryNote ? `<div class="p-1.5 bg-light rounded border mt-1 small font-monospace"><i class="bi bi-card-text me-1 text-primary"></i>${outForDeliveryNote}</div>` : ''
            },
            {
                title: 'Warehouse Receiving & Stock-In',
                icon: 'bi-box-seam-fill',
                nodeClass: isCancelled ? 'muted' : (isDelivered ? 'completed' : 'muted'),
                date: isDelivered ? 'Stocked In Successfully' : 'Awaiting Arrival',
                subtitle: isDelivered ? 'All ordered materials verified and stocked into warehouse inventory.' : 'Materials will be received and counted upon delivery arrival.',
                extra: ''
            },
            {
                title: 'Official Delivery Receipt / Invoicing Proof',
                icon: 'bi-file-earmark-medical-fill',
                nodeClass: hasReceipt ? 'completed' : (isDelivered ? 'warning' : 'muted'),
                date: hasReceipt ? 'Document Attached & Secured' : (isDelivered ? 'Action Required: Attach Receipt' : 'Pending Receipt'),
                subtitle: hasReceipt ? (receiptMeta || 'Delivery receipt uploaded to secure repository.') : (isDelivered ? 'Items delivered. Please upload supplier receipt / sales invoice to complete audit trail.' : 'Awaiting receipt upload after physical delivery.'),
                extra: hasReceipt ? `<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 mt-1" onclick="document.getElementById('poTabReceiptBtn').click()"><i class="bi bi-paperclip me-1"></i>View Attached Receipt</button>` : ''
            }
        ];

        if (isCancelled) {
            steps.push({
                title: 'Purchase Order Voided / Cancelled',
                icon: 'bi-slash-circle-fill',
                nodeClass: 'warning',
                date: 'Voided',
                subtitle: 'This purchase order has been cancelled and its audit trail frozen.',
                extra: ''
            });
        }

        let html = '';
        steps.forEach(step => {
            html += `
                <div class="po-timeline-step">
                    <div class="po-timeline-node ${step.nodeClass}">
                        <i class="bi ${step.icon}"></i>
                    </div>
                    <div class="po-timeline-card">
                        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-1 mb-1">
                            <div class="fw-bold text-dark" style="font-size: 0.82rem;">${step.title}</div>
                            <span class="badge ${step.nodeClass === 'completed' ? 'bg-success-subtle text-success border border-success-subtle' : step.nodeClass === 'active' ? 'bg-primary text-white' : step.nodeClass === 'warning' ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-light text-muted border'}" style="font-size: 0.65rem;">
                                ${step.date}
                            </span>
                        </div>
                        <div class="text-secondary small" style="font-size: 0.74rem;">${step.subtitle}</div>
                        ${step.extra ? `<div class="mt-1 small" style="font-size: 0.72rem;">${step.extra}</div>` : ''}
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;

        const alertsSection = document.getElementById('poTimelineAlertsSection');
        const alertsBody = document.getElementById('poTimelineAlertsBody');
        const alertsCount = document.getElementById('poTimelineAlertCount');

        if (delayIncidents.length > 0) {
            if (alertsCount) alertsCount.innerText = delayIncidents.length;
            let incHtml = '';
            delayIncidents.forEach((inc, idx) => {
                incHtml += `
                    <div class="p-2 bg-light rounded border mb-2 ${idx === delayIncidents.length - 1 ? 'mb-0' : ''}">
                        <div class="fw-bold text-dark" style="font-size: 0.74rem;"><i class="bi bi-exclamation-circle-fill text-warning me-1"></i>${inc.header}</div>
                        <div class="text-secondary mt-1 font-monospace" style="font-size: 0.72rem; white-space: pre-wrap;">${inc.content}</div>
                    </div>
                `;
            });
            if (alertsBody) alertsBody.innerHTML = incHtml;
            if (alertsSection) alertsSection.classList.remove('d-none');
        } else {
            if (alertsSection) alertsSection.classList.add('d-none');
        }
    };

    window.printPoDocument = function () {
        const paperEl = document.getElementById('poPrintPaper');
        const itemCount = paperEl.querySelectorAll('#printPoItemsBody tr').length;
        const isDense = itemCount >= 6 && itemCount <= 12;
        const isMultiPage = itemCount > 12;

        const printContent = paperEl.innerHTML;
        const printWindow = window.open('', '_blank', 'width=850,height=900');
        if (!printWindow) {
            alert("Pop-up blocked. Please allow pop-ups for this site to print.");
            return;
        }

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Purchase Order Print Document</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
                <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
                <style>
                    @page {
                        size: auto;
                        margin: 0 !important;
                    }
                    * {
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                        box-sizing: border-box !important;
                    }
                    html, body {
                        width: 100% !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        background: #ffffff !important;
                        color: #212529 !important;
                        font-family: 'Plus Jakarta Sans', Arial, sans-serif;
                    }
                    body {
                        font-size: ${isDense ? '0.78rem' : '0.86rem'};
                        line-height: 1.3 !important;
                    }
                    .print-wrapper {
                        width: 100% !important;
                        max-width: 100% !important;
                        margin: 0 !important;
                        padding: 0.5in 0.4in 0.4in 0.4in !important;
                        box-sizing: border-box !important;
                        ${isMultiPage ? '' : 'page-break-inside: avoid;'}
                    }
                    .row {
                        display: flex !important;
                        flex-wrap: wrap !important;
                        width: 100% !important;
                        margin-left: 0 !important;
                        margin-right: 0 !important;
                    }
                    .col-8 { width: 66.666667% !important; flex: 0 0 66.666667% !important; }
                    .col-7 { width: 58.333333% !important; flex: 0 0 58.333333% !important; }
                    .col-5 { width: 41.666667% !important; flex: 0 0 41.666667% !important; }
                    .col-4 { width: 33.333333% !important; flex: 0 0 33.333333% !important; }
                    .col-6 { width: 50% !important; flex: 0 0 50% !important; }
                    .col-9 { width: 75% !important; flex: 0 0 75% !important; }
                    .col-3 { width: 25% !important; flex: 0 0 25% !important; }
                    .col-12 { width: 100% !important; flex: 0 0 100% !important; }
                    .d-flex { display: flex !important; }
                    .align-items-center { align-items: center !important; }
                    .justify-content-between { justify-content: space-between !important; }
                    .justify-content-end { justify-content: flex-end !important; }
                    .text-end { text-align: right !important; }
                    .text-center { text-align: center !important; }
                    .text-start { text-align: left !important; }
                    .text-nowrap { white-space: nowrap !important; }
                    .text-truncate { overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
                    .table {
                        width: 100% !important;
                        margin-bottom: 0.25rem !important;
                        border-collapse: collapse !important;
                    }
                    .table-dark, thead.table-dark, thead.table-dark tr, thead.table-dark th {
                        background-color: #212529 !important;
                        color: #ffffff !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    thead { display: table-header-group !important; }
                    tr { page-break-inside: avoid !important; }
                    .table-sm th, .table-sm td {
                        padding: ${isDense ? '2px 5px' : '3px 6px'} !important;
                        line-height: ${isDense ? '1.15' : '1.25'} !important;
                    }
                    .table th:nth-child(2), .table td:nth-child(2) {
                        white-space: nowrap !important;
                        word-break: keep-all !important;
                        width: 78px !important;
                        min-width: 72px !important;
                    }
                    .bg-light {
                        background-color: #f8f9fa !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .border {
                        border: 1px solid #dee2e6 !important;
                    }
                    .border-end {
                        border-right: 1px solid #dee2e6 !important;
                    }
                    .border-bottom {
                        border-bottom: 1px solid #dee2e6 !important;
                    }
                    .border-2 {
                        border-width: 2px !important;
                    }
                    .border-dark {
                        border-color: #212529 !important;
                    }
                    .rounded-2, .rounded-3, .rounded {
                        border-radius: 6px !important;
                    }
                    .badge {
                        display: inline-block !important;
                        padding: 3px 8px !important;
                        border-radius: 4px !important;
                        font-weight: 700 !important;
                        font-size: 0.7rem !important;
                    }
                    .badge.bg-dark {
                        background-color: #212529 !important;
                        color: #ffffff !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .text-primary { color: #0d6efd !important; }
                    .text-danger { color: #dc3545 !important; }
                    .text-success { color: #198754 !important; }
                    .text-dark { color: #212529 !important; }
                    .text-muted, .text-secondary { color: #6c757d !important; }
                    .fw-bold { font-weight: 700 !important; }
                    .text-uppercase { text-transform: uppercase !important; }
                    .font-monospace { font-family: monospace !important; }
                    .ps-2 { padding-left: 0.5rem !important; }
                    .pe-2 { padding-right: 0.5rem !important; }
                    .p-2 { padding: 0.5rem !important; }
                    .mb-0 { margin-bottom: 0 !important; }
                    .mb-1 { margin-bottom: 0.25rem !important; }
                    .mb-2 { margin-bottom: 0.5rem !important; }
                    .mt-1 { margin-top: 0.25rem !important; }
                    .mt-2 { margin-top: 0.5rem !important; }
                    .pt-2 { padding-top: 0.5rem !important; }
                    .bg-success-subtle {
                        background-color: #d1e7dd !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .border-success-subtle {
                        border-color: #a3cfbb !important;
                    }
                    .border-start {
                        border-left: 1px solid #cbd5e1 !important;
                    }
                    .seal-block {
                        background-color: #f8fafc !important;
                        border: 1px solid #cbd5e1 !important;
                        border-radius: 6px !important;
                        page-break-inside: avoid !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    #printPoQrCode {
                        width: 62px !important;
                        height: 62px !important;
                        object-fit: contain !important;
                    }
                </style>
            </head>
            <body>
                <div class="print-wrapper ${isDense ? 'dense-layout' : ''}">
                    ${printContent}
                </div>
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.focus();
                            window.print();
                        }, 350);
                    };
                    window.onafterprint = function() {
                        setTimeout(function() {
                            window.close();
                        }, 200);
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    };

    window.directPrintPo = async function (poId) {
        await window.openPoPrintModal(poId);
        setTimeout(function () {
            if (typeof window.printPoDocument === 'function') {
                window.printPoDocument();
            }
        }, 500);
    };

    // Interactive KPI Filter Tiles & Multi-Criteria Table Filtering
    let currentPoTileFilter = 'all';
    const poFilterTiles = document.querySelectorAll('.po-filter-tile');

    // Multi-Criteria Table Filtering (Search, Officer, Supplier, Project, Status, Urgency, Date, Tile)
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
        const currentUserId = '<?= (string) $_SESSION['user_id'] ?>';

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

            // KPI Stat Tile Filter
            let matchesTileStatus = true;
            if (currentPoTileFilter === 'pending') {
                matchesTileStatus = ['Generated', 'Viber Order Sent', 'Out for Delivery', 'Pending Delivery', 'Partially Delivered', 'Partially Received'].includes(rowStatus);
            } else if (currentPoTileFilter === 'delayed') {
                matchesTileStatus = rowStatus.includes('Delayed');
            }

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
            } else if (statusVal === 'Partially Delivered' || statusVal === 'Partially Received') {
                matchesStatus = (rowStatus === 'Partially Delivered' || rowStatus === 'Partially Received');
            } else if (statusVal !== 'all') {
                matchesStatus = (rowStatus === statusVal);
            }

            const matchesUrgency = (urgencyVal === 'all') || (rowUrgency === urgencyVal);
            const matchesDate = !dateVal || (rowDate === dateVal);

            if (matchesSearch && matchesTileStatus && matchesCreator && matchesSupplier && matchesProject && matchesStatus && matchesUrgency && matchesDate) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Active Filter Badge Count Update
        let activeFilterCount = 0;
        if (currentPoTileFilter !== 'all') activeFilterCount++;
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

        currentPoTileFilter = 'all';
        poFilterTiles.forEach(t => {
            if ((t.getAttribute('data-filter') || 'all') === 'all') {
                t.classList.add('active-filter');
            } else {
                t.classList.remove('active-filter');
            }
        });

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

        if (searchPo) {
            searchPo.onkeyup = window.filterPoTable;
            searchPo.addEventListener('input', window.filterPoTable);
        }
        if (filterCreator) filterCreator.onchange = window.filterPoTable;
        if (filterSupplier) filterSupplier.onchange = window.filterPoTable;
        if (filterProject) filterProject.onchange = window.filterPoTable;
        if (filterStatus) filterStatus.onchange = window.filterPoTable;
        if (filterEtaUrgency) filterEtaUrgency.onchange = window.filterPoTable;
        if (filterDate) filterDate.onchange = window.filterPoTable;

        // KPI Stat Filter Tiles Click & Keyboard listeners
        poFilterTiles.forEach(tile => {
            tile.addEventListener('click', function () {
                const targetFilter = this.getAttribute('data-filter') || 'all';

                // Toggle behavior: clicking already active filter resets to 'all'
                if (currentPoTileFilter === targetFilter && targetFilter !== 'all') {
                    currentPoTileFilter = 'all';
                } else {
                    currentPoTileFilter = targetFilter;
                }

                // Sync active styling
                poFilterTiles.forEach(t => {
                    const f = t.getAttribute('data-filter') || 'all';
                    if (f === currentPoTileFilter) {
                        t.classList.add('active-filter');
                    } else {
                        t.classList.remove('active-filter');
                    }
                });

                window.filterPoTable();
            });

            // Accessibility: Keyboard Enter / Space support
            tile.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    };
    window.initPoSearch();

    // ==========================================
    // VIBER PHONE NORMALIZER & MODAL LOGIC
    // ==========================================
    window.normalizeViberPhoneClient = function(phone) {
        if (!phone) return null;
        const digits = phone.replace(/[^0-9]/g, '');
        if (digits.startsWith('09') && digits.length === 11) {
            return '+63' + digits.substring(1);
        }
        if (digits.startsWith('639') && digits.length === 12) {
            return '+' + digits;
        }
        if (digits.startsWith('9') && digits.length === 10) {
            return '+63' + digits;
        }
        return null;
    };

    window.updatePoViberPhoneFeedback = function() {
        const input = document.getElementById('viberPhone');
        const badge = document.getElementById('poViberBadge');
        const feedback = document.getElementById('viberPhoneFeedback');
        if (!input || !badge || !feedback) return;

        const val = input.value.trim();
        if (!val) {
            badge.classList.add('d-none');
            feedback.className = 'small mt-1 text-muted';
            feedback.innerHTML = '<i class="bi bi-info-circle me-1"></i>Accepts 09XX or +639XX format for direct Viber messaging.';
            return;
        }

        const norm = window.normalizeViberPhoneClient(val);
        if (norm) {
            badge.classList.remove('d-none');
            feedback.className = 'small mt-1 text-success fw-semibold';
            feedback.innerHTML = '<i class="fa-brands fa-viber me-1" style="color: #7360f2;"></i>Viber Direct Link Ready: ' + norm;
        } else {
            badge.classList.add('d-none');
            feedback.className = 'small mt-1 text-warning fw-semibold';
            feedback.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Please enter a valid mobile number (e.g. 0917-123-4567 or +63 917 123 4567).';
        }
    };

    // Make sure openViberPreviewModal is attached to window
    window.openViberPreviewModal = async function (poId, poNo, supplierId, phone) {
        document.getElementById('viberPoId').value = poId;
        document.getElementById('viberPoNo').value = poNo;
        document.getElementById('viberPhone').value = phone || '';

        const supplierSelect = document.getElementById('viberSupplierSelect');
        if (supplierSelect) {
            supplierSelect.value = supplierId;
        }

        window.updatePoViberPhoneFeedback();

        const tbody = document.getElementById('viberItemsBody');
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div> Loading items...</td></tr>';
        document.getElementById('viberMessageText').value = 'Loading Viber message template...';

        var myModalEl = document.getElementById('viberPreviewModal');
        var viberModal = bootstrap.Modal.getInstance(myModalEl);
        if (!viberModal) viberModal = new bootstrap.Modal(myModalEl);
        viberModal.show();

        let formData = new FormData();
        formData.append('action', 'fetch_po_viber_preview');
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
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2">No items found.</td></tr>';
                } else {
                    data.items.forEach(item => {
                        const tr = document.createElement('tr');
                        const unit = item.unit || 'pcs';
                        const cat = item.category || 'General';
                        tr.innerHTML = `
                            <td class="fw-bold text-dark text-wrap">${item.item_name}</td>
                            <td class="text-muted"><span class="badge bg-light text-dark border">${cat}</span></td>
                            <td class="text-center fw-bold text-danger">${item.quantity} ${unit}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                const msg = `Genetian Builders Construction PO: ${poNo}\nItems to purchase:\n${data.item_list}\nIf you have any concerns or clarifications text or email here`;
                document.getElementById('viberMessageText').value = msg;
            } else {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-2">Failed to load items.</td></tr>';
                document.getElementById('viberMessageText').value = 'Error loading items template.';
            }
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-2">Network error.</td></tr>';
            document.getElementById('viberMessageText').value = 'Network error loading template.';
        }
    };

    // Handle Send via Viber
    window.triggerViberPoSend = async function () {
        const sendBtn = document.getElementById('sendViberSubmitBtn');
        const originalBtnHtml = sendBtn ? sendBtn.innerHTML : '<i class="fa-brands fa-viber me-1"></i> Send via Viber';

        const poId = document.getElementById('viberPoId').value;
        const poNo = document.getElementById('viberPoNo').value;
        const rawPhone = document.getElementById('viberPhone').value || '';
        const supplierId = document.getElementById('viberSupplierSelect').value;
        const message = document.getElementById('viberMessageText').value || '';

        if (!rawPhone || !message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Details',
                    text: 'Please enter a valid recipient phone number and message content.',
                    confirmButtonColor: '#7360f2'
                });
            } else {
                alert("Please enter a valid phone number and message content.");
            }
            return;
        }

        // Validate and normalize Philippine phone number
        const cleanPhone = window.normalizeViberPhoneClient(rawPhone);
        if (!cleanPhone) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Contact Number',
                    text: 'Please enter a valid Philippine mobile number starting with 09 or +63 (e.g., 0917-123-4567 or +63 917 123 4567) so Viber can open the chat directly.',
                    confirmButtonColor: '#7360f2'
                });
            } else {
                alert("Please enter a valid Philippine mobile number starting with 09 or +63.");
            }
            document.getElementById('viberPhone').focus();
            return;
        }

        // Prevent double submit and display loading spinner
        if (sendBtn) {
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...';
        }

        try {
            await navigator.clipboard.writeText(message);
        } catch (err) {
            const tempArea = document.createElement('textarea');
            tempArea.value = message;
            document.body.appendChild(tempArea);
            tempArea.select();
            document.execCommand('copy');
            document.body.removeChild(tempArea);
        }

        const formData = new FormData();
        formData.append('action', 'log_viber_order_sent');
        formData.append('po_id', poId);
        formData.append('po_no', poNo);
        formData.append('supplier_id', supplierId);
        formData.append('contact_number', cleanPhone);
        formData.append('message', message);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {};
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        try {
            await fetch('process/process.php', { method: 'POST', body: formData, headers: headers });
        } catch (e) {
            console.error('Error logging Viber order send:', e);
        } finally {
            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalBtnHtml;
            }
        }

        const statusBadge = document.getElementById('status_' + poId);
        if (statusBadge) {
            statusBadge.className = 'badge bg-viber text-white px-3 py-2 shadow-sm text-uppercase';
            statusBadge.innerText = 'Viber Order Sent';
        }

        const myModalEl = document.getElementById('viberPreviewModal');
        const viberModal = bootstrap.Modal.getInstance(myModalEl);
        if (viberModal) viberModal.hide();

        if (typeof Swal !== 'undefined') {
            await Swal.fire({
                icon: 'success',
                title: 'Order Copied to Clipboard!',
                html: `Opening Viber Desktop for <strong>${cleanPhone}</strong>...<br><br><small class="text-muted"><i class="bi bi-keyboard me-1"></i>Press <strong>Ctrl + V</strong> inside Viber to paste your order.</small>`,
                timer: 2800,
                showConfirmButton: false
            });
        } else {
            alert("PO details copied to clipboard!\nOpening Viber Desktop for " + cleanPhone + "...\n\nPress Ctrl + V in Viber to paste your order.");
        }

        window.location.href = "viber://chat?number=" + encodeURIComponent(cleanPhone);
    };

    // ==========================================
    // MARK PURCHASE ORDER OUT FOR DELIVERY
    // ==========================================
    window.markPoOutForDelivery = async function (poId, poNo) {
        let deliveryNotes = '';

        if (typeof Swal !== 'undefined') {
            const { value: notes, isConfirmed } = await Swal.fire({
                title: 'Mark Out for Delivery?',
                html: `Confirm that items for <strong>${poNo}</strong> have departed the supplier and are en route to the warehouse/jobsite.`,
                icon: 'question',
                input: 'text',
                inputPlaceholder: 'Courier / Driver / Vehicle Plate # (Optional)',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-truck me-1"></i> Yes, Mark Out for Delivery',
                cancelButtonText: 'Cancel'
            });

            if (!isConfirmed) return;
            deliveryNotes = (notes || '').trim();
        } else {
            if (!confirm(`Mark Purchase Order ${poNo} as Out for Delivery?`)) return;
            deliveryNotes = (prompt("Courier / Driver / Vehicle Plate # (Optional):") || '').trim();
        }

        const formData = new FormData();
        formData.append('action', 'mark_po_out_for_delivery');
        formData.append('po_id', poId);
        formData.append('delivery_notes', deliveryNotes);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {};
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

        try {
            const resp = await fetch('process/process.php', { method: 'POST', body: formData, headers: headers });
            const data = await resp.json();

            if (data.status === 'success') {
                // Update status badge dynamically in table
                const statusBadge = document.getElementById('status_' + poId);
                if (statusBadge) {
                    statusBadge.className = 'badge bg-primary text-white px-3 py-2 shadow-sm text-uppercase';
                    statusBadge.innerHTML = '<i class="bi bi-truck me-1"></i> Out for Delivery';
                }

                // Update data-status attribute on the parent table row for dynamic filtering
                const row = document.querySelector(`.po-row:has(#status_${poId})`);
                if (row) {
                    row.setAttribute('data-status', 'Out for Delivery');
                }

                // Morph primary action button to Receive
                const primaryBtn = document.querySelector(`.primary-action-btn-${poId}`);
                if (primaryBtn) {
                    primaryBtn.className = 'btn btn-sm btn-success fw-bold shadow-sm primary-action-btn-' + poId;
                    primaryBtn.title = 'Receive Order & Ingest Inventory';
                    primaryBtn.setAttribute('onclick', `openReceiveModal(${poId}, '${poNo}')`);
                    primaryBtn.innerHTML = '<i class="bi bi-box-arrow-in-down"></i> <span class="ms-1">Receive</span>';
                }

                // Remove Out for Delivery action items from dropdown
                document.querySelectorAll(`.out-for-delivery-item-${poId}`).forEach(el => {
                    el.remove();
                });

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Shipment En Route',
                        text: data.message || `PO ${poNo} is now Out for Delivery!`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    alert(data.message || `PO ${poNo} is now Out for Delivery!`);
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Update Failed', text: data.message || 'Could not update status.' });
                } else {
                    alert(data.message || 'Could not update status.');
                }
            }
        } catch (err) {
            console.error('Error marking PO out for delivery:', err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to communicate with server.' });
            } else {
                alert('Network error: Could not reach server.');
            }
        }
    };

    // ==========================================
    // UPLOAD / ATTACH RECEIPT AJAX SUBMISSION
    // ==========================================
    window.handleUploadReceiptSubmit = async function (e) {
        if (e && e.preventDefault) e.preventDefault();

        const form = document.getElementById('uploadReceiptForm');
        if (!form) return;

        const fileInput = document.getElementById('uploadReceiptFileInput');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'File Required', text: 'Please select a receipt document (PDF, JPG, PNG).' });
            } else {
                alert('Please select a receipt document.');
            }
            return;
        }

        const poId = document.getElementById('uploadReceiptPoId').value;
        const poNo = document.getElementById('uploadReceiptPoNoDisplay').value;
        const submitBtn = document.getElementById('confirmUploadReceiptBtn');
        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

        let uploadFile = fileInput.files[0];
        if (uploadFile && uploadFile.type && uploadFile.type.startsWith('image/') && typeof window.compressImageFile === 'function') {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Optimizing Photo...';
            }
            try {
                uploadFile = await window.compressImageFile(uploadFile, { maxDimension: 1920, quality: 0.82 });
            } catch (compErr) {
                console.warn('[CIMS] Pre-upload compression bypassed:', compErr);
            }
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Uploading Receipt...';
        }

        const formData = new FormData(form);
        formData.set('proof_of_receipt', uploadFile);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {};
        if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

        try {
            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: headers
            });
            const result = await response.json();

            if (result.status === 'success') {
                const modalEl = document.getElementById('uploadReceiptModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                // Dynamically update the dropdown menu items in the table row
                const receiptWrap = document.getElementById('receiptViewItemWrap_' + poId);
                const receiptLink = document.getElementById('receiptViewLink_' + poId);
                if (receiptWrap && receiptLink && result.secure_receipt_url) {
                    receiptLink.setAttribute('href', result.secure_receipt_url);
                    receiptWrap.classList.remove('d-none');
                }

                const menuText = document.getElementById('uploadReceiptMenuText_' + poId);
                if (menuText) {
                    menuText.innerText = 'Update / Replace Receipt';
                }

                // If PO details modal is currently open or loaded, refresh it and switch to Receipt tab
                if (window.currentPoModalData && window.currentPoModalData.id == poId) {
                    openPoPrintModal(poId);
                    setTimeout(() => {
                        const receiptTabBtn = document.getElementById('poTabReceiptBtn');
                        if (receiptTabBtn && typeof bootstrap !== 'undefined') {
                            const tab = bootstrap.Tab.getInstance(receiptTabBtn) || new bootstrap.Tab(receiptTabBtn);
                            tab.show();
                        }
                    }, 400);
                }

                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Receipt Attached',
                        text: result.message || 'Delivery receipt has been attached successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    alert(result.message || 'Delivery receipt has been attached successfully.');
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        text: result.message || 'Could not attach receipt document.'
                    });
                } else {
                    alert(result.message || 'Could not attach receipt document.');
                }
            }
        } catch (err) {
            console.error('Error uploading receipt:', err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'An unexpected network error occurred while uploading the receipt. Please try again.'
                });
            } else {
                alert('Network error: Could not connect to server.');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    };

    // ==========================================
    // CANCEL / VOID PURCHASE ORDER AJAX SUBMISSION
    // ==========================================
    window.handleCancelPoSubmit = async function (e) {
        if (e && e.preventDefault) e.preventDefault();

        const form = document.getElementById('cancelPoForm');
        if (!form) return;

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const poId = document.getElementById('cancelPoId').value;
        const poNo = document.getElementById('cancelPoNoDisplay').value;
        const reason = document.getElementById('cancelPoReason').value;
        const notes = document.getElementById('cancelPoNotes').value;
        const submitBtn = document.getElementById('confirmCancelPoBtn');
        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

        if (!reason) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Reason Required',
                    text: 'Please select a reason for voiding this Purchase Order.'
                });
            } else {
                alert('Please select a reason for voiding this Purchase Order.');
            }
            return;
        }

        // Confirmation dialog before taking destructive action
        let confirmResult = false;
        if (typeof Swal !== 'undefined') {
            const res = await Swal.fire({
                title: 'Void this Purchase Order?',
                html: `Are you sure you want to void <strong>${poNo}</strong>?<br><br><span class="text-danger small"><i class="bi bi-info-circle me-1"></i>Linked Requisition will be restored to Approved status for re-issuing.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-slash-circle me-1"></i> Yes, Void Order',
                cancelButtonText: 'Keep Order Active'
            });
            confirmResult = res.isConfirmed;
        } else {
            confirmResult = confirm(`Are you sure you want to void Purchase Order ${poNo}? Linked Requisition will be restored to Approved.`);
        }

        if (!confirmResult) return;

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Voiding Order...';
        }

        const formData = new FormData(form);
        formData.append('action', 'cancel_po');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {};
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        try {
            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: headers
            });
            const result = await response.json();

            if (result.status === 'success') {
                const modalEl = document.getElementById('cancelPoModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Purchase Order Voided',
                        text: result.message || 'Purchase Order voided successfully.',
                        confirmButtonColor: '#0033cc'
                    });
                } else {
                    alert(result.message || 'Purchase Order voided successfully.');
                }
                window.location.reload();
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Cancellation Failed',
                        text: result.message || 'Could not cancel Purchase Order.',
                        confirmButtonColor: '#dc3545'
                    });
                } else {
                    alert(result.message || 'Could not cancel Purchase Order.');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
            }
        } catch (err) {
            console.error('Error voiding PO:', err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'An unexpected network error occurred while voiding the Purchase Order. Please try again.',
                    confirmButtonColor: '#dc3545'
                });
            } else {
                alert('An unexpected network error occurred. Please try again.');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    };

    window.initPoModalEvents = function () {
        const viberSupplierSelect = document.getElementById('viberSupplierSelect');
        if (viberSupplierSelect) {
            viberSupplierSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const phone = selectedOption.getAttribute('data-phone');
                document.getElementById('viberPhone').value = phone || '';
                if (typeof window.updatePoViberPhoneFeedback === 'function') {
                    window.updatePoViberPhoneFeedback();
                }
            });
        }

        const viberPhoneInput = document.getElementById('viberPhone');
        if (viberPhoneInput) {
            viberPhoneInput.addEventListener('input', function() {
                if (typeof window.updatePoViberPhoneFeedback === 'function') {
                    window.updatePoViberPhoneFeedback();
                }
            });
            viberPhoneInput.addEventListener('blur', function() {
                if (typeof window.updatePoViberPhoneFeedback === 'function') {
                    window.updatePoViberPhoneFeedback();
                }
            });
        }

        const viberModalEl = document.getElementById('viberPreviewModal');
        if (viberModalEl) {
            viberModalEl.addEventListener('hidden.bs.modal', function () {
                if (typeof window.updatePoViberPhoneFeedback === 'function') {
                    window.updatePoViberPhoneFeedback();
                }
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'new') {
            const poModalEl = document.getElementById('poModal');
            if (poModalEl) {
                new bootstrap.Modal(poModalEl).show();
            }
        }
    };
    window.initPoModalEvents();
</script>

<?php include 'layout/footer.php'; ?>