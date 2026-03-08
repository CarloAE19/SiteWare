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

// ========================================================
// ROLE-BASED DATA FETCHING
// ========================================================
if ($role === 'requestor') {
    $stmt = $pdo->prepare("SELECT * FROM requisitions WHERE requestor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statTitle = "My";
} else {
    $stmt = $pdo->query("SELECT * FROM requisitions ORDER BY created_at DESC");
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $statTitle = "System";
}

$itemsQuery = $pdo->query("SELECT ri.requisition_id, ri.quantity, ri.item_code, i.item_name, i.unit, i.quantity as current_stock 
                           FROM requisition_items ri 
                           LEFT JOIN inventory i ON ri.item_code = i.item_code");
$allItems = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
$rsItemsGrouped = [];
foreach($allItems as $item) {
    $rsItemsGrouped[$item['requisition_id']][] = $item;
}

$totalRS = count($requisitions);
$pendingRS = count(array_filter($requisitions, fn($r) => $r['status'] === 'Pending Approval'));
$approvedRS = count(array_filter($requisitions, fn($r) => in_array($r['status'], ['Approved', 'PO Created'])));

include 'layout/header.php';
?>

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
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: var(--gb-blue);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;"><?= $statTitle ?> Total Requisitions</h6>
                        <h3 class="mb-0 fw-bold"><?= $totalRS ?></h3>
                    </div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue) !important;"><i class="bi bi-file-earmark-text"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: var(--gb-yellow);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;"><?= $statTitle ?> Pending Approval</h6>
                        <h3 class="mb-0 fw-bold"><?= $pendingRS ?></h3>
                    </div>
                    <div class="fs-1 text-warning"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: #198754;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;"><?= $statTitle ?> Approved (Ready)</h6>
                        <h3 class="mb-0 fw-bold"><?= $approvedRS ?></h3>
                    </div>
                    <div class="fs-1 text-success"><i class="bi bi-check2-all"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Area -->
    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-xl-5">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-ui-checks me-2 text-primary"></i>Requisition Slips (RS)</h4>
                <small class="text-muted">Manage material requests and digital workflow approvals.</small>
            </div>
            
            <div class="col-12 col-xl-7">
                <div class="d-flex flex-column flex-md-row justify-content-xl-end gap-2">
                    <div class="input-group w-100 mb-2 mb-md-0" style="max-width: 400px;">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchRs" class="form-control border-start-0 ps-0 bg-light" placeholder="Search RS No or Project...">
                    </div>
                    
                    <?php if (in_array($role, ['requestor', 'warehouse'])): ?>
                    <button class="btn btn-brand w-100 w-md-auto fw-bold text-nowrap shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#rsModal">
                        <i class="bi bi-plus-lg me-1"></i> Create RS
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="table-responsive border rounded shadow-sm">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="rsTable">
                <thead class="table-dark">
                    <tr>
                        <th scope="col">RS Number</th>
                        <th scope="col">Project / Purpose</th>
                        <th scope="col">Requested By</th>
                        <th scope="col">Date Requested</th>
                        <th scope="col">Urgency</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($requisitions) > 0): ?>
                        <?php foreach ($requisitions as $rs): ?>
                            <tr class="rs-row">
                                <td class="fw-bold text-primary rs-no"><?= htmlspecialchars($rs['rs_no']) ?></td>
                                <td class="fw-bold rs-project"><?= htmlspecialchars($rs['project_name']) ?></td>
                                <td><i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($rs['requestor_name']) ?></td>
                                <td class="text-muted small"><?= date('M d, Y', strtotime($rs['created_at'])) ?></td>
                                <td>
                                    <?php 
                                        $urgencyClass = 'bg-secondary';
                                        if($rs['urgency'] == 'High') $urgencyClass = 'bg-warning text-dark';
                                        if($rs['urgency'] == 'Urgent') $urgencyClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $urgencyClass ?>"><?= htmlspecialchars($rs['urgency']) ?></span>
                                </td>
                                <td>
                                    <?php 
                                        $statusClass = 'bg-secondary';
                                        if($rs['status'] == 'Pending Approval') $statusClass = 'bg-warning text-dark';
                                        if($rs['status'] == 'Approved') $statusClass = 'bg-primary';
                                        if($rs['status'] == 'Rejected') $statusClass = 'bg-danger';
                                        if($rs['status'] == 'PO Created') $statusClass = 'bg-success';
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($rs['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    
                                    <!-- FIXED: Safe encoding prevents syntax errors from quotes and line breaks! -->
                                    <?php 
                                        $cleanProject = str_replace(["\r", "\n"], ["\\r", "\\n"], addslashes($rs['project_name']));
                                        $cleanRemarks = str_replace(["\r", "\n"], ["\\r", "\\n"], addslashes($rs['remarks']));
                                        $cleanRequestor = addslashes($rs['requestor_name']);
                                        $itemsB64 = base64_encode(json_encode($rsItemsGrouped[$rs['id']] ?? []));
                                    ?>
                                    
                                    <button class="btn btn-sm btn-outline-secondary fw-bold shadow-sm me-1" title="View Details" 
                                            onclick="viewRsDetails('<?= $rs['rs_no'] ?>', '<?= $cleanProject ?>', '<?= $cleanRemarks ?>', '<?= $rs['status'] ?>', '<?= $cleanRequestor ?>', '<?= date('M d, Y', strtotime($rs['created_at'])) ?>', '<?= $itemsB64 ?>')">
                                        <i class="bi bi-file-earmark-text me-1"></i> View Form
                                    </button>
                                    
                                    <?php if (in_array($role, ['management', 'admin']) && $rs['status'] === 'Pending Approval'): ?>
                                        <form method="POST" action="process/process.php" class="d-inline">
                                            <input type="hidden" name="action" value="approve_rs">
                                            <input type="hidden" name="rs_id" value="<?= $rs['id'] ?>">
                                            <button class="btn btn-sm btn-outline-success shadow-sm me-1" title="Approve RS"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-sm btn-outline-danger shadow-sm" title="Reject RS" onclick="openRejectModal(<?= $rs['id'] ?>, '<?= $rs['rs_no'] ?>')">
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
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 d-block mb-2"></i>No Requisition Slips found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'components/requisition_modals.php'; ?>
<script src="assets/js/requisitions.js"></script>
<?php include 'layout/footer.php'; ?>