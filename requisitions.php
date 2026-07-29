<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'Connection/db.php';

$role = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

// Fetch Inventory items for the "Add Item" dropdown
$itemStmt = $pdo->query("SELECT item_code, item_name, unit FROM inventory WHERE status != 'Out of Stock' ORDER BY item_name ASC");
$inventoryItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Projects
$activeProjects = $pdo->query("SELECT project_name FROM projects WHERE status = 'active' ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ========================================================
// ROLE-BASED DATA FETCHING
// ========================================================
if ($role === 'requestor') {
    $stmt = $pdo->query("SELECT * FROM requisitions WHERE type = 'project' ORDER BY created_at DESC");
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statTitle = "Project ";
} elseif ($role === 'warehouse' || $role === 'purchasing') {
    $stmt = $pdo->query("SELECT * FROM requisitions WHERE type = 'restock' ORDER BY created_at DESC");
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statTitle = ($role === 'warehouse') ? "Warehouse " : "Restock ";
} else {
    $stmt = $pdo->query("SELECT * FROM requisitions ORDER BY created_at DESC");
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statTitle = "";
}

$itemsQuery = $pdo->query("SELECT ri.requisition_id, ri.quantity, ri.item_code, i.item_name, i.unit, i.quantity as current_stock,
                                  COALESCE(p.total_pending, 0) as total_pending,
                                  p.pending_details
                           FROM requisition_items ri 
                           LEFT JOIN inventory i ON ri.item_code = i.item_code
                           LEFT JOIN (
                               SELECT ri2.item_code, SUM(ri2.quantity) as total_pending,
                                      GROUP_CONCAT(CONCAT(r2.project_name, ' [', ri2.quantity, 'x by ', r2.requestor_name, ']') SEPARATOR '; ') as pending_details
                               FROM requisition_items ri2
                               JOIN requisitions r2 ON ri2.requisition_id = r2.id
                               WHERE r2.status = 'Pending Approval'
                               GROUP BY ri2.item_code
                           ) p ON ri.item_code = p.item_code");
$allItems = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
$rsItemsGrouped = [];
foreach ($allItems as $item) {
    $rsItemsGrouped[$item['requisition_id']][] = $item;
}

$totalRS = count($requisitions);
$pendingRS = count(array_filter($requisitions, fn($r) => $r['status'] === 'Pending Approval'));
$approvedRS = count(array_filter($requisitions, fn($r) => in_array($r['status'], ['Approved', 'PO Created'])));

include 'layout/header.php';
?>

<style>
    @media (max-width: 767.98px) {

        /* FIXED: Added .rs-table-wrapper class so this CSS DOES NOT break the Modal's table! */
        .rs-table-wrapper {
            overflow-x: hidden !important;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
            padding: 0 !important;
        }

        #rsTable {
            display: block !important;
            width: 100% !important;
            white-space: normal !important;
            background: transparent !important;
            border: none !important;
        }

        #rsTable thead {
            display: none !important;
        }

        #rsTable tbody {
            display: block !important;
            width: 100% !important;
        }

        #rsTable tbody tr {
            display: flex !important;
            flex-direction: column !important;
            border: 1px solid #e0e4e8;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #fff;
            padding: 14px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        #rsTable tbody td {
            display: flex !important;
            justify-content: space-between;
            align-items: center;
            text-align: right;
            padding: 10px 4px;
            border: none !important;
            border-bottom: 1px dashed #e9ecef !important;
            white-space: normal !important;
            word-break: break-word;
            width: 100% !important;
            box-sizing: border-box;
        }

        #rsTable tbody td.d-none {
            display: none !important;
        }

        /* Actions footer row */
        #rsTable tbody td:last-child {
            border-bottom: none !important;
            justify-content: center !important;
            gap: 8px;
            padding-top: 14px;
            margin-top: 4px;
            flex-wrap: wrap;
        }

        /* Fix inline forms in the actions td */
        #rsTable tbody td:last-child form.d-inline {
            display: inline !important;
        }

        #rsTable tbody td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            text-align: left;
            padding-right: 15px;
            flex-shrink: 0;
        }

        #rsTable tbody td:last-child::before {
            display: none;
        }
    }
    
    /* Touch-friendly details dropdown styles for PWA */
    summary {
        list-style: none;
    }
    summary::-webkit-details-marker {
        display: none;
    }
    .border-bottom-dashed {
        border-bottom: 1px dashed #dee2e6;
    }
    .border-bottom-dashed:last-child {
        border-bottom: none !important;
        margin-bottom: 0 !important;
        padding-bottom: 0 !important;
    }

    /* Mobile action bar: stack search full-width, then buttons in a neat row */
    @media (max-width: 767.98px) {
        .rs-action-bar {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 10px !important;
        }
        .rs-action-bar .input-group {
            max-width: 100% !important;
            min-width: 0 !important;
            width: 100% !important;
        }
        .rs-action-bar .rs-btn-group {
            display: flex !important;
            gap: 8px !important;
            width: 100% !important;
        }
        .rs-action-bar .rs-btn-group > div,
        .rs-action-bar .rs-btn-group > .dropdown {
            flex: 1 1 0 !important;
        }
        .rs-action-bar .rs-btn-group .btn {
            width: 100% !important;
            font-size: 0.82rem !important;
            padding: 0.45rem 0.5rem !important;
        }
    }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="row mb-4 g-3">
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0" style="border-left: 4px solid var(--gb-blue) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;"><?= $statTitle ?>Total Requisitions</h6>
                        <h3 class="mb-0 fw-bold"><?= $totalRS ?></h3>
                    </div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue) !important;"><i class="bi bi-file-earmark-text"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0" style="border-left: 4px solid var(--gb-yellow) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;"><?= $statTitle ?>Pending Approval</h6>
                        <h3 class="mb-0 fw-bold"><?= $pendingRS ?></h3>
                    </div>
                    <div class="fs-1 text-warning"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0" style="border-left: 4px solid #198754 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;"><?= $statTitle ?>Approved (Ready)</h6>
                        <h3 class="mb-0 fw-bold"><?= $approvedRS ?></h3>
                    </div>
                    <div class="fs-1 text-success"><i class="bi bi-check2-all"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-xl-4 text-center text-xl-start">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-ui-checks me-2 text-primary"></i>Requisition Slips</h4>
            </div>

            <div class="col-12 col-xl-8">
                <div class="d-flex flex-wrap justify-content-start justify-content-xl-end align-items-center gap-2 w-100 rs-action-bar">

                    <div class="input-group shadow-sm flex-grow-1 flex-md-grow-0" style="max-width: 320px; min-width: 200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchRs" class="form-control border-start-0 ps-0 bg-white" placeholder="Search RS No or Project...">
                    </div>

                    <div class="d-flex flex-wrap gap-2 rs-btn-group">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary fw-bold shadow-sm px-3" type="button" id="columnToggleBtn" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                                <i class="bi bi-funnel-fill me-1 text-primary"></i> Filter
                            </button>
                            <ul class="dropdown-menu dropdown-menu-md-end shadow-lg border-0 p-2" aria-labelledby="columnToggleBtn" style="min-width: 250px;">
                                <li>
                                    <h6 class="dropdown-header fw-bold text-uppercase small text-muted">Filter Columns</h6>
                                </li>
                                <li><label class="dropdown-item d-flex align-items-center rounded py-2 text-dark fw-bold" style="cursor:pointer;"><input class="form-check-input col-toggle me-3 mt-0 border-secondary" type="checkbox" style="transform: scale(1.2);" value="col-project" checked> Project / Purpose</label></li>
                                <li><label class="dropdown-item d-flex align-items-center rounded py-2 text-dark fw-bold" style="cursor:pointer;"><input class="form-check-input col-toggle me-3 mt-0 border-secondary" type="checkbox" style="transform: scale(1.2);" value="col-requestor" checked> Requested By</label></li>
                                <li><label class="dropdown-item d-flex align-items-center rounded py-2 text-dark fw-bold" style="cursor:pointer;"><input class="form-check-input col-toggle me-3 mt-0 border-secondary" type="checkbox" style="transform: scale(1.2);" value="col-date" checked> Date Log</label></li>
                                <li><label class="dropdown-item d-flex align-items-center rounded py-2 text-dark fw-bold" style="cursor:pointer;"><input class="form-check-input col-toggle me-3 mt-0 border-secondary" type="checkbox" style="transform: scale(1.2);" value="col-urgency" checked> Urgency</label></li>
                                <li><label class="dropdown-item d-flex align-items-center rounded py-2 text-dark fw-bold" style="cursor:pointer;"><input class="form-check-input col-toggle me-3 mt-0 border-secondary" type="checkbox" style="transform: scale(1.2);" value="col-status" checked> Status</label></li>
                            </ul>
                        </div>

                        <?php if ($role === 'requestor' || $role === 'admin'): ?>
                            <div>
                                <button class="btn btn-brand btn-sm fw-bold text-nowrap shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#rsModal">
                                    <i class="bi bi-plus-lg me-1"></i> Create RS
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if ($role === 'warehouse' || $role === 'admin'): ?>
                            <div>
                                <button class="btn btn-outline-primary btn-sm fw-bold text-nowrap shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#restockModal">
                                    <i class="bi bi-box-seam me-1"></i> Request Restock
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- FIXED: Added rs-table-wrapper class here -->
        <div class="table-responsive rs-table-wrapper border rounded shadow-sm mt-3 bg-white">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="rsTable">
                <thead class="table-dark">
                    <tr>
                        <th scope="col" class="col-rs-no py-3">RS Number</th>
                        <th scope="col" class="col-project py-3">Project / Purpose</th>
                        <th scope="col" class="col-requestor py-3">Requested By</th>
                        <th scope="col" class="col-date py-3">Date Log</th>
                        <th scope="col" class="col-urgency py-3">Urgency</th>
                        <th scope="col" class="col-status py-3">Status</th>
                        <th scope="col" class="text-end py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($requisitions) > 0): ?>
                        <?php foreach ($requisitions as $rs): ?>
                            <tr class="rs-row">
                                <td class="fw-bold text-primary rs-no col-rs-no" data-label="RS Number"><?= htmlspecialchars($rs['rs_no']) ?></td>
                                <td class="fw-bold rs-project col-project text-dark" data-label="Project / Purpose">
                                    <?php if (($rs['type'] ?? 'project') === 'restock'): ?>
                                        <span class="badge bg-info text-dark shadow-sm px-2 py-1.5"><i class="bi bi-box-seam me-1"></i> Warehouse Restock</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($rs['project_name']) ?>
                                    <?php endif; ?>
                                </td>

                                <!-- Aligned left for PC, but the td's flex layout handles right-alignment on mobile -->
                                <td class="col-requestor" data-label="Requested By">
                                    <div class="d-flex align-items-center justify-content-start">
                                        <i class="bi bi-person me-1 text-muted"></i>
                                        <span class="rs-requestor fw-bold text-dark"><?= htmlspecialchars($rs['requestor_name']) ?></span>
                                        <?php if ((int)$rs['requestor_id'] === (int)$userId): ?>
                                            <span class="badge bg-primary ms-2 px-2 py-1 shadow-sm" style="font-size: 0.65rem;">You</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="col-date" data-label="Date Log">
                                    <span class="d-block text-dark fw-semibold small">
                                        <i class="bi bi-calendar3 me-1 text-muted"></i><?= date('M d, Y', strtotime($rs['created_at'])) ?>
                                    </span>
                                    <small class="text-muted" style="font-size:0.73rem;">
                                        <i class="bi bi-clock me-1 text-primary"></i><?= date('g:i A', strtotime($rs['created_at'])) ?> 
                                        <span class="text-secondary opacity-75">(<?= time_elapsed_string($rs['created_at']) ?>)</span>
                                    </small>
                                </td>
                                <td class="col-urgency" data-label="Urgency">
                                    <?php
                                    $urgencyClass = 'bg-secondary';
                                    if ($rs['urgency'] == 'High') $urgencyClass = 'bg-warning text-dark';
                                    if ($rs['urgency'] == 'Urgent') $urgencyClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $urgencyClass ?> shadow-sm"><?= htmlspecialchars($rs['urgency']) ?></span>
                                </td>
                                <td class="col-status" data-label="Status">
                                    <?php
                                    $statusClass = 'bg-secondary';
                                    if ($rs['status'] == 'Pending Approval') $statusClass = 'bg-warning text-dark';
                                    if ($rs['status'] == 'Approved') $statusClass = 'bg-primary';
                                    if ($rs['status'] == 'Rejected') $statusClass = 'bg-danger';
                                    if ($rs['status'] == 'PO Created') $statusClass = 'bg-success';
                                    if ($rs['status'] == 'Released') $statusClass = 'bg-dark';
                                    ?>
                                    <span class="badge <?= $statusClass ?> shadow-sm"><?= htmlspecialchars($rs['status']) ?></span>
                                </td>
                                <td class="text-end" data-label="Actions">

                                    <?php
                                    $cleanProject = str_replace(["\r", "\n"], ["\\r", "\\n"], addslashes($rs['project_name']));
                                    $cleanRemarks = str_replace(["\r", "\n"], ["\\r", "\\n"], addslashes($rs['remarks']));
                                    $cleanRequestor = addslashes($rs['requestor_name']);
                                    $itemsB64 = base64_encode(json_encode($rsItemsGrouped[$rs['id']] ?? []));
                                    $formattedDateLog = date('M d, Y g:i A', strtotime($rs['created_at'])) . ' (' . time_elapsed_string($rs['created_at']) . ')';
                                    ?>

                                    <button class="btn btn-sm btn-outline-secondary fw-bold shadow-sm me-1" title="View Details"
                                        onclick="viewRsDetails('<?= $rs['rs_no'] ?>', '<?= $cleanProject ?>', '<?= $cleanRemarks ?>', '<?= $rs['status'] ?>', '<?= $cleanRequestor ?>', '<?= $formattedDateLog ?>', '<?= $itemsB64 ?>', '<?= $rs['type'] ?? 'project' ?>')">
                                        <i class="bi bi-file-earmark-text me-1"></i> View
                                    </button>

                                    <?php if (in_array($role, ['management', 'admin']) && $rs['status'] === 'Pending Approval'): ?>
                                        <form method="POST" action="process/process.php" class="d-inline">
                                            <input type="hidden" name="action" value="approve_rs">
                                            <input type="hidden" name="rs_id" value="<?= $rs['id'] ?>">
                                            <button class="btn btn-sm btn-success shadow-sm me-1" title="Approve RS"><i class="bi bi-check-lg"></i></button>
                                        </form>

                                        <button type="button" class="btn btn-sm btn-danger shadow-sm" title="Reject RS" onclick="openRejectModal(<?= $rs['id'] ?>, '<?= $rs['rs_no'] ?>')">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($role === 'purchasing' && $rs['status'] === 'Approved'): ?>
                                        <button class="btn btn-sm btn-outline-primary shadow-sm" title="Generate Purchase Order"><i class="bi bi-file-earmark-plus"></i> PO</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 d-block mb-2"></i>No Requisition Slips found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'components/requisition_modals.php'; ?>
<script src="assets/js/requisitions.js?v=<?= time() ?>"></script>
<?php include 'layout/footer.php'; ?>