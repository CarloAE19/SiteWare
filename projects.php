<?php
require_once 'Connection/db.php';
init_secure_session();

if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

// Authorized roles: Admin, Management, Purchasing
if (!in_array($_SESSION['user_role'], ['admin', 'management', 'purchasing'])) {
    header("Location: index");
    exit;
}

$role = $_SESSION['user_role'];

// Fetch Projects with RS and WS transaction counts
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_code VARCHAR(50) NULL UNIQUE,
        project_name VARCHAR(150) NOT NULL UNIQUE,
        address VARCHAR(255) NULL,
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

$totalProjects = count($projects);
$activeCount = count(array_filter($projects, fn($p) => ($p['status'] ?? '') === 'active'));
$inactiveCount = count(array_filter($projects, fn($p) => ($p['status'] ?? '') !== 'active'));

include 'layout/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <!-- ALERT NOTIFICATIONS -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?? 'info' ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <!-- PAGE HEADER BANNER -->
    <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 12px;">
        <div class="card-body p-4 text-white">
            <div class="row align-items-center g-3">
                <div class="col-12 col-md-8">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 bg-primary bg-opacity-25 p-3 d-flex align-items-center justify-content-center border border-primary border-opacity-25" style="width: 55px; height: 55px;">
                            <i class="bi bi-briefcase-fill fs-3 text-warning"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-1">Projects Directory</h3>
                            <p class="text-white-50 small mb-0">Centralized jobsite hub for construction sites, delivery addresses, and real-time material tracking.</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4 text-md-end">
                    <?php if (in_array($role, ['admin', 'management'])): ?>
                        <button class="btn btn-brand fw-bold shadow px-4 py-2" data-bs-toggle="modal" data-bs-target="#projectModal" onclick="openAddProjectModal()">
                            <i class="bi bi-plus-lg me-2"></i>New Project
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN PROJECTS CARD -->
    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white rounded-3">
        <!-- Action & Filter Bar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
            <!-- Filter Pills -->
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 proj-filter-btn active" data-filter="all" onclick="filterProjectsTable('all', this)">
                    All Projects <span class="badge bg-secondary ms-1"><?= $totalProjects ?></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 proj-filter-btn" data-filter="active" onclick="filterProjectsTable('active', this)">
                    <i class="bi bi-check-circle me-1"></i>Active Sites <span class="badge bg-success ms-1"><?= $activeCount ?></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 proj-filter-btn" data-filter="inactive" onclick="filterProjectsTable('inactive', this)">
                    <i class="bi bi-pause-circle me-1"></i>Inactive / Completed <span class="badge bg-secondary ms-1"><?= $inactiveCount ?></span>
                </button>
            </div>

            <!-- Live Search Bar -->
            <div class="input-group shadow-sm" style="max-width: 320px;">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" id="searchProjectsLive" class="form-control border-start-0 ps-0 bg-white" placeholder="Search project name, ID, or site..." onkeyup="searchProjectsTable(this.value)">
            </div>
        </div>

        <!-- Projects Table -->
        <div class="table-responsive border rounded shadow-sm">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="projectsTable">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 110px;">Project ID</th>
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
                                $rsCount = (int)($proj['rs_count'] ?? 0);
                                $wsCount = (int)($proj['ws_count'] ?? 0);
                                $totalUsage = $rsCount + $wsCount;
                            ?>
                            <tr data-status="<?= htmlspecialchars($proj['status'] ?? 'active') ?>">
                                <td class="text-muted fw-bold font-monospace" data-label="Project ID">
                                    <?= htmlspecialchars($proj['project_code'] ?? '#' . $proj['id']) ?>
                                </td>
                                <td class="fw-bold text-primary proj-name-cell" data-label="Project Name">
                                    <?= htmlspecialchars($proj['project_name']) ?>
                                </td>
                                <td class="text-dark fw-semibold small proj-addr-cell" data-label="Location / Address">
                                    <?php if (!empty($proj['address'])): ?>
                                        <i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars($proj['address']) ?>
                                    <?php else: ?>
                                        <span class="text-muted opacity-75">Not specified</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted text-wrap proj-desc-cell" style="max-width: 250px;" data-label="Description">
                                    <?= htmlspecialchars($proj['description'] ?? 'No description provided.') ?>
                                </td>
                                <td class="text-center" data-label="Linked Activity">
                                    <div class="d-flex flex-wrap gap-1 justify-content-center">
                                        <?php if ($rsCount > 0): ?>
                                            <a href="requisitions?search=<?= urlencode($proj['project_name']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 text-decoration-none shadow-sm" title="View <?= $rsCount ?> Material Requisition(s)">
                                                <i class="bi bi-file-earmark-text me-1"></i><?= $rsCount ?> RS
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($wsCount > 0): ?>
                                            <a href="withdrawals?search=<?= urlencode($proj['project_name']) ?>" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 text-decoration-none shadow-sm" title="View <?= $wsCount ?> Withdrawal Slip(s)">
                                                <i class="bi bi-box-arrow-right me-1"></i><?= $wsCount ?> WS
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($totalUsage === 0): ?>
                                            <span class="badge bg-light text-muted border px-2 py-1">0 records</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center" data-label="Status">
                                    <?php if (in_array($role, ['admin', 'management'])): ?>
                                        <form method="POST" action="process/process.php" class="d-inline"
                                            onsubmit="return confirm('Toggle status of project \'<?= addslashes(htmlspecialchars($proj['project_name'])) ?>\' to <?= $proj['status'] === 'active' ? 'Inactive' : 'Active' ?>?');">
                                            <input type="hidden" name="action" value="toggle_project_status">
                                            <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                                            <input type="hidden" name="return_to" value="projects">
                                            <?php if ($proj['status'] === 'active'): ?>
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 py-1 shadow-sm" title="Active Project — Click to mark Inactive">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Active
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 shadow-sm" title="Inactive Project — Click to mark Active">
                                                    <i class="bi bi-pause-circle me-1"></i>Inactive
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge <?= $proj['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?> rounded-pill px-3 py-2">
                                            <?= ucfirst($proj['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end" data-label="Actions">
                                    <button type="button" class="btn btn-sm btn-outline-info me-1 shadow-sm"
                                        onclick="openProjectDetailsModal(<?= $proj['id'] ?>)"
                                        title="View Project Requisitions, Withdrawals & Material History">
                                        <i class="bi bi-eye"></i> Details
                                    </button>

                                    <?php if (in_array($role, ['admin', 'management'])): ?>
                                        <button class="btn btn-sm btn-outline-primary me-1"
                                            onclick="openEditProjectModal(<?= $proj['id'] ?>, '<?= addslashes(htmlspecialchars($proj['project_code'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($proj['project_name'])) ?>', '<?= addslashes(htmlspecialchars($proj['address'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($proj['description'] ?? '')) ?>', '<?= $proj['status'] ?>')">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($role === 'admin'): ?>
                                        <?php if ($totalUsage > 0): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm disabled" title="Cannot delete: Linked to <?= $rsCount ?> RS and <?= $wsCount ?> WS">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" action="process/process.php" class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to delete project \'<?= addslashes(htmlspecialchars($proj['project_name'])) ?>\'?');">
                                                <input type="hidden" name="action" value="delete_project">
                                                <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                                                <input type="hidden" name="return_to" value="projects">
                                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm" title="Delete Project">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-briefcase fs-1 d-block mb-2 opacity-50"></i>
                                No projects registered yet. Click <b>"New Project"</b> above to add one.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: ADD / EDIT PROJECT                                -->
<!-- ======================================================== -->
<div class="modal fade" id="projectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-brand text-white">
                <h5 class="modal-title fw-bold" id="projectModalTitle">Add New Project</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <input type="hidden" name="action" id="projectFormAction" value="add_project">
                <input type="hidden" name="project_id" id="projectId">
                <input type="hidden" name="return_to" value="projects">

                <div class="modal-body p-4 bg-light">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold mb-0">Project ID / Code</label>
                            <button type="button" class="btn btn-sm btn-link text-primary p-0 text-decoration-none" onclick="generateAutoProjectCode()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Auto-generate
                            </button>
                        </div>
                        <input type="text" class="form-control font-monospace fw-bold" name="project_code" id="projectCode" placeholder="e.g. PRJ-2026-001">
                        <small class="text-muted">Unique tracking identifier for construction plans & delivery slips.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Project / Site Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="project_name" id="projectName" required placeholder="e.g., Phase 2 Building">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Location / Delivery Address</label>
                        <input type="text" class="form-control" name="address" id="projectAddress" placeholder="e.g., Purok 4, San Jose, Malaybalay City">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description / Scope Notes</label>
                        <textarea class="form-control" name="description" id="projectDesc" rows="3" placeholder="Scope of work, project manager, or delivery remarks..."></textarea>
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

<!-- ======================================================== -->
<!-- MODAL: PROJECT DETAILS & JOBSITE MATERIAL HUB            -->
<!-- ======================================================== -->
<div class="modal fade" id="projectDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background-color: var(--gb-dark, #1e293b);">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary text-white p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
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
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                    <div class="fw-bold text-muted">Loading project materials and transaction records...</div>
                </div>

                <!-- Content Container -->
                <div id="projDetailsContent" style="display: none;">
                    <!-- Project Overview Card -->
                    <div class="card border-0 shadow-sm mb-4 bg-white rounded-3">
                        <div class="card-body p-3 p-md-4">
                            <div class="row align-items-center g-3">
                                <div class="col-12 col-md-8">
                                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                        <span class="badge bg-dark font-monospace px-3 py-2 fs-6" id="projDetailsCode">PRJ-000</span>
                                        <span class="badge rounded-pill px-3 py-2 fs-6" id="projDetailsStatus">Active</span>
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
                                                <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem;">Requisitions</small>
                                                <span class="fw-bold fs-5 text-primary" id="projStatRs">0</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-2 border rounded bg-light">
                                                <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem;">Withdrawals</small>
                                                <span class="fw-bold fs-5 text-success" id="projStatWs">0</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-2 border rounded bg-light">
                                                <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.7rem;">Materials</small>
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
                            <button class="nav-link active fw-bold px-3 py-2 shadow-sm" id="proj-tab-rs" data-bs-toggle="pill" data-bs-target="#proj-pane-rs" type="button" role="tab">
                                <i class="bi bi-file-earmark-text me-1"></i>Material Requisitions (<span id="projTabRsCount">0</span>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold px-3 py-2 shadow-sm" id="proj-tab-ws" data-bs-toggle="pill" data-bs-target="#proj-pane-ws" type="button" role="tab">
                                <i class="bi bi-box-arrow-right me-1"></i>Material Withdrawals (<span id="projTabWsCount">0</span>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold px-3 py-2 shadow-sm" id="proj-tab-summary" data-bs-toggle="pill" data-bs-target="#proj-pane-summary" type="button" role="tab">
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
                                                <th>Requestor</th>
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
                                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-fill me-1 text-primary"></i>Total Materials Delivered to Site</h6>
                                    <small class="text-muted">Aggregated summary of all verified jobsite dispatches</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-success fw-bold shadow-sm" onclick="exportProjectConsumptionCSV()">
                                        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold shadow-sm" onclick="printProjectConsumptionReport()">
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

<!-- ======================================================== -->
<!-- SUB-MODAL: REQUISITION DOCUMENT PREVIEW                  -->
<!-- ======================================================== -->
<div class="modal fade" id="viewProjectRsModal" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background-color: var(--gb-dark, #1e293b);">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-2 text-warning"></i>Requisition Document Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3 bg-white p-3 rounded shadow-sm">
                    <div>
                        <h4 class="fw-bold text-primary mb-1 d-flex align-items-center gap-2 flex-wrap">
                            <span id="projRsDocNo">RS-0000</span>
                            <span id="projRsDocStatus" class="badge shadow-sm" style="font-size: 0.75rem;">Pending Approval</span>
                        </h4>
                        <div class="text-muted fw-bold text-uppercase small" id="projRsDocProject">Project Name</div>
                        <div class="mt-2 text-muted small">
                            Requested By: <strong id="projRsDocRequestor" class="text-dark">User</strong><br>
                            Date Requested: <strong id="projRsDocDate" class="text-dark">Date</strong><br>
                            Urgency Level: <span id="projRsDocUrgency" class="badge bg-secondary">Normal</span>
                        </div>
                    </div>

                    <div id="projRsDocQrContainer" class="text-center d-none">
                        <img id="projRsDocQrCode" src="" alt="RS QR Code" class="border p-1 bg-white shadow-sm" style="width: 90px; height: 90px; border-radius: 6px;">
                        <small class="d-block text-muted mt-1 fw-bold" style="font-size: 0.65rem;">SCAN AT WAREHOUSE</small>
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
                    <p class="text-muted small border p-3 bg-white rounded shadow-sm mb-0" id="projRsDocRemarks" style="min-height: 50px;">No remarks provided.</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between bg-white border-top-0">
                <a href="#" id="projRsDocFullPageLink" target="_blank" class="btn btn-outline-primary fw-bold">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Open in Requisitions Tab
                </a>
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Back to Project</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- SUB-MODAL: WITHDRAWAL SLIP PREVIEW                       -->
<!-- ======================================================== -->
<div class="modal fade" id="viewProjectWdModal" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background-color: var(--gb-dark, #1e293b);">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-right me-2 text-success"></i>Material Withdrawal Slip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3 bg-white p-3 rounded shadow-sm">
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
                    <div class="card-header bg-light fw-bold text-muted small text-uppercase">Verification & Release Proof</div>
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-6" id="projWdDocSigWrapper">
                                <small class="text-muted fw-bold d-block mb-1">Receiver Signature:</small>
                                <div id="projWdDocSigContent" class="p-2 border rounded bg-light text-center">
                                    <img id="projWdDocSigImg" src="" class="img-fluid rounded" style="max-height: 90px; background-color: #fff;">
                                </div>
                            </div>
                            <div class="col-md-6" id="projWdDocPhotoWrapper">
                                <small class="text-muted fw-bold d-block mb-1">Delivery / Handover Photo:</small>
                                <div id="projWdDocPhotoContent" class="p-2 border rounded bg-light text-center">
                                    <a id="projWdDocPhotoLink" href="#" target="_blank">
                                        <img id="projWdDocPhotoImg" src="" class="img-fluid border rounded shadow-sm" style="max-height: 90px; object-fit: cover;">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h6 class="fw-bold mb-2 text-dark small text-uppercase">Release Remarks:</h6>
                    <p class="text-muted small border p-3 bg-white rounded shadow-sm mb-0" id="projWdDocRemarks">No remarks.</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between bg-white border-top-0">
                <a href="#" id="projWdDocFullPageLink" target="_blank" class="btn btn-outline-success fw-bold">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Open in Withdrawals Tab
                </a>
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Back to Project</button>
            </div>
        </div>
    </div>
</div>

<script>
window.searchProjectsTable = function (term) {
    term = (term || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#projectsTable tbody tr');
    rows.forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
};
</script>

<script src="assets/js/projects.js?v=<?= time() ?>"></script>
<?php include 'layout/footer.php'; ?>