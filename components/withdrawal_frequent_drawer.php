<?php
/**
 * CIMS — MATERIAL WITHDRAWALS FREQUENT & SMART SUGGESTIONS DRAWER
 * Enterprise-grade multi-device slide-out drawer (Desktop/Tablet) & Bottom Sheet (Mobile)
 * Designed for warehouse personnel to quickly pick and release staged/approved requisitions.
 * Fully compliant with ISO 9001, HCI usability, and mobile touch guidelines (>=44px).
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/../Connection/db.php';
}

$cimsUserId = $_SESSION['user_id'] ?? 0;
$cimsUserRole = $_SESSION['user_role'] ?? 'warehouse';

// 1. Fetch Approved, PO Created, or Staged Requisitions waiting for material release
$readyRsQuery = "
    SELECT r.id, r.rs_no, r.project_name, r.requestor_name, r.status, r.urgency, r.created_at, r.type
    FROM requisitions r
    WHERE r.status IN ('Approved', 'PO Created', 'Staged (Ready for Pickup)')
    ORDER BY 
        FIELD(r.status, 'Staged (Ready for Pickup)', 'Approved', 'PO Created'),
        r.created_at DESC
    LIMIT 15
";
try {
    $readyRsList = $pdo->query($readyRsQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $readyRsList = [];
}

// 2. Fetch items for each ready RS
$rsIds = array_column($readyRsList, 'id');
$readyRsItemsMap = [];
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
            $readyRsItemsMap[$row['requisition_id']][] = $row;
        }
    } catch (Exception $e) {
        $readyRsItemsMap = [];
    }
}

// 3. Fetch Top Frequently Withdrawn Materials (Fast-moving inventory)
try {
    $freqWdQuery = "
        SELECT wi.item_code, 
               COALESCE(i.item_name, wi.item_code) AS item_name,
               COALESCE(i.unit, 'pcs') AS unit,
               COALESCE(i.category, 'General') AS category,
               COALESCE(i.quantity, 0) AS current_stock,
               COUNT(wi.id) AS withdrawal_count,
               SUM(wi.quantity) AS total_withdrawn
        FROM withdrawal_items wi
        LEFT JOIN inventory i ON wi.item_code = i.item_code
        GROUP BY wi.item_code, i.item_name, i.unit, i.category, i.quantity
        ORDER BY withdrawal_count DESC, total_withdrawn DESC
        LIMIT 10
    ";
    $freqWdItems = $pdo->query($freqWdQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $freqWdItems = [];
}

// 4. Fetch Recent Material Withdrawals for Fast Audit / Verification
try {
    $recentWdQuery = "
        SELECT w.id, w.withdrawal_no, w.project_name, w.received_by, w.date_withdrawn,
               u.name AS releaser_name,
               COUNT(wi.id) AS item_cnt,
               COALESCE(SUM(wi.quantity), 0) AS total_qty
        FROM withdrawals w
        LEFT JOIN users u ON w.released_by = u.id
        LEFT JOIN withdrawal_items wi ON w.id = wi.withdrawal_id
        GROUP BY w.id
        ORDER BY w.date_withdrawn DESC
        LIMIT 6
    ";
    $recentWdList = $pdo->query($recentWdQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentWdList = [];
}
?>

<style>
/* ==========================================================
 * CIMS WD FREQUENT & SMART SUGGESTIONS DRAWER (MULTI-DEVICE)
 * ========================================================== */
.cims-wd-drawer {
    z-index: 1070 !important;
    width: 450px !important;
    max-width: 100vw;
    box-shadow: -4px 0 25px rgba(0, 0, 0, 0.15);
    border-left: 1px solid rgba(0, 0, 0, 0.08);
}

.cims-wd-backdrop {
    z-index: 1065 !important;
}

/* Tab Pills in Drawer */
.cims-wd-nav-pills .nav-link {
    font-size: 0.80rem;
    font-weight: 700;
    color: #475569;
    border-radius: 8px;
    padding: 0.45rem 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}
