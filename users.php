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
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show" role="alert"><?= $_SESSION['message'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="table-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-person-gear me-2"></i>Manage Users</h4>
            <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddUserModal()"><i class="bi bi-plus-lg me-1"></i> Add New User</button>
        </div>

        <table class="table table-hover align-middle">
            <thead><tr><th>ID</th><th>Full Name</th><th>Username</th><th>Role</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="text-muted">#<?= str_pad($user['id'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><span class="badge <?= $roleDisplay[$user['role']]['class'] ?? 'bg-secondary' ?>"><?= mb_strtoupper($roleDisplay[$user['role']]['label'] ?? 'UNKNOWN') ?></span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="openEditUserModal(<?= $user['id'] ?>, '<?= addslashes($user['name']) ?>', '<?= addslashes($user['username']) ?>', '<?= $user['role'] ?>')"><i class="bi bi-pencil-square"></i></button>
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Delete user?');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
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

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: var(--gb-yellow);"><h5 class="modal-title" id="userModalTitle">Add User</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" id="userFormAction" value="add_user"><input type="hidden" name="user_id" id="userId" value="">
                    <div class="mb-3"><label class="form-label fw-bold">Full Name</label><input type="text" class="form-control" name="name" id="userName" required></div>
                    <div class="mb-3"><label class="form-label fw-bold">Username</label><input type="text" class="form-control" name="username" id="userUsername" required></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label fw-bold">Role</label><select class="form-select" name="role" id="userRole"><option value="requestor">Requestor</option><option value="purchasing">Purchasing Officer</option><option value="management">Management / Approver</option><option value="warehouse">Warehouse In-Charge</option><option value="admin">Admin</option></select></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-bold">Password</label><input type="password" class="form-control" name="password" id="userPassword"><small id="passwordHelp" class="text-muted" style="font-size: 0.75rem;"></small></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-brand" id="userSubmitBtn">Save User</button></div>
            </form>
        </div>
    </div>
</div>
<?php include 'layout/footer.php'; ?>