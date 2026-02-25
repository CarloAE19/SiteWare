<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
if (!in_array($_SESSION['user_role'], ['admin', 'purchasing'])) { header("Location: index"); exit; }
require_once 'Connection/db.php';

// FIX: Define the $role variable so your components folder can read it!
$role = $_SESSION['user_role'];

$stmt = $pdo->query("SELECT * FROM suppliers ORDER BY company_name ASC");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<div class="container-fluid px-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="table-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-buildings me-2"></i>Supplier Masterlist</h4>
            <!-- Trigger the modal -->
            <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="openAddSupplier()">
                <i class="bi bi-plus-lg me-1"></i> Add Supplier
            </button>
        </div>

        <table class="table table-hover align-middle">
            <thead><tr><th>Code</th><th>Company</th><th>Contact</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($suppliers as $sup): ?>
                    <tr>
                        <td class="text-muted"><?= htmlspecialchars($sup['supplier_code']) ?></td>
                        <td class="fw-bold text-primary"><?= htmlspecialchars($sup['company_name']) ?></td>
                        <td><?= htmlspecialchars($sup['contact_person'] ?: 'N/A') ?></td>
                        <td><span class="badge <?= $sup['status'] == 'Active' ? 'bg-success' : 'bg-danger' ?>"><?= $sup['status'] ?></span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="openEditSupplier(<?= $sup['id'] ?>, '<?= addslashes($sup['supplier_code']) ?>', '<?= addslashes($sup['company_name']) ?>', '<?= addslashes($sup['contact_person']) ?>', '<?= addslashes($sup['contact_number']) ?>', '<?= addslashes($sup['email']) ?>', '<?= addslashes($sup['address']) ?>', '<?= $sup['status'] ?>')"><i class="bi bi-pencil-square"></i></button>
                            <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Delete supplier?');">
                                <input type="hidden" name="action" value="delete_supplier"><input type="hidden" name="id" value="<?= $sup['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Include the Modular Component -->
<?php include 'components/supplier_modal.php'; ?>

<?php include 'layout/footer.php'; ?>