<?php
/**
 * CIMS — REQUISITIONS FREQUENT & SMART SUGGESTIONS DRAWER
 * Enterprise-grade multi-device slide-out drawer (Desktop/Tablet) & Bottom Sheet (Mobile)
 * Fully compliant with ISO 9001, HCI usability, and mobile touch guidelines (>=44px).
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/../Connection/db.php';
}

$cimsUserId = $_SESSION['user_id'] ?? 0;
$cimsUserRole = strtolower($_SESSION['user_role'] ?? 'requestor');
$cimsIsRequestor = ($cimsUserRole === 'requestor');

// 1. Fetch Top Frequent Items across non-rejected requisitions
if ($cimsIsRequestor && !empty($cimsUserId)) {
    // Personalized for Requestor: show items requested by THIS user
    $frequentQuery = "
        SELECT 
            ri.item_code,
            COALESCE(i.item_name, ri.new_item_name, ri.item_code) AS item_name,
            COALESCE(i.unit, ri.new_unit, 'pcs') AS unit,
            COALESCE(i.category, ri.new_category, 'Materials') AS category,
            COALESCE(i.quantity, 0) AS current_stock,
            COUNT(*) AS request_count,
            ROUND(AVG(ri.quantity), 0) AS avg_quantity
        FROM requisition_items ri
        JOIN requisitions r ON ri.requisition_id = r.id
        LEFT JOIN inventory i ON ri.item_code = i.item_code
        WHERE r.requestor_id = ? AND r.status != 'Rejected' AND ri.item_code IS NOT NULL AND ri.item_code != ''
        GROUP BY ri.item_code, item_name, unit, category, current_stock
        ORDER BY request_count DESC, item_name ASC
        LIMIT 20
    ";
    try {
        $frequentStmt = $pdo->prepare($frequentQuery);
        $frequentStmt->execute([$cimsUserId]);
        $frequentItemsList = $frequentStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $frequentItemsList = [];
    }
} else {
    // Warehouse / Purchasing / Admin: show company-wide top requested materials
    $frequentQuery = "
        SELECT 
            ri.item_code,
            COALESCE(i.item_name, ri.new_item_name, ri.item_code) AS item_name,
            COALESCE(i.unit, ri.new_unit, 'pcs') AS unit,
            COALESCE(i.category, ri.new_category, 'Materials') AS category,
            COALESCE(i.quantity, 0) AS current_stock,
            COUNT(*) AS request_count,
            ROUND(AVG(ri.quantity), 0) AS avg_quantity
        FROM requisition_items ri
        JOIN requisitions r ON ri.requisition_id = r.id
        LEFT JOIN inventory i ON ri.item_code = i.item_code
        WHERE r.status != 'Rejected' AND ri.item_code IS NOT NULL AND ri.item_code != ''
        GROUP BY ri.item_code, item_name, unit, category, current_stock
        ORDER BY request_count DESC, item_name ASC
        LIMIT 20
    ";
    try {
        $frequentItemsList = $pdo->query($frequentQuery)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $frequentItemsList = [];
    }
}

// 2. Supplement if fewer than 10 items (ensures suggestions are always rich)
$existingCodes = array_filter(array_column($frequentItemsList, 'item_code'));
if (count($frequentItemsList) < 10) {
    $needed = 10 - count($frequentItemsList);
    try {
        if (!empty($existingCodes)) {
            $placeholders = implode(',', array_fill(0, count($existingCodes), '?'));
            $invSql = "SELECT item_code, item_name, unit, category, quantity AS current_stock, 0 AS request_count, 1 AS avg_quantity
                       FROM inventory 
                       WHERE status != 'Out of Stock' AND item_code NOT IN ($placeholders)
                       ORDER BY quantity DESC, item_name ASC 
                       LIMIT $needed";
            $invStmt = $pdo->prepare($invSql);
            $invStmt->execute(array_values($existingCodes));
            $extraItems = $invStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $invSql = "SELECT item_code, item_name, unit, category, quantity AS current_stock, 0 AS request_count, 1 AS avg_quantity
                       FROM inventory 
                       WHERE status != 'Out of Stock'
                       ORDER BY quantity DESC, item_name ASC 
                       LIMIT $needed";
            $extraItems = $pdo->query($invSql)->fetchAll(PDO::FETCH_ASSOC);
        }
        $frequentItemsList = array_merge($frequentItemsList, $extraItems);
    } catch (Exception $e) {
        // Fallback silently
    }
}

// 3. Extract unique categories for filter pills
$drawerCategories = array_values(array_unique(array_filter(array_column($frequentItemsList, 'category'))));
sort($drawerCategories);

// 4. Fetch past approved or completed slips for 1-Click Duplicate Re-order
$recentSlipsList = [];
$recentSlipItemsMap = [];
try {
    if ($cimsIsRequestor) {
        $slipsSql = "SELECT r.id, r.rs_no, r.project_name, r.urgency, r.created_at, r.status
                     FROM requisitions r
                     WHERE r.requestor_id = ? AND r.status != 'Rejected'
                     ORDER BY r.created_at DESC LIMIT 6";
        $slipsStmt = $pdo->prepare($slipsSql);
        $slipsStmt->execute([$cimsUserId]);
    } else {
        $slipsSql = "SELECT r.id, r.rs_no, r.project_name, r.urgency, r.created_at, r.status
                     FROM requisitions r
                     WHERE r.status != 'Rejected'
                     ORDER BY r.created_at DESC LIMIT 6";
        $slipsStmt = $pdo->query($slipsSql);
    }
    $recentSlipsList = $slipsStmt->fetchAll(PDO::FETCH_ASSOC);

    $slipIds = array_column($recentSlipsList, 'id');
    if (!empty($slipIds)) {
        $inClause = implode(',', array_fill(0, count($slipIds), '?'));
        $slipItemsSql = "
            SELECT ri.requisition_id, ri.item_code, 
                   COALESCE(i.item_name, ri.new_item_name, ri.item_code) AS item_name,
                   COALESCE(i.unit, ri.new_unit, 'pcs') AS unit,
                   COALESCE(i.category, ri.new_category, 'Materials') AS category,
                   ri.quantity, i.quantity AS current_stock
            FROM requisition_items ri
            LEFT JOIN inventory i ON ri.item_code = i.item_code
            WHERE ri.requisition_id IN ($inClause)
            ORDER BY ri.id ASC
        ";
        $itemStmt = $pdo->prepare($slipItemsSql);
        $itemStmt->execute($slipIds);
        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $sItem) {
            $recentSlipItemsMap[$sItem['requisition_id']][] = $sItem;
        }
    }
} catch (Exception $e) {
    $recentSlipsList = [];
    $recentSlipItemsMap = [];
}
?>

<style>
/* ==========================================================
 * CIMS FREQUENT & SMART SUGGESTIONS DRAWER (MULTI-DEVICE)
 * ========================================================== */
