<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: index");
    exit;
}
require_once 'Connection/db.php';
require_once 'classes/BackupService.php';

// Determine Active Tab from query param (defaults to 'users')
$validTabs = ['users', 'categories', 'units', 'projects', 'general', 'backup'];
$activeTab = isset($_GET['tab']) && in_array($_GET['tab'], $validTabs) ? $_GET['tab'] : 'users';

// Initialize SiteWare Backup Service & Auto-Daily Backup
$backupService = new BackupService($pdo);
try {
    $backupService->runScheduledDailyBackup(14);
} catch (Throwable $e) {
}
$backupFiles = $backupService->listBackups();

// ==========================================
// DATA FETCHING FOR ALL TABS
// ==========================================

// 1. Fetch Users
$stmt = $pdo->query("SELECT id, name, username, role, status, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roleDisplay = [
    'admin' => ['label' => 'Admin', 'class' => 'bg-danger'],
    'warehouse' => ['label' => 'Warehouse In-Charge', 'class' => 'bg-success'],
    'management' => ['label' => 'Management', 'class' => 'bg-warning text-dark'],
    'purchasing' => ['label' => 'Purchasing Officer', 'class' => 'bg-info text-dark'],
    'requestor' => ['label' => 'Project Engineer', 'class' => 'bg-secondary']
];

// 2. Fetch Categories
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
}
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Units
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS units (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unit_name VARCHAR(50) NOT NULL,
        abbreviation VARCHAR(20) NOT NULL,
        reorder_level INT NOT NULL DEFAULT 10,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
}
$unitsList = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM inventory i WHERE i.unit = u.unit_name) AS item_count 
    FROM units u 
    ORDER BY u.unit_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Fetch Projects
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_name VARCHAR(150) NOT NULL UNIQUE,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
}
$projects = $pdo->query("
    SELECT p.*,
           (SELECT COUNT(*) FROM requisitions r WHERE r.project_name = p.project_name) AS rs_count,
           (SELECT COUNT(*) FROM withdrawals w WHERE w.project_name = p.project_name) AS ws_count
    FROM projects p 
    ORDER BY p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch General System Settings
$bg_path = 'assets/img/default_login_bg.png';
$cur_blur = 12;
$cur_idle_enabled = true;
$cur_idle_lock = 15;
$cur_idle_logout = 30;

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('login_background','login_blur','idle_lock_enabled','idle_lock_minutes','idle_logout_minutes')");
    $stmt->execute();
    $lc_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($lc_settings['login_background']))
        $bg_path = $lc_settings['login_background'];
    if (isset($lc_settings['login_blur']) && $lc_settings['login_blur'] !== '')
        $cur_blur = (int) $lc_settings['login_blur'];
    $cur_idle_enabled = ($lc_settings['idle_lock_enabled'] ?? '1') === '1';
    $cur_idle_lock = (float) ($lc_settings['idle_lock_minutes'] ?? 15);
    $cur_idle_logout = (float) ($lc_settings['idle_logout_minutes'] ?? 30);
} catch (Exception $e) {
}
$cur_blur = max(0, min(30, $cur_blur));
$cur_scale = round(1 + ($cur_blur * 0.006), 3);
$bg_version = file_exists($bg_path) ? filemtime($bg_path) : time();
$settings_app_base = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$bg_preview_url = $settings_app_base . '/' . ltrim($bg_path, '/') . '?v=' . $bg_version;

include 'layout/header.php';
?>

