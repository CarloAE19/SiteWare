<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// Fetch all Withdrawals
$query = "SELECT w.*, u.name as releaser_name 
          FROM withdrawals w
          LEFT JOIN users u ON w.released_by = u.id
          ORDER BY w.date_withdrawn DESC";
$withdrawals = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

$itemsQuery = $pdo->query("SELECT wi.withdrawal_id, wi.quantity, wi.item_code, i.item_name, i.unit 
                           FROM withdrawal_items wi 
                           LEFT JOIN inventory i ON wi.item_code = i.item_code");
$allItems = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
$wdItemsGrouped = [];
foreach($allItems as $item) {
    $wdItemsGrouped[$item['withdrawal_id']][] = $item;
}

// Fetch Inventory items for the dropdown
$inventoryItems = $pdo->query("SELECT item_code, item_name, quantity, unit FROM inventory WHERE status != 'Out of Stock' AND quantity > 0 ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<!-- IMPORT CAMERA SCANNER LIBRARY -->
<script src="https://unpkg.com/html5-qrcode"></script>

<div class="container-fluid px-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="table-container">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-tools me-2"></i>Material Withdrawals</h4>
                <small class="text-muted">Items logged here are permanently deducted from inventory.</small>
            </div>
            
            <div class="d-flex gap-2">
                <div class="input-group"><span class="input-group-text bg-light"><i class="bi bi-search"></i></span><input type="text" class="form-control" placeholder="Search by project..."></div>
                <?php if (in_array($role, ['warehouse', 'admin'])): ?>
                <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#withdrawModal"><i class="bi bi-box-arrow-up-right me-1"></i> Release Items</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Slip No.</th><th>Project Assigned</th><th>Released By</th><th>Date & Time</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php if(count($withdrawals) > 0): ?>
                        <?php foreach ($withdrawals as $wd): ?>
                            <tr>
                                <td class="fw-bold text-danger"><?= htmlspecialchars($wd['withdrawal_no']) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($wd['project_name']) ?></td>
                                <td><i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($wd['releaser_name']) ?></td>
                                <td class="text-muted small"><?= date('M d, Y h:i A', strtotime($wd['date_withdrawn'])) ?></td>
                                <td class="text-end">
                                    <?php $currentItemsJson = htmlspecialchars(json_encode($wdItemsGrouped[$wd['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>
                                    <button class="btn btn-sm btn-outline-secondary" title="View Details" onclick="viewWdDetails('<?= $wd['withdrawal_no'] ?>', '<?= addslashes($wd['project_name']) ?>', '<?= addslashes($wd['remarks']) ?>', '<?= $currentItemsJson ?>')"><i class="bi bi-qr-code-scan"></i> View</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No material withdrawals recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Withdraw Details Modal -->
<div class="modal fade" id="viewWdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;"><h5 class="modal-title"><i class="bi bi-list-check me-2" style="color: var(--gb-yellow);"></i>Withdrawal Details</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body bg-light">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div><h5 class="fw-bold text-danger mb-0" id="viewWdNo">WD-0000</h5><div class="text-muted fw-bold" id="viewWdProject">Project Name</div></div>
                    <div><img id="viewWdQrCode" src="" alt="Document QR Code" class="border p-1 bg-white shadow-sm" style="width: 80px; height: 80px; border-radius: 6px;"></div>
                </div>
                <div class="table-responsive mb-3"><table class="table table-sm table-bordered bg-white"><thead class="table-light"><tr><th>Item Code</th><th>Item Name</th><th>Qty Released</th></tr></thead><tbody id="viewWdItemsBody"></tbody></table></div>
                <div class="mb-2"><h6 class="fw-bold mb-1">Remarks / Picked up by:</h6><p class="text-muted small border p-2 bg-white rounded" id="viewWdRemarks">No remarks.</p></div>
            </div>
            <div class="modal-footer d-flex justify-content-between"><button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print Document</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script>
function viewWdDetails(wdNo, project, remarks, itemsJson) {
    document.getElementById('viewWdNo').innerText = wdNo;
    document.getElementById('viewWdProject').innerText = project;
    document.getElementById('viewWdRemarks').innerText = remarks ? remarks : 'No remarks.';
    const qrData = encodeURIComponent(`Slip: ${wdNo} | Proj: ${project}`);
    document.getElementById('viewWdQrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${qrData}`;
    const tbody = document.getElementById('viewWdItemsBody');
    tbody.innerHTML = ''; 
    try {
        const items = JSON.parse(itemsJson);
        items.forEach(item => { tbody.innerHTML += `<tr><td class="text-muted">${item.item_code}</td><td class="fw-bold">${item.item_name}</td><td class="text-danger fw-bold">-${item.quantity} ${item.unit}</td></tr>`; });
    } catch (e) { tbody.innerHTML = `<tr><td colspan="3">Error loading items.</td></tr>`; }
    new bootstrap.Modal(document.getElementById('viewWdModal')).show();
}
</script>

<?php include 'components/withdrawal_modal.php'; ?>
<?php include 'layout/footer.php'; ?>