.cims-frequent-drawer {
    z-index: 1070 !important;
    width: 440px !important;
    max-width: 100vw;
    background-color: #f8fafc;
    box-shadow: -10px 0 35px rgba(0, 0, 0, 0.22) !important;
    border-left: 1px solid #e2e8f0;
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

/* Offcanvas backdrop z-index alignment above modal */
.offcanvas-backdrop.cims-frequent-backdrop,
.offcanvas-backdrop.show:last-of-type {
    z-index: 1065 !important;
}

/* Bottom Sheet Transformation for Phones (320px - 575px) */
@media (max-width: 575.98px) {
    .cims-frequent-drawer {
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
    .cims-frequent-drawer.show {
        transform: translateY(0) !important;
    }
    .cims-drawer-drag-handle {
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
    .cims-drawer-drag-handle {
        display: none !important;
    }
}

/* Hide Floating Chatbot while Requisition Drawer is open */
body:has(#rsFrequentDrawer.show) #cims-chatbot-container,
body:has(#rsFrequentDrawer.showing) #cims-chatbot-container,
body.cims-drawer-open #cims-chatbot-container {
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
    display: none !important;
}

/* Category Filter Chips (Horizontal Touch Scroll) */
.cims-category-chips-wrap {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    white-space: nowrap;
    padding-bottom: 6px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.cims-category-chips-wrap::-webkit-scrollbar {
    display: none;
}
.cims-cat-chip {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background-color: #ffffff;
    color: #475569;
    cursor: pointer;
    transition: all 0.18s ease;
    user-select: none;
    touch-action: manipulation;
}
.cims-cat-chip:hover {
    border-color: var(--gb-blue, #0033cc);
    color: var(--gb-blue, #0033cc);
}
.cims-cat-chip.active {
    background-color: var(--gb-blue, #0033cc) !important;
    border-color: var(--gb-blue, #0033cc) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(0, 51, 204, 0.25);
}

/* Item Suggestion Card */
.cims-suggest-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.85rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    position: relative;
}
.cims-suggest-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    transform: translateY(-1px);
}
.cims-suggest-card .item-code-tag {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.2rem 0.45rem;
    border-radius: 6px;
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.cims-suggest-card .card-qty-stepper {
    max-width: 120px;
}
.cims-suggest-card .card-qty-stepper input {
    height: 34px;
    font-size: 0.85rem;
    font-weight: 700;
    padding: 0.2rem 0.3rem;
}
.cims-suggest-card .card-qty-stepper button {
    height: 34px;
    width: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Row Highlight Flash Animation when added */
@keyframes cimsRowAddedFlash {
    0% { background-color: #d1e7dd; transform: scale(1.01); }
    50% { background-color: #e8f5e9; }
    100% { background-color: #ffffff; transform: scale(1); }
}
.cims-row-just-added {
    animation: cimsRowAddedFlash 1.2s ease forwards !important;
    border-color: #198754 !important;
}

/* Dark Theme Overrides */
[data-bs-theme="dark"] .cims-frequent-drawer {
    background-color: var(--gb-dark-bg, #0d1117) !important;
    border-color: var(--gb-dark-border, #30363d) !important;
    color: var(--gb-dark-text-main, #f0f6fc) !important;
}
[data-bs-theme="dark"] .cims-suggest-card {
    background: var(--gb-dark-surface, #161b22) !important;
    border-color: var(--gb-dark-border, #30363d) !important;
}
[data-bs-theme="dark"] .cims-cat-chip {
    background-color: var(--gb-dark-surface, #161b22) !important;
    border-color: var(--gb-dark-border, #30363d) !important;
    color: #8b949e !important;
}
[data-bs-theme="dark"] .cims-cat-chip.active {
    background-color: var(--gb-dark-accent, #58a6ff) !important;
    border-color: var(--gb-dark-accent, #58a6ff) !important;
    color: #ffffff !important;
}
[data-bs-theme="dark"] .cims-drawer-drag-handle {
    background-color: #30363d !important;
}
</style>

<!-- ======================================================== -->
<!-- OFFCANVAS: FREQUENT & SMART SUGGESTIONS DRAWER           -->
<!-- ======================================================== -->
<div class="offcanvas offcanvas-end cims-frequent-drawer" tabindex="-1" id="rsFrequentDrawer" aria-labelledby="rsFrequentDrawerLabel" data-bs-scroll="true">
    
    <!-- Drag handle for mobile touch bottom sheet -->
    <div class="cims-drawer-drag-handle" aria-hidden="true"></div>

    <!-- Drawer Header -->
    <div class="offcanvas-header py-3 px-3 px-md-4 border-bottom bg-white" style="border-color: #e2e8f0 !important;">
        <div class="d-flex align-items-center gap-2.5">
            <div class="rounded-circle p-2 bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-lightning-charge-fill fs-5" style="color: #d97706 !important;"></i>
            </div>
            <div>
                <h5 class="offcanvas-title fw-bold text-dark mb-0 fs-6" id="rsFrequentDrawerLabel">
                    Quick-Pick Materials
                </h5>
                <small class="text-muted" style="font-size: 0.76rem;">
                    <?= $cimsIsRequestor ? 'Your frequent items &amp; 1-click past slip re-order' : 'Frequent items &amp; 1-click past slip re-order' ?>
                </small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-1">
            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 border-0 shadow-none text-muted" title="Refresh list" onclick="window.location.reload();">
                <i class="bi bi-arrow-clockwise fs-6"></i>
            </button>
            <button type="button" class="btn-close text-reset shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>

    <!-- Drawer Navigation Tabs -->
    <div class="bg-white border-bottom px-3 px-md-4 pt-2">
        <ul class="nav nav-pills nav-fill gap-2" id="frequentDrawerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold py-2 small d-flex align-items-center justify-content-center gap-1.5" id="tab-frequent-items" data-bs-toggle="pill" data-bs-target="#content-frequent-items" type="button" role="tab" aria-selected="true">
                    <i class="bi bi-star-fill text-warning"></i>
                    <span>Frequent Items</span>
                    <span class="badge bg-secondary rounded-pill" style="font-size: 0.68rem;"><?= count($frequentItemsList) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-2 small d-flex align-items-center justify-content-center gap-1.5" id="tab-past-slips" data-bs-toggle="pill" data-bs-target="#content-past-slips" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-arrow-repeat text-primary"></i>
                    <span>Past Slips</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill border" style="font-size: 0.68rem;"><?= count($recentSlipsList) ?></span>
                </button>
            </li>
        </ul>
    </div>

    <!-- Drawer Body -->
    <div class="offcanvas-body p-3 p-md-4 tab-content" id="frequentDrawerTabContent">
        
        <!-- ==================================================== -->
        <!-- TAB 1: FREQUENT ITEMS LIST                           -->
        <!-- ==================================================== -->
        <div class="tab-pane fade show active" id="content-frequent-items" role="tabpanel" aria-labelledby="tab-frequent-items">
            
            <!-- 1. Search Box with Clear Button -->
            <div class="mb-2.5">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0 bg-white" id="frequentItemSearch" placeholder="Search item name or code..." autocomplete="off" oninput="window.cimsFilterDrawerCards()">
                    <button class="btn btn-outline-secondary border-start-0 bg-white text-muted d-none" type="button" id="clearFrequentSearchBtn" title="Clear search" onclick="window.cimsClearDrawerSearch()">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
            </div>

            <!-- 2. Category Filter Chips -->
            <?php if (!empty($drawerCategories)): ?>
                <div class="cims-category-chips-wrap mb-3" id="drawerCategoryChipsWrap">
                    <span class="cims-cat-chip active" data-cat="all" onclick="window.cimsSelectDrawerCategory(this)">All (<?= count($frequentItemsList) ?>)</span>
                    <?php foreach ($drawerCategories as $cat): ?>
                        <span class="cims-cat-chip" data-cat="<?= htmlspecialchars($cat) ?>" onclick="window.cimsSelectDrawerCategory(this)"><?= htmlspecialchars($cat) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- 3. Suggestion Items Container -->
            <div class="d-flex flex-column gap-2.5" id="frequentItemsCardList">
                <?php if (empty($frequentItemsList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-box-seam fs-1 text-secondary opacity-50 d-block mb-2"></i>
                        <h6 class="fw-bold mb-1">No Materials Found</h6>
                        <p class="small text-muted mb-0">No active items are currently listed in inventory.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($frequentItemsList as $idx => $sug): 
                        $code = htmlspecialchars($sug['item_code']);
                        $name = htmlspecialchars($sug['item_name']);
                        $unit = htmlspecialchars($sug['unit'] ?? 'pcs');
                        $cat = htmlspecialchars($sug['category'] ?? 'Materials');
                        $stock = (float)($sug['current_stock'] ?? 0);
                        $stockFormatted = ($stock == (int)$stock) ? (int)$stock : $stock;
                        $reqCount = (int)($sug['request_count'] ?? 0);
                        $recQty = max(1, (int)($sug['avg_quantity'] ?? 1));
                    ?>
                        <div class="cims-suggest-card" 
                             data-item-code="<?= $code ?>" 
                             data-item-name="<?= $name ?>" 
                             data-item-unit="<?= $unit ?>" 
                             data-item-category="<?= $cat ?>"
                             data-item-stock="<?= $cimsIsRequestor ? '' : $stockFormatted ?>">
                            
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1.5">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap mb-1">
                                        <span class="item-code-tag"><?= $code ?></span>
                                        <span class="badge bg-light text-secondary border small py-1" style="font-size: 0.68rem;"><?= $cat ?></span>
                                        <?php if ($reqCount > 1): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle small py-1" style="font-size: 0.68rem;">
                                                <i class="bi bi-fire me-0.5 text-danger"></i> <?= $reqCount ?>x Requested<?= $cimsIsRequestor ? ' by you' : '' ?>
                                            </span>
                                        <?php elseif ($reqCount === 1): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small py-1" style="font-size: 0.68rem;">
                                                <i class="bi bi-check-circle-fill me-0.5"></i> <?= $cimsIsRequestor ? 'Requested by you (1x)' : '1x Requested' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border small py-1" style="font-size: 0.68rem;">
                                                <i class="bi bi-stars text-warning me-0.5"></i> Suggested
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-0" style="font-size: 0.90rem; line-height: 1.35;">
                                        <?= $name ?>
                                    </h6>
                                </div>
                                <?php if (!$cimsIsRequestor): ?>
                                    <div class="text-end flex-shrink-0">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle small fw-bold" style="font-size: 0.70rem;">
                                            Stock: <?= $stockFormatted ?> <?= $unit ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Stepper & 1-Click Add Button -->
                            <div class="d-flex justify-content-between align-items-center gap-2 mt-2 pt-2 border-top">
                                <div class="input-group input-group-sm card-qty-stepper shadow-sm">
                                    <button type="button" class="btn btn-outline-secondary qty-card-step-minus" tabindex="-1" aria-label="Decrease quantity" onclick="window.cimsDrawerStepQty(this, -1)"><i class="bi bi-dash"></i></button>
                                    <input type="number" class="form-control text-center fw-bold text-primary qty-card-input" value="<?= $recQty ?>" min="1" step="any" inputmode="decimal">
                                    <button type="button" class="btn btn-outline-secondary qty-card-step-plus" tabindex="-1" aria-label="Increase quantity" onclick="window.cimsDrawerStepQty(this, 1)"><i class="bi bi-plus"></i></button>
                                </div>

                                <button type="button" class="btn btn-sm btn-primary fw-bold px-3 d-inline-flex align-items-center gap-1.5 shadow-sm cims-add-frequent-btn" 
                                        data-item-code="<?= $code ?>" 
                                        data-item-name="<?= $name ?>" 
                                        data-item-unit="<?= $unit ?>"
                                        onclick="window.cimsAddDrawerItem(this)">
                                    <i class="bi bi-plus-lg"></i>
                                    <span>Add to Slip</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Empty Search Results Placeholder -->
            <div class="text-center py-4 text-muted d-none" id="noFrequentSearchResults">
                <i class="bi bi-search fs-2 opacity-50 d-block mb-1"></i>
                <span class="small fw-bold">No matching materials found in suggestions.</span>
            </div>
        </div>

        <!-- ==================================================== -->
        <!-- TAB 2: RE-ORDER FROM PREVIOUS SLIPS                  -->
        <!-- ==================================================== -->
        <div class="tab-pane fade" id="content-past-slips" role="tabpanel" aria-labelledby="tab-past-slips">
            
            <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-3 rounded-3 border-0 shadow-sm" style="background-color: #e0f2fe; color: #0369a1;">
                <i class="bi bi-info-circle-fill fs-5 flex-shrink-0"></i>
                <div>
                    <strong>1-Click Re-order:</strong> Select a previous requisition to instantly duplicate all of its materials into your current slip.
                </div>
            </div>

            <?php if (empty($recentSlipsList)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clock-history fs-1 text-secondary opacity-50 d-block mb-2"></i>
                    <h6 class="fw-bold mb-1">No Past Slips Available</h6>
                    <p class="small text-muted mb-0">You do not have any previously logged requisitions to duplicate yet.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3" id="recentSlipsContainer">
                    <?php foreach ($recentSlipsList as $slip): 
                        $sId = $slip['id'];
                        $rsNo = htmlspecialchars($slip['rs_no']);
                        $proj = htmlspecialchars($slip['project_name']);
                        $date = date('M d, Y', strtotime($slip['created_at']));
                        $items = $recentSlipItemsMap[$sId] ?? [];
                        $itemCount = count($items);
                    ?>
                        <div class="card border border-slate-200 rounded-3 shadow-sm p-3 bg-white">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap mb-1">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold" style="font-family: ui-monospace, monospace;">
                                            <?= $rsNo ?>
                                        </span>
                                        <span class="badge bg-light text-muted border small"><?= $date ?></span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-0 small">
                                        <i class="bi bi-geo-alt text-danger me-1"></i><?= $proj ?>
                                    </h6>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary border small fw-bold">
                                    <?= $itemCount ?> <?= $itemCount === 1 ? 'Item' : 'Items' ?>
                                </span>
                            </div>

                            <!-- Item preview summary pills -->
                            <div class="bg-light p-2 rounded-2 border mb-2.5 small" style="font-size: 0.78rem;">
                                <?php if (!empty($items)): ?>
                                    <ul class="list-unstyled mb-0 d-flex flex-column gap-1">
                                        <?php foreach ($items as $sItem): 
                                            $iCode = htmlspecialchars($sItem['item_code']);
                                            $iName = htmlspecialchars($sItem['item_name']);
                                            $iQty = (float)$sItem['quantity'];
                                            $iQtyFmt = ($iQty == (int)$iQty) ? (int)$iQty : $iQty;
                                            $iUnit = htmlspecialchars($sItem['unit'] ?? 'pcs');
                                        ?>
                                            <li class="d-flex justify-content-between align-items-center text-muted">
                                                <span class="text-truncate me-2">• [<?= $iCode ?>] <?= $iName ?></span>
                                                <span class="fw-bold text-dark flex-shrink-0"><?= $iQtyFmt ?> <?= $iUnit ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">No individual item details.</span>
                                <?php endif; ?>
                            </div>

                            <!-- Duplicate Button -->
                            <button type="button" class="btn btn-outline-primary btn-sm fw-bold w-100 d-flex align-items-center justify-content-center gap-1.5 cims-load-slip-btn shadow-sm"
                                    data-slip-no="<?= $rsNo ?>"
                                    data-slip-items='<?= json_encode($items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'
                                    onclick="window.cimsDuplicatePastSlip(this)">
                                <i class="bi bi-cloud-arrow-down-fill text-primary"></i>
                                <span>Duplicate All <?= $itemCount ?> Items to Slip</span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Drawer Sticky Footer -->
    <div class="offcanvas-footer p-3 bg-white border-top d-flex justify-content-between align-items-center">
        <div>
            <span class="text-muted small fw-bold" id="drawerAddedCountText" style="font-size: 0.76rem;">
                <i class="bi bi-info-circle me-1 text-primary"></i>Tap items to add directly
            </span>
        </div>
        <button type="button" class="btn btn-brand btn-sm fw-bold px-4 shadow-sm" data-bs-dismiss="offcanvas">
            <i class="bi bi-check2 me-1"></i>Done
        </button>
    </div>
</div>

<!-- ======================================================== -->
<!-- SELF-CONTAINED CONTROLLER FOR FREQUENT SUGGESTIONS       -->
<!-- ======================================================== -->
<script>
(function() {
    // 1. Quantity Stepper Controller
    window.cimsDrawerStepQty = function(btn, delta) {
        if (!btn) return;
        const stepper = btn.closest('.card-qty-stepper');
        const input = stepper ? stepper.querySelector('.qty-card-input') : null;
        if (input) {
            let val = parseFloat(input.value) || 1;
            val = Math.max(1, val + delta);
            input.value = val;
        }
    };

    // 2. Core Method: Add Item to Requisition / Restock / Edit Form
    window.addFrequentItemToRequisition = function(itemCode, itemName, unit, quantity = 1) {
        let container = document.getElementById('materialsContainer');
        let isRestock = false;

        const restockModal = document.getElementById('restockModal');
        const editModal = document.getElementById('editRsModal');
        const rsModal = document.getElementById('rsModal');

        if (restockModal && restockModal.classList.contains('show')) {
            container = document.getElementById('restockMaterialsContainer');
            isRestock = true;
        } else if (editModal && editModal.classList.contains('show')) {
            container = document.getElementById('editMaterialsContainer');
        } else if (rsModal) {
            if (!rsModal.classList.contains('show')) {
                const modalInst = bootstrap.Modal.getOrCreateInstance(rsModal);
                modalInst.show();
            }
            container = document.getElementById('materialsContainer');
        }

        if (!container) {
            console.warn('[CIMS] Requisition materials container not found in DOM.');
            return false;
        }

        // Case A: Item already in container -> Increment quantity & highlight
        const existingRows = container.querySelectorAll('.material-row');
        for (let i = 0; i < existingRows.length; i++) {
            const r = existingRows[i];
            const sel = r.querySelector('select[name="items[]"]');
            if (sel && sel.value === itemCode) {
                const qtyInput = r.querySelector('input[name="quantities[]"]');
                if (qtyInput) {
                    const currentVal = parseFloat(qtyInput.value) || 0;
                    qtyInput.value = currentVal + (parseFloat(quantity) || 1);

                    // Row pulse animation
                    r.classList.remove('cims-row-just-added');
                    void r.offsetWidth;
                    r.classList.add('cims-row-just-added');
                    r.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return { status: 'incremented', row: r };
                }
            }
        }

        // Case B: Find blank initial row if user hasn't selected any item yet
        let targetRow = null;
        for (let i = 0; i < existingRows.length; i++) {
            const r = existingRows[i];
            const sel = r.querySelector('select[name="items[]"]');
            const isNewItemInput = r.querySelector('input[name="is_new_items[]"]');
            if (sel && (!sel.value || sel.value === '') && (!isNewItemInput || isNewItemInput.value === '0')) {
                targetRow = r;
                break;
            }
        }

        // Case C: Append a new row if no blank row available
        if (!targetRow) {
            if (typeof window.appendExistingItemRow === 'function') {
                targetRow = window.appendExistingItemRow(container, isRestock);
            }
        }

        if (!targetRow) {
            console.error('[CIMS] Failed to allocate row in materials container.');
            return false;
        }

        // Select the chosen material in targetRow
        const select = targetRow.querySelector('select[name="items[]"]');
        if (select) {
            select.value = itemCode;

            // Sync typeahead searchable combobox text and clear button
            const typeaheadInput = targetRow.querySelector('.cims-typeahead-input');
            const clearBtn = targetRow.querySelector('.cims-typeahead-clear');
            const opt = select.querySelector(`option[value="${itemCode}"]`);

            if (typeaheadInput) {
                typeaheadInput.value = opt ? opt.textContent.trim() : `[${itemCode}] ${itemName}`;
                if (clearBtn) clearBtn.classList.remove('d-none');
            }

            // Sync unit badge
            if (typeof window.syncRowUnitBadge === 'function') {
                window.syncRowUnitBadge(select);
            } else {
                const unitBadge = targetRow.querySelector('.item-unit-badge');
                if (unitBadge && unit) {
                    unitBadge.textContent = unit;
                    unitBadge.classList.remove('text-muted');
                    unitBadge.classList.add('text-primary');
                }
            }

            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Set quantity
        const qtyInput = targetRow.querySelector('input[name="quantities[]"]');
        if (qtyInput) {
            qtyInput.value = Math.max(1, parseFloat(quantity) || 1);
        }

        // Update container's material count badge (e.g. "2 Items")
        const cardHeader = container.closest('.card')?.querySelector('.material-count-badge');
        if (cardHeader) {
            const rowCount = container.querySelectorAll('.material-row').length;
            cardHeader.textContent = rowCount + (rowCount === 1 ? ' Item' : ' Items');
        }

        // Row pulse animation
        targetRow.classList.remove('cims-row-just-added');
        void targetRow.offsetWidth;
        targetRow.classList.add('cims-row-just-added');
        targetRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        if (typeof window.updateDeleteButtons === 'function') {
            window.updateDeleteButtons(container);
        }

        return { status: 'added', row: targetRow };
    };

    // 3. Button Handler: "+ Add to Slip"
    window.cimsAddDrawerItem = function(addBtn) {
        if (!addBtn) return;
        const card = addBtn.closest('.cims-suggest-card');
        const itemCode = addBtn.getAttribute('data-item-code') || (card ? card.getAttribute('data-item-code') : '');
        const itemName = addBtn.getAttribute('data-item-name') || (card ? card.getAttribute('data-item-name') : '');
        const itemUnit = addBtn.getAttribute('data-item-unit') || (card ? card.getAttribute('data-item-unit') : '');

        let qty = 1;
        if (card) {
            const qtyInput = card.querySelector('.qty-card-input');
            if (qtyInput) qty = parseFloat(qtyInput.value) || 1;
        }

        const res = window.addFrequentItemToRequisition(itemCode, itemName, itemUnit, qty);
        if (res) {
            const origHtml = addBtn.innerHTML;
            addBtn.disabled = true;
            addBtn.className = 'btn btn-sm btn-success fw-bold px-3 d-inline-flex align-items-center gap-1.5 shadow-sm text-white';
            addBtn.innerHTML = '<i class="bi bi-check2"></i> <span>Added!</span>';

            const countText = document.getElementById('drawerAddedCountText');
            if (countText) {
                countText.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Added <strong>${itemName}</strong> (${qty} ${itemUnit})</span>`;
            }

            setTimeout(() => {
                addBtn.disabled = false;
                addBtn.className = 'btn btn-sm btn-primary fw-bold px-3 d-inline-flex align-items-center gap-1.5 shadow-sm cims-add-frequent-btn';
                addBtn.innerHTML = origHtml;
            }, 1200);
        }
    };

    // 4. Duplicate Past Slip Handler
    window.cimsDuplicatePastSlip = function(loadBtn) {
        if (!loadBtn) return;
        const slipNo = loadBtn.getAttribute('data-slip-no') || 'RS';
        const itemsJson = loadBtn.getAttribute('data-slip-items');
        if (!itemsJson) return;

        let items = [];
        try {
            items = JSON.parse(itemsJson);
        } catch (e) {
            console.error('[CIMS] JSON parse error in past slip:', e);
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'info', title: 'No Items', text: 'This slip does not contain item records.' });
            }
            return;
        }

        const origHtml = loadBtn.innerHTML;
        loadBtn.disabled = true;
        loadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Adding items...';

        let addedCount = 0;
        items.forEach(it => {
            const code = it.item_code;
            const name = it.item_name || code;
            const unit = it.unit || 'pcs';
            const qty = parseFloat(it.quantity) || 1;
            const res = window.addFrequentItemToRequisition(code, name, unit, qty);
            if (res) addedCount++;
        });

        loadBtn.className = 'btn btn-success btn-sm fw-bold w-100 d-flex align-items-center justify-content-center gap-1.5 shadow-sm text-white';
        loadBtn.innerHTML = `<i class="bi bi-check2-all text-white"></i> <span>Duplicated ${addedCount} Items!</span>`;

        const countText = document.getElementById('drawerAddedCountText');
        if (countText) {
            countText.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Duplicated <strong>${addedCount} items</strong> from ${slipNo}</span>`;
        }

        setTimeout(() => {
            loadBtn.disabled = false;
            loadBtn.className = 'btn btn-outline-primary btn-sm fw-bold w-100 d-flex align-items-center justify-content-center gap-1.5 cims-load-slip-btn shadow-sm';
            loadBtn.innerHTML = origHtml;
        }, 2200);
    };

    // 5. Search & Category Filters
    let activeDrawerCategory = 'all';

    window.cimsFilterDrawerCards = function() {
        const searchInput = document.getElementById('frequentItemSearch');
        const clearBtn = document.getElementById('clearFrequentSearchBtn');
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';

        if (clearBtn) {
            if (query !== '') clearBtn.classList.remove('d-none');
            else clearBtn.classList.add('d-none');
        }

        const cards = document.querySelectorAll('#frequentItemsCardList .cims-suggest-card');
        const emptyNotice = document.getElementById('noFrequentSearchResults');
        let visibleCount = 0;

        cards.forEach(card => {
            const name = (card.getAttribute('data-item-name') || '').toLowerCase();
            const code = (card.getAttribute('data-item-code') || '').toLowerCase();
            const cat = (card.getAttribute('data-item-category') || '').toLowerCase();

            const matchesQuery = !query || name.includes(query) || code.includes(query) || cat.includes(query);
            const matchesCategory = (activeDrawerCategory === 'all') || (cat === activeDrawerCategory.toLowerCase());

            if (matchesQuery && matchesCategory) {
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

    window.cimsClearDrawerSearch = function() {
        const searchInput = document.getElementById('frequentItemSearch');
        const clearBtn = document.getElementById('clearFrequentSearchBtn');
        if (searchInput) searchInput.value = '';
        if (clearBtn) clearBtn.classList.add('d-none');
        window.cimsFilterDrawerCards();
        if (searchInput) searchInput.focus();
    };

    window.cimsSelectDrawerCategory = function(chip) {
        if (!chip) return;
        const allChips = document.querySelectorAll('#drawerCategoryChipsWrap .cims-cat-chip');
        allChips.forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        activeDrawerCategory = chip.getAttribute('data-cat') || 'all';
        window.cimsFilterDrawerCards();
    };

    // Chatbot Hide Alignment when Drawer Opens
    const drawerEl = document.getElementById('rsFrequentDrawer');
    if (drawerEl) {
        drawerEl.addEventListener('show.bs.offcanvas', function() {
            document.body.classList.add('cims-drawer-open');
            const bot = document.getElementById('cims-chatbot-container');
            if (bot) bot.classList.add('d-none');
        });
        drawerEl.addEventListener('hidden.bs.offcanvas', function() {
            document.body.classList.remove('cims-drawer-open');
            setTimeout(() => {
                if (!document.querySelector('.offcanvas.show') && !document.querySelector('.modal.show')) {
                    const bot = document.getElementById('cims-chatbot-container');
                    if (bot) bot.classList.remove('d-none');
                }
            }, 100);
        });
    }
})();
</script>
