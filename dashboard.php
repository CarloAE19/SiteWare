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
// UNIVERSAL STATS (All Roles)
// ==========================================
$totalItems = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$totalValue = $pdo->query("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM inventory")->fetchColumn();
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM inventory i LEFT JOIN units u ON i.unit = u.unit_name WHERE i.quantity > 0 AND i.quantity <= COALESCE(u.reorder_level, 10)")->fetchColumn();
$outOfStockCount = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= 0 OR status = 'Out of Stock'")->fetchColumn();

// ==========================================
// ROLE-SPECIFIC STATS
// ==========================================

// --- Requestor ---
$myTotalRS = 0;
$myPendingRS = 0;
$myApprovedRS = 0;
$myRecentRS = [];
if ($role === 'requestor' || $role === 'admin') {
    if ($role === 'requestor') {
        $myTotalRS = $pdo->prepare("SELECT COUNT(*) FROM requisitions WHERE type = 'project'");
        $myTotalRS->execute();
        $myTotalRS = $myTotalRS->fetchColumn();
        $myPendingRS = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE type = 'project' AND status = 'Pending Approval'")->fetchColumn();
        $myApprovedRS = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE type = 'project' AND status IN ('Approved', 'PO Created')")->fetchColumn();
        $myRecentRS = $pdo->query("SELECT rs_no, project_name, status, created_at FROM requisitions WHERE type = 'project' ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- Warehouse ---
$withdrawalsToday = 0;
$pendingRecountItems = 0;
if ($role === 'warehouse' || $role === 'admin') {
    $withdrawalsToday = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE DATE(date_withdrawn) = CURDATE()")->fetchColumn();
    try {
        $pendingRecountItems = $pdo->query("SELECT COUNT(*) FROM audit_items WHERE status = 'pending'")->fetchColumn();
    } catch (PDOException $e) {
        $pendingRecountItems = 0;
    }
}

// --- Purchasing ---
$rsPendingPO = 0;
$posPendingDelivery = 0;
$posDelayed = 0;
if ($role === 'purchasing' || $role === 'admin') {
    $rsPendingPO = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Approved' AND (type = 'restock' OR project_name = 'Warehouse Restock')")->fetchColumn();
    $posPendingDelivery = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('Generated', 'SMS Sent', 'Pending Delivery')")->fetchColumn();
    $posDelayed = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status LIKE '%Delayed%'")->fetchColumn();
}

// --- Management ---
$pendingApprovalRS = 0;
if ($role === 'management' || $role === 'admin') {
    $pendingApprovalRS = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending Approval'")->fetchColumn();
}

// --- Admin ---
$activeUsers = 0;
$totalPO = 0;
if ($role === 'admin') {
    $activeUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalPO = $pdo->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn();
    // If requestor stats weren't fetched for admin yet
    $myTotalRS = $pdo->query("SELECT COUNT(*) FROM requisitions")->fetchColumn();
    $myPendingRS = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending Approval'")->fetchColumn();
    $myApprovedRS = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status IN ('Approved', 'PO Created')")->fetchColumn();
}

// Recent activity for all roles
$recentActivity = $pdo->query("SELECT title, message, created_at FROM notifications ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

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

<div class="container-fluid px-3 px-md-4 py-4">

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
    <div class="dashboard-welcome mb-4">
        <div class="row align-items-center">
            <div class="col-12 col-md-8">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="welcome-avatar">
                        <i class="bi <?= $currentRole['icon'] ?>"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1 welcome-title" id="dashGreeting">
                            <i class="bi <?= $timeIcon ?> me-1" id="dashTimeIcon"></i><span
                                id="dashGreetingText"><?= $timeGreeting ?></span>, <?= htmlspecialchars($userName) ?>!
                        </h2>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span
                                class="badge <?= $currentRole['class'] ?> px-3 py-2 shadow-sm"><?= $currentRole['label'] ?></span>
                            <span class="text-muted small fw-bold"><i
                                    class="bi bi-calendar3 me-1"></i><?= date('l, F j, Y') ?></span>
                            <span class="badge bg-light text-dark border px-2.5 py-1.5 shadow-sm small fw-bold"
                                id="dashLiveClock"><i
                                    class="bi bi-clock me-1 text-primary"></i><?= date('g:i A') ?></span>
                        </div>
                        <p class="text-muted mt-2 mb-0 small fw-bold"><i
                                class="bi bi-compass me-1"></i><?= $currentRole['greeting'] ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                <a href="index" class="btn btn-outline-primary btn-sm fw-bold shadow-sm me-1">
                    <i class="bi bi-box-seam me-1"></i>Inventory
                </a>
                <?php if (in_array($role, ['admin', 'management', 'purchasing'])): ?>
                    <a href="analytics" class="btn btn-outline-dark btn-sm fw-bold shadow-sm">
                        <i class="bi bi-bar-chart-line me-1"></i>Analytics
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==========================================
         QUICK ACTION SHORTCUTS
    =========================================== -->
    <div class="card border-0 shadow-sm mb-4 quick-actions-card">
        <div class="card-body p-3 p-md-4">
            <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick
                Actions</h5>
            <div class="row g-3">

                <?php // === REQUESTOR SHORTCUTS ===
                if ($role === 'requestor' || $role === 'admin'): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="requisitions?action=new" class="shortcut-btn" id="shortcut-create-rs">
                            <div class="shortcut-icon bg-primary-subtle text-primary"><i class="bi bi-plus-circle-fill"></i>
                            </div>
                            <span class="shortcut-label">Request Item</span>
                            <small class="shortcut-desc">Create RS</small>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="requisitions" class="shortcut-btn" id="shortcut-my-requisitions">
                            <div class="shortcut-icon bg-info-subtle text-info"><i class="bi bi-card-checklist"></i></div>
                            <span class="shortcut-label">Requisitions</span>
                            <small class="shortcut-desc">View all RS</small>
                        </a>
                    </div>
                <?php endif; ?>

                <?php // === WAREHOUSE SHORTCUTS ===
                if ($role === 'warehouse' || $role === 'admin'): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="withdrawals?action=new" class="shortcut-btn" id="shortcut-record-withdrawal">
                            <div class="shortcut-icon bg-success-subtle text-success"><i class="bi bi-pencil-square"></i>
                            </div>
                            <span class="shortcut-label">Record Withdrawal</span>
                            <small class="shortcut-desc">Manual entry</small>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="requisitions?action=restock" class="shortcut-btn" id="shortcut-request-restock">
                            <div class="shortcut-icon bg-warning-subtle text-warning"><i class="bi bi-box-seam"></i></div>
                            <span class="shortcut-label">Request Restock</span>
                            <small class="shortcut-desc">Warehouse restock RS</small>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="physical_count" class="shortcut-btn" id="shortcut-start-audit">
                            <div class="shortcut-icon bg-danger-subtle text-danger"><i class="bi bi-calculator"></i>
                            </div>
                            <span class="shortcut-label">Perform Recount</span>
                            <small class="shortcut-desc">Weekly physical count</small>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="index" class="shortcut-btn" id="shortcut-view-materials">
                            <div class="shortcut-icon bg-primary-subtle text-primary"><i class="bi bi-boxes"></i></div>
                            <span class="shortcut-label">Materials</span>
                            <small class="shortcut-desc">View inventory</small>
                        </a>
                    </div>
                <?php endif; ?>

                <?php // === PURCHASING SHORTCUTS ===
                if ($role === 'purchasing' || $role === 'admin'): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="po?action=new" class="shortcut-btn" id="shortcut-create-po">
                            <div class="shortcut-icon bg-info-subtle text-info"><i class="bi bi-file-earmark-plus-fill"></i>
                            </div>
                            <span class="shortcut-label">Create PO</span>
                            <small class="shortcut-desc">Purchase Order</small>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="suppliers" class="shortcut-btn" id="shortcut-manage-suppliers">
                            <div class="shortcut-icon bg-secondary-subtle text-secondary"><i class="bi bi-buildings"></i>
                            </div>
                            <span class="shortcut-label">Suppliers</span>
                            <small class="shortcut-desc">Manage database</small>
                        </a>
                    </div>
                <?php endif; ?>

                <?php // === MANAGEMENT SHORTCUTS ===
                if ($role === 'management' || $role === 'admin'): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="requisitions" class="shortcut-btn <?= $pendingApprovalRS > 0 ? 'shortcut-pulse' : '' ?>"
                            id="shortcut-review-rs">
                            <div class="shortcut-icon bg-warning-subtle text-warning"><i
                                    class="bi bi-file-earmark-check-fill"></i></div>
                            <span class="shortcut-label">Review RS</span>
                            <small class="shortcut-desc"><?= $pendingApprovalRS ?> pending</small>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="analytics" class="shortcut-btn" id="shortcut-ai-forecast">
                            <div class="shortcut-icon bg-dark-subtle text-dark"><i class="bi bi-robot"></i></div>
                            <span class="shortcut-label">AI Forecast</span>
                            <small class="shortcut-desc">Analytics & AI</small>
                        </a>
                    </div>
                <?php endif; ?>

                <?php // === ADMIN-ONLY SHORTCUTS ===
                if ($role === 'admin'): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="users" class="shortcut-btn" id="shortcut-manage-users">
                            <div class="shortcut-icon bg-danger-subtle text-danger"><i class="bi bi-people-fill"></i></div>
                            <span class="shortcut-label">Manage Users</span>
                            <small class="shortcut-desc"><?= $activeUsers ?> active</small>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="projects" class="shortcut-btn" id="shortcut-manage-projects">
                            <div class="shortcut-icon bg-primary-subtle text-primary"><i class="bi bi-briefcase-fill"></i>
                            </div>
                            <span class="shortcut-label">Projects</span>
                            <small class="shortcut-desc">Manage projects</small>
                        </a>
                    </div>
                <?php endif; ?>

                <?php // === UNIVERSAL SHORTCUTS ===
                ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="index" class="shortcut-btn" id="shortcut-inventory-overview">
                        <div class="shortcut-icon bg-primary-subtle text-primary"><i class="bi bi-box-seam"></i></div>
                        <span class="shortcut-label">Inventory</span>
                        <small class="shortcut-desc"><?= $totalItems ?> items</small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         STAT CARDS — Role-Specific
    =========================================== -->
    <div class="row mb-4 g-3">

        <?php // ============ REQUESTOR STATS ============
        if ($role === 'requestor'): ?>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">My Requests</div>
                                <div class="stat-value"><?= $myTotalRS ?></div>
                            </div>
                            <div class="stat-icon-circle bg-primary-subtle"><i
                                    class="bi bi-card-checklist text-primary"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Total requisitions filed</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Pending</div>
                                <div class="stat-value text-warning"><?= $myPendingRS ?></div>
                            </div>
                            <div class="stat-icon-circle bg-warning-subtle"><i
                                    class="bi bi-hourglass-split text-warning"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-warning"
                                style="width: <?= $myTotalRS > 0 ? round(($myPendingRS / $myTotalRS) * 100) : 0 ?>%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Awaiting approval</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Approved</div>
                                <div class="stat-value text-success"><?= $myApprovedRS ?></div>
                            </div>
                            <div class="stat-icon-circle bg-success-subtle"><i
                                    class="bi bi-check-circle-fill text-success"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-success"
                                style="width: <?= $myTotalRS > 0 ? round(($myApprovedRS / $myTotalRS) * 100) : 0 ?>%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Ready or in process</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Available Items</div>
                                <div class="stat-value text-primary"><?= $totalItems ?></div>
                            </div>
                            <div class="stat-icon-circle bg-primary-subtle"><i class="bi bi-box-seam text-primary"></i>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">In inventory catalog</small>
                    </div>
                </div>
            </div>

        <?php // ============ WAREHOUSE STATS ============
        elseif ($role === 'warehouse'): ?>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Low Stock</div>
                                <div class="stat-value text-danger"><?= $lowStockCount ?></div>
                            </div>
                            <div class="stat-icon-circle bg-danger-subtle"><i
                                    class="bi bi-exclamation-triangle-fill text-danger"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-danger"
                                style="width: <?= $totalItems > 0 ? round(($lowStockCount / $totalItems) * 100) : 0 ?>%">
                            </div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Below reorder level</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Healthy Stock</div>
                                <div class="stat-value text-success">
                                    <?= $totalItems > 0 ? (100 - round(($lowStockCount / $totalItems) * 100)) : 100 ?>%
                                </div>
                            </div>
                            <div class="stat-icon-circle bg-success-subtle"><i class="bi bi-shield-check text-success"></i>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-success"
                                style="width: <?= $totalItems > 0 ? (100 - round(($lowStockCount / $totalItems) * 100)) : 100 ?>%">
                            </div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Above safe level</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Withdrawals Today</div>
                                <div class="stat-value text-info"><?= $withdrawalsToday ?></div>
                            </div>
                            <div class="stat-icon-circle bg-info-subtle"><i class="bi bi-tools text-info"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-info" style="width: <?= $withdrawalsToday > 0 ? '100' : '0' ?>%">
                            </div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Released materials</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Out of Stock</div>
                                <div class="stat-value text-dark"><?= $outOfStockCount ?></div>
                            </div>
                            <div class="stat-icon-circle bg-dark-subtle"><i class="bi bi-x-circle text-dark"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-dark"
                                style="width: <?= $totalItems > 0 ? round(($outOfStockCount / $totalItems) * 100) : 0 ?>%">
                            </div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Zero quantity items</small>
                    </div>
                </div>
            </div>

        <?php // ============ PURCHASING STATS ============
        elseif ($role === 'purchasing'): ?>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">RS Pending PO</div>
                                <div class="stat-value text-warning"><?= $rsPendingPO ?></div>
                            </div>
                            <div class="stat-icon-circle bg-warning-subtle"><i
                                    class="bi bi-file-earmark-check text-warning"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-warning" style="width: <?= $rsPendingPO > 0 ? '100' : '0' ?>%">
                            </div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Approved & awaiting PO</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Pending Delivery</div>
                                <div class="stat-value text-info"><?= $posPendingDelivery ?></div>
                            </div>
                            <div class="stat-icon-circle bg-info-subtle"><i class="bi bi-truck text-info"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-info" style="width: <?= $posPendingDelivery > 0 ? '100' : '0' ?>%">
                            </div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">POs awaiting delivery</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Delayed POs</div>
                                <div class="stat-value text-danger"><?= $posDelayed ?></div>
                            </div>
                            <div class="stat-icon-circle bg-danger-subtle"><i
                                    class="bi bi-exclamation-diamond text-danger"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-danger" style="width: <?= $posDelayed > 0 ? '100' : '0' ?>%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Requires follow-up</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Low Stock Items</div>
                                <div class="stat-value text-dark"><?= $lowStockCount ?></div>
                            </div>
                            <div class="stat-icon-circle bg-dark-subtle"><i class="bi bi-graph-down-arrow text-dark"></i>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-dark"
                                style="width: <?= $totalItems > 0 ? round(($lowStockCount / $totalItems) * 100) : 0 ?>%">
                            </div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">May need reorder</small>
                    </div>
                </div>
            </div>

        <?php // ============ MANAGEMENT STATS ============
        elseif ($role === 'management'): ?>
            <div class="col-6 col-md-3">
                <div
                    class="card stat-card-dash h-100 border-0 shadow-sm <?= $pendingApprovalRS > 0 ? 'stat-attention' : '' ?>">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Pending Approval</div>
                                <div class="stat-value text-warning"><?= $pendingApprovalRS ?></div>
                            </div>
                            <div class="stat-icon-circle bg-warning-subtle"><i
                                    class="bi bi-hourglass-split text-warning"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-warning"
                                style="width: <?= $pendingApprovalRS > 0 ? '100' : '0' ?>%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">RS awaiting your review</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Inventory Value</div>
                                <div class="stat-value" style="font-size: 1.1rem;">₱<?= number_format($totalValue, 0) ?>
                                </div>
                            </div>
                            <div class="stat-icon-circle bg-primary-subtle"><i class="bi bi-cash-stack text-primary"></i>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Total asset value</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Low Stock Alerts</div>
                                <div class="stat-value text-danger"><?= $lowStockCount ?></div>
                            </div>
                            <div class="stat-icon-circle bg-danger-subtle"><i
                                    class="bi bi-exclamation-triangle text-danger"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-danger"
                                style="width: <?= $totalItems > 0 ? round(($lowStockCount / $totalItems) * 100) : 0 ?>%">
                            </div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block"><?= $lowStockCount ?> of <?= $totalItems ?>
                            items</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Total Materials</div>
                                <div class="stat-value text-success"><?= $totalItems ?></div>
                            </div>
                            <div class="stat-icon-circle bg-success-subtle"><i class="bi bi-boxes text-success"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-success" style="width: 100%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">In system inventory</small>
                    </div>
                </div>
            </div>

        <?php // ============ ADMIN STATS ============
        elseif ($role === 'admin'): ?>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Inventory Value</div>
                                <div class="stat-value" style="font-size: 1.1rem;">₱<?= number_format($totalValue, 0) ?>
                                </div>
                            </div>
                            <div class="stat-icon-circle bg-primary-subtle"><i class="bi bi-cash-stack text-primary"></i>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Across <?= $totalItems ?> items</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm <?= $lowStockCount > 0 ? 'stat-attention' : '' ?>">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Low Stock</div>
                                <div class="stat-value text-danger"><?= $lowStockCount ?></div>
                            </div>
                            <div class="stat-icon-circle bg-danger-subtle"><i
                                    class="bi bi-exclamation-triangle-fill text-danger"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-danger"
                                style="width: <?= $totalItems > 0 ? round(($lowStockCount / $totalItems) * 100) : 0 ?>%">
                            </div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Needs restock attention</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card-dash h-100 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Active Users</div>
                                <div class="stat-value text-info"><?= $activeUsers ?></div>
                            </div>
                            <div class="stat-icon-circle bg-info-subtle"><i class="bi bi-people-fill text-info"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-info" style="width: 100%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Registered accounts</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div
                    class="card stat-card-dash h-100 border-0 shadow-sm <?= $pendingApprovalRS > 0 ? 'stat-attention' : '' ?>">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-label">Pending RS</div>
                                <div class="stat-value text-warning"><?= $pendingApprovalRS ?></div>
                            </div>
                            <div class="stat-icon-circle bg-warning-subtle"><i
                                    class="bi bi-file-earmark-check text-warning"></i></div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-warning"
                                style="width: <?= $pendingApprovalRS > 0 ? '100' : '0' ?>%"></div>
                        </div>
                        <small class="text-muted fw-bold mt-1 d-block">Awaiting management review</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- ==========================================
         RECENT ACTIVITY FEED
    =========================================== -->
    <div class="row g-3">
        <?php // Show recent RS timeline for requestor
        if ($role === 'requestor' && !empty($myRecentRS)): ?>
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3 p-md-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>My Recent
                            Requisitions</h6>
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
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($rs['rs_no']) ?></span>
                                                <span class="badge bg-<?= $color ?> ms-2"
                                                    style="font-size: 0.7rem;"><?= $rs['status'] ?></span>
                                            </div>
                                            <small class="text-muted"><?= date('M j', strtotime($rs['created_at'])) ?></small>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($rs['project_name']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-12 <?= ($role === 'requestor' && !empty($myRecentRS)) ? 'col-lg-6' : '' ?>">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-activity me-2 text-primary"></i>Recent Activity Feed</h6>
                        <div class="d-flex align-items-center gap-1 activity-filter-group">
                            <button type="button" class="btn btn-xs btn-primary activity-filter-btn active" data-filter="all">All</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary activity-filter-btn" data-filter="requisition">RS</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary activity-filter-btn" data-filter="po">PO</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary activity-filter-btn" data-filter="withdrawal">Withdrawal</button>
                        </div>
                    </div>
                    <?php if (!empty($recentActivity)): ?>
                        <div class="activity-feed-container">
                        <?php foreach ($recentActivity as $activity): 
                            $meta = getActivityMeta($activity['title'], $activity['message']);
                        ?>
                            <a href="<?= $meta['target'] ?>" class="activity-card-item d-flex align-items-start gap-3 p-2.5 rounded-3 mb-2 text-decoration-none text-reset" data-category="<?= $meta['type'] ?>">
                                <div class="activity-icon-badge <?= $meta['bg'] ?> text-<?= $meta['color'] ?>">
                                    <i class="bi <?= $meta['icon'] ?>"></i>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <span class="fw-bold text-dark text-truncate" style="font-size: 0.85rem;"><?= htmlspecialchars($activity['title']) ?></span>
                                        <span class="badge bg-light text-muted border px-2 py-1 flex-shrink-0" style="font-size: 0.7rem; font-weight: 600;">
                                            <i class="bi bi-clock me-1"></i><?= time_elapsed_string($activity['created_at']) ?>
                                        </span>
                                    </div>
                                    <p class="mb-0 text-muted small text-truncate-2 mt-1" style="font-size: 0.78rem; line-height: 1.35;"><?= htmlspecialchars($activity['message']) ?></p>
                                </div>
                                <i class="bi bi-chevron-right text-muted opacity-50 ms-1 align-self-center"></i>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            <span class="small fw-bold">No recent activity recorded</span>
                        </div>
                    <?php endif; ?>
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

        function initActivityFilters() {
            const filterBtns = document.querySelectorAll('.activity-filter-btn');
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterBtns.forEach(b => {
                        b.classList.remove('btn-primary', 'active');
                        b.classList.add('btn-outline-secondary');
                    });
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-primary', 'active');

                    const filter = this.getAttribute('data-filter');
                    document.querySelectorAll('.activity-card-item').forEach(item => {
                        const cat = item.getAttribute('data-category');
                        if (filter === 'all' || cat === filter) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                updateDashboardTime();
                setInterval(updateDashboardTime, 1000);
                initActivityFilters();
            });
        } else {
            updateDashboardTime();
            setInterval(updateDashboardTime, 1000);
            initActivityFilters();
        }
    })();
</script>

<?php include 'layout/footer.php'; ?>