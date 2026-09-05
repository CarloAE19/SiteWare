<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// ==========================================
// ROLE DISPLAY CONFIGURATION
// ==========================================
$roleDisplay = [
    'admin' => ['label' => 'System Administrator', 'class' => 'bg-danger', 'icon' => 'bi-shield-lock-fill', 'greeting' => 'Full System Overview'],
    'warehouse' => ['label' => 'Warehouse In-Charge', 'class' => 'bg-success', 'icon' => 'bi-house-gear-fill', 'greeting' => 'Warehouse Operations'],
    'management' => ['label' => 'Management / Approver', 'class' => 'bg-warning text-dark', 'icon' => 'bi-briefcase-fill', 'greeting' => 'Management Control Center'],
    'purchasing' => ['label' => 'Purchasing Officer', 'class' => 'bg-info text-dark', 'icon' => 'bi-cart-check-fill', 'greeting' => 'Purchasing Hub'],
    'requestor' => ['label' => 'Requestor', 'class' => 'bg-secondary', 'icon' => 'bi-person-fill-gear', 'greeting' => 'My Workspace']
];

$currentRole = $roleDisplay[$role] ?? $roleDisplay['requestor'];

// ==========================================
// METRIC INITIALIZATION & ROLE-GATED QUERIES
// ==========================================
$totalItems = 0;
$totalValue = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

$myTotalRS = 0;
$myPendingRS = 0;
$myApprovedRS = 0;
$myStagedRS = 0;
$myRecentRS = [];

$withdrawalsToday = 0;
$pendingRecountItems = 0;

$rsPendingPO = 0;
$posPendingDelivery = 0;
$posDelayed = 0;

$pendingApprovalRS = 0;
$activeUsers = 0;
$totalPO = 0;

