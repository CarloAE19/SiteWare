<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
if ($_SESSION['user_role'] !== 'admin') { header("Location: index"); exit; }
require_once 'Connection/db.php';

$stmt = $pdo->query("SELECT id, name, username, role, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roleDisplay = [
    'admin' => ['label' => 'Admin', 'class' => 'bg-danger'],
    'warehouse' => ['label' => 'Warehouse In-Charge', 'class' => 'bg-success'],
    'management' => ['label' => 'Management / Approver', 'class' => 'bg-warning text-dark'],
    'purchasing' => ['label' => 'Purchasing Officer', 'class' => 'bg-info text-dark'],
    'requestor' => ['label' => 'Requestor', 'class' => 'bg-secondary']
];

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

    <div class="table-container shadow-sm border-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-person-gear me-2 text-primary"></i>Manage Users</h4>
            <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddUserModal()"><i class="bi bi-person-plus me-1"></i> Add New User</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>ID</th><th>Full Name</th><th>Username</th><th>Role</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?= str_pad($user['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($user['name']) ?></td>
                            <td class="text-primary">@<?= htmlspecialchars($user['username']) ?></td>
                            <td><span class="badge <?= $roleDisplay[$user['role']]['class'] ?? 'bg-secondary' ?> px-3 py-2"><?= mb_strtoupper($roleDisplay[$user['role']]['label'] ?? 'UNKNOWN') ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" onclick="openEditUserModal(<?= $user['id'] ?>, '<?= addslashes($user['name']) ?>', '<?= addslashes($user['username']) ?>', '<?= $user['role'] ?>')"><i class="bi bi-pencil-square"></i></button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this user?');">
                                    <input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EXTERNAL MODAL (Keeps this file clean!) -->
<?php include 'components/user_modal.php'; ?>

<?php include 'layout/footer.php'; ?>