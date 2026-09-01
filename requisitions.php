<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'Connection/db.php';

$role = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

// Fetch Inventory items for the "Add Item" dropdown
$itemStmt = $pdo->query("SELECT item_code, item_name, unit, quantity, category FROM inventory WHERE status != 'Out of Stock' ORDER BY item_name ASC");
$inventoryItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Projects
$activeProjects = $pdo->query("SELECT project_name FROM projects WHERE status = 'active' ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Categories & Units for New Item creation
$categories = $pdo->query("SELECT category_name FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$units = $pdo->query("SELECT unit_name FROM units ORDER BY unit_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ========================================================
// ROLE-BASED DATA FETCHING
// ========================================================
if ($role === 'requestor') {
    $stmt = $pdo->prepare("SELECT * FROM requisitions WHERE requestor_id = ? AND type = 'project' ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statTitle = "My Project ";
    $rsRequestorsList = [];
    $rsProjectsList = array_values(array_unique(array_filter(array_column($requisitions, 'project_name'))));
    sort($rsProjectsList);
} elseif ($role === 'purchasing') {
    $stmt = $pdo->query("SELECT * FROM requisitions WHERE type = 'restock' ORDER BY created_at DESC");
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statTitle = "Restock ";
    $rsRequestorsList = [];
    $rsProjectsList = ['Warehouse Restock'];
} else {
    $stmt = $pdo->query("SELECT * FROM requisitions ORDER BY created_at DESC");
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statTitle = "";
    $rsRequestorsList = $pdo->query("SELECT DISTINCT requestor_id, requestor_name FROM requisitions WHERE requestor_name IS NOT NULL AND requestor_name != '' ORDER BY requestor_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $rsProjectsList = $pdo->query("SELECT DISTINCT project_name FROM requisitions WHERE project_name IS NOT NULL AND project_name != '' AND project_name != 'Warehouse Restock' ORDER BY project_name ASC")->fetchAll(PDO::FETCH_COLUMN);
}

$itemsQuery = $pdo->query("SELECT ri.id as item_id, ri.requisition_id, ri.quantity, ri.item_code, COALESCE(i.item_name, ri.new_item_name, ri.item_code) as item_name, COALESCE(i.unit, ri.new_unit, 'pcs') as unit, ri.is_new_item, ri.new_category, i.quantity as current_stock,
                                  COALESCE(p.total_pending, 0) as total_pending,
                                  p.pending_details,
                                  ri.item_status,
                                  ri.item_remarks,
                                  ri.item_notes
                           FROM requisition_items ri 
                           LEFT JOIN inventory i ON ri.item_code = i.item_code
                           LEFT JOIN (
                               SELECT ri2.item_code, SUM(ri2.quantity) as total_pending,
                                      GROUP_CONCAT(CONCAT(r2.project_name, ' [', ri2.quantity, 'x by ', r2.requestor_name, ']') SEPARATOR '; ') as pending_details
                               FROM requisition_items ri2
                               JOIN requisitions r2 ON ri2.requisition_id = r2.id
                               WHERE r2.status = 'Pending Approval'
                               GROUP BY ri2.item_code
                           ) p ON ri.item_code = p.item_code");
$allItems = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
$rsItemsGrouped = [];
foreach ($allItems as $item) {
    $rsItemsGrouped[$item['requisition_id']][] = $item;
}

$totalRS = count($requisitions);
$pendingRS = count(array_filter($requisitions, fn($r) => $r['status'] === 'Pending Approval'));
$approvedRS = count(array_filter($requisitions, fn($r) => in_array($r['status'], ['Approved', 'Partially Approved', 'PO Created', 'Staged (Ready for Pickup)'])));
$stagedRS = count(array_filter($requisitions, fn($r) => $r['status'] === 'Staged (Ready for Pickup)'));

include 'layout/header.php';
?>

<style>
    @media (max-width: 767.98px) {

        /* FIXED: Added .rs-table-wrapper class so this CSS DOES NOT break the Modal's table! */
        .rs-table-wrapper {
            overflow-x: hidden !important;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
            padding: 0 !important;
        }

        #rsTable {
            display: block !important;
            width: 100% !important;
            white-space: normal !important;
            background: transparent !important;
            border: none !important;
        }

        #rsTable thead {
            display: none !important;
        }

        #rsTable tbody {
            display: block !important;
            width: 100% !important;
        }

        #rsTable tbody tr {
            display: flex !important;
            flex-direction: column !important;
            border: 1px solid #e0e4e8;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #fff;
            padding: 14px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        #rsTable tbody tr.d-none,
        #rsTable tbody tr.rs-row-hidden,
        #rsTable tbody tr[style*="display: none"],
        #rsTable tbody tr[style*="display:none"] {
            display: none !important;
        }

        #rsTable tbody td {
            display: flex !important;
            justify-content: space-between;
            align-items: center;
            text-align: right;
            padding: 10px 4px;
            border: none !important;
            border-bottom: 1px dashed #e9ecef !important;
            white-space: normal !important;
            word-break: break-word;
            width: 100% !important;
            box-sizing: border-box;
        }

        #rsTable tbody td.d-none {
            display: none !important;
        }

        /* Actions footer row */
        #rsTable tbody td:last-child {
            border-bottom: none !important;
            justify-content: center !important;
            gap: 8px;
            padding-top: 14px;
            margin-top: 4px;
            flex-wrap: wrap;
        }

        /* Fix inline forms in the actions td */
        #rsTable tbody td:last-child form.d-inline {
            display: inline !important;
        }

        #rsTable tbody td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            text-align: left;
            padding-right: 15px;
            flex-shrink: 0;
        }

        #rsTable tbody td:last-child::before {
            display: none;
        }
    }
    
    /* Touch-friendly details dropdown styles for PWA */
    summary {
        list-style: none;
    }
    summary::-webkit-details-marker {
        display: none;
    }
    .border-bottom-dashed {
        border-bottom: 1px dashed #dee2e6;
    }
    .border-bottom-dashed:last-child {
        border-bottom: none !important;
        margin-bottom: 0 !important;
        padding-bottom: 0 !important;
    }

    /* Mobile action bar: stack search full-width, then buttons in a neat row */
    @media (max-width: 767.98px) {
        .rs-action-bar {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 10px !important;
        }
        .rs-action-bar .input-group {
            max-width: 100% !important;
            min-width: 0 !important;
            width: 100% !important;
        }
        .rs-action-bar .rs-btn-group {
            display: flex !important;
            gap: 8px !important;
            width: 100% !important;
        }
        .rs-action-bar .rs-btn-group > div,
        .rs-action-bar .rs-btn-group > .dropdown {
            flex: 1 1 0 !important;
        }
        .rs-action-bar .rs-btn-group .btn {
            width: 100% !important;
            font-size: 0.82rem !important;
            padding: 0.45rem 0.5rem !important;

        }
    }

    /* View RS Modal — Premium UI & Table Styling */
    #viewRsModal .modal-content {
        border-radius: 12px;
        overflow: hidden;
    }
    #viewRsModal .doc-summary-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1.1rem 1.25rem;
    }
    #viewRsModal .table-container-custom {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        background: #ffffff;
    }
    #viewRsModal .table {
        margin-bottom: 0;
    }
    #viewRsModal .table thead {
        background-color: #f8fafc !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }
    #viewRsModal .table thead th {
        background-color: #f8fafc !important;
        color: #475569 !important;
        font-size: 0.72rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        border-bottom: 1px solid #e2e8f0 !important;
        padding: 0.75rem 0.85rem !important;
    }
    #viewRsModal #viewRsItemsBody td {
        vertical-align: middle !important;
        padding: 0.75rem 0.85rem !important;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.88rem;
    }
    #viewRsModal #viewRsItemsBody tr:last-child td {
        border-bottom: none;
    }
    #viewRsModal #viewRsItemsBody tr:hover td {
        background-color: #f8fafc;
    }
    #viewRsModal .item-code-badge {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.76rem;
        background-color: #f8fafc;
        color: #334155;
        border: 1px solid #e2e8f0;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        display: inline-block;
        font-weight: 600;
    }
    #viewRsModal .item-remark-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background-color: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
        border-radius: 6px;
        padding: 0.25rem 0.55rem;
        font-size: 0.72rem;
        font-weight: 600;
        margin-top: 0.35rem;
        max-width: 200px;
        line-height: 1.3;
        text-align: left;
    }
    #viewRsModal .remarks-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        font-size: 0.85rem;
        color: #475569;
        line-height: 1.5;
    }
    /* Interactive KPI Filter Tiles */
    .rs-filter-tile {
        cursor: pointer;
        transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s ease, border-color 0.18s ease;
        user-select: none;
        touch-action: manipulation;
        -webkit-tap-highlight-color: transparent;
        position: relative;
    }
    .rs-filter-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08) !important;
    }
    .rs-filter-tile:active {
        transform: scale(0.98);
    }
    .rs-filter-tile.active-filter {
        box-shadow: 0 0 0 2px var(--gb-blue, #0033CC), 0 8px 20px rgba(0, 51, 204, 0.12) !important;
        background-color: #f8fafc !important;
    }
    .rs-filter-tile[data-filter="pending"].active-filter {
        box-shadow: 0 0 0 2px var(--gb-yellow, #ffc107), 0 8px 20px rgba(255, 193, 7, 0.2) !important;
    }
    .rs-filter-tile[data-filter="approved"].active-filter {
        box-shadow: 0 0 0 2px #198754, 0 8px 20px rgba(25, 135, 84, 0.2) !important;
    }

    #rsTable tbody tr.rs-empty-row {
        background: transparent !important;
        box-shadow: none !important;
        border: none !important;
    }
    #rsTable tbody tr.rs-empty-row td {
        border-bottom: none !important;
    }

    /* Material Row Item Cards & Polish (HCI Usability) */
    .material-row {
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .material-row .remove-row:disabled {
        opacity: 0.45 !important;
        cursor: not-allowed !important;
        pointer-events: auto !important;
        border-color: #cbd5e1 !important;
        color: #94a3b8 !important;
        background-color: transparent !important;
    }
    .material-row .remove-row:not(:disabled):hover {
        background-color: #dc3545 !important;
        color: #ffffff !important;
    }

    /* Modal Form Scroll Fix: Flex chain for modal-dialog-scrollable with <form> wrapper */
    .modal-dialog-scrollable .modal-content {
        display: flex !important;
        flex-direction: column !important;
        max-height: 100% !important;
        height: 100% !important;
        overflow: hidden !important;
    }
    .modal-dialog-scrollable .modal-content > form {
        display: flex !important;
        flex-direction: column !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
        max-height: 100% !important;
        height: 100% !important;
        overflow: hidden !important;
    }
    .modal-dialog-scrollable .modal-body {
        overflow-y: auto !important;
        overflow-x: hidden !important;
        -webkit-overflow-scrolling: touch !important;
        touch-action: pan-y !important;
        overscroll-behavior-y: contain !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
    }
    .modal-dialog-scrollable .modal-header,
    .modal-dialog-scrollable .modal-footer {
        flex-shrink: 0 !important;
    }

    /* Mobile & Multi-Device Touch & Layout Optimization */
    @media (max-width: 575.98px) {
        .modal-dialog {
            margin: 0.5rem !important;
            max-width: calc(100% - 1rem) !important;
        }
        .modal-dialog-scrollable {
            height: calc(100dvh - 1rem) !important;
            max-height: calc(100dvh - 1rem) !important;
            margin: 0.5rem auto !important;
        }
        .modal-content {
            border-radius: 14px !important;
            height: 100% !important;
            max-height: 100% !important;
        }
        .modal-body {
            padding: 1rem !important;
        }
        #viewRsModal .doc-summary-card {
            padding: 0.85rem !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 12px !important;
        }
        #viewRsModal #rsQrContainer {
            margin-left: 0 !important;
            align-self: center !important;
            padding-top: 8px;
            border-top: 1px dashed #e2e8f0;
            width: 100%;
        }
        #viewRsModal .modal-footer,
        #rsModal .modal-footer,
        #restockModal .modal-footer,
        #editRsModal .modal-footer,
        #approveItemsModal .modal-footer {
            flex-direction: column-reverse !important;
            gap: 8px !important;
            padding: 0.85rem 1rem !important;
        }
        #viewRsModal .modal-footer button,
        #rsModal .modal-footer button,
        #restockModal .modal-footer button,
        #editRsModal .modal-footer button,
        #approveItemsModal .modal-footer button,
        #approveItemsModal .modal-footer > div {
            width: 100% !important;
            margin: 0 !important;
            justify-content: center !important;
        }
        .material-row {
            padding: 0.85rem !important;
        }
        .material-row .d-flex.justify-content-end {
            width: 100% !important;
        }
        .material-row .remove-row {
            min-height: 40px !important;
            width: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .modal .card-header .btn {
            width: 100% !important;
            margin-top: 4px;
        }
        .modal .card-header > div:last-child {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        /* Touch friendly minimum heights */
        .btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-sm {
            min-height: 36px;
            padding: 0.4rem 0.75rem;
        }
        .form-control, .form-select {
            min-height: 42px;
            font-size: 0.92rem;
        }
        .form-control-sm, .form-select-sm {
            min-height: 36px;
            font-size: 0.85rem;
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

    <div class="row mb-4 g-3">
        <div class="col-12 col-md-4">
            <div class="card stat-card rs-filter-tile active-filter bg-white h-100 p-3 shadow-sm border-0" data-filter="all" role="button" tabindex="0" title="Click to view all requisitions" style="border-left: 4px solid var(--gb-blue) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;"><?= $statTitle ?>Total Requisitions</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $totalRS ?></h3>
                    </div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue) !important;"><i class="bi bi-file-earmark-text"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card rs-filter-tile bg-white h-100 p-3 shadow-sm border-0" data-filter="pending" role="button" tabindex="0" title="Click to filter Pending Approval" style="border-left: 4px solid var(--gb-yellow) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;"><?= $statTitle ?>Pending Approval</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $pendingRS ?></h3>
                    </div>
                    <div class="fs-1 text-warning"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card rs-filter-tile bg-white h-100 p-3 shadow-sm border-0" data-filter="approved" role="button" tabindex="0" title="Click to filter Approved & Ready" style="border-left: 4px solid #198754 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;"><?= $statTitle ?>Approved (Ready)</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $approvedRS ?></h3>
                    </div>
                    <div class="fs-1 text-success"><i class="bi bi-check2-all"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-xl-4 text-center text-xl-start">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-ui-checks me-2 text-primary"></i>Requisition Slips</h4>
            </div>

            <div class="col-12 col-xl-8">
                <div class="d-flex flex-wrap justify-content-start justify-content-xl-end align-items-center gap-2 w-100 rs-action-bar">

                    <div class="input-group shadow-sm flex-grow-1 flex-md-grow-0" style="max-width: 300px; min-width: 200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchRs" class="form-control border-start-0 ps-0 bg-white" placeholder="Search RS No or Project...">
                    </div>

                    <div class="d-flex flex-wrap gap-2 rs-btn-group">
                        <!-- Advanced Filter Toggle Button (Same as PO) -->
                        <button class="btn btn-outline-secondary btn-sm fw-bold shadow-sm d-flex align-items-center gap-1"
                            type="button" data-bs-toggle="collapse" data-bs-target="#rsFilterCollapse" aria-expanded="false"
                            aria-controls="rsFilterCollapse">
                            <i class="bi bi-funnel-fill text-primary"></i>
                            <span>Filter</span>
                            <span class="badge bg-primary rounded-pill ms-1 d-none" id="activeRsFilterBadge">0</span>
                        </button>

                        <!-- Column Visibility Dropdown -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary fw-bold shadow-sm px-2.5" type="button" id="columnToggleBtn" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside" title="Toggle Columns">
                                <i class="bi bi-layout-three-columns"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-md-end shadow-lg border-0 p-2" aria-labelledby="columnToggleBtn" style="min-width: 220px;">
                                <li>
                                    <h6 class="dropdown-header fw-bold text-uppercase small text-muted">Columns</h6>
                                </li>
                                <li><label class="dropdown-item d-flex align-items-center rounded py-1.5 text-dark small fw-bold" style="cursor:pointer;"><input class="form-check-input col-toggle me-2 mt-0 border-secondary" type="checkbox" value="col-project" checked> Project / Purpose</label></li>
                                <li><label class="dropdown-item d-flex align-items-center rounded py-1.5 text-dark small fw-bold" style="cursor:pointer;"><input class="form-check-input col-toggle me-2 mt-0 border-secondary" type="checkbox" value="col-requestor" checked> Requested By</label></li>
                                <li><label class="dropdown-item d-flex align-items-center rounded py-1.5 text-dark small fw-bold" style="cursor:pointer;"><input class="form-check-input col-toggle me-2 mt-0 border-secondary" type="checkbox" value="col-date" checked> Date Log</label></li>
                                <li><label class="dropdown-item d-flex align-items-center rounded py-1.5 text-dark small fw-bold" style="cursor:pointer;"><input class="form-check-input col-toggle me-2 mt-0 border-secondary" type="checkbox" value="col-urgency" checked> Urgency</label></li>
                                <li><label class="dropdown-item d-flex align-items-center rounded py-1.5 text-dark small fw-bold" style="cursor:pointer;"><input class="form-check-input col-toggle me-2 mt-0 border-secondary" type="checkbox" value="col-status" checked> Status</label></li>
                            </ul>
                        </div>

                        <?php if ($role === 'requestor' || $role === 'admin'): ?>
                            <div>
                                <button class="btn btn-brand btn-sm fw-bold text-nowrap shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#rsModal">
                                    <i class="bi bi-plus-lg me-1"></i> Create RS
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if ($role === 'warehouse' || $role === 'admin'): ?>
                            <div>
                                <button class="btn btn-outline-primary btn-sm fw-bold text-nowrap shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#restockModal">
                                    <i class="bi bi-box-seam me-1"></i> Request Restock
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- Collapsible Advanced Filter Panel (Same as PO) -->
        <div class="collapse mb-3" id="rsFilterCollapse">
            <div class="card card-body bg-light border-0 shadow-sm p-3 rounded-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0 small text-uppercase"><i class="bi bi-sliders me-1 text-primary"></i> Advanced Filter Requisitions</h6>
                    <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none fw-bold p-0" onclick="window.resetAllRsFilters()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset All Filters
                    </button>
                </div>
                <div class="row g-2">
                    <?php if ($role !== 'requestor' && $role !== 'purchasing'): ?>
                        <!-- 1. Requestor Filter (Admin / Warehouse / Management only) -->
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Requested By</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-person-fill text-primary"></i></span>
                                <select id="filterRsRequestor" class="form-select bg-white fw-bold small">
                                    <option value="all">All Requestors</option>
                                    <option value="me">👤 Requested by Me</option>
                                    <?php foreach ($rsRequestorsList as $req): ?>
                                        <?php if ((int)$req['requestor_id'] !== (int)$userId): ?>
                                            <option value="<?= htmlspecialchars($req['requestor_name']) ?>"><?= htmlspecialchars($req['requestor_name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($role !== 'purchasing'): ?>
                        <!-- 2. Project / Purpose Filter -->
                        <div class="col-12 col-sm-6 <?= $role === 'requestor' ? 'col-md-3' : 'col-md-4 col-lg-3' ?>">
                            <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Project Destination</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-geo-alt-fill text-danger"></i></span>
                                <select id="filterRsProject" class="form-select bg-white fw-bold small">
                                    <option value="all"><?= $role === 'requestor' ? 'All My Projects' : 'All Destinations' ?></option>
                                    <?php if ($role !== 'requestor'): ?>
                                        <option value="Warehouse Restock">📦 Warehouse Restock</option>
                                    <?php endif; ?>
                                    <?php foreach ($rsProjectsList as $projName): ?>
                                        <option value="<?= htmlspecialchars($projName) ?>"><?= htmlspecialchars($projName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 3. Status Filter -->
                    <div class="col-12 col-sm-6 <?= $role === 'requestor' ? 'col-md-3' : ($role === 'purchasing' ? 'col-md-4' : 'col-md-4 col-lg-2') ?>">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Requisition Status</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-tag-fill text-success"></i></span>
                            <select id="filterRsStatus" class="form-select bg-white fw-bold small">
                                <option value="all">All Statuses</option>
                                <option value="Pending Approval">Pending Approval</option>
                                <option value="Approved">Approved</option>
                                <option value="Partially Approved">Partially Approved</option>
                                <option value="Staged (Ready for Pickup)">Staged (Ready for Pickup)</option>
                                <option value="PO Created">PO Created</option>
                                <option value="Released">Released</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                    </div>

                    <!-- 4. Urgency Filter -->
                    <div class="col-12 col-sm-6 <?= $role === 'requestor' ? 'col-md-3' : ($role === 'purchasing' ? 'col-md-4' : 'col-md-4 col-lg-2') ?>">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Urgency Level</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-exclamation-triangle-fill text-warning"></i></span>
                            <select id="filterRsUrgency" class="form-select bg-white fw-bold small">
                                <option value="all">All Urgency</option>
                                <option value="Normal">Normal</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                    </div>

                    <!-- 5. Date Created Filter -->
                    <div class="col-12 col-sm-6 <?= $role === 'requestor' ? 'col-md-3' : ($role === 'purchasing' ? 'col-md-4' : 'col-md-4 col-lg-2') ?>">
                        <label class="form-label fw-bold text-muted small mb-1 text-uppercase">Date Logged</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white text-muted"><i class="bi bi-calendar-event text-info"></i></span>
                            <input type="date" id="filterRsDate" class="form-control bg-white fw-bold small">
                            <button type="button" class="btn btn-outline-secondary" title="Clear Date" onclick="document.getElementById('filterRsDate').value=''; window.filterRsTable();">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FIXED: Added rs-table-wrapper class here -->
        <div class="table-responsive rs-table-wrapper border rounded shadow-sm mt-3 bg-white">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="rsTable">
                <thead class="table-dark">
                    <tr>
                        <th scope="col" class="col-rs-no py-3">RS Number</th>
                        <th scope="col" class="col-project py-3">Project / Purpose</th>
                        <th scope="col" class="col-requestor py-3">Requested By</th>
                        <th scope="col" class="col-date py-3">Date Log</th>
                        <th scope="col" class="col-urgency py-3">Urgency</th>
                        <th scope="col" class="col-status py-3">Status</th>
                        <th scope="col" class="text-end py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($requisitions) > 0): ?>
                        <?php foreach ($requisitions as $rs): ?>
                            <tr class="rs-row" 
                                data-rs-no="<?= htmlspecialchars($rs['rs_no']) ?>"
                                data-requestor-id="<?= htmlspecialchars($rs['requestor_id'] ?? '') ?>"
                                data-requestor-name="<?= htmlspecialchars($rs['requestor_name'] ?? '') ?>"
                                data-project="<?= htmlspecialchars(($rs['type'] ?? 'project') === 'restock' ? 'Warehouse Restock' : $rs['project_name']) ?>"
                                data-type="<?= htmlspecialchars($rs['type'] ?? 'project') ?>"
                                data-status="<?= htmlspecialchars($rs['status']) ?>"
                                data-urgency="<?= htmlspecialchars($rs['urgency']) ?>"
                                data-created-date="<?= date('Y-m-d', strtotime($rs['created_at'])) ?>">
                                <td class="fw-bold text-primary rs-no col-rs-no" data-label="RS Number"><?= htmlspecialchars($rs['rs_no']) ?></td>
                                <td class="fw-bold rs-project col-project text-dark" data-label="Project / Purpose">
                                    <?php if (($rs['type'] ?? 'project') === 'restock'): ?>
                                        <span class="badge bg-warning text-dark shadow-sm px-2 py-1.5"><i class="bi bi-box-seam me-1"></i> Warehouse Restock</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($rs['project_name']) ?>
                                    <?php endif; ?>
                                </td>

                                <!-- Aligned left for PC, but the td's flex layout handles right-alignment on mobile -->
                                <td class="col-requestor" data-label="Requested By">
                                    <div class="d-flex align-items-center justify-content-start">
                                        <i class="bi bi-person me-1 text-muted"></i>
                                        <span class="rs-requestor fw-bold text-dark"><?= htmlspecialchars($rs['requestor_name']) ?></span>
                                        <?php if ((int)$rs['requestor_id'] === (int)$userId): ?>
                                            <span class="badge bg-primary ms-2 px-2 py-1 shadow-sm" style="font-size: 0.65rem;">You</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="col-date" data-label="Date Log">
                                    <span class="d-block text-dark fw-semibold small">
                                        <i class="bi bi-calendar3 me-1 text-muted"></i><?= date('M d, Y', strtotime($rs['created_at'])) ?>
                                    </span>
                                    <small class="text-muted" style="font-size:0.73rem;">
                                        <i class="bi bi-clock me-1 text-primary"></i><?= date('g:i A', strtotime($rs['created_at'])) ?> 
                                        <span class="text-secondary opacity-75">(<?= time_elapsed_string($rs['created_at']) ?>)</span>
                                    </small>
                                </td>
                                <td class="col-urgency" data-label="Urgency">
                                    <?php
                                    $urgencyClass = 'bg-secondary';
                                    if ($rs['urgency'] == 'High') $urgencyClass = 'bg-warning text-dark';
                                    if ($rs['urgency'] == 'Urgent') $urgencyClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $urgencyClass ?> shadow-sm"><?= htmlspecialchars($rs['urgency']) ?></span>
                                </td>
                                <td class="col-status" data-label="Status">
                                    <?php
                                    $statusClass = 'bg-secondary';
                                    if ($rs['status'] == 'Pending Approval') $statusClass = 'bg-warning text-dark';
                                    if ($rs['status'] == 'Approved') $statusClass = 'bg-success';
                                    if ($rs['status'] == 'Partially Approved') $statusClass = 'bg-warning text-dark';
                                    if ($rs['status'] == 'Staged (Ready for Pickup)') $statusClass = 'bg-info text-dark';
                                    if ($rs['status'] == 'Rejected') $statusClass = 'bg-danger';
                                    if ($rs['status'] == 'PO Created') $statusClass = 'bg-info text-dark';
                                    if ($rs['status'] == 'Released') $statusClass = 'bg-success';
                                    ?>
                                    <span class="badge <?= $statusClass ?> shadow-sm"><?= htmlspecialchars($rs['status']) ?></span>
                                </td>
                                <td class="text-end" data-label="Actions">

                                    <?php
                                    $cleanProject = str_replace(["\r", "\n"], ["\\r", "\\n"], addslashes($rs['project_name']));
                                    $cleanRemarks = str_replace(["\r", "\n"], ["\\r", "\\n"], addslashes($rs['remarks']));
                                    $cleanRequestor = addslashes($rs['requestor_name']);
                                    $itemsB64 = base64_encode(json_encode($rsItemsGrouped[$rs['id']] ?? []));
                                    $formattedDateLog = date('M d, Y g:i A', strtotime($rs['created_at'])) . ' (' . time_elapsed_string($rs['created_at']) . ')';
                                    ?>

                                    <button class="btn btn-sm btn-outline-secondary fw-bold shadow-sm me-1" title="View Details" aria-label="View Details for <?= $rs['rs_no'] ?>"
                                        onclick="viewRsDetails('<?= $rs['rs_no'] ?>', '<?= $cleanProject ?>', '<?= $cleanRemarks ?>', '<?= $rs['status'] ?>', '<?= $cleanRequestor ?>', '<?= $formattedDateLog ?>', '<?= $itemsB64 ?>', '<?= $rs['type'] ?? 'project' ?>')">
                                        <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i> View
                                    </button>

                                    <?php if (((int)$rs['requestor_id'] === (int)$userId || in_array($role, ['admin', 'management'])) && in_array($rs['status'], ['Pending Approval', 'Rejected'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold shadow-sm me-1" title="<?= $rs['status'] === 'Rejected' ? 'Edit & Resubmit Requisition' : 'Edit Request' ?>" aria-label="<?= $rs['status'] === 'Rejected' ? 'Edit and Resubmit Requisition' : 'Edit Requisition' ?>"
                                            onclick="openEditRsModal(<?= $rs['id'] ?>, '<?= $rs['rs_no'] ?>', '<?= $cleanProject ?>', '<?= $rs['urgency'] ?>', '<?= $cleanRemarks ?>', '<?= $itemsB64 ?>', '<?= $rs['type'] ?? 'project' ?>')">
                                            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i><?= $rs['status'] === 'Rejected' ? 'Resubmit' : 'Edit' ?>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($role, ['management', 'admin']) && $rs['status'] === 'Pending Approval'): ?>
                                        <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm me-1" title="Review &amp; Approve Items" aria-label="Review and Approve Items for <?= $rs['rs_no'] ?>"
                                            onclick="openApproveItemsModal(<?= $rs['id'] ?>, '<?= $rs['rs_no'] ?>', '<?= $itemsB64 ?>')">
                                            <i class="bi bi-check2-square me-1" aria-hidden="true"></i>Review
                                        </button>

                                        <button type="button" class="btn btn-sm btn-danger shadow-sm" title="Reject RS" aria-label="Reject Requisition <?= $rs['rs_no'] ?>" onclick="openRejectModal(<?= $rs['id'] ?>, '<?= $rs['rs_no'] ?>')">
                                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php 
                                    $isRestock = (($rs['type'] ?? 'project') === 'restock' || $rs['project_name'] === 'Warehouse Restock');
                                    ?>

                                    <?php if (in_array($role, ['warehouse', 'admin']) && $rs['status'] === 'Approved' && !$isRestock): ?>
                                        <form method="POST" action="process/process.php" class="d-inline">
                                            <input type="hidden" name="action" value="stage_rs_materials">
                                            <input type="hidden" name="rs_id" value="<?= $rs['id'] ?>">
                                            <button class="btn btn-sm btn-outline-info fw-bold shadow-sm me-1" title="Mark Materials as Staged & Ready for Express Pickup" aria-label="Mark Requisition Materials as Staged">
                                                <i class="bi bi-box-seam me-1" aria-hidden="true"></i> Stage
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($role === 'purchasing' && $rs['status'] === 'Approved'): ?>
                                        <button class="btn btn-sm btn-outline-primary shadow-sm" title="Generate Purchase Order" aria-label="Generate Purchase Order for <?= $rs['rs_no'] ?>"><i class="bi bi-file-earmark-plus" aria-hidden="true"></i> PO</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 d-block mb-2"></i>No Requisition Slips found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'components/requisition_modals.php'; ?>
<script src="assets/js/requisitions.js?v=<?= time() ?>"></script>
<?php include 'layout/footer.php'; ?>