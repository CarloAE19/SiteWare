<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// Fetch Inventory Data
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM inventory";
$params = [];
if ($search) {
    $query .= " WHERE item_name LIKE :search OR item_code LIKE :search OR category LIKE :search";
    $params[':search'] = "%$search%";
}
$query .= " ORDER BY last_updated DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Pending Approvals count for Management
$pendingRSCount = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending Approval'")->fetchColumn();

// Calculate Totals & Auto-Detect Low Stock (<= 10 items)
$totalItems = count($items);
$totalValue = array_reduce($items, fn($carry, $item) => $carry + ($item['quantity'] * $item['unit_price']), 0);
$lowStockCount = count(array_filter($items, fn($item) => $item['quantity'] <= 10));

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

    <!-- STAT CARDS -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: var(--gb-blue);">
                <div class="d-flex justify-content-between align-items-center">
                    <div><h6 class="text-muted text-uppercase mb-1">Total Inventory Value</h6><h2 class="mb-0 fw-bold">$<?= number_format($totalValue, 2) ?></h2></div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue) !important;"><i class="bi bi-currency-dollar"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: #dc3545;">
                <div class="d-flex justify-content-between align-items-center">
                    <div><h6 class="text-muted text-uppercase mb-1">Low Stock Alerts</h6><h2 class="mb-0 fw-bold"><?= $lowStockCount ?></h2></div>
                    <div class="fs-1 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-3">
            <?php if ($role === 'management' || $role === 'admin'): ?>
                <div class="card stat-card bg-white h-100 p-3" style="border-left-color: var(--gb-yellow); cursor: pointer;" onclick="window.location.href='requisitions'">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h6 class="text-muted text-uppercase mb-1">Action Required</h6><h2 class="mb-0 fw-bold"><?= $pendingRSCount ?> <span class="fs-6 text-muted fw-normal">Pending RS</span></h2></div>
                        <div class="fs-1 text-warning"><i class="bi bi-file-earmark-check"></i></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card stat-card bg-white h-100 p-3" style="border-left-color: #198754;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h6 class="text-muted text-uppercase mb-1">Total Unique Items</h6><h2 class="mb-0 fw-bold"><?= $totalItems ?></h2></div>
                        <div class="fs-1 text-success"><i class="bi bi-boxes"></i></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- INVENTORY TABLE -->
    <div class="table-container">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2"></i>Materials Inventory</h4>
            <div class="d-flex gap-2">
                <form method="GET" class="d-flex">
                    <div class="input-group"><span class="input-group-text bg-light"><i class="bi bi-search"></i></span><input type="text" name="search" class="form-control" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>"></div>
                </form>
                
                <?php if (in_array($role, ['admin', 'warehouse'])): ?>
                <!-- NEW: Scan to Receive Button -->
                <button class="btn btn-outline-success border-2 fw-bold" id="startReceiveScannerBtn">
                    <i class="bi bi-upc-scan me-1"></i> Scan Delivery
                </button>
                <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="openAddModal()">
                    <i class="bi bi-plus-lg me-1"></i> Add Item
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Qty & Unit</th>
                        <th>Status</th>
                        <?php if (in_array($role, ['admin', 'warehouse'])): ?><th class="text-end">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php 
                            // AUTO-STATUS LOGIC
                            $qty = (int)$item['quantity'];
                            if ($qty <= 0) {
                                $statusText = 'Out of Stock';
                                $statusClass = 'bg-danger';
                            } elseif ($qty <= 10) {
                                $statusText = 'Low Stock';
                                $statusClass = 'bg-warning text-dark';
                            } else {
                                $statusText = 'In Stock';
                                $statusClass = 'bg-success';
                            }
                        ?>
                        <tr>
                            <td class="fw-bold text-muted"><?= htmlspecialchars($item['item_code']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($item['category']) ?></span></td>
                            <td><span class="fw-bold fs-6 <?= $qty <= 0 ? 'text-danger' : '' ?>"><?= $qty ?></span> <span class="text-muted small"><?= htmlspecialchars($item['unit']) ?></span></td>
                            <!-- DYNAMIC BADGE RENDERED HERE -->
                            <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                            
                            <?php if (in_array($role, ['admin', 'warehouse'])): ?>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-dark me-1" title="Print QR Label" onclick="showItemQR('<?= $item['item_code'] ?>', '<?= addslashes($item['item_name']) ?>')">
                                    <i class="bi bi-qr-code"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="openEditModal(<?= $item['id'] ?>, '<?= $item['item_code'] ?>', '<?= addslashes($item['item_name']) ?>', '<?= addslashes($item['category']) ?>', <?= $qty ?>, '<?= addslashes($item['unit']) ?>', <?= $item['unit_price'] ?>, '<?= $statusText ?>')"><i class="bi bi-pencil-square"></i></button>
                                <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Delete item?');">
                                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: SCAN TO RECEIVE INVENTORY (STOCK IN)              -->
<!-- ======================================================== -->
<div class="modal fade" id="receiveScanModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-2 text-success"></i>Scan to Stock In</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopReceiveScanner()"></button>
            </div>
            <div class="modal-body bg-light">
                
                <!-- CAMERA CONTAINER -->
                <div id="receive-qr-reader" class="mx-auto border border-2 border-primary rounded" style="width: 100%; max-width: 400px; overflow: hidden;"></div>

                <!-- FORM CONTAINER (Hidden until successfully scanned) -->
                <div id="receiveFormContainer" style="display: none;" class="mt-3 text-center">
                    <div class="alert alert-success d-flex align-items-center mb-3">
                        <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                        <div class="text-start">
                            <small class="d-block text-dark fw-bold">Scanned Successfully!</small>
                            <span id="scannedItemDisplayName" class="fw-bold fs-5 text-dark"></span>
                        </div>
                    </div>
                    
                    <form action="process/process.php" method="POST">
                        <input type="hidden" name="action" value="stock_in_scanned">
                        <input type="hidden" name="item_code" id="scannedInputCode">
                        
                        <div class="form-group text-start mb-3">
                            <label class="fw-bold mb-1">Enter Quantity Arrived:</label>
                            <input type="number" name="added_qty" class="form-control form-control-lg text-center fw-bold text-success" required min="1" placeholder="0">
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 fw-bold py-2"><i class="bi bi-plus-circle me-2"></i>Add to Inventory</button>
                    </form>
                    <button type="button" class="btn btn-link text-muted mt-2" onclick="resetReceiveScanner()">Scan a different item</button>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: PRINT ITEM QR CODE (STOCKING IN)                  -->
<!-- ======================================================== -->
<div class="modal fade" id="itemQrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-qr-code me-2" style="color: var(--gb-yellow);"></i>Item QR Label</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center bg-light" id="qrPrintArea">
                <div class="bg-white p-3 border rounded shadow-sm d-inline-block">
                    <h6 class="fw-bold text-dark mb-2" id="qrItemName">Item Name</h6>
                    <img id="qrItemImg" src="" alt="Item QR" style="width: 150px; height: 150px;">
                    <div class="mt-2 text-muted fw-bold font-monospace" id="qrItemCode">ITM-0000</div>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-brand" onclick="printItemLabel()"><i class="bi bi-printer me-1"></i> Print Label</button>
            </div>
        </div>
    </div>
</div>

<!-- Existing Add/Edit Item Modal -->
<?php if (in_array($role, ['admin', 'warehouse'])): ?>
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title" id="modalTitle"><span style="color: var(--gb-yellow);">Add Item</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" id="formAction" value="add"><input type="hidden" name="id" id="itemId" value="">
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Item Code</label><input type="text" class="form-control" name="item_code" id="itemCode" required></div>
                        <div class="col-md-8 mb-3"><label class="form-label fw-bold">Item Name</label><input type="text" class="form-control" name="item_name" id="itemName" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label fw-bold">Category</label><select class="form-select" name="category" id="itemCategory" required><option>Materials</option><option>Tools</option><option>Equipment</option><option>Safety Gear</option></select></div>
                        <!-- Status is now hidden here since the system calculates it automatically based on QTY! -->
                        <div class="col-md-6 mb-3 d-none"><input type="hidden" name="status" id="itemStatus" value="In Stock"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Qty</label><input type="number" class="form-control" name="quantity" id="itemQuantity" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Unit</label><select class="form-select" name="unit" id="itemUnit"><option>Pieces</option><option>Bags</option><option>Units</option></select></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Price ($)</label><input type="number" step="0.01" class="form-control" name="unit_price" id="itemPrice" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-brand" id="submitBtn">Save Item</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// --- QR LABEL PRINTING ---
function showItemQR(itemCode, itemName) {
    document.getElementById('qrItemCode').innerText = itemCode;
    document.getElementById('qrItemName').innerText = itemName;
    document.getElementById('qrItemImg').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${itemCode}`;
    new bootstrap.Modal(document.getElementById('itemQrModal')).show();
}

function printItemLabel() {
    const printContent = document.getElementById('qrPrintArea').innerHTML;
    const originalContent = document.body.innerHTML;
    document.body.innerHTML = `<div style="display:flex; justify-content:center; align-items:center; height:100vh;">${printContent}</div>`;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload(); 
}

// --- SCAN TO RECEIVE LOGIC ---
const inventoryData = <?= json_encode($items) ?>; // Pass DB to JS
let receiveScanner;

const startBtn = document.getElementById('startReceiveScannerBtn');
if (startBtn) {
    startBtn.addEventListener('click', function() {
        new bootstrap.Modal(document.getElementById('receiveScanModal')).show();
        resetReceiveScanner();
    });
}

function resetReceiveScanner() {
    document.getElementById('receive-qr-reader').style.display = 'block';
    document.getElementById('receiveFormContainer').style.display = 'none';
    
    if (receiveScanner) receiveScanner.clear();
    
    receiveScanner = new Html5QrcodeScanner("receive-qr-reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);
    receiveScanner.render(onReceiveScanSuccess);
}

function stopReceiveScanner() {
    if (receiveScanner) receiveScanner.clear();
}

function onReceiveScanSuccess(decodedText, decodedResult) {
    // Play Beep Sound
    new Audio('https://www.soundjay.com/buttons/sounds/button-09a.mp3').play();

    // Look up the item name based on the scanned code
    let foundItem = inventoryData.find(item => item.item_code === decodedText);
    
    if(foundItem) {
        document.getElementById('scannedItemDisplayName').innerText = foundItem.item_name + " (" + foundItem.item_code + ")";
        document.getElementById('scannedInputCode').value = decodedText;
        
        // Hide Camera, Show Form
        receiveScanner.clear();
        document.getElementById('receive-qr-reader').style.display = 'none';
        document.getElementById('receiveFormContainer').style.display = 'block';
        
        // Auto-focus the quantity box so the user can just type the number!
        setTimeout(() => document.querySelector('input[name="added_qty"]').focus(), 500);
    } else {
        alert("Scanned item (" + decodedText + ") not found in inventory!");
    }
}
</script>

<?php include 'layout/footer.php'; ?>