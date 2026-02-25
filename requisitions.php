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

// FETCH REQUISITION ITEMS (To display in the View Details Modal)
$itemsQuery = $pdo->query("SELECT ri.requisition_id, ri.quantity, ri.item_code, i.item_name, i.unit 
                           FROM requisition_items ri 
                           LEFT JOIN inventory i ON ri.item_code = i.item_code");
$allItems = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
$rsItemsGrouped = [];
foreach($allItems as $item) {
    $rsItemsGrouped[$item['requisition_id']][] = $item;
}

// Calculate Stats dynamically
$totalRS = count($requisitions);
$pendingRS = count(array_filter($requisitions, fn($r) => $r['status'] === 'Pending Approval'));
$approvedRS = count(array_filter($requisitions, fn($r) => $r['status'] === 'Approved'));

include 'layout/header.php';
?>

<div class="container-fluid px-4 py-4">
    
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <!-- Role-Based Requisition Stats -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: var(--gb-blue);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1"><?= $statTitle ?> Total Requisitions</h6>
                        <h2 class="mb-0 fw-bold"><?= $totalRS ?></h2>
                    </div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue) !important;"><i class="bi bi-file-earmark-text"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: var(--gb-yellow);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1"><?= $statTitle ?> Pending Approval</h6>
                        <h2 class="mb-0 fw-bold"><?= $pendingRS ?></h2>
                    </div>
                    <div class="fs-1 text-warning"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: #198754;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1"><?= $statTitle ?> Approved (Ready)</h6>
                        <h2 class="mb-0 fw-bold"><?= $approvedRS ?></h2>
                    </div>
                    <div class="fs-1 text-success"><i class="bi bi-check2-all"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Area -->
    <div class="table-container">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-ui-checks me-2"></i>Requisition Slips (RS)</h4>
            
            <div class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" placeholder="Search RS No or Project...">
                </div>
                
                <?php if (in_array($role, ['requestor', 'warehouse'])): ?>
                <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#rsModal">
                    <i class="bi bi-plus-lg me-1"></i> Create RS
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
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
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($rs['rs_no']) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($rs['project_name']) ?></td>
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
                                    
                                    <!-- VIEW DETAILS BUTTON (With JSON Data injected for the Modal) -->
                                    <?php $currentItemsJson = htmlspecialchars(json_encode($rsItemsGrouped[$rs['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>
                                    <button class="btn btn-sm btn-outline-secondary me-1" title="View Details" 
                                            onclick="viewRsDetails('<?= $rs['rs_no'] ?>', '<?= addslashes($rs['project_name']) ?>', '<?= addslashes($rs['remarks']) ?>', '<?= $currentItemsJson ?>')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    
                                    <?php if ($role === 'management' && $rs['status'] === 'Pending Approval'): ?>
                                        <form method="POST" action="process/process.php" class="d-inline">
                                            <input type="hidden" name="action" value="approve_rs">
                                            <input type="hidden" name="rs_id" value="<?= $rs['id'] ?>">
                                            <button class="btn btn-sm btn-outline-success me-1" title="Approve RS"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <form method="POST" action="process/process.php" class="d-inline">
                                            <input type="hidden" name="action" value="reject_rs">
                                            <input type="hidden" name="rs_id" value="<?= $rs['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Reject RS"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($role === 'purchasing' && $rs['status'] === 'Approved'): ?>
                                        <button class="btn btn-sm btn-outline-primary" title="Generate Purchase Order"><i class="bi bi-file-earmark-plus"></i> PO</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-folder-x fs-1 d-block mb-2"></i>
                                No Requisition Slips found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: VIEW DETAILS (FOR MANAGEMENT / EVERYONE)          -->
<!-- ======================================================== -->
<div class="modal fade" id="viewRsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-card-list me-2" style="color: var(--gb-yellow);"></i>Requisition Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="mb-3">
                    <h6 class="fw-bold text-primary mb-0" id="viewRsNo">RS-0000</h6>
                    <small class="text-muted" id="viewRsProject">Project Name</small>
                </div>
                
                <h6 class="fw-bold border-bottom pb-2">Requested Items:</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered bg-white">
                        <thead class="table-light">
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Qty Requested</th>
                            </tr>
                        </thead>
                        <tbody id="viewRsItemsBody">
                            <!-- Injected by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <div class="mb-2">
                    <h6 class="fw-bold mb-1">Remarks:</h6>
                    <p class="text-muted small border p-2 bg-white rounded" id="viewRsRemarks" style="min-height: 50px;">No remarks.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<!-- ======================================================== -->
<!-- MODAL: CREATE RS (ONLY REQUESTORS & WAREHOUSE)           -->
<!-- ======================================================== -->
<?php if (in_array($role, ['requestor', 'warehouse'])): ?>
<div class="modal fade" id="rsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-file-earmark-plus me-2" style="color: var(--gb-yellow);"></i>Create Requisition Slip (RS)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form method="POST" action="process/process.php" id="rsForm">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" value="create_rs">
                    <input type="hidden" name="requestor_id" value="<?= $_SESSION['user_id'] ?>">
                    <input type="hidden" name="requestor_name" value="<?= htmlspecialchars($_SESSION['user_name']) ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">RS Number</label>
                            <input type="text" class="form-control text-primary fw-bold" name="rs_no" value="RS-<?= date('Y') ?>-<?= rand(1000,9999) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Date</label>
                            <input type="text" class="form-control" value="<?= date('M d, Y') ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Urgency</label>
                            <select class="form-select" name="urgency" required>
                                <option value="Normal">Normal</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Project Name / Purpose <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="project_name" required placeholder="e.g. City Hall Renovation Phase 1">
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-bold text-dark d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-box-seam me-2"></i>Requested Materials</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addMaterialBtn">
                                <i class="bi bi-plus-circle"></i> Add Item
                            </button>
                        </div>
                        <div class="card-body p-2" id="materialsContainer">
                            <div class="row g-2 material-row mb-2 align-items-center">
                                <div class="col-md-7">
                                    <select class="form-select" name="items[]" required>
                                        <option value="">Select Material from Inventory...</option>
                                        <?php foreach ($inventoryItems as $item): ?>
                                            <option value="<?= $item['item_code'] ?>">
                                                [<?= $item['item_code'] ?>] <?= htmlspecialchars($item['item_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control" name="quantities[]" placeholder="Qty" required min="1">
                                </div>
                                <div class="col-md-2 text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-row" disabled><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Remarks / Notes</label>
                        <textarea class="form-control" name="remarks" rows="2" placeholder="Optional details for management or purchasing..."></textarea>
                    </div>

                </div>
                <div class="modal-footer border-top-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand">Submit Requisition</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- JavaScript for Viewing Details and Adding Dynamic Rows -->
<script>
// Logic for "View Details" Modal
function viewRsDetails(rsNo, project, remarks, itemsJson) {
    document.getElementById('viewRsNo').innerText = rsNo;
    document.getElementById('viewRsProject').innerText = project;
    document.getElementById('viewRsRemarks').innerText = remarks ? remarks : 'No remarks provided.';
    
    const tbody = document.getElementById('viewRsItemsBody');
    tbody.innerHTML = ''; // Clear previous items
    
    try {
        const items = JSON.parse(itemsJson);
        if (items.length > 0) {
            items.forEach(item => {
                const itemName = item.item_name ? item.item_name : '<span class="text-danger">Item deleted from inventory</span>';
                const unit = item.unit ? item.unit : '';
                
                tbody.innerHTML += `
                    <tr>
                        <td class="text-muted">${item.item_code}</td>
                        <td class="fw-bold">${itemName}</td>
                        <td>${item.quantity} ${unit}</td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted">No items found.</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger">Error loading items.</td></tr>`;
    }
    
    new bootstrap.Modal(document.getElementById('viewRsModal')).show();
}

// Logic for Dynamic Rows (Create RS)
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('materialsContainer');
    const addBtn = document.getElementById('addMaterialBtn');

    if(addBtn && container) {
        addBtn.addEventListener('click', function() {
            const firstRow = container.querySelector('.material-row');
            const newRow = firstRow.cloneNode(true);
            newRow.querySelector('select').value = '';
            newRow.querySelector('input[type="number"]').value = '';
            newRow.querySelector('.remove-row').disabled = false;
            container.appendChild(newRow);
            updateDeleteButtons();
        });

        container.addEventListener('click', function(e) {
            if (e.target.closest('.remove-row')) {
                const rowToRemove = e.target.closest('.material-row');
                if (container.querySelectorAll('.material-row').length > 1) {
                    rowToRemove.remove();
                    updateDeleteButtons();
                }
            }
        });

        function updateDeleteButtons() {
            const rows = container.querySelectorAll('.material-row');
            if (rows.length === 1) {
                rows[0].querySelector('.remove-row').disabled = true;
            } else {
                rows.forEach(row => row.querySelector('.remove-row').disabled = false);
            }
        }
    }
});
</script>

<?php include 'layout/footer.php'; ?>