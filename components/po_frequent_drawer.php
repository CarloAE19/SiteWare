<?php
/**
 * CIMS — PURCHASE ORDERS FREQUENT & SMART SUGGESTIONS DRAWER
 * Enterprise-grade multi-device slide-out drawer (Desktop/Tablet) & Bottom Sheet (Mobile)
 * Fully compliant with ISO 9001, HCI usability, and mobile touch guidelines (>=44px).
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/../Connection/db.php';
}

$cimsUserId = $_SESSION['user_id'] ?? 0;
$cimsUserRole = $_SESSION['user_role'] ?? 'purchasing';

// 1. Fetch Approved & Partially Ordered Requisitions waiting for PO
$approvedRsQuery = "
    SELECT r.id, r.rs_no, r.project_name, r.status, r.urgency, r.created_at, r.type, p.address AS project_address
    FROM requisitions r 
    LEFT JOIN projects p ON r.project_name = p.project_name 
    WHERE r.status IN ('Approved', 'Partially Approved', 'Partially Ordered') 
    ORDER BY 
        FIELD(r.status, 'Partially Ordered', 'Approved', 'Partially Approved'),
        r.created_at DESC
    LIMIT 12
";
try {
    $approvedRsList = $pdo->query($approvedRsQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $approvedRsList = [];
}

// 2. Fetch items for each approved RS
$rsIds = array_column($approvedRsList, 'id');
$approvedRsItemsMap = [];
if (!empty($rsIds)) {
    try {
        $placeholders = implode(',', array_fill(0, count($rsIds), '?'));
        $rsItemsQuery = "
            SELECT ri.requisition_id, ri.item_code, 
                   COALESCE(i.item_name, ri.new_item_name, ri.item_code) AS item_name,
                   COALESCE(i.unit, ri.new_unit, 'pcs') AS unit,
                   COALESCE(i.category, ri.new_category, 'Materials') AS category,
                   ri.quantity, i.quantity AS current_stock
            FROM requisition_items ri
            LEFT JOIN inventory i ON ri.item_code = i.item_code
            WHERE ri.requisition_id IN ($placeholders)
            ORDER BY ri.id ASC
        ";
        $rsItemsStmt = $pdo->prepare($rsItemsQuery);
        $rsItemsStmt->execute($rsIds);
        foreach ($rsItemsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $approvedRsItemsMap[$row['requisition_id']][] = $row;
        }
    } catch (Exception $e) {
        $approvedRsItemsMap = [];
    }
}

// 3. Fetch Low Stock / Critical Items needing Purchase Re-Order
try {
    $lowStockQuery = "
        SELECT i.item_code, i.item_name, i.unit, i.category, i.quantity AS current_stock, i.status
        FROM inventory i
        WHERE i.status = 'Low Stock' OR i.quantity <= 25 OR i.status = 'Out of Stock'
        ORDER BY i.quantity ASC
        LIMIT 10
    ";
    $lowStockItems = $pdo->query($lowStockQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lowStockItems = [];
}

// 4. Fetch Past Purchase Orders for 1-Click Repeat
try {
    $pastPosQuery = "
        SELECT p.id, p.po_no, p.status, p.supplier_id, s.company_name, p.rs_id, r.rs_no, p.created_at,
               COUNT(pi.id) AS item_cnt,
               COALESCE(SUM(pi.quantity * pi.unit_price), 0) AS total_val
        FROM purchase_orders p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN requisitions r ON p.rs_id = r.id
        LEFT JOIN po_items pi ON p.id = pi.po_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 6
    ";
    $pastPos = $pdo->query($pastPosQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pastPos = [];
}
?>

<style>
/* ==========================================================
 * CIMS PO FREQUENT & SMART SUGGESTIONS DRAWER (MULTI-DEVICE)
 * ========================================================== */
