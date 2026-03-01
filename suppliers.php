<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
if (!in_array($_SESSION['user_role'], ['admin', 'purchasing'])) { header("Location: index"); exit; }
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// Fetch all suppliers
$stmt = $pdo->query("SELECT * FROM suppliers ORDER BY company_name ASC");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<div class="container-fluid px-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="table-container shadow-sm border-0">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-buildings me-2 text-primary"></i>Suppliers Database</h4>
                <small class="text-muted">Manage vendor information and contact details.</small>
            </div>
            
            <?php if (in_array($role, ['admin', 'purchasing'])): ?>
            <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="openAddSupplierModal()">
                <i class="bi bi-plus-lg me-1"></i> Add New Supplier
            </button>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Supplier Code</th>
                        <th>Company Name</th>
                        <th>Contact Person</th>
                        <th>Contact Number</th>
                        <th>Status</th>
                        <?php if (in_array($role, ['admin', 'purchasing'])): ?><th class="text-end">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $sup): ?>
                        <tr>
                            <td class="text-muted fw-bold"><?= htmlspecialchars($sup['supplier_code']) ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($sup['company_name']) ?></td>
                            <td>
                                <i class="bi bi-person text-muted me-1"></i><?= htmlspecialchars($sup['contact_person']) ?><br>
                                <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($sup['email']) ?></small>
                            </td>
                            <td class="text-primary fw-bold"><?= htmlspecialchars($sup['contact_number']) ?></td>
                            <td>
                                <?php if($sup['status'] === 'Active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="openEditSupplierModal(<?= $sup['id'] ?>, '<?= $sup['supplier_code'] ?>', '<?= addslashes($sup['company_name']) ?>', '<?= addslashes($sup['contact_person']) ?>', '<?= $sup['contact_number'] ?>', '<?= addslashes($sup['email']) ?>', '<?= addslashes($sup['address']) ?>', '<?= $sup['status'] ?>')">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this supplier?');">
                                    <input type="hidden" name="action" value="delete_supplier"><input type="hidden" name="id" value="<?= $sup['id'] ?>">
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

<!-- EXTERNAL MODAL (Keeps this file clean!) -->
<?php include 'components/supplier_modal.php'; ?>

<!-- EXTERNAL JAVASCRIPT LOGIC -->
<script src="assets/js/suppliers.js"></script>

<?php include 'layout/footer.php'; ?>