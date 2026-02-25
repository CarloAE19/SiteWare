<?php
// Modernized Function to calculate "Time Ago" for notifications (PHP 8+ Safe)
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Calculate weeks and remaining days safely
    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    // Map the values safely
    $values = [
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    ];

    $string = [
        'y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day',
        'h' => 'hour', 'i' => 'minute', 's' => 'second',
    ];

    $parts = [];
    foreach ($string as $k => $v) {
        if ($values[$k]) {
            $parts[] = $values[$k] . ' ' . $v . ($values[$k] > 1 ? 's' : '');
        }
    }

    if (!$full) $parts = array_slice($parts, 0, 1);
    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}

$currentUserRole = $_SESSION['user_role'] ?? 'requestor';
$currentUserId = $_SESSION['user_id'] ?? 0;

// FETCH NOTIFICATIONS FOR CURRENT USER OR ROLE
$notifStmt = $pdo->prepare("SELECT * FROM notifications 
                            WHERE target_user_id = ? OR target_role = ? 
                            ORDER BY created_at DESC LIMIT 5");
$notifStmt->execute([$currentUserId, $currentUserRole]);
$notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

$unreadCount = count($notifications);

// Define Role Aesthetics for the header badge
$headerRoles = [
    'admin' => ['label' => 'Admin', 'class' => 'bg-danger'],
    'warehouse' => ['label' => 'Warehouse In-Charge', 'class' => 'bg-success'],
    'management' => ['label' => 'Management / Approver', 'class' => 'bg-warning text-dark'],
    'purchasing' => ['label' => 'Purchasing Officer', 'class' => 'bg-info text-dark'],
    'requestor' => ['label' => 'Requestor', 'class' => 'bg-secondary']
];

$userBadgeClass = $headerRoles[$currentUserRole]['class'] ?? 'bg-secondary';
$userBadgeLabel = $headerRoles[$currentUserRole]['label'] ?? 'Unknown Role';

// Get current page to highlight the active menu item accurately
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GB Construction & Enterprise Inc.</title>
    <link rel="icon" type="image/png" href="assets/LogoGB.png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Centralized Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="wrapper">
        <!-- Sidebar  -->
        <nav id="sidebar">
            <div class="sidebar-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-cone-striped me-2" style="color: var(--gb-yellow);"></i>GB Inventory</h4>
                <button type="button" id="sidebarClose" class="btn btn-link text-white d-md-none p-0 text-decoration-none">
                    <i class="bi bi-x-lg fs-4"></i>
                </button>
            </div>

            <ul class="list-unstyled components">
                
                <li class="px-3 text-uppercase small fw-bold mb-2 mt-3">Master Data</li>
                
                <!-- CLEAN URL: Changed href="index.php" to href="index" -->
                <li class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
                    <a href="index"><i class="bi bi-box-seam me-3"></i> Materials Inventory</a>
                </li>
                
                <?php if (in_array($_SESSION['user_role'], ['admin', 'purchasing'])): ?>
                <!-- CLEAN URL: Changed href="suppliers.php" to href="suppliers" -->
                <li class="<?= $currentPage == 'suppliers.php' ? 'active' : '' ?>">
                    <a href="suppliers"><i class="bi bi-buildings me-3"></i> Suppliers Database</a>
                </li>
                <?php endif; ?>

                <li class="<?= $currentPage == 'withdrawals.php' ? 'active' : '' ?>">
                    <a href="withdrawals"><i class="bi bi-tools me-3"></i> Material Withdrawals</a>
                </li>

                <!-- CLEAN URL: Changed href="requisitions.php" to href="requisitions" -->
                <li class="<?= $currentPage == 'requisitions.php' ? 'active' : '' ?>">
                    <a href="requisitions"><i class="bi bi-card-checklist me-3"></i> Requisitions (RS)</a>
                </li>
                <li class="<?= $currentPage == 'po.php' ? 'active' : '' ?>">
                    <a href="po"><i class="bi bi-file-earmark-text me-3"></i> Purchase Orders (PO)</a>
                </li>
                
                <li class="px-3 text-uppercase small fw-bold mb-2 mt-4">System</li>

                <li class="<?= $currentPage == 'analytics.php' ? 'active' : '' ?>">
                    <a href="analytics"><i class="bi bi-graph-up me-3"></i> Analytics & AI Predictions</a>
                </li>
                
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <!-- CLEAN URL: Changed href="users.php" to href="users" -->
                <li class="<?= $currentPage == 'users.php' ? 'active' : '' ?>">
                    <a href="users"><i class="bi bi-people me-3"></i> Manage Users</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Page Content  -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg top-navbar">
                <div class="container-fluid px-0">
                    <button type="button" id="sidebarCollapse" class="btn btn-brand">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    
                    <div class="d-flex align-items-center ms-auto">
                        
                        <!-- DYNAMIC NOTIFICATION BELL DROPDOWN -->
                        <div class="dropdown me-3">
                            <a href="#" class="text-muted position-relative d-flex align-items-center text-decoration-none" id="dropdownNotif" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell fs-5"></i>
                                <?php if($unreadCount > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 10px; height: 10px;">
                                        <span class="visually-hidden">New alerts</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownNotif" style="width: 320px;">
                                <li><h6 class="dropdown-header fw-bold border-bottom pb-2">Notifications</h6></li>
                                
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php if($unreadCount > 0): ?>
                                        <?php foreach($notifications as $notif): ?>
                                            <li>
                                                <a class="dropdown-item py-2 border-bottom" href="#" style="white-space: normal;">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <strong class="mb-1 text-primary" style="font-size: 0.9rem;"><?= htmlspecialchars($notif['title']) ?></strong>
                                                        <small class="text-muted" style="font-size: 0.75rem;"><?= time_elapsed_string($notif['created_at']) ?></small>
                                                    </div>
                                                    <p class="mb-0 text-muted small"><?= htmlspecialchars($notif['message']) ?></p>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li><span class="dropdown-item text-muted small text-center py-3">No new notifications.</span></li>
                                    <?php endif; ?>
                                </div>
                            </ul>
                        </div>
                        <!-- END NOTIFICATION BELL -->

                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-4 me-2"></i> 
                                <div class="text-start">
                                    <span class="fw-bold d-block lh-1" style="font-size: 0.95rem;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                                    <span class="badge <?= $userBadgeClass ?> rounded-pill" style="font-size: 0.7rem;">
                                        <?= mb_strtoupper($userBadgeLabel) ?>
                                    </span>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser1">
                                <li><a class="dropdown-item" href="#">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <!-- CLEAN URL: Changed href="logout.php" to href="logout" -->
                                <li><a class="dropdown-item text-danger" href="logout"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>