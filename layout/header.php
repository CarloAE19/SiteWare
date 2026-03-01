<?php
// DB Auto-Patch: Ensure the notifications table has an "is_read" tracker
try {
    $pdo->exec("ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0");
} catch (PDOException $e) { /* Column already exists, do nothing */ }

// Modernized Function to calculate "Time Ago"
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime; $ago = new DateTime($datetime); $diff = $now->diff($ago);
    $weeks = floor($diff->d / 7); $days = $diff->d - ($weeks * 7);
    $values = ['y' => $diff->y, 'm' => $diff->m, 'w' => $weeks, 'd' => $days, 'h' => $diff->h, 'i' => $diff->i, 's' => $diff->s];
    $string = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
    $parts = []; foreach ($string as $k => $v) { if ($values[$k]) $parts[] = $values[$k] . ' ' . $v . ($values[$k] > 1 ? 's' : ''); }
    if (!$full) $parts = array_slice($parts, 0, 1); return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}

$currentUserRole = $_SESSION['user_role'] ?? 'requestor';
$currentUserId = $_SESSION['user_id'] ?? 0;

// Fetch Top 10 Notifications
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE target_user_id = ? OR target_role = ? ORDER BY created_at DESC LIMIT 10");
$notifStmt->execute([$currentUserId, $currentUserRole]);
$notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

// Count Unread
$unreadCount = 0;
foreach ($notifications as $n) {
    if ($n['is_read'] == 0) $unreadCount++;
}

$headerRoles = [
    'admin' => ['label' => 'Admin', 'class' => 'bg-danger'],
    'warehouse' => ['label' => 'Warehouse In-Charge', 'class' => 'bg-success'],
    'management' => ['label' => 'Management / Approver', 'class' => 'bg-warning text-dark'],
    'purchasing' => ['label' => 'Purchasing Officer', 'class' => 'bg-info text-dark'],
    'requestor' => ['label' => 'Requestor', 'class' => 'bg-secondary']
];