.cims-wd-nav-pills .nav-link.active {
    background-color: var(--gb-blue, #003366);
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(0, 51, 102, 0.25);
}
.cims-wd-nav-pills .nav-link:hover:not(.active) {
    background-color: #f1f5f9;
    color: #0f172a;
}

/* Card Styling */
.cims-wd-suggest-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.85rem;
    background: #ffffff;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}
.cims-wd-suggest-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-color: #cbd5e1;
}
.cims-wd-suggest-card.active-selected {
    border-color: #10b981;
    background-color: #f0fdf4;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}

/* Native Bottom Sheet on Mobile (<576px) */
@media (max-width: 575.98px) {
    .cims-wd-drawer.offcanvas-end {
        top: auto !important;
        right: 0 !important;
        left: 0 !important;
        bottom: 0 !important;
        width: 100% !important;
        max-width: 100vw !important;
        height: 85vh !important;
        border-radius: 20px 20px 0 0 !important;
        border-left: none !important;
        border-top: 1px solid rgba(0, 0, 0, 0.12) !important;
        transform: translateY(100%);
    }
    .cims-wd-drawer.offcanvas-end.show {
        transform: translateY(0) !important;
    }
    .cims-wd-mobile-handle {
        display: block !important;
    }
}

.cims-wd-mobile-handle {
    display: none;
    width: 44px;
    height: 5px;
    background-color: #cbd5e1;
    border-radius: 10px;
    margin: 8px auto 4px auto;
}
</style>

<!-- ======================================================== -->
<!-- OFFCANVAS DRAWER: MATERIAL WITHDRAWALS SMART SUGGESTIONS -->
<!-- ======================================================== -->
<div class="offcanvas offcanvas-end cims-wd-drawer bg-light" tabindex="-1" id="wdFrequentDrawer" aria-labelledby="wdFrequentDrawerLabel" data-bs-backdrop="true" data-bs-scroll="true">
    
    <!-- Mobile Drag Handle indicator -->
    <div class="cims-wd-mobile-handle"></div>

    <!-- Drawer Header -->
    <div class="offcanvas-header bg-white border-bottom py-3 px-3">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="bi bi-lightning-charge-fill text-warning fs-5"></i>
            </div>
            <div>
                <h6 class="offcanvas-title fw-bold text-dark mb-0" id="wdFrequentDrawerLabel" style="font-size: 0.95rem;">
                    Quick-Pick &amp; Staged Releases
                </h6>
                <small class="text-muted d-block" style="font-size: 0.72rem;">1-Click load approved slips into withdrawal form</small>
            </div>
        </div>
        <button type="button" class="btn-close text-reset shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <!-- Navigation Pills (3 Categorized Views) -->
    <div class="bg-white border-bottom px-3 pt-2 pb-2">
        <ul class="nav nav-pills cims-wd-nav-pills gap-1 w-100" id="wdDrawerTabs" role="tablist">
            <li class="nav-item flex-fill text-center" role="presentation">
                <button class="nav-link w-100 justify-content-center active py-1.5" id="tab-wd-ready-rs" data-bs-toggle="pill" data-bs-target="#content-wd-ready-rs" type="button" role="tab" aria-controls="content-wd-ready-rs" aria-selected="true">
                    <i class="bi bi-box-seam text-warning"></i>
                    <span>Ready for Pickup</span>
                    <span class="badge bg-white text-dark ms-1 rounded-pill" style="font-size: 0.65rem;"><?= count($readyRsList) ?></span>
                </button>
            </li>
            <li class="nav-item flex-fill text-center" role="presentation">
                <button class="nav-link w-100 justify-content-center py-1.5" id="tab-wd-freq-items" data-bs-toggle="pill" data-bs-target="#content-wd-freq-items" type="button" role="tab" aria-controls="content-wd-freq-items" aria-selected="false">
                    <i class="bi bi-fire text-danger"></i>
                    <span>Frequent Items</span>
                </button>
            </li>
            <li class="nav-item flex-fill text-center" role="presentation">
                <button class="nav-link w-100 justify-content-center py-1.5" id="tab-wd-recent-slips" data-bs-toggle="pill" data-bs-target="#content-wd-recent-slips" type="button" role="tab" aria-controls="content-wd-recent-slips" aria-selected="false">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span>Recent WD</span>
                </button>
            </li>
        </ul>
    </div>

    <!-- Drawer Body -->
    <div class="offcanvas-body p-3 overflow-y-auto">
        <div class="tab-content" id="wdDrawerTabContent">
            
            <!-- ==================================================== -->
            <!-- TAB 1: READY FOR PICKUP / APPROVED RS LIST           -->
            <!-- ==================================================== -->
            <div class="tab-pane fade show active" id="content-wd-ready-rs" role="tabpanel" aria-labelledby="tab-wd-ready-rs">
                
                <!-- Search Filter for Staged RS -->
                <div class="mb-3">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0 bg-white" id="wdRsSearchInput" placeholder="Filter by RS No, project, or item..." autocomplete="off" oninput="window.cimsFilterWdRsCards()">
                        <button class="btn btn-outline-secondary border-start-0 bg-white text-muted d-none" type="button" id="clearWdRsSearchBtn" title="Clear search" onclick="window.cimsClearWdRsSearch()">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>
                </div>

                <!-- List of Staged/Approved RS Cards -->
                <div class="d-flex flex-column gap-3" id="wdReadyRsCardList">
                    <?php if (empty($readyRsList)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-check2-circle fs-1 text-success opacity-50 d-block mb-2"></i>
                            <h6 class="fw-bold mb-1">All Requisitions Fulfilled</h6>
                            <p class="small text-muted mb-0">No approved or staged requisitions are currently waiting for material withdrawal release.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($readyRsList as $rs): 
                            $rsId = $rs['id'];
                            $rsNo = htmlspecialchars($rs['rs_no']);
                            $proj = htmlspecialchars($rs['project_name']);
                            $req = htmlspecialchars($rs['requestor_name'] ?? 'N/A');
                            $status = htmlspecialchars($rs['status']);
                            $urgency = htmlspecialchars($rs['urgency'] ?? 'Normal');
                            $date = date('M d, Y', strtotime($rs['created_at']));
                            $items = $readyRsItemsMap[$rsId] ?? [];
                            $itemCount = count($items);
                            $isStaged = ($status === 'Staged (Ready for Pickup)');
                            $isRestock = ($rs['type'] === 'restock' || $proj === 'Warehouse Restock');
                        ?>
                            <div class="cims-wd-suggest-card" 
                                 data-rs-id="<?= $rsId ?>"
                                 data-rs-no="<?= $rsNo ?>"
                                 data-project="<?= $proj ?>"
                                 data-search="<?= strtolower($rsNo . ' ' . $proj . ' ' . $req . ' ' . implode(' ', array_column($items, 'item_name'))) ?>">
                                
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap mb-1">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold" style="font-family: ui-monospace, monospace; font-size: 0.78rem;">
                                                <?= $rsNo ?>
                                            </span>
                                            <?php if ($isStaged): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle small py-1">📦 Staged / Ready</span>
                                            <?php elseif ($isRestock): ?>
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle small py-1">🔄 Restock</span>
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
                                            Requestor: <strong><?= $req ?></strong> &bull; <?= $date ?>
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

                                <!-- 1-Click Release RS Button -->
                                <button type="button" class="btn btn-primary btn-sm fw-bold w-100 d-flex align-items-center justify-content-center gap-1.5 shadow-sm cims-select-wd-rs-btn"
                                        style="min-height: 42px;"
                                        data-rs-no="<?= $rsNo ?>"
                                        onclick="window.cimsWdSelectRs('<?= $rsNo ?>', this)">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span>Release This RS (Load into Form)</span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="text-center py-4 text-muted d-none" id="noWdRsSearchResults">
                    <i class="bi bi-search fs-2 opacity-50 d-block mb-1"></i>
                    <span class="small fw-bold">No matching requisitions found.</span>
                </div>
            </div>

            <!-- ==================================================== -->
            <!-- TAB 2: FREQUENTLY WITHDRAWN MATERIALS                -->
            <!-- ==================================================== -->
            <div class="tab-pane fade" id="content-wd-freq-items" role="tabpanel" aria-labelledby="tab-wd-freq-items">
                <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-3 rounded-3 border-0 shadow-sm" style="background-color: #e0f2fe; color: #0369a1;">
                    <i class="bi bi-fire fs-5 flex-shrink-0 text-danger"></i>
                    <div>
                        <strong>High Velocity Materials:</strong> Most frequently issued items across active construction projects.
                    </div>
                </div>

                <?php if (empty($freqWdItems)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 text-secondary opacity-50 d-block mb-2"></i>
                        <h6 class="fw-bold mb-1">No Withdrawal History</h6>
                        <p class="small text-muted mb-0">Frequently withdrawn materials will appear once releases are recorded.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2.5">
                        <?php foreach ($freqWdItems as $fItem): 
                            $fCode = htmlspecialchars($fItem['item_code']);
                            $fName = htmlspecialchars($fItem['item_name']);
                            $fUnit = htmlspecialchars($fItem['unit'] ?? 'pcs');
                            $fCat = htmlspecialchars($fItem['category'] ?? 'Materials');
                            $fStock = (float)($fItem['current_stock'] ?? 0);
                            $fStockFmt = ($fStock == (int)$fStock) ? (int)$fStock : $fStock;
                            $fCount = (int)$fItem['withdrawal_count'];
                            $fTotal = (float)$fItem['total_withdrawn'];
                            $fTotalFmt = ($fTotal == (int)$fTotal) ? (int)$fTotal : $fTotal;
                            $isLowStock = ($fStock <= 25);
                        ?>
                            <div class="card border border-slate-200 rounded-3 shadow-sm p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-1.5">
                                    <div>
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap mb-1">
                                            <span class="badge bg-light text-dark border small fw-bold font-monospace">[<?= $fCode ?>]</span>
                                            <span class="badge bg-light text-secondary border small py-0.5"><?= $fCat ?></span>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle small py-0.5">
                                                <i class="bi bi-fire me-0.5"></i><?= $fCount ?> <?= $fCount === 1 ? 'Release' : 'Releases' ?>
                                            </span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-0 small"><?= $fName ?></h6>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?= $isLowStock ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-success-subtle text-success border border-success-subtle' ?> small fw-bold">
                                            Stock: <?= $fStockFmt ?> <?= $fUnit ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
                                    <small class="text-muted" style="font-size: 0.72rem;">
                                        Total Issued: <strong class="text-dark"><?= $fTotalFmt ?> <?= $fUnit ?></strong>
                                    </small>
                                    <span class="badge bg-light text-muted border small">Fast Mover</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ==================================================== -->
            <!-- TAB 3: RECENT MATERIAL WITHDRAWALS                   -->
            <!-- ==================================================== -->
            <div class="tab-pane fade" id="content-wd-recent-slips" role="tabpanel" aria-labelledby="tab-wd-recent-slips">
                <div class="alert alert-primary py-2 px-3 small d-flex align-items-center gap-2 mb-3 rounded-3 border-0 shadow-sm" style="background-color: #eff6ff; color: #1d4ed8;">
                    <i class="bi bi-shield-check fs-5 flex-shrink-0 text-primary"></i>
                    <div>
                        <strong>Audit Log &amp; Verification:</strong> Recent material releases with digital signatures &amp; photo receipts.
                    </div>
                </div>

                <?php if (empty($recentWdList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clock-history fs-1 text-secondary opacity-50 d-block mb-2"></i>
                        <h6 class="fw-bold mb-1">No Recent Withdrawals</h6>
                        <p class="small text-muted mb-0">Logged material withdrawal slips will appear here for fast audit review.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($recentWdList as $rWd): 
                            $wdNo = htmlspecialchars($rWd['withdrawal_no']);
                            $proj = htmlspecialchars($rWd['project_name']);
                            $rec = htmlspecialchars($rWd['received_by'] ?? 'N/A');
                            $rel = htmlspecialchars($rWd['releaser_name'] ?? 'Warehouse');
                            $date = date('M d, Y h:i A', strtotime($rWd['date_withdrawn']));
                            $cnt = (int)$rWd['item_cnt'];
                            $totQty = (float)$rWd['total_qty'];
                            $totQtyFmt = ($totQty == (int)$totQty) ? (int)$totQty : $totQty;
                        ?>
                            <div class="card border border-slate-200 rounded-3 shadow-sm p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-1.5">
                                    <div>
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap mb-1">
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold font-monospace"><?= $wdNo ?></span>
                                            <span class="badge bg-light text-muted border small"><?= $date ?></span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-0 small"><i class="bi bi-geo-alt me-1 text-danger"></i><?= $proj ?></h6>
                                        <small class="text-muted d-block mt-0.5" style="font-size: 0.72rem;">
                                            Receiver: <strong><?= $rec ?></strong> &bull; Released by: <?= $rel ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-secondary-subtle text-secondary border small fw-bold">
                                            <?= $cnt ?> <?= $cnt === 1 ? 'Item' : 'Items' ?> (<?= $totQtyFmt ?> Qty)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Drawer Sticky Footer -->
    <div class="offcanvas-footer p-3 bg-white border-top d-flex justify-content-between align-items-center">
        <div>
            <span class="text-muted small fw-bold" id="wdDrawerStatusText" style="font-size: 0.76rem;">
                <i class="bi bi-info-circle me-1 text-primary"></i>Tap an approved RS to load into form
            </span>
        </div>
        <button type="button" class="btn btn-brand btn-sm fw-bold px-4 shadow-sm" data-bs-dismiss="offcanvas">
            <i class="bi bi-check2 me-1"></i>Done
        </button>
    </div>
</div>

<!-- ======================================================== -->
<!-- SELF-CONTAINED CONTROLLER FOR WITHDRAWAL SUGGESTIONS     -->
<!-- ======================================================== -->
<script>
(function() {
    // 1. Select RS & Auto-Load Items into Material Withdrawal Form
    window.cimsWdSelectRs = function(rsNo, btn) {
        if (!rsNo) return;

        // Ensure withdrawModal is visible
        const wdModalEl = document.getElementById('withdrawModal');
        if (wdModalEl) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(wdModalEl);
            modalInstance.show();
        }

        const inputField = document.getElementById('manualRsInputText');
        if (inputField) {
            inputField.value = rsNo;
        }

        // Call the built-in loader function
        if (typeof window.loadRsDataToWithdrawalForm === 'function') {
            window.loadRsDataToWithdrawalForm(rsNo);
        } else if (typeof window.lookupManualRsInput === 'function') {
            window.lookupManualRsInput();
        }

        // Highlight selected card
        const allCards = document.querySelectorAll('#wdReadyRsCardList .cims-wd-suggest-card');
        allCards.forEach(c => c.classList.remove('active-selected'));
        if (btn) {
            const card = btn.closest('.cims-wd-suggest-card');
            if (card) card.classList.add('active-selected');

            // Button feedback
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.className = 'btn btn-success btn-sm fw-bold w-100 d-flex align-items-center justify-content-center gap-1.5 shadow-sm text-white';
            btn.innerHTML = '<i class="bi bi-check2-circle text-white"></i> <span>Loaded into Release Form!</span>';

            const statusText = document.getElementById('wdDrawerStatusText');
            if (statusText) {
                statusText.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Loaded ${rsNo} into Release Form</span>`;
            }

            setTimeout(() => {
                btn.disabled = false;
                btn.className = 'btn btn-primary btn-sm fw-bold w-100 d-flex align-items-center justify-content-center gap-1.5 shadow-sm cims-select-wd-rs-btn';
                btn.innerHTML = origHtml;
            }, 1800);
        }

        // Scroll smoothly to materials container inside the modal
        setTimeout(() => {
            const matContainer = document.getElementById('wdMaterialsContainer');
            if (matContainer) {
                matContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 200);
    };

    // 2. Search Filter for Staged / Ready RS Cards
    window.cimsFilterWdRsCards = function() {
        const searchInput = document.getElementById('wdRsSearchInput');
        const clearBtn = document.getElementById('clearWdRsSearchBtn');
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';

        if (clearBtn) {
            if (query !== '') clearBtn.classList.remove('d-none');
            else clearBtn.classList.add('d-none');
        }

        const cards = document.querySelectorAll('#wdReadyRsCardList .cims-wd-suggest-card');
        const emptyNotice = document.getElementById('noWdRsSearchResults');
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

    window.cimsClearWdRsSearch = function() {
        const searchInput = document.getElementById('wdRsSearchInput');
        const clearBtn = document.getElementById('clearWdRsSearchBtn');
        if (searchInput) searchInput.value = '';
        if (clearBtn) clearBtn.classList.add('d-none');
        window.cimsFilterWdRsCards();
        if (searchInput) searchInput.focus();
    };

    // 3. Backdrop Layering Alignment when Drawer Opens
    const drawerEl = document.getElementById('wdFrequentDrawer');
    if (drawerEl) {
        drawerEl.addEventListener('show.bs.offcanvas', function() {
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.offcanvas-backdrop');
                backdrops.forEach(b => b.classList.add('cims-wd-backdrop'));
            }, 15);
        });
    }
})();
</script>
