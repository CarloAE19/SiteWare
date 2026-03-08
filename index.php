<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
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

$pendingRSCount = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending Approval'")->fetchColumn();
$totalItems = count($items);
$totalValue = array_reduce($items, fn($carry, $item) => $carry + ($item['quantity'] * $item['unit_price']), 0);
$lowStockCount = count(array_filter($items, fn($item) => $item['quantity'] <= 10));

// ==========================================
// FIXED: FETCH BOTH UNITS AND CATEGORIES
// ==========================================
$dynamicUnits = $pdo->query("SELECT unit_name FROM units ORDER BY unit_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$dynamicCategories = $pdo->query("SELECT category_name FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<script src="https://unpkg.com/html5-qrcode"></script>
<script> const inventoryData = <?= json_encode($items) ?>; </script>

<div class="container-fluid px-3 px-md-4 py-4"> <!-- Mobile Padding Fix -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="row mb-4 g-3">
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: var(--gb-blue);">
                <div class="d-flex justify-content-between align-items-center">
                    <div><h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">Total Inventory Value</h6><h3 class="mb-0 fw-bold">₱<?= number_format($totalValue, 2) ?></h3></div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue) !important;"><i class="bi bi-cash-stack"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: #dc3545;">
                <div class="d-flex justify-content-between align-items-center">
                    <div><h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">Low Stock Alerts</h6><h3 class="mb-0 fw-bold"><?= $lowStockCount ?></h3></div>
                    <div class="fs-1 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <?php if ($role === 'management' || $role === 'admin'): ?>
                <div class="card stat-card bg-white h-100 p-3" style="border-left-color: var(--gb-yellow); cursor: pointer;" onclick="window.location.href='requisitions'">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">Action Required</h6><h3 class="mb-0 fw-bold"><?= $pendingRSCount ?> <span class="fs-6 text-muted fw-normal">Pending RS</span></h3></div>
                        <div class="fs-1 text-warning"><i class="bi bi-file-earmark-check"></i></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card stat-card bg-white h-100 p-3" style="border-left-color: #198754;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><h6 class="text-muted text-uppercase mb-1" style="font-size:0.8rem;">Total Unique Items</h6><h3 class="mb-0 fw-bold"><?= $totalItems ?></h3></div>
                        <div class="fs-1 text-success"><i class="bi bi-boxes"></i></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- INVENTORY TABLE -->
    <div class="table-container bg-white rounded shadow-sm border-0 p-3 p-md-4">
        
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-xl-4">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i>Materials Inventory</h4>
            </div>
            
            <div class="col-12 col-xl-8">
                <div class="d-flex flex-column flex-md-row justify-content-xl-end gap-2">
                    <form method="GET" class="d-flex w-100 mb-2 mb-md-0" style="max-width: 400px;">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </form>
                    
                    <?php if (in_array($role, ['admin', 'warehouse'])): ?>
                    <div class="d-flex gap-2 w-100 w-md-auto">
                        <button class="btn btn-outline-success border-2 fw-bold flex-fill text-nowrap" onclick="startDeliveryScanner()">
                            <i class="bi bi-upc-scan me-1"></i> Scan Delivery
                        </button>
                        <button class="btn btn-brand flex-fill text-nowrap" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="openAddModal()">
                            <i class="bi bi-plus-lg me-1"></i> Add Item
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="inventoryTable">
                <thead class="table-light">
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
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $item): ?>
                            <?php 
                                $qty = (int)$item['quantity'];
                                if ($qty <= 0) { $statusText = 'Out of Stock'; $statusClass = 'bg-danger'; } 
                                elseif ($qty <= 10) { $statusText = 'Low Stock'; $statusClass = 'bg-warning text-dark'; } 
                                else { $statusText = 'In Stock'; $statusClass = 'bg-success'; }
                            ?>
                            <tr>
                                <td class="fw-bold text-muted"><?= htmlspecialchars($item['item_code']) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($item['category']) ?></span></td>
                                
                                <td>
                                    <span class="fw-bold fs-6 <?= $qty <= 0 ? 'text-danger' : '' ?>" id="qty_<?= htmlspecialchars($item['item_code']) ?>"><?= $qty ?></span> 
                                    <span class="text-muted small"><?= htmlspecialchars($item['unit']) ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $statusClass ?>" id="status_<?= htmlspecialchars($item['item_code']) ?>"><?= $statusText ?></span>
                                </td>
                                
                                <?php if (in_array($role, ['admin', 'warehouse'])): ?>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-dark me-1" title="Print QR Label" onclick="showItemQR('<?= $item['item_code'] ?>', '<?= addslashes($item['item_name']) ?>')"><i class="bi bi-qr-code"></i></button>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="openEditModal(<?= $item['id'] ?>, '<?= $item['item_code'] ?>', '<?= addslashes($item['item_name']) ?>', '<?= addslashes($item['category']) ?>', <?= $qty ?>, '<?= addslashes($item['unit']) ?>', <?= $item['unit_price'] ?>, '<?= $statusText ?>')"><i class="bi bi-pencil-square"></i></button>
                                    <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Delete item?');">
                                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No items found in inventory.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- PREMIUM SPA SCANNER MODAL -->
