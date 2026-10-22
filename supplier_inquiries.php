<?php
// ============================================================================
// SUPPLIER INQUIRIES MANAGEMENT MODULE
// Pre-PO Supplier Stock Verification, Quotation Tracking & Availability Portal
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'management'])) {
    header("Location: index");
    exit;
}

require_once 'Connection/db.php';
$role = $_SESSION['user_role'];

// Fetch Inquiry Expiration Configuration from system_settings
$defaultExpiryHours = (int)get_system_setting('inquiry_default_expiry_hours', 48);
if ($defaultExpiryHours < 1) $defaultExpiryHours = 48;
$allowCustomExpiry = (int)get_system_setting('inquiry_allow_custom_expiry', 1);

// 1. Fetch Inquiries with aggregated summary stats
$inquiriesStmt = $pdo->query("
    SELECT 
        si.*,
        s.company_name,
        s.contact_person,
        s.contact_number,
        r.rs_no,
        r.project_name,
        (SELECT COUNT(*) FROM supplier_inquiry_items WHERE inquiry_id = si.id) AS total_items,
        (SELECT COUNT(*) FROM supplier_inquiry_items WHERE inquiry_id = si.id AND availability_status = 'Available') AS available_items,
        (SELECT COUNT(*) FROM supplier_inquiry_items WHERE inquiry_id = si.id AND availability_status = 'Partial') AS partial_items,
        (SELECT COUNT(*) FROM supplier_inquiry_items WHERE inquiry_id = si.id AND availability_status = 'Unavailable') AS unavailable_items,
        (SELECT COALESCE(SUM(available_qty * offered_price), 0) FROM supplier_inquiry_items WHERE inquiry_id = si.id AND offered_price IS NOT NULL) AS total_offered_amount
    FROM supplier_inquiries si
    LEFT JOIN suppliers s ON si.supplier_id = s.id
    LEFT JOIN requisitions r ON si.rs_id = r.id
    ORDER BY si.id DESC
");
$inquiries = $inquiriesStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Compute KPI Metrics
$totalInquiries = count($inquiries);
$pendingCount = 0;
$respondedCount = 0;
$expiredCount = 0;
$now = new DateTime();

foreach ($inquiries as $inq) {
    $exp = new DateTime($inq['expires_at']);
    $isExp = ($now > $exp && $inq['status'] !== 'Responded');

    if ($inq['status'] === 'Responded') {
        $respondedCount++;
    } elseif ($isExp || $inq['status'] === 'Expired') {
        $expiredCount++;
    } elseif ($inq['status'] === 'Pending') {
        $pendingCount++;
    }
}

// 3. Fetch Active Suppliers for New Inquiry Modal
$suppliers = $pdo->query("
    SELECT id, company_name, contact_person, contact_number 
    FROM suppliers 
    WHERE status = 'Active' 
    ORDER BY company_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Fetch Approved Requisitions for quick item auto-population
$approvedRS = $pdo->query("
    SELECT r.id, r.rs_no, r.project_name, p.address AS project_address
    FROM requisitions r
    LEFT JOIN projects p ON r.project_name = p.project_name
    WHERE r.status IN ('Approved', 'Partially Approved', 'Partially Ordered')
    ORDER BY r.id DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Inventory items for auto-complete/lookup
$inventoryItems = $pdo->query("
    SELECT item_code, item_name, unit, unit_price AS standard_cost 
    FROM inventory 
    WHERE status != 'Inactive' 
    ORDER BY item_name ASC 
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<!-- Custom Styles for Supplier Inquiries Module -->
<style>
    /* Interactive KPI Filter Tiles */
    .inq-filter-tile {
        cursor: pointer;
        transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s ease, border-color 0.18s ease;
        user-select: none;
        touch-action: manipulation;
        -webkit-tap-highlight-color: transparent;
        position: relative;
    }
    .inq-filter-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08) !important;
    }
    .inq-filter-tile:active {
        transform: scale(0.98);
    }
    .inq-filter-tile.active-filter {
        box-shadow: 0 0 0 2px var(--gb-blue, #0033CC), 0 8px 20px rgba(0, 51, 204, 0.12) !important;
        background-color: #f8fafc !important;
    }
    .inq-filter-tile[data-filter="pending"].active-filter {
        box-shadow: 0 0 0 2px #d97706, 0 8px 20px rgba(217, 119, 6, 0.2) !important;
    }
    .inq-filter-tile[data-filter="responded"].active-filter {
        box-shadow: 0 0 0 2px #198754, 0 8px 20px rgba(25, 135, 84, 0.2) !important;
    }
    .inq-filter-tile[data-filter="expired"].active-filter {
        box-shadow: 0 0 0 2px #dc3545, 0 8px 20px rgba(220, 53, 69, 0.2) !important;
    }

    [data-bs-theme="dark"] .inq-filter-tile.active-filter {
        box-shadow: 0 0 0 2px var(--gb-dark-accent, #58a6ff), 0 8px 20px rgba(0, 0, 0, 0.4) !important;
        background-color: var(--gb-dark-hover, #21262d) !important;
        color: #f0f6fc !important;
    }
    [data-bs-theme="dark"] .inq-filter-tile[data-filter="pending"].active-filter {
        box-shadow: 0 0 0 2px #ffc107, 0 8px 20px rgba(255, 193, 7, 0.25) !important;
    }
    [data-bs-theme="dark"] .inq-filter-tile[data-filter="responded"].active-filter {
        box-shadow: 0 0 0 2px #2ea043, 0 8px 20px rgba(46, 160, 67, 0.25) !important;
    }
    [data-bs-theme="dark"] .inq-filter-tile[data-filter="expired"].active-filter {
        box-shadow: 0 0 0 2px #f85149, 0 8px 20px rgba(248, 81, 73, 0.25) !important;
    }

    .inq-main-table-wrap {
        border-radius: 8px;
        overflow: hidden;
    }

    .inq-main-table-wrap .table thead th {
        border-bottom: 3px solid var(--gb-yellow, #ffc107) !important;
        background-color: var(--gb-dark, #1a1a2e) !important;
        color: #ffffff !important;
        font-weight: 600;
        font-size: 0.80rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    /* Modern Horizontal Scroll Filter Pills (Mobile) */
    .inq-filter-scroll-wrap {
        scrollbar-width: none;
        -ms-overflow-style: none;
        -webkit-overflow-scrolling: touch;
    }
    .inq-filter-scroll-wrap::-webkit-scrollbar {
        display: none;
    }
    .inq-pill-btn {
        border: 1px solid #e2e8f0;
        background-color: #ffffff;
        color: #475569;
        font-size: 0.82rem;
        font-weight: 600;
        border-radius: 9999px;
        padding: 8px 16px;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        touch-action: manipulation;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        cursor: pointer;
    }
    .inq-pill-btn:hover {
        background-color: #f8fafc;
        border-color: #cbd5e1;
        color: #0f172a;
    }
    .inq-pill-btn.active-pill {
        background-color: var(--gb-blue, #0033CC) !important;
        border-color: var(--gb-blue, #0033CC) !important;
        color: #ffffff !important;
        box-shadow: 0 3px 10px rgba(0, 51, 204, 0.3) !important;
    }
    .inq-pill-btn.active-pill .badge {
        background-color: rgba(255, 255, 255, 0.28) !important;
        color: #ffffff !important;
    }
    .inq-pill-btn[data-filter="pending"].active-pill {
        background-color: #d97706 !important;
        border-color: #d97706 !important;
        color: #ffffff !important;
        box-shadow: 0 3px 10px rgba(217, 119, 6, 0.3) !important;
    }
    .inq-pill-btn[data-filter="responded"].active-pill {
        background-color: #198754 !important;
        border-color: #198754 !important;
        color: #ffffff !important;
        box-shadow: 0 3px 10px rgba(25, 135, 84, 0.3) !important;
    }
    .inq-pill-btn[data-filter="expired"].active-pill {
        background-color: #dc3545 !important;
        border-color: #dc3545 !important;
        color: #ffffff !important;
        box-shadow: 0 3px 10px rgba(220, 53, 69, 0.3) !important;
    }

    [data-bs-theme="dark"] .inq-pill-btn {
        background-color: #1e293b;
        border-color: #334155;
        color: #94a3b8;
    }
    [data-bs-theme="dark"] .inq-pill-btn:hover {
        background-color: #334155;
        color: #f8fafc;
    }

    /* Mobile Form Usability & Layout Polish (per Quality Standards §3) */
    @media (max-width: 768px) {
        #newInquiryModal .form-control,
        #newInquiryModal .form-select,
        #shareInquiryModal .form-control,
        #inquirySearchInput {
            font-size: 16px !important;
        }
        
        .modal-fullscreen-sm-down .modal-header,
        .modal-fullscreen-sm-down .modal-footer {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }

        .inq-main-card {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
        .inq-mobile-toolbar {
            background: #ffffff;
            border-radius: 14px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            margin-bottom: 14px;
        }
        .inq-mobile-toolbar .input-group {
            max-width: 100% !important;
            width: 100% !important;
        }
        .inq-desktop-heading {
            display: none !important;
        }
    }

    [data-bs-theme="dark"] .inq-mobile-toolbar {
        background-color: #1e293b;
        border-color: #334155;
    }

    /* Touch-friendly Minimum 44px Hit Targets (Quality Standards §3) */
    .touch-btn {
        min-height: 44px;
        min-width: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* Expiry Option Tile Selectors */
    .expiry-opt-card {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        position: relative;
        background-color: #ffffff;
        user-select: none;
    }
    .expiry-opt-card:hover {
        border-color: #94a3b8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .expiry-opt-card.selected-expiry {
        border-color: var(--gb-blue, #0033CC) !important;
        background-color: #f0f4ff !important;
        box-shadow: 0 0 0 1px var(--gb-blue, #0033CC), 0 6px 16px rgba(0, 51, 204, 0.12) !important;
    }
    [data-bs-theme="dark"] .expiry-opt-card {
        background-color: #1e293b;
        border-color: #334155;
        color: #f1f5f9;
    }
    [data-bs-theme="dark"] .expiry-opt-card:hover {
        border-color: #64748b;
    }
    [data-bs-theme="dark"] .expiry-opt-card.selected-expiry {
        background-color: rgba(59, 130, 246, 0.15) !important;
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 1px #3b82f6, 0 6px 16px rgba(0, 0, 0, 0.3) !important;
    }
</style>

<div class="container-fluid px-3 px-md-4 py-3 py-md-4">

    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 mb-md-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-chat-left-dots text-primary"></i> Supplier Inquiries
            </h1>
            <p class="text-muted small mb-0 mt-0.5">
                Verify material availability and prices with suppliers before issuing purchase orders.
            </p>
        </div>
    </div>

    <!-- Mobile Horizontal Scroll Filter Pills (< 768px) -->
    <div class="d-flex d-md-none overflow-x-auto pb-2 mb-3 gap-2 inq-filter-scroll-wrap">
        <button type="button" class="btn inq-pill-btn active-pill flex-shrink-0" data-filter="all">
            All Inquiries <span class="badge bg-light text-dark ms-1"><?= $totalInquiries ?></span>
        </button>
        <button type="button" class="btn inq-pill-btn flex-shrink-0" data-filter="pending">
            <i class="bi bi-hourglass-split me-1 text-warning"></i>Awaiting Reply <span class="badge bg-warning-subtle text-dark ms-1"><?= $pendingCount ?></span>
        </button>
        <button type="button" class="btn inq-pill-btn flex-shrink-0" data-filter="responded">
            <i class="bi bi-check-circle-fill me-1 text-success"></i>Confirmed <span class="badge bg-success-subtle text-success ms-1"><?= $respondedCount ?></span>
        </button>
        <button type="button" class="btn inq-pill-btn flex-shrink-0" data-filter="expired">
            <i class="bi bi-clock-history me-1 text-danger"></i>Expired <span class="badge bg-danger-subtle text-danger ms-1"><?= $expiredCount ?></span>
        </button>
    </div>

    <!-- Desktop KPI Stat Filter Tiles (>= 768px) -->
    <div class="row mb-4 g-3 d-none d-md-flex">
        <!-- 1. Total Inquiries -->
        <div class="col-6 col-xl-3">
            <div class="card stat-card inq-filter-tile active-filter bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                data-filter="all" role="button" tabindex="0" title="Click to view all supplier inquiries"
                style="border-left: 5px solid var(--gb-blue, #0033CC) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Total Inquiries</h6>
                        <h3 class="mb-0 fw-bold text-dark fs-3"><?= number_format($totalInquiries) ?></h3>
                        <small class="text-muted" style="font-size: 0.72rem;">Lifetime vendor checks</small>
                    </div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue, #0033CC) !important; opacity: 0.85;">
                        <i class="bi bi-chat-left-dots-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Awaiting Reply -->
        <div class="col-6 col-xl-3">
            <div class="card stat-card inq-filter-tile bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                data-filter="pending" role="button" tabindex="0" title="Click to filter inquiries awaiting vendor reply"
                style="border-left: 5px solid #d97706 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Awaiting Reply</h6>
                        <h3 class="mb-0 fw-bold text-warning-emphasis fs-3"><?= number_format($pendingCount) ?></h3>
                        <small class="text-warning fw-semibold" style="font-size: 0.72rem;">Pending vendor response</small>
                    </div>
                    <div class="fs-1 text-warning" style="opacity: 0.85;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Stock Confirmed -->
        <div class="col-6 col-xl-3">
            <div class="card stat-card inq-filter-tile bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                data-filter="responded" role="button" tabindex="0" title="Click to filter responded inquiries"
                style="border-left: 5px solid #198754 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Stock Confirmed</h6>
                        <h3 class="mb-0 fw-bold text-success fs-3"><?= number_format($respondedCount) ?></h3>
                        <small class="text-success fw-semibold" style="font-size: 0.72rem;">Ready for Purchase Order</small>
                    </div>
                    <div class="fs-1 text-success" style="opacity: 0.85;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Expired / Past 48h -->
        <div class="col-6 col-xl-3">
            <div class="card stat-card inq-filter-tile bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                data-filter="expired" role="button" tabindex="0" title="Click to filter expired inquiry links"
                style="border-left: 5px solid #dc3545 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Expired / Past 48h</h6>
                        <h3 class="mb-0 fw-bold text-danger fs-3"><?= number_format($expiredCount) ?></h3>
                        <small class="text-danger fw-semibold" style="font-size: 0.72rem;">Links automatically closed</small>
                    </div>
                    <div class="fs-1 text-danger" style="opacity: 0.85;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Datatable Card -->
    <div class="card inq-main-card border-0 shadow-sm p-3 p-md-4 bg-white rounded-3">
        <!-- Main Datatable Top Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2 inq-mobile-toolbar">
            <div class="inq-desktop-heading">
                <h4 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-chat-left-dots-fill text-primary"></i> Supplier Inquiries List
                </h4>
                <small class="text-muted">
                    Filtered by: <span id="activeFilterBadge" class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">All Inquiries (<?= $totalInquiries ?>)</span>
                </small>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 ms-md-auto w-100 w-md-auto">
                <!-- Live Search Input -->
                <div class="input-group shadow-sm" style="width: 280px; max-width: 100%;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="inquirySearchInput" class="form-control border-start-0 ps-0 bg-white" placeholder="Search inquiry #, supplier, RS...">
                    <button class="btn btn-white border border-start-0 text-muted d-none" type="button" id="clearSearchBtn" title="Clear Search">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0">
                    <!-- Refresh Button -->
                    <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm d-flex align-items-center justify-content-center gap-1.5 flex-fill flex-md-grow-0" style="min-height: 38px;" onclick="window.location.reload()" title="Refresh List">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>

                    <!-- Settings Button (Opens Expiration Settings Modal) -->
                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm d-flex align-items-center justify-content-center gap-1.5 flex-fill flex-md-grow-0" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#inquirySettingsModal" title="Inquiry Link Expiration Settings">
                            <i class="bi bi-gear-fill"></i> Settings
                        </button>
                    <?php endif; ?>

                    <!-- New Inquiry Button -->
                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                        <button type="button" class="btn btn-primary btn-sm shadow-sm d-flex align-items-center justify-content-center gap-1.5 flex-fill flex-md-grow-0 fw-semibold" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#newInquiryModal">
                            <i class="bi bi-plus-lg"></i> New Inquiry
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Table Container (Desktop / Tablet >= 768px) -->
        <div class="table-responsive border rounded shadow-sm bg-white inq-main-table-wrap d-none d-md-block">
            <table class="table table-hover align-middle mb-0" id="inquiriesTable">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3 ps-3">Inquiry Details</th>
                        <th class="py-3">Supplier Partner</th>
                        <th class="py-3">Associated Requisition</th>
                        <th class="py-3 text-center">Items & Availability</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3 text-end pe-3" style="min-width: 170px;">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y" id="inquiriesTableBody">
                    <!-- Dynamic Empty Filter Result Row -->
                    <tr id="noFilterResultsRow" style="display: none;">
                        <td colspan="6" class="text-center py-5">
                            <div class="py-4 text-muted">
                                <i class="bi bi-search fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                <h6 class="fw-bold text-dark mb-1">No Inquiries Found</h6>
                                <p class="small text-muted mb-3" style="max-width: 360px; margin: 0 auto;">No records match your active filter or search keywords.</p>
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold px-3 shadow-sm" onclick="resetInquiryFilters()">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php if (empty($inquiries)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    <h5 class="fw-bold text-dark">No Supplier Inquiries Recorded</h5>
                                    <p class="small text-muted mb-3">Create an inquiry before generating a PO to verify material availability and prices via Viber.</p>
                                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newInquiryModal">
                                            <i class="bi bi-plus-circle me-1"></i> Create First Inquiry
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inquiries as $inq): 
                            $expDate = new DateTime($inq['expires_at']);
                            $isExpired = ($now > $expDate && $inq['status'] !== 'Responded');
                            $status = $inq['status'];
                            if ($isExpired && $status !== 'Cancelled') $status = 'Expired';

                            // Clean link (without .php and without ?token=)
                            $cleanPortalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/supplier_inquiry/' . $inq['token'];
                        ?>
                            <tr class="inquiry-row" 
                                data-status="<?= strtolower($status) ?>"
                                data-search="<?= strtolower(htmlspecialchars($inq['inquiry_no'] . ' ' . $inq['company_name'] . ' ' . ($inq['contact_person'] ?? '') . ' ' . ($inq['rs_no'] ?? '') . ' ' . $inq['delivery_destination'])) ?>">
                                
                                <!-- Inquiry Details -->
                                <td class="ps-3 py-3">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1">
                                            <?= htmlspecialchars($inq['inquiry_no']) ?>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-link text-muted p-0" title="Copy clean link" onclick="copyInquiryLink('<?= htmlspecialchars($cleanPortalUrl) ?>')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="bi bi-calendar-event me-1"></i><?= date('M d, Y h:i A', strtotime($inq['created_at'])) ?>
                                    </div>
                                    <div class="small text-secondary mt-0.5" style="font-size: 0.74rem;">
                                        <i class="bi bi-geo-alt me-1 text-primary"></i><?= htmlspecialchars($inq['delivery_destination']) ?>
                                    </div>
                                </td>

                                <!-- Supplier -->
                                <td class="py-3">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($inq['company_name'] ?: 'Unknown Supplier') ?></div>
                                    <?php if (!empty($inq['contact_person'])): ?>
                                        <small class="text-muted d-block">
                                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($inq['contact_person']) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if (!empty($inq['contact_number'])): ?>
                                        <small class="text-muted d-block font-monospace">
                                            <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($inq['contact_number']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <!-- Associated RS -->
                                <td class="py-3">
                                    <?php if (!empty($inq['rs_no'])): ?>
                                        <a href="requisitions" class="badge bg-light text-dark border text-decoration-none fw-semibold">
                                            <i class="bi bi-card-checklist text-primary me-1"></i><?= htmlspecialchars($inq['rs_no']) ?>
                                        </a>
                                        <?php if (!empty($inq['project_name'])): ?>
                                            <small class="text-muted d-block mt-0.5 text-truncate" style="max-width: 180px;">
                                                <?= htmlspecialchars($inq['project_name']) ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small"><em>Direct Material Inquiry</em></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Items & Availability Status -->
                                <td class="py-3 text-center">
                                    <div class="fw-bold text-dark"><?= (int)$inq['total_items'] ?> Items</div>
                                    <?php if ($status === 'Responded'): ?>
                                        <div class="d-flex justify-content-center gap-1 mt-1">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" title="Available">
                                                <?= (int)$inq['available_items'] ?> Avail
                                            </span>
                                            <?php if ((int)$inq['partial_items'] > 0): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Partial">
                                                    <?= (int)$inq['partial_items'] ?> Part
                                                </span>
                                            <?php endif; ?>
                                            <?php if ((int)$inq['unavailable_items'] > 0): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="Out of Stock">
                                                    <?= (int)$inq['unavailable_items'] ?> Out
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ((float)$inq['total_offered_amount'] > 0): ?>
                                            <div class="small fw-bold text-primary mt-1">
                                                ₱<?= number_format((float)$inq['total_offered_amount'], 2) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <small class="text-muted d-block mt-1">Awaiting Vendor</small>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3 text-center">
                                    <?php if ($status === 'Responded'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 fw-semibold">
                                            <i class="bi bi-check-circle-fill me-1"></i> Responded
                                        </span>
                                        <?php if (!empty($inq['responded_at'])): ?>
                                            <small class="text-muted d-block mt-1" style="font-size: 0.70rem;">
                                                <?= date('M d, h:i A', strtotime($inq['responded_at'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php elseif ($status === 'Pending'): ?>
                                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2.5 py-1.5 fw-semibold">
                                            <i class="bi bi-hourglass-split me-1"></i> Awaiting Reply
                                        </span>
                                        <small class="text-danger d-block mt-1 fw-medium" style="font-size: 0.70rem;">
                                            Expires <?= date('M d, h:i A', strtotime($inq['expires_at'])) ?>
                                        </small>
                                    <?php elseif ($status === 'Expired'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 fw-semibold">
                                            <i class="bi bi-clock-history me-1"></i> Expired
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-white px-2.5 py-1.5 fw-semibold">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <!-- View Details -->
                                        <button type="button" class="btn btn-outline-primary" title="View Response Breakdown" 
                                            onclick="viewInquiryDetails(<?= $inq['id'] ?>)">
                                            <i class="bi bi-eye-fill"></i> View
                                        </button>

                                        <!-- Share / Viber Modal -->
                                        <button type="button" class="btn btn-outline-secondary" title="Share via Viber / Copy Link"
                                            onclick="openShareModal(<?= htmlspecialchars(json_encode([
                                                'inquiry_no' => $inq['inquiry_no'],
                                                'company_name' => $inq['company_name'],
                                                'phone' => $inq['contact_number'] ?? '',
                                                'clean_url' => $cleanPortalUrl,
                                                'expires_at' => date('M d, Y h:i A', strtotime($inq['expires_at'])),
                                                'status' => $status
                                            ])) ?>)">
                                            <i class="bi bi-share-fill"></i>
                                        </button>

                                        <!-- Create PO from Responded Inquiry -->
                                        <?php if ($status === 'Responded' && in_array($role, ['admin', 'purchasing'])): ?>
                                            <a href="po" class="btn btn-success" title="Proceed to Purchase Orders">
                                                <i class="bi bi-file-earmark-plus"></i> PO
                                            </a>
                                        <?php endif; ?>

                                        <!-- Cancel Inquiry -->
                                        <?php if ($status === 'Pending' && in_array($role, ['admin', 'purchasing'])): ?>
                                            <button type="button" class="btn btn-outline-danger" title="Cancel Inquiry"
                                                onclick="cancelInquiry(<?= $inq['id'] ?>, '<?= htmlspecialchars($inq['inquiry_no']) ?>')">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View (< 768px per quality-standards §3) -->
        <div id="inquiriesMobileCards" class="d-block d-md-none">
            <!-- Dynamic Empty Filter Result Card for Mobile -->
            <div id="noFilterResultsCard" class="card border-0 shadow-sm p-4 text-center text-muted mb-3 bg-white rounded-3" style="display: none;">
                <i class="bi bi-search fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h6 class="fw-bold text-dark mb-1">No Inquiries Found</h6>
                <p class="small text-muted mb-3">No records match your active filter or search keywords.</p>
                <div>
                    <button type="button" class="btn btn-outline-primary btn-sm fw-bold px-3 shadow-sm touch-btn" onclick="resetInquiryFilters()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                    </button>
                </div>
            </div>

            <?php if (empty($inquiries)): ?>
                <div class="card border-0 shadow-sm p-4 text-center text-muted mb-3 bg-white rounded-3">
                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    <h6 class="fw-bold text-dark">No Supplier Inquiries Recorded</h6>
                    <p class="small text-muted mb-3">Create an inquiry before generating a PO to verify material availability and prices via Viber.</p>
                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                        <button class="btn btn-primary btn-sm w-100 touch-btn" data-bs-toggle="modal" data-bs-target="#newInquiryModal">
                            <i class="bi bi-plus-circle me-1"></i> Create First Inquiry
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($inquiries as $inq): 
                    $expDate = new DateTime($inq['expires_at']);
                    $isExpired = ($now > $expDate && $inq['status'] !== 'Responded');
                    $status = $inq['status'];
                    if ($isExpired && $status !== 'Cancelled') $status = 'Expired';
                    $cleanPortalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/supplier_inquiry/' . $inq['token'];
                ?>
                    <div class="cims-mobile-card inquiry-mobile-card"
                        data-status="<?= strtolower($status) ?>"
                        data-search="<?= strtolower(htmlspecialchars($inq['inquiry_no'] . ' ' . $inq['company_name'] . ' ' . ($inq['contact_person'] ?? '') . ' ' . ($inq['rs_no'] ?? '') . ' ' . $inq['delivery_destination'])) ?>">
                        
                        <!-- Top Header: Supplier Name & Status -->
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2.5 overflow-hidden">
                                <div class="rounded-circle bg-primary-subtle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                                    <i class="bi bi-shop text-primary fs-5"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <h6 class="fw-bold text-dark mb-0 text-truncate" style="font-size: 0.95rem;">
                                        <?= htmlspecialchars($inq['company_name'] ?: 'Unknown Supplier') ?>
                                    </h6>
                                    <div class="d-flex align-items-center gap-2 mt-0.5">
                                        <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none d-inline-flex align-items-center gap-1 font-monospace"
                                            onclick="copyInquiryLink('<?= htmlspecialchars($cleanPortalUrl) ?>')"
                                            title="Tap to copy link">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-1.5 py-0.5" style="font-size: 0.70rem; font-weight: 600;">
                                                <i class="bi bi-clipboard me-1"></i><?= htmlspecialchars($inq['inquiry_no']) ?>
                                            </span>
                                        </button>
                                        <span class="text-muted" style="font-size: 0.68rem;">
                                            <?= date('M d, Y', strtotime($inq['created_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <?php if ($status === 'Responded'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-check-circle-fill me-1"></i> Responded
                                    </span>
                                <?php elseif ($status === 'Pending'): ?>
                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2 py-1 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-hourglass-split me-1"></i> Awaiting
                                    </span>
                                <?php elseif ($status === 'Expired'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="bi bi-clock-history me-1"></i> Expired
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-white px-2 py-1 fw-semibold" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Supplier Details: Contact Person, Phone, Destination, RS -->
                        <div class="mb-2.5">
                            <div class="d-flex flex-wrap align-items-center gap-3 text-muted mb-1.5" style="font-size: 0.76rem;">
                                <?php if (!empty($inq['contact_person'])): ?>
                                    <span><i class="bi bi-person me-1 text-secondary"></i><?= htmlspecialchars($inq['contact_person']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($inq['contact_number'])): ?>
                                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $inq['contact_number']) ?>" class="text-decoration-none text-muted font-monospace">
                                        <i class="bi bi-telephone me-1 text-primary"></i><?= htmlspecialchars($inq['contact_number']) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex flex-wrap align-items-center gap-1.5">
                                <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.72rem;">
                                    <i class="bi bi-geo-alt text-primary me-1"></i><?= htmlspecialchars($inq['delivery_destination']) ?>
                                </span>
                                <?php if (!empty($inq['rs_no'])): ?>
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.72rem;">
                                        <i class="bi bi-file-earmark-text text-primary me-1"></i><?= htmlspecialchars($inq['rs_no']) ?><?php if (!empty($inq['project_name'])): ?> &bull; <?= htmlspecialchars($inq['project_name']) ?><?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Materials & Quoted Amount Banner -->
                        <div class="p-2.5 rounded-3 bg-light border border-light-subtle d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded bg-white p-1 shadow-xs border d-flex align-items-center justify-content-center text-primary" style="width: 32px; height: 32px;">
                                    <i class="bi bi-boxes fs-6"></i>
                                </div>
                                <div>
                                    <span class="fw-semibold text-dark d-block" style="font-size: 0.82rem;">
                                        <?= (int)$inq['total_items'] ?> Materials Inquired
                                    </span>
                                    <?php if ($status === 'Responded'): ?>
                                        <small class="text-success fw-medium d-block" style="font-size: 0.70rem;">
                                            <i class="bi bi-check-circle me-1"></i><?= (int)$inq['available_items'] ?> available in stock
                                        </small>
                                    <?php elseif ($status === 'Pending'): ?>
                                        <small class="text-warning-emphasis fw-medium d-block" style="font-size: 0.70rem;">
                                            <i class="bi bi-clock me-1"></i>Expires <?= date('M d, h:i A', strtotime($inq['expires_at'])) ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-danger fw-medium d-block" style="font-size: 0.70rem;">
                                            <i class="bi bi-x-circle me-1"></i>Link expired
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($status === 'Responded' && (float)$inq['total_offered_amount'] > 0): ?>
                                <div class="text-end">
                                    <span class="text-muted d-block" style="font-size: 0.68rem; text-transform: uppercase;">Quoted</span>
                                    <strong class="text-primary font-monospace" style="font-size: 0.95rem;">
                                        ₱<?= number_format((float)$inq['total_offered_amount'], 2) ?>
                                    </strong>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="cims-mobile-actions d-flex align-items-center gap-2 pt-1 border-top">
                            <button type="button" class="btn btn-outline-primary btn-sm flex-fill" onclick="viewInquiryDetails(<?= $inq['id'] ?>)">
                                <i class="bi bi-eye me-1"></i> Details
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="openShareModal(<?= htmlspecialchars(json_encode([
                                'inquiry_no' => $inq['inquiry_no'],
                                'company_name' => $inq['company_name'],
                                'phone' => $inq['contact_number'] ?? '',
                                'clean_url' => $cleanPortalUrl,
                                'expires_at' => date('M d, Y h:i A', strtotime($inq['expires_at'])),
                                'status' => $status
                            ])) ?>)">
                                <i class="bi bi-share me-1"></i> Share
                            </button>
                            <?php if ($status === 'Responded' && in_array($role, ['admin', 'purchasing'])): ?>
                                <a href="po" class="btn btn-success btn-sm flex-fill">
                                    <i class="bi bi-cart-check-fill me-1"></i> PO
                                </a>
                            <?php endif; ?>
                            <?php if ($status === 'Pending' && in_array($role, ['admin', 'purchasing'])): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" style="width: 44px; min-width: 44px;" title="Cancel Inquiry" onclick="cancelInquiry(<?= $inq['id'] ?>, '<?= htmlspecialchars($inq['inquiry_no']) ?>')">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 0. MODAL: INQUIRY LINK EXPIRATION & ACCESS SETTINGS                       -->
<!-- ========================================================================= -->
<div class="modal fade" id="inquirySettingsModal" tabindex="-1" aria-labelledby="inquirySettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning text-dark p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-gear-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="inquirySettingsModalLabel">Supplier Inquiry Settings</h5>
                        <small class="text-white-50">Configure link expiration duration, access policy, and officer permissions</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="inquirySettingsForm" onsubmit="saveInquirySettings(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                <div class="modal-body p-3 p-md-4 bg-light">
                    <!-- SECTION 1: DEFAULT EXPIRATION PERIOD -->
                    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label fw-bold text-dark mb-0 fs-6">
                                <i class="bi bi-clock-history text-primary me-1"></i> Default Link Expiration Duration
                            </label>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill" id="activeSettingSummaryBadge" style="font-size: 0.70rem;">
                                <?= $defaultExpiryHours ?>h Default
                            </span>
                        </div>
                        <p class="text-muted small mb-3">
                            When an inquiry link is generated, its expiration timestamp is automatically calculated using this duration. Vendors can only submit quotes while the link remains active.
                        </p>

                        <!-- Grid of Expiration Option Cards -->
                        <div class="row g-2.5 mb-3" id="expiryOptionsGrid">
                            <!-- 24 Hours -->
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="expiry-opt-card h-100 <?= $defaultExpiryHours === 24 ? 'selected-expiry' : '' ?>" 
                                    onclick="selectExpiryOption(24, this)" data-hours="24">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="fw-bold fs-6 text-dark">24 Hours</div>
                                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle" style="font-size: 0.65rem;">Urgent</span>
                                    </div>
                                    <div class="small text-muted" style="font-size: 0.75rem;">1 Day validity for fast-moving spot materials.</div>
                                </div>
                            </div>

                            <!-- 48 Hours (Standard) -->
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="expiry-opt-card h-100 <?= $defaultExpiryHours === 48 ? 'selected-expiry' : '' ?>" 
                                    onclick="selectExpiryOption(48, this)" data-hours="48">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="fw-bold fs-6 text-primary">48 Hours</div>
                                        <span class="badge bg-primary text-white" style="font-size: 0.65rem;">Standard</span>
                                    </div>
                                    <div class="small text-muted" style="font-size: 0.75rem;">2 Days validity. Standard business default.</div>
                                </div>
                            </div>

                            <!-- 72 Hours -->
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="expiry-opt-card h-100 <?= $defaultExpiryHours === 72 ? 'selected-expiry' : '' ?>" 
                                    onclick="selectExpiryOption(72, this)" data-hours="72">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="fw-bold fs-6 text-dark">72 Hours</div>
                                        <span class="badge bg-secondary-subtle text-dark border" style="font-size: 0.65rem;">Weekend Safe</span>
                                    </div>
                                    <div class="small text-muted" style="font-size: 0.75rem;">3 Days validity for inquiries sent before weekends.</div>
                                </div>
                            </div>

                            <!-- 120 Hours (5 Days) -->
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="expiry-opt-card h-100 <?= $defaultExpiryHours === 120 ? 'selected-expiry' : '' ?>" 
                                    onclick="selectExpiryOption(120, this)" data-hours="120">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="fw-bold fs-6 text-dark">5 Days</div>
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 0.65rem;">Work Week</span>
                                    </div>
                                    <div class="small text-muted" style="font-size: 0.75rem;">120 Hours. Full work week for high-volume items.</div>
                                </div>
                            </div>

                            <!-- 168 Hours (7 Days) -->
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="expiry-opt-card h-100 <?= $defaultExpiryHours === 168 ? 'selected-expiry' : '' ?>" 
                                    onclick="selectExpiryOption(168, this)" data-hours="168">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="fw-bold fs-6 text-dark">7 Days</div>
                                        <span class="badge bg-secondary-subtle text-dark border" style="font-size: 0.65rem;">1 Week</span>
                                    </div>
                                    <div class="small text-muted" style="font-size: 0.75rem;">168 Hours for heavy machinery or specialty items.</div>
                                </div>
                            </div>

                            <!-- Custom Option -->
                            <?php 
                            $isPreset = in_array($defaultExpiryHours, [24, 48, 72, 120, 168]);
                            ?>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="expiry-opt-card h-100 <?= !$isPreset ? 'selected-expiry' : '' ?>" 
                                    onclick="selectExpiryOption('custom', this)" data-hours="custom">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="fw-bold fs-6 text-dark">Custom Hours</div>
                                        <span class="badge bg-dark text-white" style="font-size: 0.65rem;">Custom</span>
                                    </div>
                                    <div class="small text-muted" style="font-size: 0.75rem;">Enter custom duration (1 to 720 hours).</div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden input storing selected hours -->
                        <input type="hidden" name="default_expiry_hours" id="selectedDefaultExpiryHours" value="<?= $defaultExpiryHours ?>">

                        <!-- Custom Hours Input Field (Visible if custom selected) -->
                        <div id="customHoursInputContainer" class="p-2.5 bg-light rounded-3 border mb-3 <?= $isPreset ? 'd-none' : '' ?>">
                            <div class="row align-items-center g-2">
                                <div class="col-12 col-sm-auto">
                                    <label for="customExpiryHoursInput" class="form-label small fw-bold mb-0 text-dark">
                                        Custom Duration:
                                    </label>
                                </div>
                                <div class="col-12 col-sm-auto" style="width: 140px;">
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control text-center fw-bold" id="customExpiryHoursInput" min="1" max="720" value="<?= $defaultExpiryHours ?>" oninput="onCustomHoursInput(this.value)">
                                        <span class="input-group-text bg-white">hrs</span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm">
                                    <small class="text-muted">Enter any integer between 1 and 720 hours (up to 30 days).</small>
                                </div>
                            </div>
                        </div>

                        <!-- Live Expiration Preview Box -->
                        <div class="p-3 rounded-3 bg-primary-subtle border border-primary-subtle d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2.5">
                                <div class="rounded-circle bg-primary text-white p-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                    <i class="bi bi-broadcast fs-6"></i>
                                </div>
                                <div>
                                    <div class="small fw-bold text-primary text-uppercase" style="letter-spacing: 0.5px; font-size: 0.68rem;">
                                        Live Calculation Preview
                                    </div>
                                    <div class="text-dark small" style="font-size: 0.82rem;">
                                        An inquiry generated right now would expire on:
                                        <span class="fw-bold text-primary" id="settingsExpiryLiveTime">
                                            <?= date('M d, Y h:i A', strtotime("+{$defaultExpiryHours} hours")) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <span class="badge bg-primary text-white px-2.5 py-1 rounded-pill fw-semibold" id="settingsExpiryHoursBadge" style="font-size: 0.75rem;">
                                <?= $defaultExpiryHours ?> Hours (<?= round($defaultExpiryHours / 24, 1) ?> Days)
                            </span>
                        </div>
                    </div>

                    <!-- SECTION 2: OFFICER OVERRIDE PERMISSIONS -->
                    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-3">
                        <label class="form-label fw-bold text-dark mb-1 fs-6">
                            <i class="bi bi-person-gear text-primary me-1"></i> Creation Form Override Permissions
                        </label>
                        <p class="text-muted small mb-2.5">
                            Determine whether purchasing officers may customize the expiration period for individual inquiries or if all links must strictly adhere to the default policy.
                        </p>

                        <div class="form-check form-switch p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between ps-3 pe-3 mb-0">
                            <div>
                                <label class="form-check-label fw-bold text-dark d-block mb-0" for="allowCustomExpirySwitch">
                                    Allow Custom Expiration on Inquiry Creation
                                </label>
                                <small class="text-muted d-block">
                                    When enabled, officers can select custom date & time overrides in the "New Inquiry" modal. When disabled, inquiries strictly inherit the default policy.
                                </small>
                            </div>
                            <input class="form-check-input ms-3 flex-shrink-0" type="checkbox" role="switch" id="allowCustomExpirySwitch" name="allow_custom_expiry" value="1" <?= $allowCustomExpiry ? 'checked' : '' ?> style="width: 2.8rem; height: 1.4rem; cursor: pointer;">
                        </div>
                    </div>

                    <!-- SECTION 3: SECURITY SAFEGUARDS -->
                    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                        <label class="form-label fw-bold text-dark mb-1 fs-6">
                            <i class="bi bi-shield-check text-success me-1"></i> Security Safeguards
                        </label>
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <div class="p-2.5 border rounded-3 bg-light h-100">
                                    <div class="text-primary fw-bold small mb-1"><i class="bi bi-key-fill me-1"></i>Cryptographic Token</div>
                                    <div class="text-muted" style="font-size: 0.74rem;">48-character hex token prevents link discovery and guessing.</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="p-2.5 border rounded-3 bg-light h-100">
                                    <div class="text-success fw-bold small mb-1"><i class="bi bi-lock-fill me-1"></i>Single-Submit Lock</div>
                                    <div class="text-muted" style="font-size: 0.74rem;">Quotes instantly lock upon submission to prevent retroactive edits.</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="p-2.5 border rounded-3 bg-light h-100">
                                    <div class="text-danger fw-bold small mb-1"><i class="bi bi-clock-fill me-1"></i>Auto-Expiry Closure</div>
                                    <div class="text-muted" style="font-size: 0.74rem;">Expired links automatically close with no submissions allowed.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODAL FOOTER -->
                <div class="modal-footer bg-white border-top py-2.5 px-3 px-md-4 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary btn-sm touch-btn" onclick="resetInqSettingsToDefault()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset to 48h
                    </button>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-light btn-sm border touch-btn px-3" data-bs-dismiss="modal">
                            Close
                        </button>
                        <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                            <button type="submit" class="btn btn-primary btn-sm fw-semibold shadow-sm touch-btn px-4 d-flex align-items-center gap-1.5" id="saveInqSettingsBtn">
                                <i class="bi bi-check-lg fs-6"></i>
                                <span>Save Settings</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 1. MODAL: CREATE NEW SUPPLIER INQUIRY                                     -->
<!-- ========================================================================= -->
<div class="modal fade" id="newInquiryModal" tabindex="-1" aria-labelledby="newInquiryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-white text-primary p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-chat-left-dots-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="newInquiryModalLabel">New Supplier Material Inquiry</h5>
                        <small class="text-white-50">Create an inquiry link to verify material stock and prices with the supplier</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="createInquiryForm" onsubmit="submitNewInquiry(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <div class="modal-body p-4 bg-light">
                    <!-- General Details Row -->
                    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-3">
                        <div class="row g-3">
                            <!-- Supplier Picker -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark">Select Supplier <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" name="supplier_id" id="inqSupplierSelect" required onchange="onSupplierSelectChange(this)">
                                    <option value="">-- Choose Supplier Partner --</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= $s['id'] ?>" 
                                            data-phone="<?= htmlspecialchars($s['contact_number'] ?? '') ?>"
                                            data-person="<?= htmlspecialchars($s['contact_person'] ?? '') ?>">
                                            <?= htmlspecialchars($s['company_name']) ?> <?= !empty($s['contact_number']) ? '('.htmlspecialchars($s['contact_number']).')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Optional Link to Approved RS -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark">Link to Approved Requisition (Optional)</label>
                                <select class="form-select form-select-sm" name="rs_id" id="inqRsSelect" onchange="onRsSelectChange(this)">
                                    <option value="">-- Direct Inquiry (No RS linked) --</option>
                                    <?php foreach ($approvedRS as $r): ?>
                                        <option value="<?= $r['id'] ?>" data-project="<?= htmlspecialchars($r['project_name'] ?? '') ?>" data-address="<?= htmlspecialchars($r['project_address'] ?? '') ?>">
                                            <?= htmlspecialchars($r['rs_no']) ?> — <?= htmlspecialchars($r['project_name'] ?: 'Warehouse Restock') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Drop Destination -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark">Target Drop Destination</label>
                                <input type="text" class="form-control form-control-sm" name="delivery_destination" id="inqDestination" 
                                    value="Central Warehouse (Main Storage)" placeholder="e.g. Central Warehouse or Jobsite Address">
                            </div>

                            <!-- Target Delivery Date -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark">Target Delivery Date</label>
                                <input type="date" class="form-control form-control-sm" name="expected_delivery_date" 
                                    value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                            </div>

                            <!-- Link Expiration Validity -->
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold text-dark d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-clock-history me-1 text-primary"></i>Link Expiration Period</span>
                                    <span class="badge bg-light text-muted border fw-normal" style="font-size: 0.70rem;">Auto-closes link</span>
                                </label>
                                <select class="form-select form-select-sm" name="validity_duration" id="inqValidityDuration" onchange="onValidityDurationChange(this)">
                                    <option value="24" <?= $defaultExpiryHours === 24 ? 'selected' : '' ?>>24 Hours (1 Day - Urgent / Rush)</option>
                                    <option value="48" <?= $defaultExpiryHours === 48 ? 'selected' : '' ?>>48 Hours (2 Days - Standard Default)</option>
                                    <option value="72" <?= $defaultExpiryHours === 72 ? 'selected' : '' ?>>72 Hours (3 Days)</option>
                                    <option value="120" <?= $defaultExpiryHours === 120 ? 'selected' : '' ?>>5 Days (120 Hours)</option>
                                    <option value="168" <?= $defaultExpiryHours === 168 ? 'selected' : '' ?>>7 Days (1 Week)</option>
                                    <?php if (!in_array($defaultExpiryHours, [24, 48, 72, 120, 168])): ?>
                                        <option value="<?= $defaultExpiryHours ?>" selected><?= $defaultExpiryHours ?> Hours (Configured Default)</option>
                                    <?php endif; ?>
                                    <?php if ($allowCustomExpiry): ?>
                                        <option value="custom">Custom Date & Time...</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Custom Expiry Date & Time (Conditional) -->
                            <div class="col-12 col-md-6 d-none" id="customExpiryWrapper">
                                <label class="form-label small fw-bold text-dark">
                                    <i class="bi bi-calendar-x me-1 text-danger"></i>Specify Custom Expiry Date & Time
                                </label>
                                <input type="datetime-local" class="form-control form-control-sm" name="custom_expires_at" id="inqCustomExpiry"
                                    min="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>"
                                    value="<?= date('Y-m-d\TH:i', strtotime('+48 hours')) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Requested Materials Section -->
                    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2.5">
                            <label class="form-label small fw-bold text-dark mb-0">
                                <i class="bi bi-box-seam me-1 text-primary"></i>Materials to Inquire <span class="text-danger">*</span>
                            </label>
                            <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2.5" onclick="addNewItemRow()">
                                <i class="bi bi-plus-lg me-1"></i> Add Material
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0" id="inqItemsTable">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th style="min-width: 220px;">Item Description / Material Name</th>
                                        <th style="width: 110px;">Requested Qty</th>
                                        <th style="width: 100px;">Unit</th>
                                        <th style="width: 50px;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="inqItemsTableBody">
                                    <!-- Dynamic rows -->
                                    <tr class="inq-item-row">
                                        <td>
                                            <input type="text" class="form-control form-control-sm item-name-input" placeholder="e.g. Portland Cement Type 1" required list="inventoryCatalogList">
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm item-qty-input text-center" min="1" value="10" required>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm item-unit-input text-center" value="pcs" placeholder="pcs, bags, etc." required>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm touch-btn p-0 border-0" onclick="removeInquiryRow(this)" title="Remove item" aria-label="Remove item" style="width: 44px; height: 44px; border-radius: 8px;">
                                                <i class="bi bi-trash fs-6"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <datalist id="inventoryCatalogList">
                            <?php foreach ($inventoryItems as $inv): ?>
                                <option value="<?= htmlspecialchars($inv['item_name']) ?> (<?= htmlspecialchars($inv['item_code']) ?>)"><?= htmlspecialchars($inv['unit']) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <!-- Purchasing Officer Remarks -->
                    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="bi bi-chat-left-text me-1 text-primary"></i>Notes / Special Instructions for Vendor (Optional)
                        </label>
                        <textarea class="form-control form-control-sm" name="notes" rows="2" 
                            placeholder="e.g. Please specify brand, payment terms, or earliest dispatch date..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-white border-top py-2.5">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3 touch-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitNewInquiry" class="btn btn-primary btn-sm px-4 fw-bold touch-btn">
                        <i class="bi bi-link-45deg me-1"></i> Generate Inquiry Link
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. MODAL: VIEW INQUIRY BREAKDOWN & RESPONSE                               -->
<!-- ========================================================================= -->
<div class="modal fade" id="viewInquiryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill text-primary fs-4"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="viewInqTitle">Inquiry Response Summary</h5>
                        <small class="text-white-50" id="viewInqSubtitle">Checking vendor quotation & availability details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="viewInqModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="text-muted mt-2 small">Loading quotation details...</div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2.5">
                <button type="button" class="btn btn-secondary btn-sm px-3 touch-btn" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. MODAL: SHARE INQUIRY (VIBER / CLEAN URL)                               -->
<!-- ========================================================================= -->
<div class="modal fade" id="shareInquiryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-share-fill fs-5"></i>
                    <h5 class="modal-title fw-bold mb-0">Share Inquiry with Supplier</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="text-center mb-3">
                    <div class="rounded-circle bg-primary-subtle text-primary mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="bi bi-chat-left-dots fs-3"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1" id="shareSupplierName">Valued Supplier</h6>
                    <small class="text-muted" id="shareInquiryNo">INQ-XXXXXXXX-XXXX</small>
                </div>

                <!-- Clean Link Card -->
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.72rem;">
                        Clean Mobile Verification Link:
                    </label>
                    <div class="input-group">
                        <input type="text" id="shareCleanUrlInput" class="form-control form-control-sm font-monospace bg-light" readonly>
                        <button class="btn btn-outline-primary btn-sm px-3" type="button" onclick="copyShareModalLink()">
                            <i class="bi bi-clipboard me-1"></i> Copy
                        </button>
                    </div>
                    <small class="text-danger mt-1 d-block" id="shareExpiresText" style="font-size: 0.72rem;">
                        <i class="bi bi-clock me-1"></i>Active for 48 hours
                    </small>
                </div>

                <!-- Pre-filled Viber Message Preview -->
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.72rem;">
                        Viber Message Preview:
                    </label>
                    <div class="p-2.5 bg-light rounded-2 text-secondary small font-monospace mb-3" id="shareViberPreviewText" style="font-size: 0.82rem; white-space: pre-wrap;"></div>
                    <a id="btnOpenViberApp" href="#" class="btn btn-primary touch-btn w-100 d-flex align-items-center justify-content-center gap-2" style="background-color: #7360f2; border-color: #7360f2;">
                        <i class="bi bi-chat-dots-fill fs-5"></i> Open in Viber & Send Message
                    </a>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2.5">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 4. CLIENT JAVASCRIPT LOGIC                                                -->
<!-- ========================================================================= -->
<script>
    const CSRF_TOKEN = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';

    // --- Interactive KPI Tile Filter & Search Logic ---
    let currentInqFilter = 'all';

    function filterInquiryTable() {
        const searchInput = document.getElementById('inquirySearchInput');
        const clearBtn = document.getElementById('clearSearchBtn');
        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();

        if (clearBtn) {
            clearBtn.classList.toggle('d-none', !query);
        }

        const rows = document.querySelectorAll('#inquiriesTableBody tr.inquiry-row');
        const cards = document.querySelectorAll('#inquiriesMobileCards .inquiry-mobile-card');
        let visibleCount = 0;

        rows.forEach(row => {
            const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
            const rowSearch = (row.getAttribute('data-search') || '').toLowerCase();

            const matchesStatus = (currentInqFilter === 'all') || (rowStatus === currentInqFilter);
            const matchesQuery = !query || rowSearch.includes(query);

            if (matchesStatus && matchesQuery) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        cards.forEach(card => {
            const cardStatus = (card.getAttribute('data-status') || '').toLowerCase();
            const cardSearch = (card.getAttribute('data-search') || '').toLowerCase();

            const matchesStatus = (currentInqFilter === 'all') || (cardStatus === currentInqFilter);
            const matchesQuery = !query || cardSearch.includes(query);

            if (matchesStatus && matchesQuery) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });

        const noResultsRow = document.getElementById('noFilterResultsRow');
        if (noResultsRow) {
            noResultsRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }

        const noResultsCard = document.getElementById('noFilterResultsCard');
        if (noResultsCard) {
            noResultsCard.style.display = (visibleCount === 0 && cards.length > 0) ? '' : 'none';
        }

        // Update badge indicator
        const badge = document.getElementById('activeFilterBadge');
        if (badge) {
            let label = 'All Inquiries';
            if (currentInqFilter === 'pending') label = 'Awaiting Reply';
            else if (currentInqFilter === 'responded') label = 'Stock Confirmed';
            else if (currentInqFilter === 'expired') label = 'Expired / Past 48h';
            
            badge.innerText = `${label} (${visibleCount})`;
        }
    }

    window.resetInquiryFilters = function () {
        currentInqFilter = 'all';
        const searchInput = document.getElementById('inquirySearchInput');
        if (searchInput) searchInput.value = '';

        const allFilterTriggers = document.querySelectorAll('.inq-filter-tile, .inq-pill-btn');
        allFilterTriggers.forEach(t => {
            const f = t.getAttribute('data-filter') || 'all';
            if (f === 'all') {
                t.classList.add('active-filter');
                t.classList.add('active-pill');
            } else {
                t.classList.remove('active-filter');
                t.classList.remove('active-pill');
            }
        });

        filterInquiryTable();
    };

    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('inquirySearchInput');
        const clearBtn = document.getElementById('clearSearchBtn');
        const filterTriggers = document.querySelectorAll('.inq-filter-tile, .inq-pill-btn');

        if (searchInput) {
            searchInput.addEventListener('input', filterInquiryTable);
            searchInput.addEventListener('keyup', filterInquiryTable);
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (searchInput) searchInput.value = '';
                filterInquiryTable();
                if (searchInput) searchInput.focus();
            });
        }

        filterTriggers.forEach(tile => {
            tile.addEventListener('click', function () {
                const targetFilter = this.getAttribute('data-filter') || 'all';

                // Toggle behavior: clicking already active filter resets to 'all'
                if (currentInqFilter === targetFilter && targetFilter !== 'all') {
                    currentInqFilter = 'all';
                } else {
                    currentInqFilter = targetFilter;
                }

                // Sync active classes across both desktop stat cards and mobile pills
                filterTriggers.forEach(t => {
                    const f = t.getAttribute('data-filter') || 'all';
                    if (f === currentInqFilter) {
                        t.classList.add('active-filter');
                        t.classList.add('active-pill');
                    } else {
                        t.classList.remove('active-filter');
                        t.classList.remove('active-pill');
                    }
                });

                filterInquiryTable();
            });

            // Keyboard accessibility
            tile.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    });

    // --- Dynamic Items Repeater for New Inquiry ---
    function addNewItemRow(itemName = '', qty = 10, unit = 'pcs') {
        const tbody = document.getElementById('inqItemsTableBody');
        const tr = document.createElement('tr');
        tr.className = 'inq-item-row';
        tr.innerHTML = `
            <td>
                <input type="text" class="form-control form-control-sm item-name-input" placeholder="e.g. Steel Bar 12mm" value="${escapeHtml(itemName)}" required list="inventoryCatalogList">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-qty-input text-center" min="1" value="${parseInt(qty) || 1}" required>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm item-unit-input text-center" value="${escapeHtml(unit)}" placeholder="pcs, bags, etc." required>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm touch-btn p-0 border-0" onclick="removeInquiryRow(this)" title="Remove item" aria-label="Remove item" style="width: 44px; height: 44px; border-radius: 8px;">
                    <i class="bi bi-trash fs-6"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    function removeInquiryRow(btn) {
        const tbody = document.getElementById('inqItemsTableBody');
        if (tbody.querySelectorAll('tr.inq-item-row').length > 1) {
            btn.closest('tr').remove();
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Minimum Material Required',
                text: 'At least one material item is required for the supplier inquiry.'
            });
        }
    }

    // Auto-fill destination if Project RS is chosen
    function onRsSelectChange(select) {
        const opt = select.options[select.selectedIndex];
        const address = opt.getAttribute('data-address');
        const project = opt.getAttribute('data-project');
        const destInput = document.getElementById('inqDestination');

        if (address && address.trim()) {
            destInput.value = `${project} — ${address}`;
        } else if (project && project.trim()) {
            destInput.value = `${project} Jobsite`;
        } else {
            destInput.value = 'Central Warehouse (Main Storage)';
        }

        // Fetch RS items if available
        const rsId = select.value;
        if (rsId) {
            const fd = new FormData();
            fd.append('action', 'fetch_rs_data');
            fd.append('rs_id', rsId);

            fetch('process/process.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' && data.items && data.items.length > 0) {
                        const tbody = document.getElementById('inqItemsTableBody');
                        tbody.innerHTML = '';
                        data.items.forEach(it => {
                            addNewItemRow(it.item_name || it.description, it.quantity || it.requested_qty || 1, it.unit || 'pcs');
                        });
                    }
                })
                .catch(() => {});
        }
    }

    function onSupplierSelectChange(select) {
        // Can be used for custom supplier info if needed
    }

    function onValidityDurationChange(select) {
        const customWrap = document.getElementById('customExpiryWrapper');
        const customInput = document.getElementById('inqCustomExpiry');
        if (!customWrap) return;
        if (select.value === 'custom') {
            customWrap.classList.remove('d-none');
            if (customInput) customInput.required = true;
        } else {
            customWrap.classList.add('d-none');
            if (customInput) customInput.required = false;
        }
    }

    // --- Submit New Supplier Inquiry ---
    function submitNewInquiry(e) {
        e.preventDefault();
        const form = document.getElementById('createInquiryForm');
        const btn = document.getElementById('btnSubmitNewInquiry');

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const rows = document.querySelectorAll('#inqItemsTableBody tr.inq-item-row');
        const items = [];
        rows.forEach(r => {
            const name = r.querySelector('.item-name-input').value.trim();
            const qty = parseFloat(r.querySelector('.item-qty-input').value) || 1;
            const unit = r.querySelector('.item-unit-input').value.trim() || 'pcs';
            if (name) {
                items.push({ item_name: name, requested_qty: qty, unit: unit });
            }
        });

        if (items.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Empty Materials', text: 'Please specify at least one material to inquire.' });
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating Link...';

        const fd = new FormData(form);
        fd.append('action', 'create_supplier_inquiry');
        fd.append('items', JSON.stringify(items));

        fetch('process/process.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-link-45deg me-1"></i> Generate Inquiry Link';

                if (res.status === 'success') {
                    // Close creation modal
                    bootstrap.Modal.getInstance(document.getElementById('newInquiryModal')).hide();

                    // Prompt with Share modal
                    const supplierSelect = document.getElementById('inqSupplierSelect');
                    const supplierName = supplierSelect.options[supplierSelect.selectedIndex].text;
                    const phone = supplierSelect.options[supplierSelect.selectedIndex].getAttribute('data-phone') || '';

                    openShareModal({
                        inquiry_no: res.inquiry_no,
                        company_name: supplierName,
                        phone: phone,
                        clean_url: res.portal_url,
                        expires_at: res.expires_at,
                        status: 'Pending'
                    });

                    // Reload table in background or on modal dismiss
                    document.getElementById('shareInquiryModal').addEventListener('hidden.bs.modal', function () {
                        window.location.reload();
                    }, { once: true });
                } else {
                    Swal.fire({ icon: 'error', title: 'Creation Failed', text: res.message || 'Unable to generate inquiry link.' });
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-link-45deg me-1"></i> Generate Inquiry Link';
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to communicate with the server. Please try again.' });
            });
    }

    // --- Share / Viber Modal Open ---
    function openShareModal(data) {
        document.getElementById('shareSupplierName').innerText = data.company_name || 'Valued Supplier';
        document.getElementById('shareInquiryNo').innerText = data.inquiry_no || '';
        document.getElementById('shareCleanUrlInput').value = data.clean_url || '';
        document.getElementById('shareExpiresText').innerHTML = `<i class="bi bi-clock me-1"></i>Active link expires on: <strong>${data.expires_at || '48 hours'}</strong>`;

        const viberMessage = `Hello ${data.company_name || 'Supplier'}! GB Construction & Enterprise Inc. procurement is inquiring about current material availability and quotation for Inquiry #${data.inquiry_no}.\n\nPlease tap our secure mobile link to confirm available stock and net unit pricing directly:\n${data.clean_url}\n\nThank you!`;
        document.getElementById('shareViberPreviewText').innerText = viberMessage;

        const viberBtn = document.getElementById('btnOpenViberApp');
        viberBtn.href = `viber://forward?text=${encodeURIComponent(viberMessage)}`;

        new bootstrap.Modal(document.getElementById('shareInquiryModal')).show();
    }

    function copyShareModalLink() {
        const input = document.getElementById('shareCleanUrlInput');
        copyInquiryLink(input.value);
    }

    function copyInquiryLink(url) {
        if (!url) return;
        navigator.clipboard.writeText(url).then(() => {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Link copied to clipboard!'
            });
        }).catch(() => {
            prompt('Copy inquiry link:', url);
        });
    }

    // --- View Inquiry Full Breakdown ---
    function viewInquiryDetails(inquiryId) {
        const modal = new bootstrap.Modal(document.getElementById('viewInquiryModal'));
        const modalBody = document.getElementById('viewInqModalBody');
        
        modalBody.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="text-muted mt-2 small">Loading quotation breakdown...</div>
            </div>
        `;
        modal.show();

        const fd = new FormData();
        fd.append('action', 'fetch_supplier_inquiry_details');
        fd.append('inquiry_id', inquiryId);
        fd.append('csrf_token', CSRF_TOKEN);

        fetch('process/process.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success' && res.inquiry) {
                    renderInquiryDetails(res.inquiry, res.items || []);
                } else {
                    modalBody.innerHTML = `<div class="alert alert-danger py-3">${escapeHtml(res.message || 'Unable to fetch details.')}</div>`;
                }
            })
            .catch(() => {
                modalBody.innerHTML = `<div class="alert alert-danger py-3">Network error loading inquiry details.</div>`;
            });
    }

    function renderInquiryDetails(inq, items) {
        document.getElementById('viewInqTitle').innerText = `${inq.inquiry_no} — ${inq.company_name}`;
        document.getElementById('viewInqSubtitle').innerText = `Status: ${inq.status} | Destination: ${inq.delivery_destination}`;

        let itemsHtml = '';
        let grandTotal = 0;

        items.forEach((it, idx) => {
            let statusBadge = '<span class="badge bg-secondary">Pending</span>';
            if (it.availability_status === 'Available') {
                statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Available</span>';
            } else if (it.availability_status === 'Partial') {
                statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-pie-chart me-1"></i>Partial</span>';
            } else if (it.availability_status === 'Unavailable') {
                statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Out of Stock</span>';
            }

            const availQty = it.available_qty !== null ? parseInt(it.available_qty) : '-';
            const price = it.offered_price !== null ? parseFloat(it.offered_price) : 0;
            const lineTotal = (it.available_qty !== null && it.offered_price !== null) ? (it.available_qty * it.offered_price) : 0;
            if (lineTotal > 0) grandTotal += lineTotal;

            itemsHtml += `
                <tr>
                    <td class="fw-bold">${idx + 1}. ${escapeHtml(it.item_name)}</td>
                    <td class="text-center">${parseInt(it.requested_qty)} ${escapeHtml(it.unit || 'pcs')}</td>
                    <td class="text-center">${statusBadge}</td>
                    <td class="text-center fw-bold text-dark">${availQty} ${escapeHtml(it.unit || 'pcs')}</td>
                    <td class="text-end fw-bold text-primary">${price > 0 ? '₱' + price.toFixed(2) : '-'}</td>
                    <td class="text-end fw-bold text-dark">${lineTotal > 0 ? '₱' + lineTotal.toLocaleString('en-US', {minimumFractionDigits: 2}) : '-'}</td>
                </tr>
                ${it.item_remarks ? `<tr><td colspan="6" class="small text-muted ps-4 py-1 bg-light"><em>Remarks: ${escapeHtml(it.item_remarks)}</em></td></tr>` : ''}
            `;
        });

        const html = `
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <div class="card border rounded-3 p-3 bg-white h-100">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.70rem;">Supplier Information</small>
                        <h6 class="fw-bold text-dark mb-1 mt-1">${escapeHtml(inq.company_name)}</h6>
                        <div class="small text-muted"><i class="bi bi-person me-1"></i>Contact: ${escapeHtml(inq.contact_person || 'N/A')}</div>
                        <div class="small text-muted"><i class="bi bi-telephone me-1"></i>Phone: ${escapeHtml(inq.contact_number || 'N/A')}</div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card border rounded-3 p-3 bg-white h-100">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.70rem;">Logistics & Target</small>
                        <div class="small text-dark fw-medium mt-1"><i class="bi bi-geo-alt text-primary me-1"></i>Destination: ${escapeHtml(inq.delivery_destination)}</div>
                        <div class="small text-dark fw-medium"><i class="bi bi-calendar-event text-primary me-1"></i>Target Date: ${inq.expected_delivery_date || 'ASAP'}</div>
                        <div class="small text-muted"><i class="bi bi-person-badge text-secondary me-1"></i>Purchasing Officer: ${escapeHtml(inq.purchasing_officer_name || 'Procurement Team')}</div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card border rounded-3 bg-white overflow-hidden mb-3">
                <div class="card-header bg-light py-2 px-3 fw-bold small text-secondary">
                    Quoted Materials & Confirmed Availability
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Requested</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Confirmed Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                        ${grandTotal > 0 ? `
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="5" class="text-end text-dark">Total Confirmed Value:</td>
                                    <td class="text-end text-primary fs-6">₱${grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                </tr>
                            </tfoot>
                        ` : ''}
                    </table>
                </div>
            </div>

            ${inq.supplier_notes ? `
                <div class="card border rounded-3 p-3 bg-white mb-2">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.70rem;"><i class="bi bi-chat-quote text-primary me-1"></i>Supplier Notes / Terms</small>
                    <div class="small text-dark mt-1 font-monospace" style="white-space: pre-wrap;">${escapeHtml(inq.supplier_notes)}</div>
                </div>
            ` : ''}
        `;

        document.getElementById('viewInqModalBody').innerHTML = html;
    }

    // --- Cancel Inquiry ---
    function cancelInquiry(inquiryId, inqNo) {
        Swal.fire({
            title: 'Cancel Inquiry?',
            text: `Are you sure you want to deactivate and cancel inquiry ${inqNo}? The vendor link will be disabled.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Cancel Inquiry'
        }).then(result => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'cancel_supplier_inquiry');
                fd.append('inquiry_id', inquiryId);
                fd.append('csrf_token', CSRF_TOKEN);

                fetch('process/process.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.status === 'success') {
                            Swal.fire({ icon: 'success', title: 'Cancelled', text: res.message }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Unable to cancel inquiry.' });
                        }
                    })
                    .catch(() => {
                        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to communicate with server.' });
                    });
            }
        });
    }

    // =========================================================================
    // INQUIRY LINK EXPIRATION SETTINGS MODAL MANAGEMENT
    // =========================================================================
    function updateExpiryLivePreview(hours) {
        hours = parseInt(hours) || 48;
        if (hours < 1) hours = 1;
        const now = new Date();
        const expiryDate = new Date(now.getTime() + (hours * 60 * 60 * 1000));
        const options = { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric', 
            hour: 'numeric', 
            minute: '2-digit', 
            hour12: true 
        };
        const formatted = expiryDate.toLocaleDateString('en-US', options);
        const liveTimeEl = document.getElementById('settingsExpiryLiveTime');
        if (liveTimeEl) liveTimeEl.innerText = formatted;

        const badgeEl = document.getElementById('settingsExpiryHoursBadge');
        if (badgeEl) {
            const days = (hours / 24).toFixed(hours % 24 === 0 ? 0 : 1);
            badgeEl.innerText = `${hours} Hours (${days} Days)`;
        }
    }

    function selectExpiryOption(hours, cardEl) {
        document.querySelectorAll('#expiryOptionsGrid .expiry-opt-card').forEach(c => {
            c.classList.remove('selected-expiry');
        });
        if (cardEl) cardEl.classList.add('selected-expiry');

        const customContainer = document.getElementById('customHoursInputContainer');
        const hiddenHoursInput = document.getElementById('selectedDefaultExpiryHours');
        const customInput = document.getElementById('customExpiryHoursInput');

        if (hours === 'custom') {
            if (customContainer) customContainer.classList.remove('d-none');
            const customVal = customInput ? (parseInt(customInput.value) || 48) : 48;
            if (hiddenHoursInput) hiddenHoursInput.value = customVal;
            if (customInput) customInput.focus();
            updateExpiryLivePreview(customVal);
        } else {
            if (customContainer) customContainer.classList.add('d-none');
            if (hiddenHoursInput) hiddenHoursInput.value = hours;
            if (customInput) customInput.value = hours;
            updateExpiryLivePreview(hours);
        }
    }

    function onCustomHoursInput(val) {
        const hours = parseInt(val) || 48;
        const hiddenHoursInput = document.getElementById('selectedDefaultExpiryHours');
        if (hiddenHoursInput) hiddenHoursInput.value = hours;
        updateExpiryLivePreview(hours);
    }

    function resetInqSettingsToDefault() {
        const card48 = document.querySelector('#expiryOptionsGrid .expiry-opt-card[data-hours="48"]');
        selectExpiryOption(48, card48);
        const switchEl = document.getElementById('allowCustomExpirySwitch');
        if (switchEl) switchEl.checked = true;
    }

    function saveInquirySettings(e) {
        e.preventDefault();
        const form = document.getElementById('inquirySettingsForm');
        const btn = document.getElementById('saveInqSettingsBtn');
        const hoursInput = document.getElementById('selectedDefaultExpiryHours');
        const allowCustomSwitch = document.getElementById('allowCustomExpirySwitch');

        const hours = parseInt(hoursInput ? hoursInput.value : 48) || 48;
        const allowCustom = (allowCustomSwitch && allowCustomSwitch.checked) ? '1' : '0';

        if (hours < 1 || hours > 720) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Duration',
                text: 'Expiration period must be between 1 and 720 hours (up to 30 days).'
            });
            return;
        }

        const originalBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Saving Settings...';

        const fd = new FormData();
        fd.append('action', 'save_inquiry_settings');
        fd.append('default_expiry_hours', hours);
        fd.append('allow_custom_expiry', allowCustom);
        fd.append('csrf_token', CSRF_TOKEN);

        fetch('process/process.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;

            if (res.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Settings Saved',
                    text: res.message,
                    timer: 2000,
                    showConfirmButton: false
                });

                // Close Settings Modal
                const settingsModalEl = document.getElementById('inquirySettingsModal');
                if (settingsModalEl) {
                    const inst = bootstrap.Modal.getInstance(settingsModalEl);
                    if (inst) inst.hide();
                }

                // Update summary badge
                const activeBadge = document.getElementById('activeSettingSummaryBadge');
                if (activeBadge) activeBadge.innerText = `${hours}h Default`;

                // Update New Inquiry Modal select dropdown
                const modalSelect = document.getElementById('inqValidityDuration');
                if (modalSelect) {
                    let optionExists = false;
                    for (let opt of modalSelect.options) {
                        if (opt.value === String(hours)) {
                            opt.selected = true;
                            optionExists = true;
                        }
                    }
                    if (!optionExists && hours > 0) {
                        const newOpt = new Option(`${hours} Hours (Configured Default)`, String(hours), true, true);
                        modalSelect.add(newOpt, 2);
                    }

                    // Update custom option visibility according to allowCustom
                    let customOpt = modalSelect.querySelector('option[value="custom"]');
                    if (allowCustom === '1') {
                        if (!customOpt) {
                            modalSelect.add(new Option('Custom Date & Time...', 'custom'));
                        }
                    } else {
                        if (customOpt) customOpt.remove();
                        const customWrap = document.getElementById('customExpiryWrapper');
                        if (customWrap) customWrap.classList.add('d-none');
                    }
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed to Save',
                    text: res.message || 'Unable to save settings.'
                });
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: 'Failed to reach the server. Please check your connection.'
            });
        });
    }

    // =========================================================================
    // MODAL LIFECYCLE MANAGEMENT (per cims-modal-ajax-handler standard)
    // =========================================================================
    document.addEventListener('DOMContentLoaded', function () {
        const inqSettingsModalEl = document.getElementById('inquirySettingsModal');
        if (inqSettingsModalEl) {
            inqSettingsModalEl.addEventListener('shown.bs.modal', function () {
                const activeHours = document.getElementById('selectedDefaultExpiryHours');
                if (activeHours) updateExpiryLivePreview(activeHours.value);
            });
        }

        const newInquiryModalEl = document.getElementById('newInquiryModal');
        if (newInquiryModalEl) {
            // 1. Accessibility: Auto-focus the first editable input when opened
            newInquiryModalEl.addEventListener('shown.bs.modal', function () {
                const firstInput = document.getElementById('inqSupplierSelect');
                if (firstInput) firstInput.focus();
            });

            // 2. Clean up: Reset form and dynamic preview states when closed
            newInquiryModalEl.addEventListener('hidden.bs.modal', function () {
                const form = document.getElementById('createInquiryForm');
                if (form) {
                    form.reset();
                    form.classList.remove('was-validated');
                }
                // Reset items table to default 1 empty row
                const tbody = document.getElementById('inqItemsTableBody');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr class="inq-item-row">
                            <td>
                                <input type="text" class="form-control form-control-sm item-name-input" placeholder="e.g. Portland Cement Type 1" required list="inventoryCatalogList">
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm item-qty-input text-center" min="1" value="10" required>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm item-unit-input text-center" value="pcs" placeholder="pcs, bags, etc." required>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-sm touch-btn p-0 border-0" onclick="removeInquiryRow(this)" title="Remove item" aria-label="Remove item" style="width: 44px; height: 44px; border-radius: 8px;">
                                    <i class="bi bi-trash fs-6"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }
                const destInput = document.getElementById('inqDestination');
                if (destInput) destInput.value = 'Central Warehouse (Main Storage)';

                // Reset expiration validity controls using active configured default
                const defaultHoursConfig = '<?= $defaultExpiryHours ?>';
                const validitySelect = document.getElementById('inqValidityDuration');
                if (validitySelect) validitySelect.value = defaultHoursConfig;
                const customExpiryWrap = document.getElementById('customExpiryWrapper');
                if (customExpiryWrap) customExpiryWrap.classList.add('d-none');
                const customInput = document.getElementById('inqCustomExpiry');
                if (customInput) customInput.required = false;
            });
        }

        const viewInquiryModalEl = document.getElementById('viewInquiryModal');
        if (viewInquiryModalEl) {
            viewInquiryModalEl.addEventListener('hidden.bs.modal', function () {
                const modalBody = document.getElementById('viewInqModalBody');
                if (modalBody) {
                    modalBody.innerHTML = `
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <div class="text-muted mt-2 small">Loading quotation details...</div>
                        </div>
                    `;
                }
            });
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>

<?php include 'layout/footer.php'; ?>
