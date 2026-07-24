<?php
// DB Auto-Patch
if (!defined('DB_OFFLINE') && isset($pdo) && $pdo !== null) {
    try { $pdo->exec("ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN fcm_token TEXT DEFAULT NULL"); } catch (PDOException $e) { }
}

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

$notifications = [];
$unreadCount = 0;
if (!defined('DB_OFFLINE') && isset($pdo) && $pdo !== null) {
    $notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE target_user_id = ? OR target_role = ? ORDER BY created_at DESC LIMIT 10");
    $notifStmt->execute([$currentUserId, $currentUserRole]);
    $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
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

// Count Unread
$unreadCount = 0;
foreach ($notifications as $n) {
    if ($n['is_read'] == 0) $unreadCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>GB Construction & Enterprise Inc.</title>

    <link rel="manifest" href="manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GB Inventory">
    <link rel="apple-touch-icon" href="assets/LogoGB.png">
    <link rel="icon" type="image/png" href="assets/LogoGB.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= time() ?>">

    <!-- EARLY THEME INITIALIZATION (Prevents Flash of Unstyled Content) -->
    <script>
        (function() {
            const storedTheme = localStorage.getItem('cims_theme_preference') || 'system';
            function getSystemTheme() {
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            const effectiveTheme = storedTheme === 'system' ? getSystemTheme() : storedTheme;
            document.documentElement.setAttribute('data-bs-theme', effectiveTheme);
        })();
    </script>

    <style>
        #content {
            padding: 0 !important; 
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f4f7f6;
            width: 100%;
            overflow-x: hidden;
            transition: all 0.3s;
        }
        .top-navbar {
            padding: 12px 20px !important;
            background-color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important;
            border-bottom: 1px solid #e0e4e8;
            margin-bottom: 0 !important;
            width: 100%;
            z-index: 1000;
        }
        .app-footer {
            background-color: #ffffff;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
            margin-top: auto; 
            width: 100%;
        }
        
        /* ==========================================
           FIXED: SIDEBAR TOGGLE & ALIGNMENT
           ========================================== */
        #sidebar {
            width: 275px;
            min-width: 275px;
            transition: all 0.3s ease-in-out;
        }

        /* Desktop: Sidebar open by default, active class HIDES it */
        @media (min-width: 769px) {
            #sidebar.active {
                margin-left: -275px !important;
            }
        }

        /* Mobile: Sidebar hidden by default, active class SHOWS it */
        @media (max-width: 768px) {
            #sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1050;
                margin-left: -275px; 
            }
            #sidebar.active {
                margin-left: 0 !important;
            }
            #sidebarClose {
                display: block !important;
            }
            .user-role-text { display: none !important; }
            .top-navbar { padding: 12px 15px !important; }
        }

        /* Prevent text wrapping and misaligned icons */
        #sidebar .components li a {
            display: flex !important;
            align-items: center !important;
            white-space: normal !important; 
            line-height: 1.3 !important; 
            padding-right: 15px !important;
        }
        #sidebar .components li a i {
            min-width: 35px !important; 
            flex-shrink: 0 !important; 
            text-align: left !important;
            font-size: 1.15rem !important;
        }
    </style>

    <!-- CIMS THEME & SIDEBAR SCRIPTS -->
    <script>
        (function() {
            function getSystemTheme() {
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            function applyCimsTheme(pref) {
                const effectiveTheme = pref === 'system' ? getSystemTheme() : pref;
                document.documentElement.setAttribute('data-bs-theme', effectiveTheme);
                updateThemeDropdownUI(pref);

                // Dynamically sync Chart.js text & grid colors
                if (window.Chart) {
                    const isDark = effectiveTheme === 'dark';
                    Chart.defaults.color = isDark ? '#adbac7' : '#666666';
                    Chart.defaults.borderColor = isDark ? '#30363d' : '#e0e0e0';
                    ['pctChartInstance', 'pieChartInstance', 'daysChartInstance'].forEach(inst => {
                        if (window[inst]) {
                            if (window[inst].options.scales) {
                                if (window[inst].options.scales.x && window[inst].options.scales.x.grid) {
                                    window[inst].options.scales.x.grid.color = isDark ? '#21262d' : '#f0f0f0';
                                }
                                if (window[inst].options.scales.y && window[inst].options.scales.y.grid) {
                                    window[inst].options.scales.y.grid.color = isDark ? '#21262d' : '#f0f0f0';
                                }
                            }
                            window[inst].update();
                        }
                    });
                }
            }

            function updateThemeDropdownUI(pref) {
                document.querySelectorAll('.theme-option').forEach(el => {
                    const mode = el.getAttribute('data-theme-value');
                    const check = el.querySelector('.theme-check-icon');
                    if (mode === pref) {
                        el.classList.add('active', 'fw-bold');
                        if (check) check.classList.remove('d-none');
                    } else {
                        el.classList.remove('active', 'fw-bold');
                        if (check) check.classList.add('d-none');
                    }
                });
            }

            window.setCimsTheme = function(pref) {
                localStorage.setItem('cims_theme_preference', pref);
                applyCimsTheme(pref);
            };

            // Listen for OS theme changes if in system mode
            try {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
                    const currentPref = localStorage.getItem('cims_theme_preference') || 'system';
                    if (currentPref === 'system') {
                        applyCimsTheme('system');
                    }
                });
            } catch (e) {}

            document.addEventListener("DOMContentLoaded", function() {
                const currentPref = localStorage.getItem('cims_theme_preference') || 'system';
                applyCimsTheme(currentPref);

                const sidebar = document.getElementById('sidebar');
                const collapseBtn = document.getElementById('sidebarCollapse');
                const closeBtn = document.getElementById('sidebarClose');

                // Toggle Button (Hamburger)
                if (collapseBtn && sidebar) {
                    collapseBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        sidebar.classList.toggle('active');
                    });
                }

                // Close Button (Mobile X)
                if (closeBtn && sidebar) {
                    closeBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        sidebar.classList.remove('active');
                    });
                }

                // Auto-Close when clicking outside on mobile
                document.body.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('active')) {
                        if (!sidebar.contains(e.target) && !collapseBtn.contains(e.target)) {
                            sidebar.classList.remove('active');
                        }
                    }
                });
            });
        })();
    </script>
