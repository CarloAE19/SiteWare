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

<div class="container-fluid px-3 px-md-4 py-4"> <!-- FIXED: Mobile Padding -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
        
        <!-- FIXED: Bulletproof Mobile Header Grid -->
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-xl-6">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-tools me-2 text-primary"></i>Material Withdrawals</h4>
                <small class="text-muted">Items logged here are permanently deducted from inventory.</small>
            </div>
            
            <div class="col-12 col-xl-6">
                <div class="d-flex flex-column flex-md-row justify-content-xl-end gap-2">
                    <div class="input-group w-100 mb-2 mb-md-0" style="max-width: 400px;">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchWithdrawals" class="form-control border-start-0 ps-0 bg-light" placeholder="Search by project or slip no...">
                    </div>
                    
                    <?php if (in_array($role, ['warehouse', 'admin'])): ?>
                    <!-- PHASE 2 BUTTONS: Scan RS & Manual Entry -->
                    <div class="d-flex gap-2 w-100 w-md-auto">
                        <button class="btn btn-outline-brand border-2 fw-bold flex-fill text-nowrap shadow-sm" onclick="startRsScanner()">
                            <i class="bi bi-upc-scan me-1"></i> Scan RS
                        </button>
                        <button class="btn btn-brand flex-fill text-nowrap shadow-sm" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                            <i class="bi bi-pencil-square me-1"></i> Manual Entry
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="table-responsive border rounded shadow-sm">
            <!-- FIXED: text-nowrap and IDs for pagination/search -->
            <table class="table table-hover align-middle mb-0 text-nowrap" id="withdrawalsTable">
                <thead class="table-dark">
                    <tr>
                        <th>Slip No.</th>
                        <th>Project Assigned</th>
                        <th>Released By</th>
                        <th>Date & Time</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($withdrawals) > 0): ?>
                        <?php foreach ($withdrawals as $wd): ?>
                            <tr class="withdrawal-row">
                                <td class="fw-bold text-danger wd-slip"><?= htmlspecialchars($wd['withdrawal_no']) ?></td>
                                <td class="fw-bold wd-project"><?= htmlspecialchars($wd['project_name']) ?></td>
                                <td><i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($wd['releaser_name']) ?></td>
                                <td class="text-muted small"><?= date('M d, Y h:i A', strtotime($wd['date_withdrawn'])) ?></td>
                                <td class="text-end">
                                    <?php $currentItemsJson = htmlspecialchars(json_encode($wdItemsGrouped[$wd['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>
                                    <button class="btn btn-sm btn-outline-secondary fw-bold shadow-sm" title="View Details" onclick="viewWdDetails('<?= $wd['withdrawal_no'] ?>', '<?= addslashes($wd['project_name']) ?>', '<?= addslashes($wd['remarks']) ?>', '<?= $currentItemsJson ?>')">
                                        <i class="bi bi-qr-code-scan me-1"></i> View Trail
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="emptyRow"><td colspan="5" class="text-center py-5 text-muted">No material withdrawals recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CAMERA SCANNER FOR APPROVED RS                    -->
<!-- ======================================================== -->
<div class="modal fade" id="rsScannerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-brand text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-upc-scan me-2"></i>Scan Approved RS</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopRsScanner()"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="rsReader" class="mx-auto" style="width: 100%; border-radius: 8px; overflow: hidden; border: 2px solid var(--gb-blue);"></div>
                <div id="rsScannerResult" class="mt-3 fw-bold text-muted">Point your camera at the Approved RS Document QR Code...</div>
                <button type="button" class="btn btn-link text-muted mt-3" data-bs-dismiss="modal" onclick="stopRsScanner()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Withdraw Details Modal -->
<div class="modal fade" id="viewWdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-list-check me-2" style="color: var(--gb-yellow);"></i>Withdrawal Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3">
                    <div>
                        <h4 class="fw-bold text-danger mb-0" id="viewWdNo">WD-0000</h4>
                        <div class="text-muted fw-bold text-uppercase small" id="viewWdProject">Project Name</div>
                    </div>
                    <div>
                        <img id="viewWdQrCode" src="" alt="Document QR Code" class="border p-1 bg-white shadow-sm" style="width: 80px; height: 80px; border-radius: 6px;">
                    </div>
                </div>
                
                <div class="table-responsive mb-4 rounded border shadow-sm">
                    <table class="table table-sm table-hover mb-0 bg-white text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th class="text-end">Qty Released</th>
                            </tr>
                        </thead>
                        <tbody id="viewWdItemsBody"></tbody>
                    </table>
                </div>
                
                <div>
                    <h6 class="fw-bold mb-2 text-dark small text-uppercase">Remarks / Notes:</h6>
                    <p class="text-muted small border p-3 bg-white rounded shadow-sm mb-0" id="viewWdRemarks">No remarks.</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between bg-white border-top-0">
                <button type="button" class="btn btn-outline-primary fw-bold px-4" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print Slip</button>
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Load Modals and Separate Javascript Engine -->
<?php include 'components/withdrawal_modal.php'; ?>
<script src="assets/js/withdrawals.js"></script>

<?php include 'layout/footer.php'; ?>