// Execute queries ONLY for active role's requirements
if ($role === 'requestor') {
    $reqUserId = (int)$_SESSION['user_id'];
    $myTotalStmt = $pdo->prepare("SELECT COUNT(*) FROM requisitions WHERE requestor_id = ? AND type = 'project'");
    $myTotalStmt->execute([$reqUserId]);
    $myTotalRS = (int)$myTotalStmt->fetchColumn();

    $myPendingStmt = $pdo->prepare("SELECT COUNT(*) FROM requisitions WHERE requestor_id = ? AND type = 'project' AND status = 'Pending Approval'");
    $myPendingStmt->execute([$reqUserId]);
    $myPendingRS = (int)$myPendingStmt->fetchColumn();

    $myApprovedStmt = $pdo->prepare("SELECT COUNT(*) FROM requisitions WHERE requestor_id = ? AND type = 'project' AND status IN ('Approved', 'PO Created')");
    $myApprovedStmt->execute([$reqUserId]);
    $myApprovedRS = (int)$myApprovedStmt->fetchColumn();

    $myStagedStmt = $pdo->prepare("SELECT COUNT(*) FROM requisitions WHERE requestor_id = ? AND type = 'project' AND status = 'Staged (Ready for Pickup)'");
    $myStagedStmt->execute([$reqUserId]);
    $myStagedRS = (int)$myStagedStmt->fetchColumn();

    $myRecentStmt = $pdo->prepare("SELECT rs_no, project_name, status, created_at FROM requisitions WHERE requestor_id = ? AND type = 'project' ORDER BY created_at DESC LIMIT 5");
    $myRecentStmt->execute([$reqUserId]);
    $myRecentRS = $myRecentStmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($role === 'warehouse') {
    $totalItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory i LEFT JOIN units u ON i.unit = u.unit_name WHERE i.quantity > 0 AND i.quantity <= COALESCE(u.reorder_level, 10)")->fetchColumn();
    $outOfStockCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= 0 OR status = 'Out of Stock'")->fetchColumn();
    $withdrawalsToday = (int)$pdo->query("SELECT COUNT(*) FROM withdrawals WHERE DATE(date_withdrawn) = CURDATE()")->fetchColumn();
    try {
        $pendingRecountItems = (int)$pdo->query("SELECT COUNT(*) FROM audit_items WHERE status = 'pending'")->fetchColumn();
    } catch (PDOException $e) {
        $pendingRecountItems = 0;
    }

} elseif ($role === 'purchasing') {
    $totalItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory i LEFT JOIN units u ON i.unit = u.unit_name WHERE i.quantity > 0 AND i.quantity <= COALESCE(u.reorder_level, 10)")->fetchColumn();
    $rsPendingPO = (int)$pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Approved' AND (type = 'restock' OR project_name = 'Warehouse Restock')")->fetchColumn();
    $posPendingDelivery = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('Generated', 'SMS Sent', 'Pending Delivery')")->fetchColumn();
    $posDelayed = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status LIKE '%Delayed%'")->fetchColumn();

} elseif ($role === 'management') {
    $totalItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    $totalValue = (float)$pdo->query("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM inventory")->fetchColumn();
    $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory i LEFT JOIN units u ON i.unit = u.unit_name WHERE i.quantity > 0 AND i.quantity <= COALESCE(u.reorder_level, 10)")->fetchColumn();
    $pendingApprovalRS = (int)$pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending Approval'")->fetchColumn();

} elseif ($role === 'admin') {
    $totalItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    $totalValue = (float)$pdo->query("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM inventory")->fetchColumn();
    $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM inventory i LEFT JOIN units u ON i.unit = u.unit_name WHERE i.quantity > 0 AND i.quantity <= COALESCE(u.reorder_level, 10)")->fetchColumn();
    $pendingApprovalRS = (int)$pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending Approval'")->fetchColumn();
    $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalPO = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn();
}

// Recent activity for all roles
if ($role === 'requestor') {
    $recStmt = $pdo->prepare("
        SELECT id, title, message, created_at, COALESCE(is_read, 0) as is_read 
        FROM notifications 
        WHERE (target_user_id = ? OR target_role = 'requestor')
          AND title NOT LIKE '%PO%' 
          AND title NOT LIKE '%Purchase Order%'
          AND message NOT LIKE '%PO-%' 
          AND message NOT LIKE '%Purchase Order%'
        ORDER BY created_at DESC LIMIT 12
    ");
    $recStmt->execute([$_SESSION['user_id']]);
    $recentActivity = $recStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $recStmt = $pdo->prepare("
        SELECT id, title, message, created_at, COALESCE(is_read, 0) as is_read 
        FROM notifications 
        WHERE target_user_id = ? OR target_role = ? OR target_role = 'all'
        ORDER BY created_at DESC LIMIT 12
    ");
    $recStmt->execute([$_SESSION['user_id'], $role]);
    $recentActivity = $recStmt->fetchAll(PDO::FETCH_ASSOC);
}

$unreadActivityCount = 0;
foreach ($recentActivity as $act) {
    if ((int)$act['is_read'] === 0) {
        $unreadActivityCount++;
    }
}

// Helper to categorize recent activity for rich UI feed
function getActivityMeta($title, $message) {
    $t = strtolower($title . ' ' . $message);
    if (strpos($t, 'requisition') !== false || strpos($t, 'rs') !== false) {
        return ['type' => 'requisition', 'icon' => 'bi-card-checklist', 'color' => 'primary', 'bg' => 'bg-primary-subtle', 'target' => 'requisitions'];
    } elseif (strpos($t, 'po') !== false || strpos($t, 'purchase order') !== false) {
        return ['type' => 'po', 'icon' => 'bi-file-earmark-text', 'color' => 'info', 'bg' => 'bg-info-subtle', 'target' => 'po'];
    } elseif (strpos($t, 'withdrawal') !== false || strpos($t, 'withdrawn') !== false) {
        return ['type' => 'withdrawal', 'icon' => 'bi-tools', 'color' => 'success', 'bg' => 'bg-success-subtle', 'target' => 'withdrawals'];
    } elseif (strpos($t, 'low stock') !== false || strpos($t, 'recount') !== false || strpos($t, 'audit') !== false) {
        return ['type' => 'alert', 'icon' => 'bi-exclamation-triangle-fill', 'color' => 'danger', 'bg' => 'bg-danger-subtle', 'target' => 'audit'];
    } elseif (strpos($t, 'sms') !== false || strpos($t, 'eta') !== false || strpos($t, 'supplier') !== false) {
        return ['type' => 'po', 'icon' => 'bi-chat-left-text-fill', 'color' => 'warning', 'bg' => 'bg-warning-subtle', 'target' => 'po'];
    }
    return ['type' => 'system', 'icon' => 'bi-bell-fill', 'color' => 'secondary', 'bg' => 'bg-secondary-subtle', 'target' => 'index'];
}

include 'layout/header.php';
?>

<!-- Dashboard Stylesheet -->
<link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">

<div class="container-fluid dashboard-container px-2 px-sm-3 px-md-4 py-3 py-md-4">

    <!-- ==========================================
         WELCOME HEADER
    =========================================== -->
    <?php
    $hour = (int) date('H');
    if ($hour >= 5 && $hour < 12) {
        $timeGreeting = "Good morning";
        $timeIcon = "bi-sun-fill text-warning";
    } elseif ($hour >= 12 && $hour < 18) {
        $timeGreeting = "Good afternoon";
        $timeIcon = "bi-brightness-high-fill text-warning";
    } else {
        $timeGreeting = "Good evening";
        $timeIcon = "bi-moon-stars-fill text-info";
    }
    ?>
    <div class="dashboard-welcome mb-3 mb-md-4">
        <div class="row align-items-center g-0 g-md-3">
            <div class="col-12 col-md-8">
                <div class="d-flex align-items-center gap-2.5 gap-md-3">
                    <div class="welcome-avatar flex-shrink-0">
                        <i class="bi <?= $currentRole['icon'] ?>"></i>
                    </div>
                    <div class="min-w-0 flex-grow-1">
                        <h2 class="fw-bold mb-1 welcome-title text-truncate" id="dashGreeting">
                            <i class="bi <?= $timeIcon ?> me-1" id="dashTimeIcon"></i><span id="dashGreetingText"><?= $timeGreeting ?></span>, <?= htmlspecialchars($userName) ?>!
                        </h2>
                        <div class="d-flex align-items-center gap-1.5 flex-wrap welcome-meta-badges">
                            <span class="badge <?= $currentRole['class'] ?> px-2.5 py-1 shadow-sm"><?= $currentRole['label'] ?></span>
                            <span class="badge bg-light text-secondary border px-2 py-1 shadow-sm small fw-bold d-none d-sm-inline-flex"><i class="bi bi-compass me-1 text-muted"></i><?= htmlspecialchars($currentRole['greeting']) ?></span>
                            <span class="text-muted small fw-bold d-none d-md-inline-flex"><i class="bi bi-calendar3 me-1"></i><?= date('l, F j, Y') ?></span>
                            <span class="badge bg-light text-dark border px-2 py-1 shadow-sm small fw-bold" id="dashLiveClock"><i class="bi bi-clock me-1 text-primary"></i><?= date('g:i A') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 text-md-end welcome-actions-col">
                <div class="welcome-actions-wrap d-flex gap-2 justify-content-start justify-content-md-end">
                    <?php if ($role !== 'requestor'): ?>
                        <a href="index" class="welcome-action-pill welcome-pill-primary flex-grow-1 flex-md-grow-0" id="welcomeBtnInventory">
                            <i class="bi bi-box-seam me-1.5"></i>Inventory
                        </a>
                    <?php endif; ?>
                    <?php if (in_array($role, ['admin', 'management', 'purchasing'])): ?>
                        <a href="analytics" class="welcome-action-pill welcome-pill-neutral flex-grow-1 flex-md-grow-0" id="welcomeBtnAnalytics">
                            <i class="bi bi-bar-chart-line me-1.5"></i>Analytics
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         QUICK ACTION SHORTCUTS (Horizontal Scrollable Strip)
    =========================================== -->
    <div class="mb-3 mb-md-4">
        <div class="d-flex justify-content-between align-items-center mb-2.5">
            <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                <i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick Actions
            </h6>
            <span class="quick-swipe-hint text-muted small fw-semibold d-flex align-items-center gap-1 d-md-none">
                <span>Swipe</span><i class="bi bi-arrow-right-short text-warning fs-6"></i>
            </span>
        </div>
        
        <div class="quick-actions-wrapper" id="quickActionsWrapper">
            <!-- Floating Left Scroll Button (desktop only) -->
            <button type="button" class="quick-nav-btn quick-nav-prev d-none d-md-flex" id="quickScrollPrev" aria-label="Scroll left">
                <i class="bi bi-chevron-left"></i>
            </button>

            <!-- Horizontal Action Buttons Track -->
            <div class="quick-actions-track" id="quickActionsTrack">

                <?php // === ADMIN ROLE SHORTCUTS (Admin-first priority) ===
                if ($role === 'admin'): ?>
                    <a href="users" class="shortcut-btn" id="shortcut-manage-users">
                        <div class="shortcut-icon bg-danger-subtle text-danger">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Manage Users</span>
                            <small class="shortcut-desc"><?= $activeUsers ?> active</small>
                        </div>
                    </a>
                    <a href="projects" class="shortcut-btn" id="shortcut-manage-projects">
                        <div class="shortcut-icon bg-primary-subtle text-primary">
                            <i class="bi bi-briefcase-fill"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Projects</span>
                            <small class="shortcut-desc">Manage projects</small>
                        </div>
                    </a>
                    <a href="requisitions" class="shortcut-btn <?= $pendingApprovalRS > 0 ? 'shortcut-pulse' : '' ?>" id="shortcut-review-rs">
                        <div class="shortcut-icon bg-warning-subtle text-warning">
                            <i class="bi bi-file-earmark-check-fill"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Review RS</span>
                            <?php if ($pendingApprovalRS > 0): ?>
                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-1.5 py-0.5 rounded-pill shortcut-badge-pending"><?= $pendingApprovalRS ?> pending</span>
                            <?php else: ?>
                                <small class="shortcut-desc">0 pending</small>
                            <?php endif; ?>
                        </div>
                    </a>
                    <a href="analytics" class="shortcut-btn" id="shortcut-ai-forecast">
                        <div class="shortcut-icon bg-dark-subtle text-dark">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">AI Forecast</span>
                            <small class="shortcut-desc">Analytics & AI</small>
                        </div>
                    </a>
                    <a href="index" class="shortcut-btn" id="shortcut-inventory-overview">
                        <div class="shortcut-icon bg-primary-subtle text-primary">
                            <i class="bi bi-boxes"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Inventory</span>
                            <small class="shortcut-desc"><?= $totalItems ?> items</small>
                        </div>
                    </a>
                    <a href="withdrawals?action=new" class="shortcut-btn" id="shortcut-record-withdrawal">
                        <div class="shortcut-icon bg-success-subtle text-success">
                            <i class="bi bi-pencil-square"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Record Withdrawal</span>
                            <small class="shortcut-desc">Manual entry</small>
                        </div>
                    </a>
                    <a href="po?action=new" class="shortcut-btn" id="shortcut-create-po">
                        <div class="shortcut-icon bg-info-subtle text-info">
                            <i class="bi bi-file-earmark-plus-fill"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Create PO</span>
                            <small class="shortcut-desc">Purchase Order</small>
                        </div>
                    </a>
                    <a href="physical_count" class="shortcut-btn" id="shortcut-start-audit">
                        <div class="shortcut-icon bg-danger-subtle text-danger">
                            <i class="bi bi-calculator"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Perform Recount</span>
                            <small class="shortcut-desc">Weekly physical count</small>
                        </div>
                    </a>
                    <a href="suppliers" class="shortcut-btn" id="shortcut-manage-suppliers">
                        <div class="shortcut-icon bg-secondary-subtle text-secondary">
                            <i class="bi bi-buildings"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Suppliers</span>
                            <small class="shortcut-desc">Manage database</small>
                        </div>
                    </a>
                    <a href="requisitions?action=new" class="shortcut-btn" id="shortcut-create-rs">
                        <div class="shortcut-icon bg-primary-subtle text-primary">
                            <i class="bi bi-plus-circle-fill"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Request Item</span>
                            <small class="shortcut-desc">Create RS</small>
                        </div>
                    </a>

                <?php // === REQUESTOR ROLE SHORTCUTS ===
                elseif ($role === 'requestor'): ?>
                    <a href="requisitions?action=new" class="shortcut-btn" id="shortcut-create-rs">
                        <div class="shortcut-icon bg-primary-subtle text-primary">
                            <i class="bi bi-plus-circle-fill"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Request Item</span>
                            <small class="shortcut-desc">Create RS</small>
                        </div>
                    </a>
                    <a href="requisitions" class="shortcut-btn" id="shortcut-my-requisitions">
                        <div class="shortcut-icon bg-info-subtle text-info">
                            <i class="bi bi-card-checklist"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Requisitions</span>
                            <small class="shortcut-desc">View all RS</small>
                        </div>
                    </a>

                <?php // === WAREHOUSE ROLE SHORTCUTS ===
                elseif ($role === 'warehouse'): ?>
                    <a href="withdrawals?action=new" class="shortcut-btn" id="shortcut-record-withdrawal">
                        <div class="shortcut-icon bg-success-subtle text-success">
                            <i class="bi bi-pencil-square"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Record Withdrawal</span>
                            <small class="shortcut-desc">Manual entry</small>
                        </div>
                    </a>
                    <a href="requisitions?action=restock" class="shortcut-btn" id="shortcut-request-restock">
                        <div class="shortcut-icon bg-warning-subtle text-warning">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Request Restock</span>
                            <small class="shortcut-desc">Warehouse restock RS</small>
                        </div>
                    </a>
                    <a href="physical_count" class="shortcut-btn" id="shortcut-start-audit">
                        <div class="shortcut-icon bg-danger-subtle text-danger">
                            <i class="bi bi-calculator"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Perform Recount</span>
                            <small class="shortcut-desc">Weekly physical count</small>
                        </div>
                    </a>
                    <a href="index" class="shortcut-btn" id="shortcut-view-materials">
                        <div class="shortcut-icon bg-primary-subtle text-primary">
                            <i class="bi bi-boxes"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Materials</span>
                            <small class="shortcut-desc">View inventory</small>
                        </div>
                    </a>

                <?php // === PURCHASING ROLE SHORTCUTS ===
                elseif ($role === 'purchasing'): ?>
                    <a href="po?action=new" class="shortcut-btn" id="shortcut-create-po">
                        <div class="shortcut-icon bg-info-subtle text-info">
                            <i class="bi bi-file-earmark-plus-fill"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Create PO</span>
                            <small class="shortcut-desc">Purchase Order</small>
                        </div>
                    </a>
                    <a href="suppliers" class="shortcut-btn" id="shortcut-manage-suppliers">
                        <div class="shortcut-icon bg-secondary-subtle text-secondary">
                            <i class="bi bi-buildings"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Suppliers</span>
                            <small class="shortcut-desc">Manage database</small>
                        </div>
                    </a>
                    <a href="index" class="shortcut-btn" id="shortcut-inventory-overview">
                        <div class="shortcut-icon bg-primary-subtle text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Inventory</span>
                            <small class="shortcut-desc"><?= $totalItems ?> items</small>
                        </div>
                    </a>

                <?php // === MANAGEMENT ROLE SHORTCUTS ===
                elseif ($role === 'management'): ?>
                    <a href="requisitions" class="shortcut-btn <?= $pendingApprovalRS > 0 ? 'shortcut-pulse' : '' ?>" id="shortcut-review-rs">
                        <div class="shortcut-icon bg-warning-subtle text-warning">
                            <i class="bi bi-file-earmark-check-fill"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Review RS</span>
                            <?php if ($pendingApprovalRS > 0): ?>
                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-1.5 py-0.5 rounded-pill shortcut-badge-pending"><?= $pendingApprovalRS ?> pending</span>
                            <?php else: ?>
                                <small class="shortcut-desc">0 pending</small>
                            <?php endif; ?>
                        </div>
                    </a>
                    <a href="analytics" class="shortcut-btn" id="shortcut-ai-forecast">
                        <div class="shortcut-icon bg-dark-subtle text-dark">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">AI Forecast</span>
                            <small class="shortcut-desc">Analytics & AI</small>
                        </div>
                    </a>
                    <a href="index" class="shortcut-btn" id="shortcut-inventory-overview">
                        <div class="shortcut-icon bg-primary-subtle text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="shortcut-text">
                            <span class="shortcut-label">Inventory</span>
                            <small class="shortcut-desc"><?= $totalItems ?> items</small>
                        </div>
                    </a>
                <?php endif; ?>

            </div>

            <!-- Floating Right Scroll Button -->
            <button type="button" class="quick-nav-btn quick-nav-next d-none" id="quickScrollNext" aria-label="Scroll right">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>

    <!-- ==========================================
         KEY METRICS & KPIS — Role-Specific
    =========================================== -->
    <div class="metrics-section-header mb-2.5 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
            <i class="bi bi-bar-chart-fill me-2 text-primary"></i>Key Metrics
        </h6>
        <span class="metrics-drilldown-hint text-muted small fw-semibold d-flex align-items-center gap-1">
            <span>Tap to inspect</span><i class="bi bi-arrow-up-right-square text-primary"></i>
        </span>
    </div>

    <div class="row mb-4 g-3">
        <?php // ============ REQUESTOR STATS ============
        if ($role === 'requestor'): ?>
            <div class="col-6 col-md-3">
                <a href="requisitions" class="stat-card-link" aria-label="View My Requisitions">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">My Requests</div>
                                    <div class="stat-value"><?= $myTotalRS ?></div>
                                </div>
                                <div class="stat-icon-circle bg-primary-subtle"><i class="bi bi-card-checklist text-primary"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Total requisitions filed</small>
                                <i class="bi bi-arrow-right-short text-primary stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="requisitions" class="stat-card-link" aria-label="View Pending Requisitions">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Pending</div>
                                    <div class="stat-value text-warning"><?= $myPendingRS ?></div>
                                </div>
                                <div class="stat-icon-circle bg-warning-subtle"><i class="bi bi-hourglass-split text-warning"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Awaiting approval</small>
                                <i class="bi bi-arrow-right-short text-warning stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="requisitions" class="stat-card-link" aria-label="View Approved Requisitions">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Approved</div>
                                    <div class="stat-value text-success"><?= $myApprovedRS ?></div>
                                </div>
                                <div class="stat-icon-circle bg-success-subtle"><i class="bi bi-check-circle-fill text-success"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Ready or in process</small>
                                <i class="bi bi-arrow-right-short text-success stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="requisitions" class="stat-card-link" aria-label="View Staged Requisitions">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Ready for Pickup</div>
                                    <div class="stat-value text-info"><?= $myStagedRS ?></div>
                                </div>
                                <div class="stat-icon-circle bg-info-subtle"><i class="bi bi-box-arrow-right text-info"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Staged for collection</small>
                                <i class="bi bi-arrow-right-short text-info stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

        <?php // ============ WAREHOUSE STATS ============
        elseif ($role === 'warehouse'): ?>
            <div class="col-6 col-md-3">
                <a href="index" class="stat-card-link" aria-label="View Low Stock Items">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Low Stock</div>
                                    <div class="stat-value text-danger"><?= $lowStockCount ?></div>
                                </div>
                                <div class="stat-icon-circle bg-danger-subtle"><i class="bi bi-exclamation-triangle-fill text-danger"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Below reorder level</small>
                                <i class="bi bi-arrow-right-short text-danger stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="index" class="stat-card-link" aria-label="View Healthy Stock">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Healthy Stock</div>
                                    <div class="stat-value text-success">
                                        <?= $totalItems > 0 ? (100 - round(($lowStockCount / $totalItems) * 100)) : 100 ?>%
                                    </div>
                                </div>
                                <div class="stat-icon-circle bg-success-subtle"><i class="bi bi-shield-check text-success"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Above safe level</small>
                                <i class="bi bi-arrow-right-short text-success stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="withdrawals" class="stat-card-link" aria-label="View Today's Withdrawals">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Withdrawals Today</div>
                                    <div class="stat-value text-info"><?= $withdrawalsToday ?></div>
                                </div>
                                <div class="stat-icon-circle bg-info-subtle"><i class="bi bi-tools text-info"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Released materials</small>
                                <i class="bi bi-arrow-right-short text-info stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="index" class="stat-card-link" aria-label="View Out of Stock Items">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Out of Stock</div>
                                    <div class="stat-value text-dark"><?= $outOfStockCount ?></div>
                                </div>
                                <div class="stat-icon-circle bg-dark-subtle"><i class="bi bi-x-circle text-dark"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Zero quantity items</small>
                                <i class="bi bi-arrow-right-short text-dark stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

        <?php // ============ PURCHASING STATS ============
        elseif ($role === 'purchasing'): ?>
            <div class="col-6 col-md-3">
                <a href="requisitions" class="stat-card-link" aria-label="View Requisitions Pending PO">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">RS Pending PO</div>
                                    <div class="stat-value text-warning"><?= $rsPendingPO ?></div>
                                </div>
                                <div class="stat-icon-circle bg-warning-subtle"><i class="bi bi-file-earmark-check text-warning"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Approved & awaiting PO</small>
                                <i class="bi bi-arrow-right-short text-warning stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="po" class="stat-card-link" aria-label="View Purchase Orders Pending Delivery">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Pending Delivery</div>
                                    <div class="stat-value text-info"><?= $posPendingDelivery ?></div>
                                </div>
                                <div class="stat-icon-circle bg-info-subtle"><i class="bi bi-truck text-info"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">POs awaiting delivery</small>
                                <i class="bi bi-arrow-right-short text-info stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="po" class="stat-card-link" aria-label="View Delayed Purchase Orders">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Delayed POs</div>
                                    <div class="stat-value text-danger"><?= $posDelayed ?></div>
                                </div>
                                <div class="stat-icon-circle bg-danger-subtle"><i class="bi bi-exclamation-diamond text-danger"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Requires follow-up</small>
                                <i class="bi bi-arrow-right-short text-danger stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="index" class="stat-card-link" aria-label="View Low Stock Items">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Low Stock Items</div>
                                    <div class="stat-value text-dark"><?= $lowStockCount ?></div>
                                </div>
                                <div class="stat-icon-circle bg-dark-subtle"><i class="bi bi-graph-down-arrow text-dark"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">May need reorder</small>
                                <i class="bi bi-arrow-right-short text-dark stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

        <?php // ============ MANAGEMENT STATS ============
        elseif ($role === 'management'): ?>
            <div class="col-6 col-md-3">
                <a href="requisitions" class="stat-card-link" aria-label="View Pending Requisitions for Approval">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm <?= $pendingApprovalRS > 0 ? 'stat-attention' : '' ?>">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Pending Approval</div>
                                    <div class="stat-value text-warning"><?= $pendingApprovalRS ?></div>
                                </div>
                                <div class="stat-icon-circle bg-warning-subtle"><i class="bi bi-hourglass-split text-warning"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">RS awaiting your review</small>
                                <i class="bi bi-arrow-right-short text-warning stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="index" class="stat-card-link" aria-label="View Inventory Overview">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Inventory Value</div>
                                    <div class="stat-value" style="font-size: 1.35rem;">₱<?= number_format($totalValue, 0) ?></div>
                                </div>
                                <div class="stat-icon-circle bg-primary-subtle"><i class="bi bi-cash-stack text-primary"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Total asset value</small>
                                <i class="bi bi-arrow-right-short text-primary stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="index" class="stat-card-link" aria-label="View Low Stock Alerts">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Low Stock Alerts</div>
                                    <div class="stat-value text-danger"><?= $lowStockCount ?></div>
                                </div>
                                <div class="stat-icon-circle bg-danger-subtle"><i class="bi bi-exclamation-triangle text-danger"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small"><?= $lowStockCount ?> of <?= $totalItems ?> items</small>
                                <i class="bi bi-arrow-right-short text-danger stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="index" class="stat-card-link" aria-label="View Total Materials">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Total Materials</div>
                                    <div class="stat-value text-success"><?= $totalItems ?></div>
                                </div>
                                <div class="stat-icon-circle bg-success-subtle"><i class="bi bi-boxes text-success"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">In system inventory</small>
                                <i class="bi bi-arrow-right-short text-success stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

        <?php // ============ ADMIN STATS ============
        elseif ($role === 'admin'): ?>
            <div class="col-6 col-md-3">
                <a href="index" class="stat-card-link" aria-label="View Total Inventory Value">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Inventory Value</div>
                                    <div class="stat-value" style="font-size: 1.35rem;">₱<?= number_format($totalValue, 0) ?></div>
                                </div>
                                <div class="stat-icon-circle bg-primary-subtle"><i class="bi bi-cash-stack text-primary"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Across <?= $totalItems ?> items</small>
                                <i class="bi bi-arrow-right-short text-primary stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="index" class="stat-card-link" aria-label="View Low Stock Items">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm <?= $lowStockCount > 0 ? 'stat-attention' : '' ?>">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Low Stock</div>
                                    <div class="stat-value text-danger"><?= $lowStockCount ?></div>
                                </div>
                                <div class="stat-icon-circle bg-danger-subtle"><i class="bi bi-exclamation-triangle-fill text-danger"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Needs restock attention</small>
                                <i class="bi bi-arrow-right-short text-danger stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="users" class="stat-card-link" aria-label="View Active Registered Users">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Active Users</div>
                                    <div class="stat-value text-info"><?= $activeUsers ?></div>
                                </div>
                                <div class="stat-icon-circle bg-info-subtle"><i class="bi bi-people-fill text-info"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Registered accounts</small>
                                <i class="bi bi-arrow-right-short text-info stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="requisitions" class="stat-card-link" aria-label="View Pending Requisitions">
                    <div class="card stat-card-dash h-100 border-0 shadow-sm <?= $pendingApprovalRS > 0 ? 'stat-attention' : '' ?>">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="stat-label">Pending RS</div>
                                    <div class="stat-value text-warning"><?= $pendingApprovalRS ?></div>
                                </div>
                                <div class="stat-icon-circle bg-warning-subtle"><i class="bi bi-file-earmark-check text-warning"></i></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted small">Awaiting management review</small>
                                <i class="bi bi-arrow-right-short text-warning stat-arrow-hint"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endif; ?>

    </div>

    <!-- ==========================================
         RECENT ACTIVITY FEED
    =========================================== -->
    <div class="row g-3">
        <?php // Show recent RS timeline for requestor (with actionable onboarding empty state)
        if ($role === 'requestor'): ?>
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>My Recent Requisitions</h6>
                            <?php if (!empty($myRecentRS)): ?>
                                <a href="requisitions" class="btn btn-link btn-xs text-decoration-none fw-bold p-0">View all</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($myRecentRS)): ?>
                            <div class="recent-rs-timeline">
                                <?php foreach ($myRecentRS as $rs):
                                    $statusColors = [
                                        'Pending Approval' => 'warning',
                                        'Approved' => 'success',
                                        'Rejected' => 'danger',
                                        'PO Created' => 'info',
                                        'Released' => 'success'
                                    ];
                                    $color = $statusColors[$rs['status']] ?? 'secondary';
                                    ?>
                                    <div class="timeline-item d-flex align-items-start gap-3 mb-3 pb-3 border-bottom">
                                        <div class="timeline-dot bg-<?= $color ?>"></div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($rs['rs_no'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="badge bg-<?= $color ?> ms-2"
                                                        style="font-size: 0.7rem;"><?= htmlspecialchars($rs['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                                <small class="text-muted"><?= date('M j', strtotime($rs['created_at'])) ?></small>
                                            </div>
                                            <small class="text-muted"><?= htmlspecialchars($rs['project_name'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-onboarding-card my-2">
                                <div class="mb-2 text-primary">
                                    <i class="bi bi-file-earmark-plus fs-1 opacity-75"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">No Requisitions Yet</h6>
                                <p class="text-muted small mb-3">Submit your first material request for your assigned project.</p>
                                <a href="requisitions?action=new" class="btn btn-sm btn-primary rounded-pill px-3.5 py-1.5 shadow-sm fw-bold">
                                    <i class="bi bi-plus-circle me-1"></i>Create Requisition
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-12 <?= $role === 'requestor' ? 'col-lg-6' : '' ?>">
            <div class="card border-0 shadow-sm h-100" id="activityCard">
                <div class="card-body p-3 p-md-4">
                    <!-- ACTIVITY FEED HEADER -->
                    <div class="activity-feed-header mb-3">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2.5">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <h6 class="fw-bold text-dark mb-0 text-truncate"><i class="bi bi-activity me-1.5 text-primary"></i>Recent Activity Feed</h6>
                                <span class="badge bg-danger rounded-pill px-2 py-0.5 small fw-bold flex-shrink-0" id="dashUnreadBadge" style="<?= $unreadActivityCount > 0 ? '' : 'display:none;' ?>"><?= $unreadActivityCount ?> New</span>
                            </div>
                            <div class="flex-shrink-0">
                                <button type="button" class="btn btn-link btn-xs text-decoration-none text-primary p-0 fw-bold mark-all-read-btn" id="markAllReadBtn" onclick="markAllDashboardActivityRead()" style="<?= $unreadActivityCount > 0 ? '' : 'display:none;' ?>">
                                    <i class="bi bi-check2-all me-1"></i><span class="mark-all-text">Mark all read</span>
                                </button>
                            </div>
                        </div>

                        <!-- TOUCH-FRIENDLY FILTER BAR -->
                        <div class="activity-filter-wrapper">
                            <div class="activity-filter-group" id="activityFilterGroup">
                                <button type="button" class="btn btn-xs <?= $unreadActivityCount > 0 ? 'btn-primary active' : 'btn-outline-secondary' ?> activity-filter-btn" data-filter="unread">Unread</button>
                                <button type="button" class="btn btn-xs <?= $unreadActivityCount === 0 ? 'btn-primary active' : 'btn-outline-secondary' ?> activity-filter-btn" data-filter="all">All</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary activity-filter-btn" data-filter="requisition">Requisitions</button>
                                <?php if ($role !== 'requestor'): ?>
                                    <button type="button" class="btn btn-xs btn-outline-secondary activity-filter-btn" data-filter="po">Purchase Orders</button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary activity-filter-btn" data-filter="withdrawal">Withdrawals</button>
                            </div>
                        </div>
                    </div>

                    <!-- CAUGHT UP VIEW (Shown when no unread or after marking read) -->
                    <div class="caught-up-container text-center py-4 px-3 <?= ($unreadActivityCount === 0 && !empty($recentActivity)) ? '' : 'd-none' ?>" id="caughtUpView">
                        <div class="caught-up-icon-wrapper mb-3 mx-auto">
                            <i class="bi bi-shield-check text-success fs-1"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">You're all caught up!</h5>
                        <p class="text-muted small mb-3">No unread activity notifications. You have reviewed all recent updates.</p>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3.5 py-1.5 fw-bold shadow-sm" onclick="showAllActivityHistory()">
                            <i class="bi bi-clock-history me-1"></i>View Activity History
                        </button>
                    </div>

                    <!-- ACTIVITY FEED ITEMS -->
                    <div class="activity-feed-container <?= ($unreadActivityCount === 0 && !empty($recentActivity)) ? 'd-none' : '' ?>" id="activityFeedList">
                        <?php if (!empty($recentActivity)): ?>
                            <?php foreach ($recentActivity as $activity): 
                                $meta = getActivityMeta($activity['title'], $activity['message']);
                                $isUnread = (int)$activity['is_read'] === 0;
                            ?>
                                <a href="javascript:void(0)" onclick="readNotifAndNavigate(<?= (int)$activity['id'] ?>, '<?= htmlspecialchars($meta['target'], ENT_QUOTES, 'UTF-8') ?>')" class="activity-card-item d-flex align-items-start gap-2.5 p-2.5 rounded-3 mb-2 text-decoration-none text-reset <?= $isUnread ? 'activity-unread' : 'activity-read' ?>" data-category="<?= htmlspecialchars($meta['type'], ENT_QUOTES, 'UTF-8') ?>" data-read="<?= $isUnread ? '0' : '1' ?>">
                                    <div class="activity-icon-badge <?= $meta['bg'] ?> text-<?= $meta['color'] ?> position-relative flex-shrink-0">
                                        <i class="bi <?= $meta['icon'] ?>"></i>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                            <div class="min-w-0 d-flex align-items-center gap-1.5 flex-wrap">
                                                <span class="activity-item-title fw-bold text-dark text-truncate" style="font-size: 0.85rem;">
                                                    <?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <?php if ($isUnread): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-1.5 py-0.5 new-tag-badge flex-shrink-0" style="font-size: 0.62rem; font-weight: 700;">NEW</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge bg-light text-muted border px-1.5 py-0.5 flex-shrink-0 activity-time-badge" style="font-size: 0.68rem; font-weight: 600;">
                                                <i class="bi bi-clock me-1"></i><?= time_elapsed_string($activity['created_at']) ?>
                                            </span>
                                        </div>
                                        <p class="mb-0 text-muted small text-truncate-2" style="font-size: 0.77rem; line-height: 1.35;"><?= htmlspecialchars($activity['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted opacity-40 ms-0.5 align-self-center d-none d-sm-block flex-shrink-0"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                <span class="small fw-bold">No recent activity recorded</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- EMPTY FILTER FEEDBACK -->
                    <div class="text-center py-4 text-muted d-none" id="emptyFilterFeedback">
                        <i class="bi bi-funnel fs-2 d-block mb-2 text-secondary opacity-50"></i>
                        <span class="small fw-bold d-block text-secondary">No activity records match this filter</span>
                        <button type="button" class="btn btn-xs btn-link text-decoration-none mt-1 fw-bold" onclick="showAllActivityHistory()">Show all activity</button>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>

<script>
    (function () {
        function updateDashboardTime() {
            const now = new Date();
            const hours = now.getHours();

            let greeting = 'Good morning';
            let iconClass = 'bi-sun-fill text-warning';

            if (hours >= 5 && hours < 12) {
                greeting = 'Good morning';
                iconClass = 'bi-sun-fill text-warning';
            } else if (hours >= 12 && hours < 18) {
                greeting = 'Good afternoon';
                iconClass = 'bi-brightness-high-fill text-warning';
            } else {
                greeting = 'Good evening';
                iconClass = 'bi-moon-stars-fill text-info';
            }

            const greetingText = document.getElementById('dashGreetingText');
            const timeIcon = document.getElementById('dashTimeIcon');
            const liveClock = document.getElementById('dashLiveClock');

            if (greetingText) greetingText.innerText = greeting;
            if (timeIcon) timeIcon.className = `bi ${iconClass} me-1`;

            if (liveClock) {
                const timeString = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });
                liveClock.innerHTML = `<i class="bi bi-clock me-1 text-primary"></i>${timeString}`;
            }
        }

        function getDashboardCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }

        window.markAllDashboardActivityRead = async function() {
            const markBtn = document.getElementById('markAllReadBtn');
            const originalBtnHtml = markBtn ? markBtn.innerHTML : '<i class="bi bi-check2-all me-1"></i>Mark all read';

            if (markBtn) {
                markBtn.disabled = true;
                markBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Marking...';
            }

            const csrfToken = getDashboardCsrfToken();
            let formData = new FormData();
            formData.append('action', 'read_all_notifs');
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            try {
                const response = await fetch('process/process_notif.php', { 
                    method: 'POST', 
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken
                    }
                });

                const result = await response.json();
                if (result.status !== 'success') {
                    throw new Error(result.message || 'Failed to mark notifications as read.');
                }

                // Update UI state for all activity items
                document.querySelectorAll('.activity-card-item').forEach(item => {
                    item.setAttribute('data-read', '1');
                    item.classList.remove('activity-unread');
                    item.classList.add('activity-read');
                    const newTag = item.querySelector('.new-tag-badge');
                    if (newTag) newTag.remove();
                });

                // Update dashboard unread badge
                const unreadBadge = document.getElementById('dashUnreadBadge');
                if (unreadBadge) unreadBadge.style.display = 'none';

                // Synchronize global system notification badge if present
                const topNotifBadge = document.getElementById('systemNotifBadge');
                if (topNotifBadge) topNotifBadge.style.display = 'none';

                if (markBtn) markBtn.style.display = 'none';

                // If user is viewing the 'unread' filter tab, show caught up view
                const activeFilter = document.querySelector('.activity-filter-btn.active');
                if (!activeFilter || activeFilter.getAttribute('data-filter') === 'unread') {
                    const caughtUp = document.getElementById('caughtUpView');
                    const feedList = document.getElementById('activityFeedList');
                    if (caughtUp) caughtUp.classList.remove('d-none');
                    if (feedList) feedList.classList.add('d-none');
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'All notifications marked as read',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            } catch (e) {
                console.error('AJAX Error:', e);
                if (markBtn) {
                    markBtn.disabled = false;
                    markBtn.innerHTML = originalBtnHtml;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: e.message || 'Could not update notification status.'
                    });
                } else {
                    alert(e.message || 'Could not update notification status.');
                }
            }
        };

        window.showAllActivityHistory = function() {
            const allBtn = document.querySelector('.activity-filter-btn[data-filter="all"]');
            if (allBtn) {
                allBtn.click();
            }
        };

        function initActivityFilters() {
            const filterBtns = document.querySelectorAll('.activity-filter-btn');
            const caughtUpView = document.getElementById('caughtUpView');
            const feedList = document.getElementById('activityFeedList');
            const emptyFilter = document.getElementById('emptyFilterFeedback');

            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterBtns.forEach(b => {
                        b.classList.remove('btn-primary', 'active');
                        b.classList.add('btn-outline-secondary');
                    });
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-primary', 'active');

                    const filter = this.getAttribute('data-filter');
                    const items = document.querySelectorAll('.activity-card-item');
                    let visibleCount = 0;

                    items.forEach(item => {
                        const cat = item.getAttribute('data-category');
                        const isRead = item.getAttribute('data-read');

                        let show = false;
                        if (filter === 'all') {
                            show = true;
                        } else if (filter === 'unread') {
                            show = (isRead === '0');
                        } else {
                            show = (cat === filter);
                        }

                        if (show) {
                            item.style.display = 'flex';
                            visibleCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    if (filter === 'unread' && visibleCount === 0) {
                        if (caughtUpView) caughtUpView.classList.remove('d-none');
                        if (feedList) feedList.classList.add('d-none');
                        if (emptyFilter) emptyFilter.classList.add('d-none');
                    } else if (visibleCount === 0) {
                        if (caughtUpView) caughtUpView.classList.add('d-none');
                        if (feedList) feedList.classList.add('d-none');
                        if (emptyFilter) emptyFilter.classList.remove('d-none');
                    } else {
                        if (caughtUpView) caughtUpView.classList.add('d-none');
                        if (feedList) feedList.classList.remove('d-none');
                        if (emptyFilter) emptyFilter.classList.add('d-none');
                    }
                });
            });
        }

        function initQuickActionsScroll() {
            const track = document.getElementById('quickActionsTrack');
            const prevBtn = document.getElementById('quickScrollPrev');
            const nextBtn = document.getElementById('quickScrollNext');
            if (!track || !prevBtn || !nextBtn) return;

            function updateNavButtons() {
                const hasOverflow = track.scrollWidth > track.clientWidth + 4;
                if (!hasOverflow) {
                    prevBtn.classList.add('d-none');
                    nextBtn.classList.add('d-none');
                    return;
                }

                const atStart = track.scrollLeft <= 6;
                const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 6;

                if (atStart) {
                    prevBtn.classList.add('d-none');
                } else {
                    prevBtn.classList.remove('d-none');
                }

                if (atEnd) {
                    nextBtn.classList.add('d-none');
                } else {
                    nextBtn.classList.remove('d-none');
                }
            }

            prevBtn.addEventListener('click', () => {
                track.scrollBy({ left: -260, behavior: 'smooth' });
            });

            nextBtn.addEventListener('click', () => {
                track.scrollBy({ left: 260, behavior: 'smooth' });
            });

            track.addEventListener('scroll', updateNavButtons, { passive: true });
            window.addEventListener('resize', updateNavButtons, { passive: true });

            // Drag-to-scroll for desktop mouse interaction
            let isDown = false;
            let startX = 0;
            let scrollLeft = 0;
            let hasDragged = false;

            track.addEventListener('mousedown', (e) => {
                isDown = true;
                hasDragged = false;
                startX = e.pageX - track.offsetLeft;
                scrollLeft = track.scrollLeft;
            });

            track.addEventListener('mouseleave', () => {
                isDown = false;
                track.classList.remove('is-dragging');
            });

            track.addEventListener('mouseup', () => {
                isDown = false;
                setTimeout(() => {
                    track.classList.remove('is-dragging');
                    hasDragged = false;
                }, 50);
            });

            track.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                const x = e.pageX - track.offsetLeft;
                const walk = (x - startX) * 1.3;
                if (Math.abs(walk) > 6) {
                    hasDragged = true;
                    track.classList.add('is-dragging');
                    e.preventDefault();
                    track.scrollLeft = scrollLeft - walk;
                }
            });

            // Prevent link navigation ONLY if the user actively dragged the track
            track.querySelectorAll('.shortcut-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    if (hasDragged) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                    }
                });
            });

            // Initial check
            setTimeout(updateNavButtons, 80);
        }

        function initLiveActivityPolling() {
            async function checkUnreadActivity() {
                if (document.hidden) return; // Skip polling when browser tab is in background

                try {
                    const response = await fetch('process/process_notif.php?action=get_unread_count', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const res = await response.json();
                    if (res && res.status === 'success') {
                        const count = parseInt(res.unread_count, 10) || 0;
                        const unreadBadge = document.getElementById('dashUnreadBadge');
                        const topNotifBadge = document.getElementById('systemNotifBadge');
                        const markBtn = document.getElementById('markAllReadBtn');

                        if (count > 0) {
                            if (unreadBadge) {
                                unreadBadge.textContent = `${count} New`;
                                unreadBadge.style.display = '';
                            }
                            if (topNotifBadge) {
                                topNotifBadge.textContent = count;
                                topNotifBadge.style.display = '';
                            }
                            if (markBtn) {
                                markBtn.style.display = '';
                            }
                        } else {
                            if (unreadBadge) unreadBadge.style.display = 'none';
                            if (topNotifBadge) topNotifBadge.style.display = 'none';
                            if (markBtn) markBtn.style.display = 'none';
                        }
                    }
                } catch (err) {
                    // Silently ignore background network errors to avoid user annoyance
                }
            }

            // Periodic 60s background check
            setInterval(checkUnreadActivity, 60000);

            // Instant check when user switches focus back to this browser tab
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    checkUnreadActivity();
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                updateDashboardTime();
                setInterval(updateDashboardTime, 1000);
                initActivityFilters();
                initQuickActionsScroll();
                initLiveActivityPolling();
            });
        } else {
            updateDashboardTime();
            setInterval(updateDashboardTime, 1000);
            initActivityFilters();
            initQuickActionsScroll();
            initLiveActivityPolling();
        }
    })();
</script>

<?php include 'layout/footer.php'; ?>