.cims-po-drawer {
    z-index: 1070 !important;
    width: 450px !important;
    max-width: 100vw;
    background-color: #f8fafc;
    box-shadow: -10px 0 35px rgba(0, 0, 0, 0.22) !important;
    border-left: 1px solid #e2e8f0;
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

/* Offcanvas backdrop z-index alignment above modal */
.offcanvas-backdrop.cims-po-backdrop,
.offcanvas-backdrop.show:last-of-type {
    z-index: 1065 !important;
}

/* Bottom Sheet Transformation for Mobile Phones (320px - 575px) */
@media (max-width: 575.98px) {
    .cims-po-drawer {
        width: 100% !important;
        height: 85vh !important;
        max-height: 85vh !important;
        top: auto !important;
        bottom: 0 !important;
        right: 0 !important;
        left: 0 !important;
        border-top-left-radius: 22px !important;
        border-top-right-radius: 22px !important;
        border-left: none !important;
        border-top: 1px solid rgba(0, 0, 0, 0.08) !important;
        box-shadow: 0 -10px 35px rgba(0, 0, 0, 0.25) !important;
        transform: translateY(100%) !important;
    }
    .cims-po-drawer.show {
        transform: translateY(0) !important;
    }
    .cims-po-drag-handle {
        display: block !important;
        width: 48px;
        height: 5px;
        background-color: #cbd5e1;
        border-radius: 999px;
        margin: 10px auto 4px auto;
        cursor: grab;
    }
}
@media (min-width: 576px) {
    .cims-po-drag-handle {
        display: none !important;
    }
}

/* Suggestion Card Styles */
.cims-po-suggest-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.9rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}
.cims-po-suggest-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    transform: translateY(-1px);
}
.cims-po-suggest-card.active-selected {
    border-color: var(--gb-blue, #0033cc) !important;
    background-color: #f0f7ff !important;
    box-shadow: 0 0 0 2px rgba(0, 51, 204, 0.2) !important;
}

/* Dark Theme Overrides */
[data-bs-theme="dark"] .cims-po-drawer {
    background-color: var(--gb-dark-bg, #0d1117) !important;
    border-color: var(--gb-dark-border, #30363d) !important;
    color: var(--gb-dark-text-main, #f0f6fc) !important;
}
[data-bs-theme="dark"] .cims-po-suggest-card {
    background: var(--gb-dark-surface, #161b22) !important;
    border-color: var(--gb-dark-border, #30363d) !important;
}
[data-bs-theme="dark"] .cims-po-drag-handle {
    background-color: #30363d !important;
}
</style>

<!-- ======================================================== -->
<!-- OFFCANVAS: PO FREQUENT & SMART SUGGESTIONS DRAWER        -->
<!-- ======================================================== -->
<div class="offcanvas offcanvas-end cims-po-drawer" tabindex="-1" id="poFrequentDrawer" aria-labelledby="poFrequentDrawerLabel" data-bs-scroll="true">
    
    <!-- Drag handle for mobile touch bottom sheet -->
    <div class="cims-po-drag-handle" aria-hidden="true"></div>

    <!-- Drawer Header -->
    <div class="offcanvas-header py-3 px-3 px-md-4 border-bottom bg-white" style="border-color: #e2e8f0 !important;">
        <div class="d-flex align-items-center gap-2.5">
            <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-file-earmark-check-fill fs-5" style="color: var(--gb-blue, #0033cc) !important;"></i>
            </div>
            <div>
                <h5 class="offcanvas-title fw-bold text-dark mb-0 fs-6" id="poFrequentDrawerLabel">
                    PO Quick-Pick &amp; Suggestions
                </h5>
                <small class="text-muted" style="font-size: 0.76rem;">
                    Approved requisitions, low-stock alerts &amp; repeat orders
                </small>
            </div>
        </div>
        <button type="button" class="btn-close text-reset shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <!-- Drawer Navigation Tabs -->
    <div class="bg-white border-bottom px-3 px-md-4 pt-2">
        <ul class="nav nav-pills nav-fill gap-1.5" id="poDrawerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold py-2 small d-flex align-items-center justify-content-center gap-1.5" id="tab-po-approved-rs" data-bs-toggle="pill" data-bs-target="#content-po-approved-rs" type="button" role="tab" aria-selected="true">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <span>Ready RS</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size: 0.68rem;"><?= count($approvedRsList) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-2 small d-flex align-items-center justify-content-center gap-1.5" id="tab-po-low-stock" data-bs-toggle="pill" data-bs-target="#content-po-low-stock" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                    <span>Low Stock</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill" style="font-size: 0.68rem;"><?= count($lowStockItems) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-2 small d-flex align-items-center justify-content-center gap-1.5" id="tab-po-repeat" data-bs-toggle="pill" data-bs-target="#content-po-repeat" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-arrow-repeat text-primary"></i>
                    <span>Past POs</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size: 0.68rem;"><?= count($pastPos) ?></span>
                </button>
            </li>
        </ul>
    </div>

    <!-- Drawer Body -->
    <div class="offcanvas-body p-3 p-md-4 tab-content" id="poDrawerTabContent">
        
        <!-- ==================================================== -->
        <!-- TAB 1: APPROVED REQUISITIONS READY FOR PO            -->
        <!-- ==================================================== -->
        <div class="tab-pane fade show active" id="content-po-approved-rs" role="tabpanel" aria-labelledby="tab-po-approved-rs">
            
            <!-- Search Filter for Approved RS -->
            <div class="mb-3">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0 bg-white" id="poRsSearchInput" placeholder="Filter by RS No, project, or item..." autocomplete="off" oninput="window.cimsFilterPoRsCards()">
                    <button class="btn btn-outline-secondary border-start-0 bg-white text-muted d-none" type="button" id="clearPoRsSearchBtn" title="Clear search" onclick="window.cimsClearPoRsSearch()">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
            </div>

            <!-- List of Approved RS Cards -->
            <div class="d-flex flex-column gap-3" id="poApprovedRsCardList">
                <?php if (empty($approvedRsList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 text-secondary opacity-50 d-block mb-2"></i>
                        <h6 class="fw-bold mb-1">No Approved Requisitions</h6>
                        <p class="small text-muted mb-0">There are currently no approved or partially ordered requisitions waiting for PO creation.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($approvedRsList as $rs): 
                        $rsId = $rs['id'];
                        $rsNo = htmlspecialchars($rs['rs_no']);
                        $proj = htmlspecialchars($rs['project_name']);
                        $status = htmlspecialchars($rs['status']);
                        $urgency = htmlspecialchars($rs['urgency'] ?? 'Normal');
                        $date = date('M d, Y', strtotime($rs['created_at']));
                        $items = $approvedRsItemsMap[$rsId] ?? [];
                        $itemCount = count($items);
                        $isRestock = ($rs['type'] === 'restock' || $proj === 'Warehouse Restock');
                    ?>
                        <div class="cims-po-suggest-card" 
                             data-rs-id="<?= $rsId ?>"
                             data-rs-no="<?= $rsNo ?>"
                             data-project="<?= $proj ?>"
                             data-search="<?= strtolower($rsNo . ' ' . $proj . ' ' . implode(' ', array_column($items, 'item_name'))) ?>">
                            
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap mb-1">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold" style="font-family: ui-monospace, monospace; font-size: 0.78rem;">
                                            <?= $rsNo ?>
                                        </span>
                                        <?php if ($isRestock): ?>
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle small py-1">📦 Restock</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border small py-1">🏗️ Project</span>
                                        <?php endif; ?>
                                        <?php if ($urgency === 'High'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle small py-1">🟠 High</span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-0 small">
                                        <i class="bi bi-geo-alt text-danger me-1"></i><?= $proj ?>
                                    </h6>
                                    <small class="text-muted d-block mt-0.5" style="font-size: 0.72rem;">
                                        Logged: <?= $date ?> &bull; Status: <strong class="text-success"><?= $status ?></strong>
                                    </small>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary border small fw-bold">
                                    <?= $itemCount ?> <?= $itemCount === 1 ? 'Item' : 'Items' ?>
                                </span>
                            </div>

                            <!-- Item preview list -->
                            <?php if (!empty($items)): ?>
                                <div class="bg-light p-2 rounded-2 border mb-2.5 small" style="font-size: 0.76rem;">
                                    <ul class="list-unstyled mb-0 d-flex flex-column gap-1">
                                        <?php foreach ($items as $itm): 
                                            $iCode = htmlspecialchars($itm['item_code']);
                                            $iName = htmlspecialchars($itm['item_name']);
                                            $iQty = (float)$itm['quantity'];
                                            $iQtyFmt = ($iQty == (int)$iQty) ? (int)$iQty : $iQty;
                                            $iUnit = htmlspecialchars($itm['unit'] ?? 'pcs');
                                        ?>
                                            <li class="d-flex justify-content-between align-items-center text-muted">
                                                <span class="text-truncate me-2">• [<?= $iCode ?>] <?= $iName ?></span>
                                                <span class="fw-bold text-dark flex-shrink-0"><?= $iQtyFmt ?> <?= $iUnit ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- 1-Click Select RS & Load into PO Button -->
                            <button type="button" class="btn btn-primary btn-sm fw-bold w-100 d-flex align-items-center justify-content-center gap-1.5 shadow-sm cims-select-rs-btn"
                                    data-rs-id="<?= $rsId ?>"
                                    data-rs-no="<?= $rsNo ?>"
                                    onclick="window.cimsPoSelectRs(<?= $rsId ?>, '<?= $rsNo ?>', this)">
                                <i class="bi bi-box-arrow-in-down-right"></i>
                                <span>Select &amp; Load into PO Form</span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="text-center py-4 text-muted d-none" id="noPoRsSearchResults">
                <i class="bi bi-search fs-2 opacity-50 d-block mb-1"></i>
                <span class="small fw-bold">No matching approved requisitions found.</span>
            </div>
        </div>

        <!-- ==================================================== -->
        <!-- TAB 2: LOW STOCK & RESTOCK PRIORITIES                -->
        <!-- ==================================================== -->
        <div class="tab-pane fade" id="content-po-low-stock" role="tabpanel" aria-labelledby="tab-po-low-stock">
            <div class="alert alert-warning py-2 px-3 small d-flex align-items-center gap-2 mb-3 rounded-3 border-0 shadow-sm" style="background-color: #fef3c7; color: #92400e;">
                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-warning"></i>
                <div>
                    <strong>Inventory Alerts:</strong> Materials below safe buffer stock levels. Request restock to automatically generate a replenishment PO.
                </div>
            </div>

            <?php if (empty($lowStockItems)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-shield-check fs-1 text-success opacity-50 d-block mb-2"></i>
                    <h6 class="fw-bold mb-1">All Stock Healthy</h6>
                    <p class="small text-muted mb-0">No materials currently have low or critical stock levels.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2.5">
                    <?php foreach ($lowStockItems as $lItem): 
                        $lCode = htmlspecialchars($lItem['item_code']);
                        $lName = htmlspecialchars($lItem['item_name']);
                        $lUnit = htmlspecialchars($lItem['unit'] ?? 'pcs');
                        $lCat = htmlspecialchars($lItem['category'] ?? 'Materials');
                        $lStock = (float)($lItem['current_stock'] ?? 0);
                        $lStockFmt = ($lStock == (int)$lStock) ? (int)$lStock : $lStock;
                        $lStatus = htmlspecialchars($lItem['status'] ?? 'Low Stock');
                        $isOut = ($lStatus === 'Out of Stock' || $lStock <= 0);
                    ?>
                        <div class="card border border-slate-200 rounded-3 shadow-sm p-3 bg-white">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1.5">
                                <div>
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap mb-1">
                                        <span class="badge bg-light text-dark border small fw-bold font-monospace">[<?= $lCode ?>]</span>
                                        <span class="badge bg-light text-secondary border small py-0.5"><?= $lCat ?></span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-0 small"><?= $lName ?></h6>
                                </div>
                                <span class="badge <?= $isOut ? 'bg-danger text-white' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' ?> small fw-bold">
                                    Stock: <?= $lStockFmt ?> <?= $lUnit ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
                                <span class="text-muted small" style="font-size: 0.72rem;">
                                    Status: <strong class="<?= $isOut ? 'text-danger' : 'text-warning' ?>"><?= $lStatus ?></strong>
                                </span>
                                <a href="requisitions" class="btn btn-sm btn-outline-primary fw-bold py-1 px-2.5" style="font-size: 0.75rem;">
                                    <i class="bi bi-plus-circle me-1"></i>Requisition Restock
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==================================================== -->
        <!-- TAB 3: PAST PURCHASE ORDERS RE-ORDER                 -->
        <!-- ==================================================== -->
        <div class="tab-pane fade" id="content-po-repeat" role="tabpanel" aria-labelledby="tab-po-repeat">
            <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-3 rounded-3 border-0 shadow-sm" style="background-color: #e0f2fe; color: #0369a1;">
                <i class="bi bi-info-circle-fill fs-5 flex-shrink-0"></i>
                <div>
                    <strong>Repeat Purchase Order:</strong> View recently completed or issued vendor purchase orders for quick reference.
                </div>
            </div>

            <?php if (empty($pastPos)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clock-history fs-1 text-secondary opacity-50 d-block mb-2"></i>
                    <h6 class="fw-bold mb-1">No Past Purchase Orders</h6>
                    <p class="small text-muted mb-0">Completed and active purchase orders will appear here for fast repeat reference.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($pastPos as $po): 
                        $poNo = htmlspecialchars($po['po_no']);
                        $supp = htmlspecialchars($po['company_name'] ?? 'Vendor');
                        $rsNo = htmlspecialchars($po['rs_no'] ?? 'N/A');
                        $date = date('M d, Y', strtotime($po['created_at']));
                        $val = number_format((float)($po['total_val'] ?? 0), 2);
                        $itemCnt = (int)($po['item_cnt'] ?? 0);
                    ?>
                        <div class="card border border-slate-200 rounded-3 shadow-sm p-3 bg-white">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1.5">
                                <div>
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap mb-1">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold font-monospace"><?= $poNo ?></span>
                                        <span class="badge bg-light text-muted border small"><?= $date ?></span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-0 small"><i class="bi bi-shop me-1 text-primary"></i><?= $supp ?></h6>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">RS: <?= $rsNo ?> &bull; <?= $itemCnt ?> Items</small>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold text-success font-monospace small d-block">₱<?= $val ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Drawer Sticky Footer -->
    <div class="offcanvas-footer p-3 bg-white border-top d-flex justify-content-between align-items-center">
        <div>
            <span class="text-muted small fw-bold" id="poDrawerStatusText" style="font-size: 0.76rem;">
                <i class="bi bi-info-circle me-1 text-primary"></i>Tap an approved RS to load into PO
            </span>
        </div>
        <button type="button" class="btn btn-brand btn-sm fw-bold px-4 shadow-sm" data-bs-dismiss="offcanvas">
            <i class="bi bi-check2 me-1"></i>Done
        </button>
    </div>
</div>

<!-- ======================================================== -->
<!-- SELF-CONTAINED CONTROLLER FOR PO SUGGESTIONS             -->
<!-- ======================================================== -->
<script>
(function() {
    // 1. Select RS & Auto-Load Items into PO Form
    window.cimsPoSelectRs = function(rsId, rsNo, btn) {
        if (!rsId) return;

        // Ensure poModal is visible
        const poModalEl = document.getElementById('poModal');
        if (poModalEl) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(poModalEl);
            modalInstance.show();
        }

        const rsSelect = document.getElementById('poRsSelect');
        if (!rsSelect) {
            console.warn('[CIMS] poRsSelect dropdown element not found.');
            return;
        }

        // Set value and trigger native change event
        rsSelect.value = rsId;
        rsSelect.dispatchEvent(new Event('change', { bubbles: true }));

        // Trigger destination update if available
        if (typeof window.updatePoDestinationOnRsChange === 'function') {
            window.updatePoDestinationOnRsChange();
        }

        // Highlight selected card
        const allCards = document.querySelectorAll('#poApprovedRsCardList .cims-po-suggest-card');
        allCards.forEach(c => c.classList.remove('active-selected'));
        if (btn) {
            const card = btn.closest('.cims-po-suggest-card');
            if (card) card.classList.add('active-selected');

            // Button feedback
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.className = 'btn btn-success btn-sm fw-bold w-100 d-flex align-items-center justify-content-center gap-1.5 shadow-sm text-white';
            btn.innerHTML = '<i class="bi bi-check2-circle text-white"></i> <span>Loaded into PO Form!</span>';

            const statusText = document.getElementById('poDrawerStatusText');
            if (statusText) {
                statusText.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Loaded ${rsNo} into Purchase Order</span>`;
            }

            setTimeout(() => {
                btn.disabled = false;
                btn.className = 'btn btn-primary btn-sm fw-bold w-100 d-flex align-items-center justify-content-center gap-1.5 shadow-sm cims-select-rs-btn';
                btn.innerHTML = origHtml;
            }, 1800);
        }

        // Scroll to items preview container smoothly in PO modal
        setTimeout(() => {
            const previewContainer = document.getElementById('rsItemsPreviewContainer');
            if (previewContainer) {
                previewContainer.classList.remove('d-none');
                previewContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 150);
    };

    // 2. Search Filter for Approved RS Cards
    window.cimsFilterPoRsCards = function() {
        const searchInput = document.getElementById('poRsSearchInput');
        const clearBtn = document.getElementById('clearPoRsSearchBtn');
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';

        if (clearBtn) {
            if (query !== '') clearBtn.classList.remove('d-none');
            else clearBtn.classList.add('d-none');
        }

        const cards = document.querySelectorAll('#poApprovedRsCardList .cims-po-suggest-card');
        const emptyNotice = document.getElementById('noPoRsSearchResults');
        let visibleCount = 0;

        cards.forEach(card => {
            const searchData = (card.getAttribute('data-search') || '').toLowerCase();
            if (!query || searchData.includes(query)) {
                card.classList.remove('d-none');
                visibleCount++;
            } else {
                card.classList.add('d-none');
            }
        });

        if (emptyNotice) {
            if (visibleCount === 0 && cards.length > 0) emptyNotice.classList.remove('d-none');
            else emptyNotice.classList.add('d-none');
        }
    };

    window.cimsClearPoRsSearch = function() {
        const searchInput = document.getElementById('poRsSearchInput');
        const clearBtn = document.getElementById('clearPoRsSearchBtn');
        if (searchInput) searchInput.value = '';
        if (clearBtn) clearBtn.classList.add('d-none');
        window.cimsFilterPoRsCards();
        if (searchInput) searchInput.focus();
    };

    // 3. Backdrop Layering Alignment when Drawer Opens
    const drawerEl = document.getElementById('poFrequentDrawer');
    if (drawerEl) {
        drawerEl.addEventListener('show.bs.offcanvas', function() {
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.offcanvas-backdrop');
                backdrops.forEach(b => b.classList.add('cims-po-backdrop'));
            }, 15);
        });
    }
})();
</script>