</head>
<body>
    <?php // include_once 'components/splash_screen.php'; ?>
    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0 d-flex align-items-center"><img src="assets/LogoGB.png" alt="GB Logo" class="me-2" style="height: 32px; width: auto; object-fit: contain;">GB Inventory</h4>
                <button type="button" id="sidebarClose" class="btn btn-link text-white d-md-none p-0 text-decoration-none"><i class="bi bi-x-lg fs-4"></i></button>
            </div>

            <ul class="list-unstyled components">
                <?php if (in_array($_SESSION['user_role'], ['admin', 'management', 'purchasing'])): ?>
                <li class="<?= $currentPage == 'analytics.php' || $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                    <a href="analytics"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <?php endif; ?>

                <li class="px-3 text-uppercase small fw-bold mb-2 mt-4" style="color: #adb5bd;">Master Data</li>
                <li class="<?= $currentPage == 'index.php' ? 'active' : '' ?>"><a href="index"><i class="bi bi-box-seam"></i> Materials Inventory</a></li>
                
                <?php if (in_array($_SESSION['user_role'], ['admin', 'purchasing'])): ?>
                <li class="<?= $currentPage == 'suppliers.php' ? 'active' : '' ?>"><a href="suppliers"><i class="bi bi-buildings"></i> Suppliers Database</a></li>
                <?php endif; ?>

                <li class="px-3 text-uppercase small fw-bold mb-2 mt-4" style="color: #adb5bd;">Transactions</li>
                <li class="<?= $currentPage == 'withdrawals.php' ? 'active' : '' ?>"><a href="withdrawals"><i class="bi bi-tools"></i> Material Withdrawals</a></li>
                <li class="<?= $currentPage == 'requisitions.php' ? 'active' : '' ?>"><a href="requisitions"><i class="bi bi-card-checklist"></i> Requisitions (RS)</a></li>
                <li class="<?= $currentPage == 'po.php' ? 'active' : '' ?>"><a href="po"><i class="bi bi-file-earmark-text"></i> Purchase Orders (PO)</a></li>
                
                <?php if (in_array($_SESSION['user_role'], ['admin', 'management', 'warehouse'])): ?>
                <li class="px-3 text-uppercase small fw-bold mb-2 mt-4" style="color: #adb5bd;">System</li>
                <li class="<?= $currentPage == 'audit.php' ? 'active' : '' ?>"><a href="audit"><i class="bi bi-clipboard-check"></i> Weekly Audit (Recount)</a></li>
                <?php endif; ?>

                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <li class="<?= $currentPage == 'projects.php' ? 'active' : '' ?>"><a href="projects"><i class="bi bi-briefcase"></i> Manage Projects</a></li>
                <li class="<?= $currentPage == 'categories.php' ? 'active' : '' ?>"><a href="categories"><i class="bi bi-tags"></i> Manage Categories</a></li>
                <li class="<?= $currentPage == 'units.php' ? 'active' : '' ?>"><a href="units"><i class="bi bi-rulers"></i> Manage Units</a></li>
                <li class="<?= $currentPage == 'users.php' ? 'active' : '' ?>"><a href="users"><i class="bi bi-people"></i> Manage Users</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <div id="content">
            <nav class="navbar navbar-expand-lg top-navbar">
                <div class="container-fluid px-0">
                    <button type="button" id="sidebarCollapse" class="btn btn-brand"><i class="bi bi-list fs-5"></i></button>
                    
                    <div class="d-flex align-items-center ms-auto">
                        <!-- SMS Inbox Quick Button -->
                        <?php if (in_array($currentUserRole, ['admin', 'purchasing', 'management'])): ?>
                        <div class="me-3 position-relative">
                            <button type="button" class="btn btn-link text-muted p-0 position-relative text-decoration-none" onclick="openSmsInboxModal()" title="Supplier SMS Inbox">
                                <i class="bi bi-chat-left-text fs-5"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success d-none" id="smsGlobalUnreadBadge" style="font-size: 0.65rem;">0</span>
                            </button>
                        </div>
                        <?php endif; ?>

                        <div class="dropdown me-3">
                            <a href="#" class="text-muted position-relative d-flex align-items-center text-decoration-none" id="dropdownNotif" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell fs-5"></i>
                                <?php if($unreadCount > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;"><?= $unreadCount ?></span>
                                <?php endif; ?>
                            </a>
                            
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
                                            $titleLower = strtolower($notif['title']);
                                            $targetLink = 'index'; 
                                            if (strpos($titleLower, 'requisition') !== false || strpos($titleLower, 'ready for po') !== false) {
                                                $targetLink = 'requisitions';
                                            } elseif (strpos($titleLower, 'po ') !== false || strpos($titleLower, 'delivery') !== false || strpos($titleLower, 'supply') !== false || strpos($titleLower, 'sms') !== false) {
                                                $targetLink = 'po';
                                            } elseif (strpos($titleLower, 'audit') !== false) {
                                                $targetLink = 'audit';
                                            }
                                            $bgClass = $notif['is_read'] == 0 ? 'bg-light border-start border-primary border-4' : 'bg-white';
                                            $iconClass = $notif['is_read'] == 0 ? 'text-primary' : 'text-muted';
                                        ?>
                                            <li>
                                                <a class="dropdown-item py-3 border-bottom <?= $bgClass ?>" href="<?= $targetLink ?>" style="white-space: normal;">
                                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                                        <strong class="mb-1 <?= $iconClass ?>" style="font-size: 0.9rem;"><?= htmlspecialchars($notif['title']) ?></strong>
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

                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-body" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-4 me-2"></i> 
                                <div class="text-start user-role-text">
                                    <span class="fw-bold d-block lh-1" style="font-size: 0.95rem;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser1" style="min-width: 200px;">
                                <li><a class="dropdown-item py-2 d-flex align-items-center" href="profile"><i class="bi bi-person-circle me-2 text-muted fs-6"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header text-uppercase fs-7 fw-bold opacity-75">Theme Preference</h6></li>
                                <li>
                                    <button type="button" class="dropdown-item py-2 d-flex align-items-center justify-content-between theme-option" data-theme-value="light" onclick="setCimsTheme('light')">
                                        <span class="d-flex align-items-center"><i class="bi bi-sun-fill me-2 text-warning"></i> Light</span>
                                        <i class="bi bi-check2 theme-check-icon d-none text-primary fw-bold"></i>
                                    </button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item py-2 d-flex align-items-center justify-content-between theme-option" data-theme-value="dark" onclick="setCimsTheme('dark')">
                                        <span class="d-flex align-items-center"><i class="bi bi-moon-stars-fill me-2 text-info"></i> Dark</span>
                                        <i class="bi bi-check2 theme-check-icon d-none text-primary fw-bold"></i>
                                    </button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item py-2 d-flex align-items-center justify-content-between theme-option" data-theme-value="system" onclick="setCimsTheme('system')">
                                        <span class="d-flex align-items-center"><i class="bi bi-display me-2 text-secondary"></i> System</span>
                                        <i class="bi bi-check2 theme-check-icon d-none text-primary fw-bold"></i>
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger d-flex align-items-center" href="logout"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            <?php if (defined('DB_OFFLINE')): ?>
                <div class="alert alert-danger border-0 rounded-0 m-0 d-flex align-items-center justify-content-between px-4 py-3 shadow-sm" role="alert" style="background-color: #fef2f2; border-bottom: 1px solid #fca5a5 !important;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-database-fill-slash text-danger fs-5 animate-pulse-db-header"></i>
                        <div>
                            <strong class="text-danger">Can't Connect, You're Offline</strong>
                            <span class="text-danger-emphasis ms-2 d-none d-md-inline" style="color: #991b1b;">The database is currently offline. Viewing page shell and cached elements only.</span>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger fw-bold border-2" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Retry
                    </button>
                </div>
                <style>
                    @keyframes pulseDbHeader {
                        0%, 100% { opacity: 1; }
                        50% { opacity: 0.4; }
                    }
                    .animate-pulse-db-header {
                        animation: pulseDbHeader 2s infinite ease-in-out;
                    }
                </style>
            <?php endif; ?>
            <!-- Content wrapper opens here, closes in footer.php -->