<div class="modal fade" id="deliveryScannerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-upc-scan me-2"></i>Scan Delivery QR</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopScanner()"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="reader" style="width: 100%; border-radius: 8px; overflow: hidden; border: 2px solid #198754;"></div>
                <div id="scannerResult" class="mt-3 text-muted">Point your camera at the item's QR Code...</div>
                
                <form id="stockInForm" class="d-none mt-3 text-start" method="POST">
                    <input type="hidden" name="action" value="stock_in_scanned">
                    <input type="hidden" name="item_code" id="scan_item_code">
                    
                    <div class="alert alert-success d-flex align-items-center mb-3">
                        <i class="bi bi-check-circle-fill fs-3 me-3"></i>
                        <div>
                            <strong id="scan_item_name" class="d-block fs-5 text-dark">Item Name</strong>
                            <small id="scan_item_category" class="text-muted">Category</small>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">Quantity Delivered</label>
                        <div class="input-group input-group-lg">
                            <input type="number" class="form-control fw-bold text-center text-success" name="added_qty" id="scan_added_qty" required min="1" placeholder="0">
                            <span class="input-group-text bg-light text-dark fw-bold" id="scan_item_unit">Unit</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100 btn-lg fw-bold shadow-sm" id="receiveSubmitBtn">
                        <i class="bi bi-box-arrow-in-down me-2"></i>Confirm Delivery
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SPA-SAFE SCRIPTS (Pagination ONLY) -->
<script>
function initInventoryPagination() {
    function setupPagination(tableId, rowsPerPage) {
        const table = document.getElementById(tableId);
        if (!table) return;
        if (table.parentElement.querySelector('.pagination-wrapper')) return;

        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
        if (rows.length <= rowsPerPage) return;

        let currentPage = 1;
        const totalPages = Math.ceil(rows.length / rowsPerPage);

        const paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'd-flex justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper';
        
        const infoText = document.createElement('span');
        infoText.className = 'text-muted small fw-bold';
        
        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group shadow-sm';

        const prevBtn = document.createElement('button');
        prevBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        prevBtn.type = 'button';
        prevBtn.innerHTML = '<i class="bi bi-chevron-left me-1"></i> Prev';

        const pageIndicator = document.createElement('button');
        pageIndicator.className = 'btn btn-sm btn-brand fw-bold px-3 pe-none';
        pageIndicator.type = 'button';
        
        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        nextBtn.type = 'button';
        nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right ms-1"></i>';

        btnGroup.appendChild(prevBtn);
        btnGroup.appendChild(pageIndicator);
        btnGroup.appendChild(nextBtn);
        
        paginationWrapper.appendChild(infoText);
        paginationWrapper.appendChild(btnGroup);
        table.parentElement.appendChild(paginationWrapper);

        function showPage(page) {
            currentPage = page;
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            rows.forEach((row, index) => {
                row.style.display = (index >= start && index < end) ? '' : 'none';
            });

            infoText.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, rows.length)}</b> of <b>${rows.length}</b> entries`;
            pageIndicator.innerText = `Page ${page} / ${totalPages}`;
            
            prevBtn.disabled = page === 1;
            nextBtn.disabled = page === totalPages;
        }

        prevBtn.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
        nextBtn.addEventListener('click', () => { if (currentPage < totalPages) showPage(currentPage + 1); });

        showPage(1); 
    }
    setupPagination('inventoryTable', 10);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initInventoryPagination);
} else {
    initInventoryPagination();
}
</script>

<?php include 'components/inventory_modals.php'; ?>
<?php include 'layout/footer.php'; ?>