$userBadgeClass = $headerRoles[$currentUserRole]['class'] ?? 'bg-secondary';
$userBadgeLabel = $headerRoles[$currentUserRole]['label'] ?? 'Unknown Role';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GB Construction & Enterprise Inc.</title>
    <link rel="icon" type="image/png" href="assets/LogoGB.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>

    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-cone-striped me-2" style="color: var(--gb-yellow);"></i>GB Inventory</h4>
                <button type="button" id="sidebarClose" class="btn btn-link text-white d-md-none p-0 text-decoration-none"><i class="bi bi-x-lg fs-4"></i></button>
            </div>

            <ul class="list-unstyled components">
                <?php if (in_array($_SESSION['user_role'], ['admin', 'management', 'purchasing'])): ?>
                <li class="px-3 text-uppercase small fw-bold mb-2 mt-2" style="color: #adb5bd;">Main Menu</li>
                <li class="<?= $currentPage == 'analytics.php' || $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                    <a href="analytics"><i class="bi bi-speedometer2 me-3"></i> Dashboard</a>
                </li>
                <?php endif; ?>

                <li class="px-3 text-uppercase small fw-bold mb-2 mt-4" style="color: #adb5bd;">Master Data</li>
                <li class="<?= $currentPage == 'index.php' ? 'active' : '' ?>"><a href="index"><i class="bi bi-box-seam me-3"></i> Materials Inventory</a></li>
                
                <?php if (in_array($_SESSION['user_role'], ['admin', 'purchasing'])): ?>
                <li class="<?= $currentPage == 'suppliers.php' ? 'active' : '' ?>"><a href="suppliers"><i class="bi bi-buildings me-3"></i> Suppliers Database</a></li>
                <?php endif; ?>

                <li class="px-3 text-uppercase small fw-bold mb-2 mt-4" style="color: #adb5bd;">Transactions</li>
                <li class="<?= $currentPage == 'withdrawals.php' ? 'active' : '' ?>"><a href="withdrawals"><i class="bi bi-tools me-3"></i> Material Withdrawals</a></li>
                <li class="<?= $currentPage == 'requisitions.php' ? 'active' : '' ?>"><a href="requisitions"><i class="bi bi-card-checklist me-3"></i> Requisitions (RS)</a></li>
                <li class="<?= $currentPage == 'po.php' ? 'active' : '' ?>"><a href="po"><i class="bi bi-file-earmark-text me-3"></i> Purchase Orders (PO)</a></li>
                
                <?php if (in_array($_SESSION['user_role'], ['admin', 'management', 'warehouse'])): ?>
                <li class="px-3 text-uppercase small fw-bold mb-2 mt-4" style="color: #adb5bd;">System</li>
                <li class="<?= $currentPage == 'audit.php' ? 'active' : '' ?>"><a href="audit"><i class="bi bi-clipboard-check me-3"></i> Monthly Audit (Recount)</a></li>
                <?php endif; ?>

                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <li class="<?= $currentPage == 'units.php' ? 'active' : '' ?>"><a href="units"><i class="bi bi-rulers me-3"></i> Manage Units</a></li>
                <li class="<?= $currentPage == 'users.php' ? 'active' : '' ?>"><a href="users"><i class="bi bi-people me-3"></i> Manage Users</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <div id="content">
            <nav class="navbar navbar-expand-lg top-navbar">
                <div class="container-fluid px-0">
                    <button type="button" id="sidebarCollapse" class="btn btn-brand"><i class="bi bi-list fs-5"></i></button>
                    
                    <div class="d-flex align-items-center ms-auto">
                        <!-- SMART NOTIFICATIONS DROPDOWN -->
                        <div class="dropdown me-3">
                            <a href="#" class="text-muted position-relative d-flex align-items-center text-decoration-none" id="dropdownNotif" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell fs-5"></i>
                                <?php if($unreadCount > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                                        <?= $unreadCount ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            
                            <!-- FIXED: Added 'notif-menu' class instead of inline 350px width -->
                            <ul class="dropdown-menu dropdown-menu-end shadow notif-menu" aria-labelledby="dropdownNotif">
                                <li>
                                    <div class="dropdown-header d-flex justify-content-between align-items-center border-bottom pb-2">
                                        <h6 class="fw-bold mb-0 text-dark">Notifications</h6>
                                        <?php if($unreadCount > 0): ?>
                                            <button class="btn btn-sm btn-link text-decoration-none p-0 text-primary fw-bold" onclick="markAllNotifsRead()" style="font-size: 0.8rem;">Mark all as read</button>
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <div style="max-height: 350px; overflow-y: auto;">
                                    <?php if(count($notifications) > 0): ?>
                                        <?php foreach($notifications as $notif): 
                                            // SMART ROUTER: Decides where clicking the notification takes you
                                            $titleLower = strtolower($notif['title']);
                                            $targetLink = 'index';
                                            if (strpos($titleLower, 'requisition') !== false || strpos($titleLower, 'ready for po') !== false) {
                                                $targetLink = 'requisitions';
                                            } elseif (strpos($titleLower, 'po ') !== false || strpos($titleLower, 'delivery') !== false || strpos($titleLower, 'supply') !== false || strpos($titleLower, 'sms') !== false) {
                                                $targetLink = 'po';
                                            } elseif (strpos($titleLower, 'audit') !== false) {
                                                $targetLink = 'audit';
                                            }
                                            
                                            // Styling for Unread vs Read
                                            $bgClass = $notif['is_read'] == 0 ? 'bg-light border-start border-primary border-4' : 'bg-white';
                                            $iconClass = $notif['is_read'] == 0 ? 'text-primary' : 'text-muted';
                                        ?>
                                            <li>
                                                <a class="dropdown-item py-3 border-bottom <?= $bgClass ?>" href="#" onclick="readNotifAndNavigate(<?= $notif['id'] ?>, '<?= $targetLink ?>')" style="white-space: normal;">
                                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                                        <strong class="mb-1 <?= $iconClass ?>" style="font-size: 0.9rem;">
                                                            <?= htmlspecialchars($notif['title']) ?>
                                                        </strong>
                                                        <small class="text-muted" style="font-size: 0.7rem; min-width: 60px; text-align: right;"><?= time_elapsed_string($notif['created_at']) ?></small>
                                                    </div>
                                                    <p class="mb-0 text-muted" style="font-size: 0.8rem; line-height: 1.4;"><?= htmlspecialchars($notif['message']) ?></p>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li><span class="dropdown-item text-muted small text-center py-4"><i class="bi bi-bell-slash d-block fs-3 mb-2"></i>No new notifications.</span></li>
                                    <?php endif; ?>
                                </div>
                            </ul>
                        </div>

                        <!-- USER DROPDOWN -->
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
                                <li><a class="dropdown-item" href="profile"><i class="bi bi-person-circle me-2 text-muted"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>