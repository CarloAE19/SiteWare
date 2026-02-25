<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// Fetch all POs for the table
$query = "SELECT po.*, r.rs_no, r.project_name, s.company_name, u.name as preparer_name 
          FROM purchase_orders po
          LEFT JOIN requisitions r ON po.rs_id = r.id
          LEFT JOIN suppliers s ON po.supplier_id = s.id
          LEFT JOIN users u ON po.prepared_by = u.id
          ORDER BY po.created_at DESC";
$pos = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch Approved RS (that don't have a PO yet) for the dropdown
$approvedRS = $pdo->query("SELECT id, rs_no, project_name FROM requisitions WHERE status = 'Approved'")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Suppliers for the dropdown
$suppliers = $pdo->query("SELECT id, company_name FROM suppliers WHERE status = 'Active' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats
$totalPOs = count($pos);
$pendingDelivery = count(array_filter($pos, fn($p) => $p['status'] === 'Pending Delivery'));

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

    <!-- PO Stats -->
    <div class="row mb-4">
        <div class="col-xl-6 col-md-6 mb-3">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: var(--gb-blue);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1">Total Purchase Orders</h6>
                        <h2 class="mb-0 fw-bold"><?= $totalPOs ?></h2>
                    </div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue) !important;"><i class="bi bi-file-earmark-text"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-md-6 mb-3">
            <div class="card stat-card bg-white h-100 p-3" style="border-left-color: var(--gb-yellow);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1">Awaiting Delivery</h6>
                        <h2 class="mb-0 fw-bold"><?= $pendingDelivery ?></h2>
                    </div>
                    <div class="fs-1 text-warning"><i class="bi bi-truck"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Area -->
    <div class="table-container">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-cart-check me-2"></i>Purchase Orders (PO)</h4>
            
            <div class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" placeholder="Search PO No...">
                </div>
                
                <?php if (in_array($role, ['purchasing', 'admin'])): ?>
                <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#poModal">
                    <i class="bi bi-plus-lg me-1"></i> Generate PO
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col">PO Number</th>
                        <th scope="col">Linked RS</th>
                        <th scope="col">Supplier</th>
                        <th scope="col">Prepared By</th>
                        <th scope="col">Date Issued</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($pos) > 0): ?>
                        <?php foreach ($pos as $po): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($po['po_no']) ?></td>
                                <td><span class="badge bg-light text-dark border"><i class="bi bi-link-45deg"></i> <?= htmlspecialchars($po['rs_no']) ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($po['company_name']) ?></td>
                                <td><i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($po['preparer_name']) ?></td>
                                <td class="text-muted small"><?= date('M d, Y', strtotime($po['created_at'])) ?></td>
                                <td>
                                    <?php 
                                        $statusClass = 'bg-warning text-dark'; // Pending Delivery
                                        if($po['status'] == 'Partial Delivery') $statusClass = 'bg-info text-dark';
                                        if($po['status'] == 'Completed') $statusClass = 'bg-success';
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><i class="bi bi-truck me-1"></i><?= htmlspecialchars($po['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" title="Print PO">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-folder-x fs-1 d-block mb-2"></i>
                                No Purchase Orders found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Include the modularized PO Form Component -->
<?php include 'components/po_modal.php'; ?>

<?php include 'layout/footer.php'; ?>