<!-- Inject Page Specific CSS -->
<link rel="stylesheet" href="assets/css/projects.css">
<style>
    .settings-nav-pills .nav-link {
        color: #495057;
        font-weight: 600;
        padding: 0.75rem 1.25rem;
        border-radius: 10px;
        transition: all 0.2s ease-in-out;
        border: 1px solid transparent;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .settings-nav-pills .nav-link:hover {
        background-color: #e9ecef;
        color: var(--gb-dark, #212529);
    }

    .settings-nav-pills .nav-link.active {
        background-color: var(--gb-dark, #1e293b);
        color: #ffffff !important;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }

    .settings-nav-pills .nav-link.active .badge-count {
        background-color: var(--gb-yellow, #ffc107) !important;
        color: #000 !important;
    }

    .settings-header-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: white;
        border-radius: 14px;
    }

    [data-bs-theme="dark"] .settings-nav-pills .nav-link {
        color: #cbd5e1;
    }

    [data-bs-theme="dark"] .settings-nav-pills .nav-link:hover {
        background-color: #334155;
    }

    [data-bs-theme="dark"] .settings-nav-pills .nav-link.active {
        background-color: #3b82f6;
        color: #ffffff !important;
    }

    @media (max-width: 767.98px) {
        .settings-nav-pills {
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 8px;
        }

        .settings-nav-pills .nav-item {
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* Mobile Touch & Multi-Device Optimizations for SiteWare Backup & Recovery */
        .backup-mobile-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 12px;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        [data-bs-theme="dark"] .backup-mobile-card {
            background: #1e293b;
            border-color: #334155;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        .backup-mobile-actions .btn {
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            font-weight: 600;
            border-radius: 8px;
        }

        .backup-action-header-btns .btn {
            min-height: 46px;
            border-radius: 10px;
        }

        .backup-mobile-card:active {
            transform: scale(0.99);
        }
    }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <!-- Page Header Banner -->
    <div class="card border-0 shadow-sm p-4 mb-4 settings-header-card">
        <div class="row align-items-center g-3">
            <div class="col-12 col-md-8 text-center text-md-start">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
                    <span class="badge bg-warning text-dark px-3 py-1 fw-bold rounded-pill text-uppercase"
                        style="font-size: 0.75rem;">Control Panel</span>
                </div>
                <h3 class="mb-1 fw-bold text-white"><i class="bi bi-gear-fill me-2 text-warning"></i>System Settings
                </h3>
                <p class="text-white-50 mb-0 small">Centralized hub to manage user accounts, material categories,
                    measurement units, active projects, and system configurations.</p>
            </div>
        </div>
    </div>

    <!-- Tab Navigation Bar -->
    <div class="card border-0 shadow-sm p-2 mb-4 bg-white rounded-3">
        <ul class="nav nav-pills settings-nav-pills w-100" id="settingsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'users' ? 'active' : '' ?>" id="users-tab"
                    data-bs-toggle="pill" data-bs-target="#tab-users" type="button" role="tab"
                    onclick="switchSettingsTab('users')">
                    <i class="bi bi-people-fill"></i> Users & Access
                    <span class="badge rounded-pill bg-secondary badge-count ms-1"><?= count($users) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'categories' ? 'active' : '' ?>" id="categories-tab"
                    data-bs-toggle="pill" data-bs-target="#tab-categories" type="button" role="tab"
                    onclick="switchSettingsTab('categories')">
                    <i class="bi bi-tags-fill"></i> Categories
                    <span class="badge rounded-pill bg-secondary badge-count ms-1"><?= count($categories) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'units' ? 'active' : '' ?>" id="units-tab"
                    data-bs-toggle="pill" data-bs-target="#tab-units" type="button" role="tab"
                    onclick="switchSettingsTab('units')">
                    <i class="bi bi-rulers"></i> Units of Measure
                    <span class="badge rounded-pill bg-secondary badge-count ms-1"><?= count($unitsList) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'projects' ? 'active' : '' ?>" id="projects-tab"
                    data-bs-toggle="pill" data-bs-target="#tab-projects" type="button" role="tab"
                    onclick="switchSettingsTab('projects')">
                    <i class="bi bi-briefcase-fill"></i> Projects
                    <span class="badge rounded-pill bg-secondary badge-count ms-1"><?= count($projects) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>" id="general-tab"
                    data-bs-toggle="pill" data-bs-target="#tab-general" type="button" role="tab"
                    onclick="switchSettingsTab('general')">
                    <i class="bi bi-sliders"></i> General & Appearance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'backup' ? 'active' : '' ?>" id="backup-tab"
                    data-bs-toggle="pill" data-bs-target="#tab-backup" type="button" role="tab"
                    onclick="switchSettingsTab('backup')">
                    <i class="bi bi-shield-lock-fill text-info"></i> Backup &amp; Restore
                    <span class="badge rounded-pill bg-secondary badge-count ms-1"
                        id="backupNavBadge"><?= count($backupFiles) ?></span>
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="settingsTabContent">

        <!-- ======================================================== -->
        <!-- TAB 1: USERS & ACCESS MANAGEMENT                         -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'users' ? 'show active' : '' ?>" id="tab-users" role="tabpanel">
            <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                <div class="row align-items-center mb-4 g-3">
                    <div class="col-12 col-md-8 text-center text-md-start">
                        <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-people me-2 text-primary"></i>User Account
                            Directory</h4>
                        <small class="text-muted">Manage system privileges, activate/deactivate staff accounts, or
                            update login credentials.</small>
                    </div>
                    <div class="col-12 col-md-4 text-md-end">
                        <button class="btn btn-brand shadow-sm w-100 w-md-auto fw-bold px-4" data-bs-toggle="modal"
                            data-bs-target="#userModal" onclick="openAddUserModal()">
                            <i class="bi bi-person-plus me-1"></i> Add New User
                        </button>
                    </div>
                </div>

                <div class="table-responsive border rounded shadow-sm">
                    <table class="table table-hover align-middle mb-0 text-nowrap" id="usersTable">
                        <thead class="table-dark">
                            <tr>
                                <th class="py-3">ID</th>
                                <th class="py-3">Full Name</th>
                                <th class="py-3">Username</th>
                                <th class="py-3">Role</th>
                                <th class="py-3 text-center">Status</th>
                                <th class="text-center py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user):
                                $status = strtolower($user['status'] ?? 'active');
                                $isActive = ($status === 'active');
                                ?>
                                <tr class="<?= !$isActive ? 'table-light' : '' ?>">
                                    <td class="text-muted fw-bold" data-label="ID">
                                        #<?= str_pad($user['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td class="fw-bold text-dark" data-label="Full Name">
                                        <span><i
                                                class="bi bi-person-circle me-2 <?= $isActive ? 'text-muted' : 'text-danger' ?>"></i><?= htmlspecialchars($user['name']) ?></span>
                                    </td>
                                    <td class="text-primary fw-bold" data-label="Username">
                                        @<?= htmlspecialchars($user['username']) ?></td>
                                    <td data-label="Role">
                                        <span
                                            class="badge <?= $roleDisplay[$user['role']]['class'] ?? 'bg-secondary' ?> px-3 py-2 shadow-sm">
                                            <?= mb_strtoupper($roleDisplay[$user['role']]['label'] ?? 'UNKNOWN') ?>
                                        </span>
                                    </td>
                                    <td class="text-center" data-label="Status">
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-success px-3 py-2 shadow-sm"><i
                                                    class="bi bi-check-circle me-1"></i>ACTIVE</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger px-3 py-2 shadow-sm"><i
                                                    class="bi bi-slash-circle me-1"></i>INACTIVE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" data-label="Actions">
                                        <button type="button" class="btn btn-sm btn-outline-primary shadow-sm me-1"
                                            data-bs-toggle="modal" data-bs-target="#userModal"
                                            onclick="openEditUserModal(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>', '<?= htmlspecialchars(addslashes($user['username'])) ?>', '<?= htmlspecialchars($user['role']) ?>', '<?= htmlspecialchars($status) ?>')">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>

                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                            <form method="POST" action="process/process.php" class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to <?= $isActive ? 'deactivate' : 'activate' ?> <?= htmlspecialchars(addslashes($user['name'])) ?>?');">
                                                <input type="hidden" name="action" value="toggle_user_status">
                                                <input type="hidden" name="csrf_token"
                                                    value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="return_tab" value="users">
                                                <?php if ($isActive): ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-warning shadow-sm"
                                                        title="Deactivate User">
                                                        <i class="bi bi-person-x"></i> Deactivate
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-success shadow-sm"
                                                        title="Activate User">
                                                        <i class="bi bi-person-check"></i> Activate
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border px-2 py-2"
                                                title="Current Active Session"><i
                                                    class="bi bi-person-check-fill text-success me-1"></i>You</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- TAB 2: INVENTORY CATEGORIES                              -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'categories' ? 'show active' : '' ?>" id="tab-categories"
            role="tabpanel">
            <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                <div class="row align-items-center mb-4 g-3">
                    <div class="col-12 col-md-8 text-center text-md-start">
                        <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-tags me-2 text-primary"></i>Material
                            Categories</h4>
                        <small class="text-muted">Define and organize product categories across the inventory
                            database.</small>
                    </div>
                    <div class="col-12 col-md-4 text-md-end">
                        <button class="btn btn-brand w-100 w-md-auto fw-bold shadow-sm" data-bs-toggle="modal"
                            data-bs-target="#categoryModal" onclick="openAddCategoryModal()">
                            <i class="bi bi-plus-lg me-2"></i>Add Category
                        </button>
                    </div>
                </div>

                <div class="table-responsive border rounded shadow-sm">
                    <table class="table table-hover align-middle mb-0 text-nowrap" id="categoriesTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Category Name</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($categories) > 0): ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td class="text-muted fw-bold">#<?= $cat['id'] ?></td>
                                        <td class="fw-bold text-primary fs-6"><?= htmlspecialchars($cat['category_name']) ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary me-1"
                                                onclick="openEditCategoryModal(<?= $cat['id'] ?>, '<?= addslashes(htmlspecialchars($cat['category_name'])) ?>')">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            <form method="POST" action="process/process.php" class="d-inline"
                                                onsubmit="return confirm('Delete this category?');">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                <input type="hidden" name="return_tab" value="categories">
                                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm"><i
                                                        class="bi bi-trash3"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No categories found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- TAB 3: UNITS OF MEASUREMENT                              -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'units' ? 'show active' : '' ?>" id="tab-units" role="tabpanel">
            <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                <div class="row align-items-center mb-4 g-3">
                    <div class="col-12 col-md-8 text-center text-md-start">
                        <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-rulers me-2 text-primary"></i>Units of
                            Measurement</h4>
                        <small class="text-muted">Set standard unit abbreviations and default low-stock threshold
                            triggers.</small>
                    </div>
                    <div class="col-12 col-md-4 text-md-end">
                        <button class="btn btn-brand shadow-sm w-100 w-md-auto fw-bold px-4" data-bs-toggle="modal"
                            data-bs-target="#unitModal" onclick="openAddUnitModal()">
                            <i class="bi bi-plus-circle me-1"></i> Add New Unit
                        </button>
                    </div>
                </div>

                <div class="table-responsive border rounded shadow-sm">
                    <table class="table table-hover align-middle mb-0 text-nowrap" id="unitsTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Unit Name</th>
                                <th>Abbreviation</th>
                                <th class="text-center">Low Stock Alert (≤)</th>
                                <th class="text-center">Assigned Items</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($unitsList) > 0): ?>
                                <?php foreach ($unitsList as $u): ?>
                                    <?php $itemCount = (int) ($u['item_count'] ?? 0); ?>
                                    <tr>
                                        <td class="text-muted fw-bold">#<?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($u['unit_name']) ?></td>
                                        <td><span
                                                class="badge bg-secondary px-3 py-2"><?= htmlspecialchars($u['abbreviation']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark px-3 py-2 shadow-sm">
                                                <i class="bi bi-exclamation-triangle me-1"></i>≤
                                                <?= (int) $u['reorder_level'] ?>         <?= htmlspecialchars($u['abbreviation']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($itemCount > 0): ?>
                                                <a href="index?search=<?= urlencode($u['unit_name']) ?>"
                                                    class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 text-decoration-none shadow-sm"
                                                    title="View <?= $itemCount ?> item(s) in Inventory">
                                                    <i class="bi bi-boxes me-1"></i><?= $itemCount ?>
                                                    item<?= $itemCount > 1 ? 's' : '' ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-3 py-2">0 items</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary me-1"
                                                onclick="openEditUnitModal(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['unit_name'])) ?>', '<?= addslashes(htmlspecialchars($u['abbreviation'])) ?>', <?= (int) $u['reorder_level'] ?>)">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            <?php if ($itemCount > 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm disabled"
                                                    title="Cannot delete: In use by <?= $itemCount ?> inventory item(s)">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            <?php else: ?>
                                                <form method="POST" action="process/process.php" class="d-inline"
                                                    onsubmit="return confirm('Are you sure you want to delete unit \'<?= addslashes(htmlspecialchars($u['unit_name'])) ?>\'?');">
                                                    <input type="hidden" name="action" value="delete_unit">
                                                    <input type="hidden" name="unit_id" value="<?= $u['id'] ?>">
                                                    <input type="hidden" name="return_tab" value="units">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm"
                                                        title="Delete Unit">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No measurement units found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- TAB 4: PROJECTS DIRECTORY                                -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'projects' ? 'show active' : '' ?>" id="tab-projects"
            role="tabpanel">
            <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
                <div class="row align-items-center mb-4 g-3">
                    <div class="col-12 col-md-8 text-center text-md-start">
                        <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-briefcase me-2 text-primary"></i>Projects
                            Directory</h4>
                        <small class="text-muted">Register construction sites, project codes, and delivery
                            addresses.</small>
                    </div>
                    <div class="col-12 col-md-4 text-md-end">
                        <button class="btn btn-brand w-100 w-md-auto fw-bold shadow-sm" data-bs-toggle="modal"
                            data-bs-target="#projectModal" onclick="openAddProjectModal()">
                            <i class="bi bi-plus-lg me-2"></i>New Project
                        </button>
                    </div>
                </div>

                <!-- Quick Status Filter Pills -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 proj-filter-btn active"
                        data-filter="all" onclick="filterProjectsTable('all', this)">
                        All Projects <span class="badge bg-secondary ms-1"><?= count($projects) ?></span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 proj-filter-btn"
                        data-filter="active" onclick="filterProjectsTable('active', this)">
                        <i class="bi bi-check-circle me-1"></i>Active Sites <span
                            class="badge bg-success ms-1"><?= count(array_filter($projects, fn($p) => ($p['status'] ?? '') === 'active')) ?></span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 proj-filter-btn"
                        data-filter="inactive" onclick="filterProjectsTable('inactive', this)">
                        <i class="bi bi-pause-circle me-1"></i>Inactive / Completed <span
                            class="badge bg-secondary ms-1"><?= count(array_filter($projects, fn($p) => ($p['status'] ?? '') !== 'active')) ?></span>
                    </button>
                </div>

                <div class="table-responsive border rounded shadow-sm">
                    <table class="table table-hover align-middle mb-0 text-nowrap" id="projectsTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 100px;">Project ID</th>
                                <th>Project Name</th>
                                <th>Location / Address</th>
                                <th>Description</th>
                                <th class="text-center">Linked Activity</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($projects) > 0): ?>
                                <?php foreach ($projects as $proj): ?>
                                    <?php
                                    $rsCount = (int) ($proj['rs_count'] ?? 0);
                                    $wsCount = (int) ($proj['ws_count'] ?? 0);
                                    $totalUsage = $rsCount + $wsCount;
                                    ?>
                                    <tr data-status="<?= htmlspecialchars($proj['status'] ?? 'active') ?>">
                                        <td class="text-muted fw-bold" data-label="ID">
                                            <?= htmlspecialchars($proj['project_code'] ?? '#' . $proj['id']) ?>
                                        </td>
                                        <td class="fw-bold text-primary" data-label="Project Name">
                                            <?= htmlspecialchars($proj['project_name']) ?>
                                        </td>
                                        <td class="text-dark fw-semibold small" data-label="Location / Address">
                                            <?php if (!empty($proj['address'])): ?>
                                                <i
                                                    class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars($proj['address']) ?>
                                            <?php else: ?>
                                                <span class="text-muted opacity-75">Not specified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted text-wrap" style="max-width: 250px;" data-label="Description">
                                            <?= htmlspecialchars($proj['description'] ?? 'No description provided.') ?>
                                        </td>
                                        <td class="text-center" data-label="Linked Activity">
                                            <div class="d-flex flex-wrap gap-1 justify-content-center">
                                                <?php if ($rsCount > 0): ?>
                                                    <a href="requisitions?search=<?= urlencode($proj['project_name']) ?>"
                                                        class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 text-decoration-none shadow-sm"
                                                        title="View <?= $rsCount ?> Material Requisition(s)">
                                                        <i class="bi bi-file-earmark-text me-1"></i><?= $rsCount ?> RS
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($wsCount > 0): ?>
                                                    <a href="withdrawals?search=<?= urlencode($proj['project_name']) ?>"
                                                        class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 text-decoration-none shadow-sm"
                                                        title="View <?= $wsCount ?> Withdrawal Slip(s)">
                                                        <i class="bi bi-box-arrow-right me-1"></i><?= $wsCount ?> WS
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($totalUsage === 0): ?>
                                                    <span class="badge bg-light text-muted border px-2 py-1">0 records</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center" data-label="Status">
                                            <form method="POST" action="process/process.php" class="d-inline"
                                                onsubmit="return confirm('Toggle status of project \'<?= addslashes(htmlspecialchars($proj['project_name'])) ?>\' to <?= $proj['status'] === 'active' ? 'Inactive' : 'Active' ?>?');">
                                                <input type="hidden" name="action" value="toggle_project_status">
                                                <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                                                <input type="hidden" name="return_tab" value="projects">
                                                <?php if ($proj['status'] === 'active'): ?>
                                                    <button type="submit"
                                                        class="btn btn-sm btn-success rounded-pill px-3 py-1 shadow-sm"
                                                        title="Active Project — Click to mark Inactive">
                                                        <i class="bi bi-check-circle-fill me-1"></i>Active
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit"
                                                        class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 shadow-sm"
                                                        title="Inactive Project — Click to mark Active">
                                                        <i class="bi bi-pause-circle me-1"></i>Inactive
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                        <td class="text-end" data-label="Actions">
                                            <button type="button" class="btn btn-sm btn-outline-info me-1 shadow-sm"
                                                onclick="openProjectDetailsModal(<?= $proj['id'] ?>)"
                                                title="View Project Requisitions, Withdrawals & Material History">
                                                <i class="bi bi-eye"></i> Details
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary me-1"
                                                onclick="openEditProjectModal(<?= $proj['id'] ?>, '<?= addslashes(htmlspecialchars($proj['project_code'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($proj['project_name'])) ?>', '<?= addslashes(htmlspecialchars($proj['address'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($proj['description'] ?? '')) ?>', '<?= $proj['status'] ?>')">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            <?php if ($totalUsage > 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm disabled"
                                                    title="Cannot delete: Linked to <?= $rsCount ?> RS and <?= $wsCount ?> WS">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            <?php else: ?>
                                                <form method="POST" action="process/process.php" class="d-inline"
                                                    onsubmit="return confirm('Are you sure you want to delete project \'<?= addslashes(htmlspecialchars($proj['project_name'])) ?>\'?');">
                                                    <input type="hidden" name="action" value="delete_project">
                                                    <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                                                    <input type="hidden" name="return_tab" value="projects">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm"
                                                        title="Delete Project">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No projects found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- TAB 5: GENERAL & APPEARANCE PREFERENCES                 -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'general' ? 'show active' : '' ?>" id="tab-general"
            role="tabpanel">
            <div class="card border-0 shadow-sm p-4 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-image text-primary me-2"></i>Login Page Customization
                    </h5>
                </div>
                <p class="text-muted small mb-4">Customize the backdrop image and real-time frosted glass blur intensity
                    for staff on the login authentication portal.</p>

                <!-- ===== LIVE PREVIEW ===== -->
                <h6 class="fw-bold mb-3 border-bottom pb-2">
                    <i class="bi bi-eye me-1 text-primary"></i>Live Preview
                </h6>
                <div id="settingsLoginPreviewWrap" style="
                    position: relative;
                    width: 100%;
                    height: 340px;
                    border-radius: 14px;
                    overflow: hidden;
                    border: 1.5px solid #e2e8f0;
                    background: #0f172a;
                    margin-bottom: 2rem;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                ">
                    <!-- Blurred background layer -->
                    <div id="settingsPreviewBgLayer" style="
                        position: absolute; inset: 0;
                        background-image: url('<?= htmlspecialchars($bg_preview_url) ?>');
                        background-size: cover;
                        background-position: center;
                        filter: blur(<?= $cur_blur ?>px);
                        transform: scale(<?= $cur_scale ?>);
                        transform-origin: center;
                        transition: filter 0.25s ease, transform 0.25s ease;
                    "></div>
                    <!-- Dark overlay -->
                    <div style="position: absolute; inset: 0; background: rgba(15,23,42,0.45);"></div>

                    <!-- ★ Scaled accurate login page replica ★ -->
                    <div style="
                        position: absolute; inset: 0;
                        display: flex; flex-direction: column;
                        align-items: center; justify-content: center;
                        padding-bottom: 18px;
                    ">
                        <!-- Login card — full real size, then CSS-scaled down -->
                        <div style="
                            transform: scale(0.52);
                            transform-origin: center center;
                            background: #ffffff;
                            width: 440px;
                            border-radius: 20px;
                            padding: 36px 40px 32px;
                            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);
                            text-align: center;
                            flex-shrink: 0;
                            pointer-events: none;
                            user-select: none;
                        ">
                            <!-- Logo -->
                            <img src="<?= $settings_app_base ?>/assets/LogoGB.png" alt="Logo" style="width:68px;height:68px;object-fit:contain;border-radius:14px;
                                        background:#fff;padding:5px;
                                        box-shadow:0 4px 12px rgba(0,0,0,0.08);
                                        margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;">
                            <!-- Brand name -->
                            <div
                                style="font-size:1.45rem;font-weight:800;color:#0f172a;letter-spacing:-0.02em;margin-bottom:2px;font-family:'Inter',sans-serif;">
                                SiteWare</div>
                            <!-- Heading -->
                            <div
                                style="font-size:1.55rem;font-weight:700;color:#0f172a;letter-spacing:-0.02em;margin-bottom:24px;font-family:'Inter',sans-serif;">
                                Login</div>

                            <!-- Username field -->
                            <div style="margin-bottom:18px;text-align:left;">
                                <div
                                    style="font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:6px;letter-spacing:0.02em;">
                                    Username</div>
                                <div style="position:relative;">
                                    <i class="bi bi-person"
                                        style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#64748b;font-size:1.05rem;z-index:1;"></i>
                                    <div
                                        style="width:100%;padding:11px 14px 11px 42px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.92rem;color:#94a3b8;background:#f1f5f9;font-family:'Inter',sans-serif;">
                                        Enter your username
                                    </div>
                                </div>
                            </div>

                            <!-- Password field -->
                            <div style="margin-bottom:18px;text-align:left;">
                                <div
                                    style="font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:6px;letter-spacing:0.02em;">
                                    Password</div>
                                <div style="position:relative;">
                                    <i class="bi bi-lock"
                                        style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#64748b;font-size:1.05rem;z-index:1;"></i>
                                    <i class="bi bi-eye-slash"
                                        style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#64748b;font-size:1.05rem;z-index:1;"></i>
                                    <div
                                        style="width:100%;padding:11px 42px 11px 42px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.92rem;color:#94a3b8;background:#f1f5f9;font-family:'Inter',sans-serif;">
                                        Enter your password
                                    </div>
                                </div>
                            </div>

                            <!-- Remember Me & Forgot Password replica -->
                            <div
                                style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:14px;text-align:left;">
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <div
                                        style="width:14px;height:14px;border:1.5px solid #cbd5e1;border-radius:4px;background:#fff;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-check" style="font-size:0.75rem;color:#4f46e5;"></i>
                                    </div>
                                    <span style="font-size:0.72rem;color:#475569;font-weight:500;">Remember
                                        username</span>
                                </div>
                                <span style="font-size:0.72rem;color:#4f46e5;font-weight:600;">Forgot password?</span>
                            </div>

                            <!-- Login button -->
                            <div
                                style="background:linear-gradient(135deg,#4f46e5 0%,#3730a3 100%);color:#fff;font-size:0.95rem;font-weight:700;border-radius:10px;padding:13px;text-align:center;letter-spacing:0.02em;font-family:'Inter',sans-serif;box-shadow:0 4px 12px rgba(79,70,229,0.25);">
                                Login
                            </div>
                        </div>
                    </div>

                    <!-- Page footer replica -->
                    <div
                        style="position:absolute;bottom:8px;left:0;right:0;text-align:center;font-size:0.62rem;color:rgba(255,255,255,0.85);text-shadow:0 1px 3px rgba(0,0,0,0.7);letter-spacing:0.01em;pointer-events:none;">
                        &copy; 2026 Genetian Builders &amp; Enterprises Inc. &nbsp;|&nbsp; Powered by <span
                            style="font-weight:700;text-decoration:underline;">The Medyas</span>
                    </div>

                    <!-- Blur badge -->
                    <div style="
                        position: absolute; top: 10px; right: 10px;
                        background: rgba(0,0,0,0.55); backdrop-filter: blur(6px);
                        color: #fff; font-size: 0.72rem; font-weight: 700;
                        padding: 3px 9px; border-radius: 20px; letter-spacing: 0.02em;
                    ">
                        Blur: <span id="settingsBlurBadge"><?= $cur_blur ?>px</span>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- LEFT: Background image upload -->
                    <div class="col-12 col-md-6 border-end pe-md-4">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">
                            <i class="bi bi-image-fill me-1 text-primary"></i>Background Image
                        </h6>
                        <form method="POST" action="process/process.php" enctype="multipart/form-data" class="mb-3"
                            id="settingsBgUploadForm">
                            <input type="hidden" name="action" value="update_login_bg">
                            <input type="hidden" name="return_tab" value="general">
                            <label class="form-label fw-bold small">Upload New Background</label>
                            <div class="input-group mb-2">
                                <input type="file" class="form-control form-control-sm" name="login_bg"
                                    accept="image/jpeg,image/png,image/webp,image/gif" id="settingsBgFileInput"
                                    required>
                                <button class="btn btn-brand btn-sm fw-bold px-3" type="submit">
                                    <i class="bi bi-upload me-1"></i>Upload
                                </button>
                            </div>
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>JPG, PNG, WEBP, GIF · Max
                                5MB</small>
                        </form>

                        <?php if ($bg_path !== 'assets/img/default_login_bg.png'): ?>
                            <form method="POST" action="process/process.php">
                                <input type="hidden" name="action" value="reset_login_bg">
                                <input type="hidden" name="return_tab" value="general">
                                <button class="btn btn-outline-danger btn-sm fw-bold w-100" type="submit"
                                    onclick="return confirm('Reset the background image to default?');">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Default Image
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- RIGHT: Blur intensity slider -->
                    <div class="col-12 col-md-6 ps-md-4">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">
                            <i class="bi bi-sliders me-1 text-primary"></i>Blur Intensity
                        </h6>
                        <form method="POST" action="process/process.php" id="settingsBlurForm">
                            <input type="hidden" name="action" value="update_login_blur">
                            <input type="hidden" name="return_tab" value="general">

                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold small mb-0" for="settingsBlurSlider">Blur
                                    Amount</label>
                                <span class="badge bg-primary" id="settingsBlurValueBadge"><?= $cur_blur ?>px</span>
                            </div>

                            <input type="range" class="form-range" id="settingsBlurSlider" name="login_blur" min="0"
                                max="30" step="1" value="<?= $cur_blur ?>" style="accent-color: #4f46e5;">

                            <div class="d-flex justify-content-between text-muted"
                                style="font-size: 0.72rem; margin-top: -2px;">
                                <span>0px (No blur)</span>
                                <span>30px (Max blur)</span>
                            </div>

                            <!-- Preset buttons -->
                            <div class="d-flex gap-2 flex-wrap mt-3 mb-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm settings-blur-preset"
                                    data-val="0">None</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm settings-blur-preset"
                                    data-val="6">Light</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm settings-blur-preset"
                                    data-val="12">Medium</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm settings-blur-preset"
                                    data-val="20">Heavy</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm settings-blur-preset"
                                    data-val="30">Max</button>
                            </div>

                            <button type="submit" class="btn btn-brand fw-bold w-100" id="settingsSaveBlurBtn">
                                <i class="bi bi-save me-1"></i>Save Blur Setting
                            </button>
                        </form>
                    </div>
                </div><!-- /row -->

            </div><!-- /card -->

            <!-- ===== SESSION INACTIVITY & AUTO-LOCK POLICY CARD (ISO/IEC 25010) ===== -->
            <div class="card border-0 shadow-sm p-4 bg-white mt-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h5 class="fw-bold mb-1 text-dark">
                            <i class="bi bi-shield-lock-fill text-primary me-2"></i>Inactivity &amp; Auto-Lock Policy
                        </h5>
                        <p class="text-muted small mb-0">Enforce enterprise workstation privacy and session protection
                            against unattended devices.</p>
                    </div>
                </div>

                <form id="idleSettingsForm" method="POST" action="process/process.php" class="mt-2">
                    <input type="hidden" name="action" value="update_idle_settings">
                    <input type="hidden" name="return_tab" value="general">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>

                    <div class="row g-4">
                        <!-- Left Column: Master Toggle & Overview -->
                        <div class="col-12 col-lg-5">
                            <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input fs-5" type="checkbox" role="switch"
                                            id="idleLockEnabledSwitch" name="idle_lock_enabled" value="1"
                                            <?= $cur_idle_enabled ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark pt-1 ms-2"
                                            for="idleLockEnabledSwitch">
                                            Enable Inactivity Guard
                                        </label>
                                    </div>
                                    <p class="text-secondary small mb-3">
                                        When enabled, CIMS monitors user input across all open tabs. If no interaction
                                        is detected, it triggers the two-tier protection sequence:
                                    </p>
                                    <ul class="text-muted small ps-3 mb-0">
                                        <li class="mb-2"><strong>Tier 1 (Soft Lock):</strong> Blurs screen to prevent
                                            shoulder-surfing, preserving unsaved form drafts until password is verified.
                                        </li>
                                        <li><strong>Tier 2 (Hard Expiry):</strong> Destroys server session after
                                            extended absence with a 60-second warning countdown.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Threshold Dropdowns & Action Button -->
                        <div class="col-12 col-lg-7">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold text-dark small" for="idleLockMinutesSelect">
                                        <i class="bi bi-lock me-1 text-primary"></i>Tier 1: Soft Screen Lock
                                    </label>
                                    <select class="form-select" id="idleLockMinutesSelect" name="idle_lock_minutes"
                                        <?= !$cur_idle_enabled ? 'disabled' : '' ?>>
                                        <option value="0.166" <?= abs($cur_idle_lock - 0.166) < 0.05 ? 'selected' : '' ?>>10 seconds (Testing)</option>
                                        <option value="0.5" <?= abs($cur_idle_lock - 0.5) < 0.05 ? 'selected' : '' ?>>30
                                            seconds (Testing)</option>
                                        <option value="1" <?= $cur_idle_lock == 1 ? 'selected' : '' ?>>1 minute (Testing)
                                        </option>
                                        <option value="5" <?= $cur_idle_lock == 5 ? 'selected' : '' ?>>5 minutes</option>
                                        <option value="10" <?= $cur_idle_lock == 10 ? 'selected' : '' ?>>10 minutes
                                        </option>
                                        <option value="15" <?= $cur_idle_lock == 15 ? 'selected' : '' ?>>15 minutes
                                            (Standard)</option>
                                        <option value="30" <?= $cur_idle_lock == 30 ? 'selected' : '' ?>>30 minutes
                                        </option>
                                        <option value="60" <?= $cur_idle_lock == 60 ? 'selected' : '' ?>>60 minutes
                                        </option>
                                    </select>
                                    <div class="form-text text-muted" style="font-size: 0.75rem;">Prompts for password;
                                        keeps drafts intact.</div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold text-dark small" for="idleLogoutMinutesSelect">
                                        <i class="bi bi-box-arrow-right me-1 text-danger"></i>Tier 2: Hard Auto-Logout
                                    </label>
                                    <select class="form-select" id="idleLogoutMinutesSelect" name="idle_logout_minutes"
                                        <?= !$cur_idle_enabled ? 'disabled' : '' ?>>
                                        <option value="0" <?= $cur_idle_logout == 0 ? 'selected' : '' ?>>Off / Disabled (Soft Lock Only)</option>
                                        <option value="1" <?= $cur_idle_logout == 1 ? 'selected' : '' ?>>1 minute (Testing)
                                        </option>
                                        <option value="2" <?= $cur_idle_logout == 2 ? 'selected' : '' ?>>2 minutes
                                            (Testing)</option>
                                        <option value="15" <?= $cur_idle_logout == 15 ? 'selected' : '' ?>>15 minutes
                                        </option>
                                        <option value="30" <?= $cur_idle_logout == 30 ? 'selected' : '' ?>>30 minutes
                                            (Recommended)</option>
                                        <option value="60" <?= $cur_idle_logout == 60 ? 'selected' : '' ?>>60 minutes
                                        </option>
                                        <option value="120" <?= $cur_idle_logout == 120 ? 'selected' : '' ?>>2 hours
                                        </option>
                                    </select>
                                    <div class="form-text text-muted" style="font-size: 0.75rem;">Fully terminates PHP
                                        session and redirects.</div>
                                </div>
                            </div>

                            <div
                                class="mt-4 pt-2 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <button type="button" class="btn btn-outline-primary fw-bold px-3 py-2 shadow-sm"
                                    onclick="if (typeof window.cimsLockScreenNow === 'function') { window.cimsLockScreenNow(); } else { alert('Screen lock module is ready. Refresh page if needed.'); }">
                                    <i class="bi bi-play-circle-fill me-1"></i>Test Lock Screen Now
                                </button>
                                <button type="submit" class="btn btn-brand fw-bold px-4 py-2 shadow-sm"
                                    id="saveIdleSettingsBtn">
                                    <i class="bi bi-check2-circle me-1"></i>Save Inactivity Policy
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div><!-- /card -->
        </div>

        <!-- ======================================================== -->
        <!-- TAB 6: SITEWARE BACKUP & DISASTER RECOVERY               -->
        <!-- ======================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'backup' ? 'show active' : '' ?>" id="tab-backup" role="tabpanel">
            <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">

                <!-- Section Header Banner & Quick Actions -->
                <div class="row align-items-center mb-4 g-3">
                    <div class="col-12 col-lg-7 text-center text-lg-start">
                        <h4 class="mb-1 fw-bold text-dark">
                            <i class="bi bi-database-fill-gear me-2 text-info"></i>SiteWare Backup &amp; Disaster
                            Recovery
                        </h4>
                        <small class="text-muted">
                            Create on-demand database snapshots, download SQL backup archives, or restore the database
                            to a prior verified state.
                        </small>
                    </div>
                    <div class="col-12 col-lg-5 text-center text-lg-end">
                        <div
                            class="d-grid d-sm-flex flex-wrap gap-2 justify-content-center justify-content-lg-end backup-action-header-btns">
                            <button type="button"
                                class="btn btn-brand fw-bold px-3 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2"
                                id="btnCreateBackup" onclick="handleCreateBackup()">
                                <i class="bi bi-cloud-arrow-down-fill"></i>
                                <span>Create Backup Now</span>
                            </button>
                            <button type="button"
                                class="btn btn-outline-primary fw-bold px-3 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2"
                                data-bs-toggle="modal" data-bs-target="#restoreBackupModal">
                                <i class="bi bi-cloud-arrow-up-fill"></i>
                                <span>Upload &amp; Restore</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Info Cards / Recovery Safeguard Notice -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="p-3 border rounded-3 bg-light d-flex align-items-center gap-3 h-100 shadow-sm">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary d-flex align-items-center justify-content-center"
                                style="width: 48px; height: 48px;">
                                <i class="bi bi-server fs-5"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Stored Backups</div>
                                <div class="fs-5 fw-bold text-dark" id="statBackupCount"><?= count($backupFiles) ?>
                                    Files</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-3 border rounded-3 bg-light d-flex align-items-center gap-3 h-100 shadow-sm">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success d-flex align-items-center justify-content-center"
                                style="width: 48px; height: 48px;">
                                <i class="bi bi-clock-history fs-5"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Latest Snapshot</div>
                                <div class="fs-6 fw-bold text-dark text-truncate" style="max-width: 200px;"
                                    id="statLatestBackup">
                                    <?= !empty($backupFiles) ? htmlspecialchars($backupFiles[0]['created_at_relative']) : 'None created yet' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-3 border rounded-3 bg-light d-flex align-items-center gap-3 h-100 shadow-sm">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning d-flex align-items-center justify-content-center"
                                style="width: 48px; height: 48px;">
                                <i class="bi bi-shield-lock-fill fs-5 text-dark"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold text-uppercase">Safety Failsafe</div>
                                <div class="fs-6 fw-bold text-success">Pre-Restore Auto Snapshot</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Callout Banner on Safe Recovery -->
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-start gap-3 mb-4 rounded-3"
                    role="alert">
                    <i class="bi bi-info-circle-fill fs-4 flex-shrink-0 text-info"></i>
                    <div class="small">
                        <strong class="d-block mb-1 text-dark">Enterprise Risk Mitigation & Process
                            Control:</strong>
                        <span class="text-secondary">
                            Before restoring any database file, SiteWare automatically compiles a safety pre-restore
                            backup (prefixed with <code>siteware_auto_prerestore_</code>).
                            If an unintended restore occurs, you can instantly revert by selecting the automated safety
                            snapshot from the list below.
                        </span>
                    </div>
                </div>

                <!-- Cyber-Defense & Automation Status Panel -->
                <div class="card border-0 bg-light p-3 p-md-4 mb-4 rounded-3 shadow-sm">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger p-2"><i class="bi bi-shield-shaded"></i></span>
                            <h6 class="fw-bold mb-0 text-dark">Automated Cyber-Defense &amp; Scheduling Engine</h6>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success fw-bold px-3 py-1">
                            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Active Protection
                        </span>
                    </div>

                    <div class="row g-3">
                        <!-- Attack Detection Status -->
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="small fw-bold text-muted text-uppercase">Attack-Detection
                                        Trigger</span>
                                    <span class="badge bg-danger text-white">Armed</span>
                                </div>
                                <div class="small text-dark mb-1 fw-semibold">
                                    <i class="bi bi-cpu text-danger me-1"></i> Brute-Force Freeze
                                </div>
                                <div class="text-muted" style="font-size: 0.78rem;">
                                    Automatically freezes a <code>siteware_security_snapshot_</code> when 5+ failed
                                    login attempts trigger an account lockout. Protected by 30-min anti-spam cooldown.
                                </div>
                            </div>
                        </div>

                        <!-- Daily Backup Status -->
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="small fw-bold text-muted text-uppercase">Scheduled Daily Backups</span>
                                    <span class="badge bg-info text-dark">Zero-Config</span>
                                </div>
                                <div class="small text-dark mb-1 fw-semibold">
                                    <i class="bi bi-calendar-check text-info me-1"></i> Daily Heartbeat
                                </div>
                                <div class="text-muted" style="font-size: 0.78rem;">
                                    Runs daily on first admin activity or CLI cron. Generates
                                    <code>siteware_daily_</code> snapshots and auto-purges snapshots older than 14 days.
                                </div>
                            </div>
                        </div>

                        <!-- Windows Task Scheduler CLI Info -->
                        <div class="col-12 col-md-12 col-lg-4">
                            <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="small fw-bold text-muted text-uppercase">Windows Task Scheduler</span>
                                    <span class="badge bg-secondary text-white">CLI Ready</span>
                                </div>
                                <div class="small text-dark mb-1 fw-semibold">
                                    <i class="bi bi-terminal text-primary me-1"></i> Headless Runner
                                </div>
                                <div class="input-group input-group-sm mt-1">
                                    <input type="text" class="form-control font-monospace" style="font-size: 0.72rem;"
                                        value="C:\xampp\php\php.exe <?= htmlspecialchars(str_replace('/', '\\', dirname(__FILE__))) ?>\scripts\auto_backup.php"
                                        id="cliTaskCmd" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyCliCommand()"
                                        title="Copy command">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backups History Table -->
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-archive-fill me-2 text-secondary"></i>Backup Archives Repository
                        </h5>
                        <small class="text-muted">Directly download, restore, or delete database files securely stored
                            on the server.</small>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary fw-bold shadow-sm"
                            onclick="refreshBackupsList(true)">
                            <i class="bi bi-arrow-clockwise me-1" id="refreshBackupsSpinner"></i> Refresh List
                        </button>
                    </div>
                </div>

                <!-- Desktop Table View (>= 768px) -->
                <div class="d-none d-md-block table-responsive border rounded-3 shadow-sm mb-3">
                    <table class="table table-hover align-middle mb-0 text-nowrap" id="backupsTable">
                        <thead class="table-dark">
                            <tr>
                                <th class="py-3">Backup File Name</th>
                                <th class="py-3 text-center">Type</th>
                                <th class="py-3 text-center">File Size</th>
                                <th class="py-3">Date Created</th>
                                <th class="py-3 text-center" style="width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="backupsTableBody">
                            <?php if (empty($backupFiles)): ?>
                                <tr id="noBackupsRow">
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i
                                            class="bi bi-database-exclamation fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                        <h6 class="fw-bold mb-1">No database backups found</h6>
                                        <p class="small mb-3">Click &ldquo;Create Backup Now&rdquo; to generate your first
                                            SiteWare database backup.</p>
                                        <button type="button" class="btn btn-sm btn-brand fw-bold px-3 shadow-sm"
                                            onclick="handleCreateBackup()">
                                            <i class="bi bi-cloud-arrow-down-fill me-1"></i> Create Backup Now
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($backupFiles as $backup): ?>
                                    <tr id="row_<?= htmlspecialchars(md5($backup['filename'])) ?>">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-file-earmark-code-fill text-primary fs-5"></i>
                                                <span
                                                    class="fw-bold text-dark font-monospace"><?= htmlspecialchars($backup['filename']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill <?= $backup['badge_class'] ?> px-2 py-1 fw-bold"
                                                style="font-size: 0.72rem;">
                                                <?= htmlspecialchars($backup['type_label']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center font-monospace text-muted fw-semibold">
                                            <?= htmlspecialchars($backup['filesize_formatted']) ?>
                                        </td>
                                        <td>
                                            <div class="text-dark fw-semibold small">
                                                <?= htmlspecialchars($backup['created_at']) ?>
                                            </div>
                                            <small class="text-muted"
                                                style="font-size: 0.75rem;"><?= htmlspecialchars($backup['created_at_relative']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <!-- Download Button -->
                                                <a href="process/module_backup.php?action=download_backup&file=<?= urlencode($backup['filename']) ?>"
                                                    class="btn btn-sm btn-outline-success" title="Download SQL File"
                                                    aria-label="Download <?= htmlspecialchars($backup['filename']) ?>">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <!-- Restore Button -->
                                                <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold"
                                                    title="Restore Database from this file"
                                                    aria-label="Restore database from <?= htmlspecialchars($backup['filename']) ?>"
                                                    onclick="confirmServerRestore('<?= htmlspecialchars(addslashes($backup['filename'])) ?>')">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Restore
                                                </button>
                                                <!-- Delete Button -->
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    title="Delete Backup File"
                                                    aria-label="Delete <?= htmlspecialchars($backup['filename']) ?>"
                                                    onclick="confirmDeleteBackup('<?= htmlspecialchars(addslashes($backup['filename'])) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Phone Cards View (< 768px) -->
                <div id="backupsMobileCards" class="d-block d-md-none mb-3">
                    <?php if (empty($backupFiles)): ?>
                        <div class="text-center py-5 text-muted border rounded-3 bg-light p-3">
                            <i class="bi bi-database-exclamation fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-bold mb-1">No database backups found</h6>
                            <p class="small mb-3">Click &ldquo;Create Backup Now&rdquo; to generate your first SiteWare
                                database backup.</p>
                            <button type="button" class="btn btn-sm btn-brand fw-bold px-3 shadow-sm w-100"
                                onclick="handleCreateBackup()">
                                <i class="bi bi-cloud-arrow-down-fill me-1"></i> Create Backup Now
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($backupFiles as $backup): ?>
                            <div class="backup-mobile-card">
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden" style="max-width: 70%;">
                                        <i class="bi bi-file-earmark-code-fill text-primary fs-4 flex-shrink-0"></i>
                                        <span
                                            class="fw-bold text-dark font-monospace text-truncate small"><?= htmlspecialchars($backup['filename']) ?></span>
                                    </div>
                                    <span
                                        class="badge rounded-pill <?= $backup['badge_class'] ?> px-2 py-1 fw-bold flex-shrink-0"
                                        style="font-size: 0.68rem;">
                                        <?= htmlspecialchars($backup['type_label']) ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between text-muted small mb-3 border-bottom pb-2"
                                    style="font-size: 0.78rem;">
                                    <div><i class="bi bi-hdd me-1"></i> <?= htmlspecialchars($backup['filesize_formatted']) ?>
                                    </div>
                                    <div><i class="bi bi-clock me-1"></i>
                                        <?= htmlspecialchars($backup['created_at_relative']) ?>
                                    </div>
                                </div>
                                <div class="row g-2 backup-mobile-actions">
                                    <div class="col-4">
                                        <a href="process/module_backup.php?action=download_backup&file=<?= urlencode($backup['filename']) ?>"
                                            class="btn btn-sm btn-outline-success w-100 fw-bold">
                                            <i class="bi bi-download me-1"></i> Save
                                        </a>
                                    </div>
                                    <div class="col-4">
                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark w-100 fw-bold"
                                            onclick="confirmServerRestore('<?= htmlspecialchars(addslashes($backup['filename'])) ?>')">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                        </button>
                                    </div>
                                    <div class="col-4">
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100 fw-bold"
                                            onclick="confirmDeleteBackup('<?= htmlspecialchars(addslashes($backup['filename'])) ?>')">
                                            <i class="bi bi-trash me-1"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- ======================================================== -->
<!-- MODALS                                                   -->
<!-- ======================================================== -->

<!-- SiteWare Restore Database Modal (Conforms to CIMS Modal AJAX Skill) -->
<div class="modal fade" id="restoreBackupModal" tabindex="-1" aria-labelledby="restoreBackupModalTitle"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="restoreBackupModalTitle">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Restore SiteWare Database
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="restoreUploadForm" enctype="multipart/form-data">
                <div class="modal-body p-3 p-md-4">
                    <input type="hidden" name="action" value="restore_backup">
                    <input type="hidden" name="source_type" value="upload">

                    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-start gap-2 mb-3"
                        role="alert">
                        <i class="bi bi-shield-exclamation fs-4 flex-shrink-0 text-danger"></i>
                        <div class="small">
                            <strong class="d-block text-danger">High Impact Operation:</strong>
                            Restoring will overwrite current database records with the selected backup file. An
                            automated pre-restore safety snapshot will be taken immediately before restoring.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="restoreBackupFileInput" class="form-label fw-bold text-dark">
                            Select SiteWare SQL Backup File <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control form-control-lg" id="restoreBackupFileInput"
                            name="backup_file" accept=".sql" required>
                        <div class="form-text small text-muted">
                            Only valid <code>.sql</code> files generated by SiteWare or MySQL (max 100MB) are accepted.
                        </div>
                    </div>

                    <div class="p-3 border rounded-3 bg-light mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="restoreSafetyCheckbox" required>
                            <label class="form-check-label small fw-semibold text-dark user-select-none"
                                for="restoreSafetyCheckbox">
                                I confirm that I want to restore this backup. I understand that all existing tables will
                                be synchronized to the state in the backup file.
                            </label>
                        </div>
                    </div>
                </div>
                <div
                    class="modal-footer bg-light d-flex flex-column-reverse flex-sm-row justify-content-sm-between gap-2">
                    <button type="button" class="btn btn-secondary fw-bold px-3 w-100 w-sm-auto"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4 w-100 w-sm-auto"
                        id="btnSubmitRestoreUpload">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Restore Database
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="categoryModalTitle">Add Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="categoryFormAction" value="add_category">
                    <input type="hidden" name="category_id" id="categoryId">
                    <input type="hidden" name="return_tab" value="categories">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg fw-bold" name="category_name"
                            id="categoryName" placeholder="e.g. Electrical Supplies" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Project Modal -->
<div class="modal fade" id="projectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="projectModalTitle">Add Project</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="projectFormAction" value="add_project">
                    <input type="hidden" name="project_id" id="projectId">
                    <input type="hidden" name="return_tab" value="projects">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Project ID <small class="text-muted fw-normal">(Optional -
                                leave blank to auto-generate)</small></label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg fw-bold" name="project_code"
                                id="projectCode" placeholder="e.g. 20JE0010 or PRJ-2026-001">
                            <button type="button" class="btn btn-outline-primary fw-bold px-3"
                                onclick="generateAutoProjectCode()" title="Auto-Generate Project ID">
                                <i class="bi bi-magic me-1"></i>Auto
                            </button>
                        </div>
                        <small class="text-muted" style="font-size:0.75rem;">Leave blank or click "Auto" to generate a
                            Project ID automatically.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Project Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg fw-bold" name="project_name"
                            id="projectName" placeholder="e.g. Phase 1 Building" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Location / Address</label>
                        <input type="text" class="form-control" name="address" id="projectAddress"
                            placeholder="e.g. Brgy. San Jose, Malaybalay City, Bukidnon">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea class="form-control" name="description" id="projectDesc" rows="3"
                            placeholder="Additional details..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Status</label>
                        <select class="form-select" name="status" id="projectStatus" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4">Save Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Project Details & Material History Modal -->
<div class="modal fade" id="projectDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background-color: var(--gb-dark, #1e293b);">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary text-white p-2 d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px;">
                        <i class="bi bi-building fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="projDetailsTitle">Project Details</h5>
                        <small class="text-white-50" id="projDetailsSubtitle">Jobsite Material & Activity Hub</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Loading State -->
                <div id="projDetailsLoading" class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                    </div>
                    <div class="fw-bold text-muted">Loading project materials and transaction records...</div>
                </div>

                <!-- Content Container (Hidden while loading) -->
                <div id="projDetailsContent" style="display: none;">
                    <!-- Project Overview Card -->
                    <div class="card border-0 shadow-sm mb-4 bg-white rounded-3">
                        <div class="card-body p-3 p-md-4">
                            <div class="row align-items-center g-3">
                                <div class="col-12 col-md-8">
                                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                        <span class="badge bg-dark font-monospace px-3 py-2 fs-6"
                                            id="projDetailsCode">PRJ-000</span>
                                        <span class="badge rounded-pill px-3 py-2 fs-6"
                                            id="projDetailsStatus">Active</span>
                                    </div>
                                    <h4 class="fw-bold text-dark mb-1" id="projDetailsName">Project Name</h4>
                                    <p class="text-muted small mb-1" id="projDetailsAddress">
                                        <i class="bi bi-geo-alt text-danger me-1"></i>Location
                                    </p>
                                    <p class="text-muted small mb-0 fst-italic" id="projDetailsDesc">Description</p>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="row g-2 text-center">
                                        <div class="col-4">
                                            <div class="p-2 border rounded bg-light">
                                                <small class="text-muted d-block fw-bold text-uppercase"
                                                    style="font-size: 0.7rem;">Requisitions</small>
                                                <span class="fw-bold fs-5 text-primary" id="projStatRs">0</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-2 border rounded bg-light">
                                                <small class="text-muted d-block fw-bold text-uppercase"
                                                    style="font-size: 0.7rem;">Withdrawals</small>
                                                <span class="fw-bold fs-5 text-success" id="projStatWs">0</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-2 border rounded bg-light">
                                                <small class="text-muted d-block fw-bold text-uppercase"
                                                    style="font-size: 0.7rem;">Materials</small>
                                                <span class="fw-bold fs-5 text-warning" id="projStatMaterials">0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Tabs -->
                    <ul class="nav nav-pills mb-3 gap-2" id="projDetailsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold px-3 py-2 shadow-sm" id="proj-tab-rs"
                                data-bs-toggle="pill" data-bs-target="#proj-pane-rs" type="button" role="tab">
                                <i class="bi bi-file-earmark-text me-1"></i>Material Requisitions (<span
                                    id="projTabRsCount">0</span>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold px-3 py-2 shadow-sm" id="proj-tab-ws" data-bs-toggle="pill"
                                data-bs-target="#proj-pane-ws" type="button" role="tab">
                                <i class="bi bi-box-arrow-right me-1"></i>Material Withdrawals (<span
                                    id="projTabWsCount">0</span>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold px-3 py-2 shadow-sm" id="proj-tab-summary"
                                data-bs-toggle="pill" data-bs-target="#proj-pane-summary" type="button" role="tab">
                                <i class="bi bi-bar-chart-fill me-1"></i>Consumption Summary
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Contents -->
                    <div class="tab-content" id="projDetailsTabContent">
                        <!-- TAB 1: REQUISITIONS -->
                        <div class="tab-pane fade show active" id="proj-pane-rs" role="tabpanel">
                            <div class="card border-0 shadow-sm">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>RS No.</th>
                                                <th>Project Engineer / Requestor</th>
                                                <th>Date Requested</th>
                                                <th>Urgency</th>
                                                <th>Status</th>
                                                <th>Requested Items</th>
                                            </tr>
                                        </thead>
                                        <tbody id="projRsTableBody">
                                            <!-- Dynamically injected -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: WITHDRAWALS -->
                        <div class="tab-pane fade" id="proj-pane-ws" role="tabpanel">
                            <div class="card border-0 shadow-sm">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Withdrawal No.</th>
                                                <th>Received By</th>
                                                <th>Date Dispatched</th>
                                                <th>Released By</th>
                                                <th>Materials Dispatched</th>
                                                <th>Remarks / Proof</th>
                                            </tr>
                                        </thead>
                                        <tbody id="projWsTableBody">
                                            <!-- Dynamically injected -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: CONSUMPTION SUMMARY -->
                        <div class="tab-pane fade" id="proj-pane-summary" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div>
                                    <h6 class="fw-bold text-dark mb-0"><i
                                            class="bi bi-bar-chart-fill me-1 text-primary"></i>Total Materials Delivered
                                        to Site</h6>
                                    <small class="text-muted">Aggregated summary of all verified jobsite
                                        dispatches</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-success fw-bold shadow-sm"
                                        onclick="exportProjectConsumptionCSV()">
                                        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold shadow-sm"
                                        onclick="printProjectConsumptionReport()">
                                        <i class="bi bi-printer me-1"></i>Print Report
                                    </button>
                                </div>
                            </div>
                            <div class="card border-0 shadow-sm">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Item Code</th>
                                                <th>Material Name</th>
                                                <th class="text-center">Total Delivered to Site</th>
                                                <th class="text-center">Dispatches</th>
                                            </tr>
                                        </thead>
                                        <tbody id="projSummaryTableBody">
                                            <!-- Dynamically injected -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Sub-Modal: Project Requisition Document Preview -->
<div class="modal fade" id="viewProjectRsModal" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background-color: var(--gb-dark, #1e293b);">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-2 text-warning"></i>Requisition
                    Document Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div
                    class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3 bg-white p-3 rounded shadow-sm">
                    <div>
                        <h4 class="fw-bold text-primary mb-1 d-flex align-items-center gap-2 flex-wrap">
                            <span id="projRsDocNo">RS-0000</span>
                            <span id="projRsDocStatus" class="badge shadow-sm" style="font-size: 0.75rem;">Pending
                                Approval</span>
                        </h4>
                        <div class="text-muted fw-bold text-uppercase small" id="projRsDocProject">Project Name</div>
                        <div class="mt-2 text-muted small">
                            Requested By: <strong id="projRsDocRequestor" class="text-dark">User</strong><br>
                            Date Requested: <strong id="projRsDocDate" class="text-dark">Date</strong><br>
                            Urgency Level: <span id="projRsDocUrgency" class="badge bg-secondary">Normal</span>
                        </div>
                    </div>

                    <!-- QR Code Container -->
                    <div id="projRsDocQrContainer" class="text-center d-none">
                        <img id="projRsDocQrCode" src="" alt="RS QR Code" class="border p-1 bg-white shadow-sm"
                            style="width: 90px; height: 90px; border-radius: 6px;">
                        <small class="d-block text-muted mt-1 fw-bold" style="font-size: 0.65rem;">SCAN AT
                            WAREHOUSE</small>
                    </div>
                </div>

                <h6 class="fw-bold text-uppercase small text-muted mb-2">Requested Items:</h6>
                <div class="table-responsive mb-4 rounded border shadow-sm">
                    <table class="table table-sm table-hover mb-0 bg-white">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">Item Code</th>
                                <th>Item Name</th>
                                <th class="text-center" style="width: 90px;">Quantity</th>
                                <th class="text-center" style="width: 130px;">Item Status</th>
                            </tr>
                        </thead>
                        <tbody id="projRsDocItemsBody"></tbody>
                    </table>
                </div>

                <div>
                    <h6 class="fw-bold mb-2 text-dark small text-uppercase">Remarks / Purpose:</h6>
                    <p class="text-muted small border p-3 bg-white rounded shadow-sm mb-0" id="projRsDocRemarks"
                        style="min-height: 50px;">No remarks provided.</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between bg-white border-top-0">
                <a href="#" id="projRsDocFullPageLink" target="_blank" class="btn btn-outline-primary fw-bold">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Open in Requisitions Tab
                </a>
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Back to
                    Project</button>
            </div>
        </div>
    </div>
</div>

<!-- Sub-Modal: Project Withdrawal Document Preview -->
<div class="modal fade" id="viewProjectWdModal" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background-color: var(--gb-dark, #1e293b);">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-right me-2 text-success"></i>Material
                    Withdrawal Slip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div
                    class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3 bg-white p-3 rounded shadow-sm">
                    <div>
                        <h4 class="fw-bold text-success mb-1" id="projWdDocNo">WD-0000</h4>
                        <div class="text-muted fw-bold text-uppercase small" id="projWdDocProject">Project Name</div>
                        <div class="mt-2 text-muted small">
                            Received By: <strong id="projWdDocReceiver" class="text-dark">User</strong><br>
                            Released By: <strong id="projWdDocReleaser" class="text-dark">Warehouse Officer</strong><br>
                            Date Dispatched: <strong id="projWdDocDate" class="text-dark">Date</strong>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-uppercase small text-muted mb-2">Dispatched Materials:</h6>
                <div class="table-responsive mb-4 rounded border shadow-sm">
                    <table class="table table-sm table-hover mb-0 bg-white">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">Item Code</th>
                                <th>Item Name</th>
                                <th class="text-center" style="width: 110px;">Qty Dispatched</th>
                            </tr>
                        </thead>
                        <tbody id="projWdDocItemsBody"></tbody>
                    </table>
                </div>

                <!-- Proof & Signature Section -->
                <div class="card border-0 shadow-sm mb-3 bg-white" id="projWdDocProofCard">
                    <div class="card-header bg-light fw-bold text-muted small text-uppercase">Verification & Release
                        Proof</div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-6" id="projWdDocSigWrapper">
                                <small class="text-muted fw-bold d-block mb-1">Receiver Signature:</small>
                                <div id="projWdDocSigContent" class="p-2 border rounded bg-light text-center">
                                    <img id="projWdDocSigImg" src="" class="img-fluid rounded"
                                        style="max-height: 90px; background-color: #fff;">
                                </div>
                            </div>
                            <div class="col-md-6" id="projWdDocPhotoWrapper">
                                <small class="text-muted fw-bold d-block mb-1">Delivery / Handover Photo:</small>
                                <div id="projWdDocPhotoContent" class="p-2 border rounded bg-light text-center">
                                    <a id="projWdDocPhotoLink" href="#" target="_blank">
                                        <img id="projWdDocPhotoImg" src="" class="img-fluid border rounded shadow-sm"
                                            style="max-height: 90px; object-fit: cover;">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h6 class="fw-bold mb-2 text-dark small text-uppercase">Release Remarks:</h6>
                    <p class="text-muted small border p-3 bg-white rounded shadow-sm mb-0" id="projWdDocRemarks">No
                        remarks.</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between bg-white border-top-0">
                <a href="#" id="projWdDocFullPageLink" target="_blank" class="btn btn-outline-success fw-bold">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Open in Withdrawals Tab
                </a>
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Back to
                    Project</button>
            </div>
        </div>
    </div>
</div>

<!-- Included External Modals -->
<?php include 'components/user_modal.php'; ?>
<?php include 'components/unit_modal.php'; ?>

<!-- Inject Page Specific JS -->
<script src="assets/js/projects.js"></script>

<script>
    // Tab URL Synchronizer
    function switchSettingsTab(tabName) {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url.toString());
    }

    // Category Modal Handlers
    window.openAddCategoryModal = function () {
        document.getElementById('categoryModalTitle').innerText = 'Add New Category';
        document.getElementById('categoryFormAction').value = 'add_category';
        document.getElementById('categoryId').value = '';
        document.getElementById('categoryName').value = '';
    };

    window.openEditCategoryModal = function (id, name) {
        document.getElementById('categoryModalTitle').innerText = 'Edit Category';
        document.getElementById('categoryFormAction').value = 'edit_category';
        document.getElementById('categoryId').value = id;
        document.getElementById('categoryName').value = name;
        new bootstrap.Modal(document.getElementById('categoryModal')).show();
    };

    // General Settings: Real-time Live Preview & Blur Slider
    (function () {
        const slider = document.getElementById('settingsBlurSlider');
        const valueBadge = document.getElementById('settingsBlurValueBadge');
        const blurBadge = document.getElementById('settingsBlurBadge');
        const bgLayer = document.getElementById('settingsPreviewBgLayer');
        const bgFileInput = document.getElementById('settingsBgFileInput');

        if (slider && valueBadge && blurBadge && bgLayer) {
            function applySettingsBlur(val) {
                val = Math.max(0, Math.min(30, parseInt(val) || 0));
                const scale = (1 + val * 0.006).toFixed(3);
                bgLayer.style.filter = `blur(${val}px)`;
                bgLayer.style.transform = `scale(${scale})`;
                valueBadge.textContent = val + 'px';
                blurBadge.textContent = val + 'px';
            }

            slider.addEventListener('input', () => applySettingsBlur(slider.value));

            // Preset buttons
            document.querySelectorAll('.settings-blur-preset').forEach(btn => {
                btn.addEventListener('click', () => {
                    slider.value = btn.dataset.val;
                    applySettingsBlur(btn.dataset.val);
                });
            });

            // Image file picker → live preview
            if (bgFileInput) {
                bgFileInput.addEventListener('change', function () {
                    const file = this.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = e => {
                        bgLayer.style.backgroundImage = `url('${e.target.result}')`;
                    };
                    reader.readAsDataURL(file);
                });
            }
        }
    })();
</script>

<!-- SweetAlert2 CDN for Enterprise Feedback Dialogs -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- SiteWare Backup & Disaster Recovery Client Handlers (Conforms to CIMS Modal AJAX Skill) -->
<script>
    // Utility: XSS-safe string escaping
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // Copy Windows Task Scheduler CLI command
    function copyCliCommand() {
        const cmd = document.getElementById('cliTaskCmd');
        if (cmd) {
            navigator.clipboard.writeText(cmd.value).then(() => {
                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'CLI Task command copied!'
                    });
                } else {
                    alert('CLI Task command copied to clipboard!');
                }
            });
        }
    }

    // Dynamic Client-Side Re-render of Backups Table & Status Badges
    function renderBackupsTable(backups) {
        const tbody = document.getElementById('backupsTableBody');
        const countBadge = document.getElementById('backupNavBadge');
        const statCount = document.getElementById('statBackupCount');
        const statLatest = document.getElementById('statLatestBackup');

        if (countBadge) countBadge.textContent = backups.length;
        if (statCount) statCount.textContent = backups.length + ' Files';
        if (statLatest) {
            statLatest.textContent = backups.length > 0 ? (backups[0].created_at_relative || backups[0].created_at) : 'None created yet';
        }

        if (!tbody) return;

        if (!backups || backups.length === 0) {
            tbody.innerHTML = `
                <tr id="noBackupsRow">
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-database-exclamation fs-1 d-block mb-2 text-secondary opacity-50"></i>
                        <h6 class="fw-bold mb-1">No database backups found</h6>
                        <p class="small mb-3">Click &ldquo;Create Backup Now&rdquo; to generate your first SiteWare database backup.</p>
                        <button type="button" class="btn btn-sm btn-brand fw-bold px-3 shadow-sm" onclick="handleCreateBackup()">
                            <i class="bi bi-cloud-arrow-down-fill me-1"></i> Create Backup Now
                        </button>
                    </td>
                </tr>`;
            return;
        }

        let html = '';
        backups.forEach(b => {
            const escapedName = escapeHtml(b.filename);
            const rawNameForJs = b.filename.replace(/'/g, "\\'");
            html += `
                <tr id="row_${b.timestamp}">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-code-fill text-primary fs-5"></i>
                            <span class="fw-bold text-dark font-monospace">${escapedName}</span>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge rounded-pill ${b.badge_class} px-2 py-1 fw-bold" style="font-size: 0.72rem;">
                            ${escapeHtml(b.type_label)}
                        </span>
                    </td>
                    <td class="text-center font-monospace text-muted fw-semibold">
                        ${escapeHtml(b.filesize_formatted)}
                    </td>
                    <td>
                        <div class="text-dark fw-semibold small">${escapeHtml(b.created_at)}</div>
                        <small class="text-muted" style="font-size: 0.75rem;">${escapeHtml(b.created_at_relative)}</small>
                    </td>
                    <td class="text-center">
                        <div class="d-inline-flex gap-1">
                            <a href="process/module_backup.php?action=download_backup&file=${encodeURIComponent(b.filename)}" 
                               class="btn btn-sm btn-outline-success" 
                               title="Download SQL File"
                               aria-label="Download ${escapedName}">
                                <i class="bi bi-download"></i>
                            </a>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-warning text-dark fw-bold" 
                                    title="Restore Database from this file"
                                    aria-label="Restore database from ${escapedName}"
                                    onclick="confirmServerRestore('${rawNameForJs}')">
                                <i class="bi bi-arrow-counterclockwise"></i> Restore
                            </button>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger" 
                                    title="Delete Backup File"
                                    aria-label="Delete ${escapedName}"
                                    onclick="confirmDeleteBackup('${rawNameForJs}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });

        tbody.innerHTML = html;

        // Mobile Cards rendering (< 768px viewports)
        const mobileContainer = document.getElementById('backupsMobileCards');
        if (mobileContainer) {
            if (!backups || backups.length === 0) {
                mobileContainer.innerHTML = `
                    <div class="text-center py-5 text-muted border rounded-3 bg-light p-3">
                        <i class="bi bi-database-exclamation fs-1 d-block mb-2 text-secondary opacity-50"></i>
                        <h6 class="fw-bold mb-1">No database backups found</h6>
                        <p class="small mb-3">Click &ldquo;Create Backup Now&rdquo; to generate your first SiteWare database backup.</p>
                        <button type="button" class="btn btn-sm btn-brand fw-bold px-3 shadow-sm w-100" onclick="handleCreateBackup()">
                            <i class="bi bi-cloud-arrow-down-fill me-1"></i> Create Backup Now
                        </button>
                    </div>`;
            } else {
                let mHtml = '';
                backups.forEach(b => {
                    const escapedName = escapeHtml(b.filename);
                    const rawNameForJs = b.filename.replace(/'/g, "\\'");
                    mHtml += `
                        <div class="backup-mobile-card">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <div class="d-flex align-items-center gap-2 overflow-hidden" style="max-width: 70%;">
                                    <i class="bi bi-file-earmark-code-fill text-primary fs-4 flex-shrink-0"></i>
                                    <span class="fw-bold text-dark font-monospace text-truncate small">${escapedName}</span>
                                </div>
                                <span class="badge rounded-pill ${b.badge_class} px-2 py-1 fw-bold flex-shrink-0" style="font-size: 0.68rem;">
                                    ${escapeHtml(b.type_label)}
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between text-muted small mb-3 border-bottom pb-2" style="font-size: 0.78rem;">
                                <div><i class="bi bi-hdd me-1"></i> ${escapeHtml(b.filesize_formatted)}</div>
                                <div><i class="bi bi-clock me-1"></i> ${escapeHtml(b.created_at_relative)}</div>
                            </div>
                            <div class="row g-2 backup-mobile-actions">
                                <div class="col-4">
                                    <a href="process/module_backup.php?action=download_backup&file=${encodeURIComponent(b.filename)}" class="btn btn-sm btn-outline-success w-100 fw-bold">
                                        <i class="bi bi-download me-1"></i> Save
                                    </a>
                                </div>
                                <div class="col-4">
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark w-100 fw-bold" onclick="confirmServerRestore('${rawNameForJs}')">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                    </button>
                                </div>
                                <div class="col-4">
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100 fw-bold" onclick="confirmDeleteBackup('${rawNameForJs}')">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>`;
                });
                mobileContainer.innerHTML = mHtml;
            }
        }
    }

    // Refresh backups list asynchronously
    async function refreshBackupsList(showFeedback = false) {
        const spinner = document.getElementById('refreshBackupsSpinner');
        if (spinner) spinner.classList.add('spin-animation');

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch('process/process.php?action=fetch_backups', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                }
            });
            const data = await res.json();
            if (data.success && Array.isArray(data.data)) {
                renderBackupsTable(data.data);
                if (showFeedback && typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Backup list refreshed.'
                    });
                }
            }
        } catch (err) {
            console.error('Error refreshing backups:', err);
        } finally {
            if (spinner) spinner.classList.remove('spin-animation');
        }
    }

    // 1. Action: Trigger Immediate Backup Generation
    async function handleCreateBackup() {
        const btn = document.getElementById('btnCreateBackup');
        const originalHtml = btn ? btn.innerHTML : 'Create Backup Now';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generating Backup...';
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'create_backup');
            formData.append('csrf_token', csrfToken);

            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();
            const isSuccess = result.success === true || result.status === 'success';

            if (isSuccess) {
                if (result.data && Array.isArray(result.data.backups)) {
                    renderBackupsTable(result.data.backups);
                } else {
                    refreshBackupsList();
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Backup Created!',
                        html: `
                            <p class="mb-2">SiteWare database snapshot generated successfully.</p>
                            <div class="p-2 border rounded bg-light font-monospace small mb-2 text-dark">
                                ${escapeHtml(result.data?.backup?.filename || 'Backup file')}
                            </div>
                            <small class="text-muted">Total records exported: <strong>${result.data?.backup?.total_rows || 'N/A'}</strong> (${result.data?.backup?.filesize_formatted || ''})</small>
                        `,
                        confirmButtonColor: '#1e293b'
                    });
                }
            } else {
                throw new Error(result.message || 'Failed to generate backup.');
            }
        } catch (error) {
            console.error('Create Backup Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Backup Failed',
                    text: error.message || 'Unable to generate database backup.'
                });
            } else {
                alert(error.message || 'Unable to generate database backup.');
            }
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }

    // 2. Action: Restore Database from Server File
    function confirmServerRestore(filename) {
        if (typeof Swal === 'undefined') {
            if (!confirm(`Warning: Restoring will overwrite existing records. Proceed with restoring ${filename}?`)) return;
            executeServerRestore(filename);
            return;
        }

        Swal.fire({
            title: 'Restore Database?',
            html: `
                <div class="text-start">
                    <p class="mb-2">You are about to restore the database from:</p>
                    <div class="p-2 bg-light border rounded font-monospace small mb-3 text-dark fw-bold">
                        ${escapeHtml(filename)}
                    </div>
                    <div class="alert alert-warning py-2 px-3 small border-0 mb-0">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        <strong>Safety Guarantee:</strong> An automated safety snapshot will be created immediately before changes are applied.
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i> Yes, Restore Database',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                executeServerRestore(filename);
            }
        });
    }

    async function executeServerRestore(filename) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Restoring Database...',
                html: 'Applying database snapshot. Please do not close this window.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'restore_backup');
            formData.append('source_type', 'server');
            formData.append('filename', filename);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();
            const isSuccess = result.success === true || result.status === 'success';

            if (isSuccess) {
                if (result.data && Array.isArray(result.data.backups)) {
                    renderBackupsTable(result.data.backups);
                } else {
                    refreshBackupsList();
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Database Restored!',
                        html: `
                            <p class="mb-2 text-dark">SiteWare database was restored successfully.</p>
                            <div class="small text-muted mb-2">
                                Queries executed: <strong>${result.data?.restore_summary?.queries_executed || 'N/A'}</strong>
                                (${result.data?.restore_summary?.duration_seconds || '0'}s)
                            </div>
                            <div class="p-2 border rounded bg-light text-start small">
                                <span class="fw-bold text-success d-block"><i class="bi bi-check-circle-fill me-1"></i> Pre-restore safety snapshot created:</span>
                                <span class="font-monospace text-dark">${escapeHtml(result.data?.restore_summary?.safety_snapshot || 'Created')}</span>
                            </div>
                        `,
                        confirmButtonColor: '#1e293b'
                    });
                }
            } else {
                throw new Error(result.message || 'Failed to restore database.');
            }
        } catch (error) {
            console.error('Restore Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Restoration Failed',
                    text: error.message || 'An error occurred during database restoration.'
                });
            } else {
                alert(error.message || 'An error occurred during database restoration.');
            }
        }
    }

    // 3. Action: Delete a Backup File
    function confirmDeleteBackup(filename) {
        if (typeof Swal === 'undefined') {
            if (!confirm(`Delete backup file ${filename}?`)) return;
            executeDeleteBackup(filename);
            return;
        }

        Swal.fire({
            title: 'Delete Backup File?',
            html: `Are you sure you want to permanently delete <strong class="font-monospace text-danger">${escapeHtml(filename)}</strong>?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-trash me-1"></i> Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                executeDeleteBackup(filename);
            }
        });
    }

    async function executeDeleteBackup(filename) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'delete_backup');
            formData.append('filename', filename);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();
            const isSuccess = result.success === true || result.status === 'success';

            if (isSuccess) {
                if (result.data && Array.isArray(result.data.backups)) {
                    renderBackupsTable(result.data.backups);
                } else {
                    refreshBackupsList();
                }

                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Backup file deleted.'
                    });
                }
            } else {
                throw new Error(result.message || 'Failed to delete backup file.');
            }
        } catch (error) {
            console.error('Delete Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Delete Failed',
                    text: error.message || 'Unable to delete backup file.'
                });
            } else {
                alert(error.message || 'Unable to delete backup file.');
            }
        }
    }

    // 4. Modal Lifecycle & Upload Form Submission (Complies with CIMS Modal AJAX Skill)
    document.addEventListener('DOMContentLoaded', () => {
        const restoreModalEl = document.getElementById('restoreBackupModal');
        const restoreForm = document.getElementById('restoreUploadForm');

        if (restoreModalEl) {
            // Auto-focus primary input on open (Skill Principle 3)
            restoreModalEl.addEventListener('shown.bs.modal', () => {
                const fileInput = document.getElementById('restoreBackupFileInput');
                if (fileInput) fileInput.focus();
            });

            // Clean up and reset on modal close (Skill Principle 3)
            restoreModalEl.addEventListener('hidden.bs.modal', () => {
                if (restoreForm) {
                    restoreForm.reset();
                    restoreForm.classList.remove('was-validated');
                }
            });
        }

        if (restoreForm) {
            restoreForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const submitBtn = document.getElementById('btnSubmitRestoreUpload');
                const originalBtnText = submitBtn ? submitBtn.innerHTML : 'Restore Database';

                // Client-side validation
                if (!restoreForm.checkValidity()) {
                    restoreForm.reportValidity();
                    return;
                }

                const safetyCheck = document.getElementById('restoreSafetyCheckbox');
                if (safetyCheck && !safetyCheck.checked) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Confirmation Required',
                            text: 'Please check the confirmation box before proceeding with database restore.'
                        });
                    } else {
                        alert('Please check the confirmation box before proceeding with database restore.');
                    }
                    return;
                }

                // Prevent double submit & show loading state (Skill Principle 2)
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Restoring Database...';
                }

                try {
                    const formData = new FormData(restoreForm);
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('process/process.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': csrfToken
                        }
                    });

                    const result = await response.json();
                    const isSuccess = result.success === true || result.status === 'success';

                    if (isSuccess) {
                        // Hide modal
                        const modalInstance = bootstrap.Modal.getInstance(restoreModalEl);
                        if (modalInstance) modalInstance.hide();

                        // Refresh table with returned backups
                        if (result.data && Array.isArray(result.data.backups)) {
                            renderBackupsTable(result.data.backups);
                        } else {
                            refreshBackupsList();
                        }

                        // Success notification via SweetAlert2 (Skill Principle 4)
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Database Restored!',
                                html: `
                                    <p class="mb-2 text-dark">The uploaded SQL backup has been successfully imported.</p>
                                    <div class="small text-muted mb-2">
                                        Queries executed: <strong>${result.data?.restore_summary?.queries_executed || 'N/A'}</strong>
                                    </div>
                                    <div class="p-2 border rounded bg-light text-start small">
                                        <span class="fw-bold text-success d-block"><i class="bi bi-check-circle-fill me-1"></i> Safety Pre-restore Snapshot:</span>
                                        <span class="font-monospace text-dark">${escapeHtml(result.data?.restore_summary?.safety_snapshot || 'Created')}</span>
                                    </div>
                                `,
                                confirmButtonColor: '#1e293b'
                            });
                        }
                    } else {
                        throw new Error(result.message || 'Failed to restore database.');
                    }
                } catch (error) {
                    console.error('Upload Restore Error:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Restore Failed',
                            text: error.message || 'Failed to process uploaded backup file.'
                        });
                    } else {
                        alert(error.message || 'Failed to process uploaded backup file.');
                    }
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                }
            });
        }

        // ========================================================
        // INACTIVITY SETTINGS AJAX HANDLER (CIMS Standards)
        // ========================================================
        const idleEnabledSwitch = document.getElementById('idleLockEnabledSwitch');
        const idleLockSelect = document.getElementById('idleLockMinutesSelect');
        const idleLogoutSelect = document.getElementById('idleLogoutMinutesSelect');
        const idleSettingsForm = document.getElementById('idleSettingsForm');
        const saveIdleBtn = document.getElementById('saveIdleSettingsBtn');

        if (idleEnabledSwitch && idleLockSelect && idleLogoutSelect) {
            idleEnabledSwitch.addEventListener('change', () => {
                const isChecked = idleEnabledSwitch.checked;
                idleLockSelect.disabled = !isChecked;
                idleLogoutSelect.disabled = !isChecked;
            });
        }

        if (idleSettingsForm) {
            idleSettingsForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const lockMin = parseFloat(idleLockSelect ? idleLockSelect.value : '15');
                const logoutMin = parseFloat(idleLogoutSelect ? idleLogoutSelect.value : '30');

                if (idleEnabledSwitch && idleEnabledSwitch.checked && logoutMin > 0 && lockMin >= logoutMin) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Validation Error',
                            text: `Auto-Logout threshold (${logoutMin}m) must be greater than Screen Lock threshold (${lockMin}m).`
                        });
                    } else {
                        alert(`Auto-Logout threshold (${logoutMin}m) must be greater than Screen Lock threshold (${lockMin}m).`);
                    }
                    return;
                }

                const originalBtnText = saveIdleBtn ? saveIdleBtn.innerHTML : 'Save Inactivity Policy';
                if (saveIdleBtn) {
                    saveIdleBtn.disabled = true;
                    saveIdleBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                }

                try {
                    const formData = new FormData(idleSettingsForm);
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                    const response = await fetch('process/process.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': csrfToken
                        }
                    });

                    const result = await response.json();
                    const isSuccess = (result.success === true || result.status === 'success');

                    if (isSuccess) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Settings Saved!',
                                text: result.message || 'Inactivity security policy updated successfully.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            alert(result.message || 'Inactivity security policy updated successfully.');
                        }

                        // Update current window config in real-time
                        if (window.cimsIdleConfig && result.data) {
                            window.cimsIdleConfig.enabled = (result.data.idle_lock_enabled === '1');
                            window.cimsIdleConfig.lockMinutes = parseInt(result.data.idle_lock_minutes, 10);
                            window.cimsIdleConfig.logoutMinutes = parseInt(result.data.idle_logout_minutes, 10);
                        }
                    } else {
                        throw new Error(result.message || 'Failed to save inactivity settings.');
                    }
                } catch (error) {
                    console.error('Idle Settings AJAX Error:', error);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'Failed to update security settings.'
                        });
                    } else {
                        alert(error.message || 'Failed to update security settings.');
                    }
                } finally {
                    if (saveIdleBtn) {
                        saveIdleBtn.disabled = false;
                        saveIdleBtn.innerHTML = originalBtnText;
                    }
                }
            });
        }
    });
</script>

<?php include 'layout/footer.php'; ?>