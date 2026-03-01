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
// Fetch Dynamic Units for the Add/Edit Modal
$dynamicUnits = $pdo->query("SELECT unit_name FROM units ORDER BY unit_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<!-- Import Scanner & Pass Data to JS -->
<script src="https://unpkg.com/html5-qrcode"></script>
<script> const inventoryData = <?= json_encode($items) ?>; </script>

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
                            $qty = (int)$item['quantity'];
                            if ($qty <= 0) { $statusText = 'Out of Stock'; $statusClass = 'bg-danger'; } 
                            elseif ($qty <= 10) { $statusText = 'Low Stock'; $statusClass = 'bg-warning text-dark'; } 
                            else { $statusText = 'In Stock'; $statusClass = 'bg-success'; }
                        ?>
                        <tr>
                            <td class="fw-bold text-muted"><?= htmlspecialchars($item['item_code']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($item['category']) ?></span></td>
                            
                            <!-- FIXED: Added IDs so AJAX can update these instantly! -->
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
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EXTERNAL MODALS -->
<?php include 'components/inventory_modals.php'; ?>

<?php include 'layout/footer.php'; ?>