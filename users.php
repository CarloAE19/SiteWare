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

<!-- Mobile Card Table CSS for Users -->
<style>
    @media (max-width: 767.98px) {
        .table-responsive { overflow-x: hidden !important; border: none !important; box-shadow: none !important; background: transparent !important; }
        #usersTable { display: block; width: 100%; background: transparent !important; }
        #usersTable thead { display: none; }
        #usersTable tbody { display: block; width: 100%; }
        
        #usersTable tbody tr { 
            display: flex; flex-direction: column; border: 1px solid #e0e4e8; border-radius: 12px; 
            margin-bottom: 1rem; background: #fff; padding: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); 
        }
        
        #usersTable tbody td { 
            display: flex; justify-content: space-between; align-items: center; text-align: right; 
            padding: 10px 4px; border: none; border-bottom: 1px dashed #e9ecef; white-space: normal !important; word-break: break-word; 
        }
        
        /* Center the Actions button at the bottom of the card */
        #usersTable tbody td:last-child { 
            border-bottom: none; justify-content: center !important; gap: 10px; padding-top: 16px; margin-top: 4px; 
        }
        
        #usersTable tbody td::before { 
            content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #6c757d; 
            text-transform: uppercase; text-align: left; padding-right: 15px; flex-shrink: 0; 
        }
        
        #usersTable tbody td:last-child::before { display: none; }
    }
</style>

<div class="container-fluid px-3 px-md-4 py-4"> 
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
        
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-md-8 text-center text-md-start">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-person-gear me-2 text-primary"></i>Manage Users</h4>
                <small class="text-muted">Add, edit, or remove system access.</small>
            </div>
            <div class="col-12 col-md-4 text-md-end">
                <button class="btn btn-brand shadow-sm w-100 w-md-auto fw-bold px-4" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddUserModal()">
                    <i class="bi bi-person-plus me-1"></i> Add New User
                </button>
            </div>
        </div>

        <div class="table-responsive border rounded shadow-sm">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="usersTable">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3">ID</th>
                        <th class="py-3">Full Name</th>
                        <th class="py-3">Username</th>
                        <th class="py-3">Role</th>
                        <th class="text-center py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="text-muted fw-bold" data-label="ID">#<?= str_pad($user['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            
                            <!-- THE FIX: Wrapped the icon and name in a <span> -->
                            <td class="fw-bold text-dark" data-label="Full Name">
                                <span><i class="bi bi-person-circle me-2 text-muted"></i><?= htmlspecialchars($user['name']) ?></span>
                            </td>
                            
                            <td class="text-primary fw-bold" data-label="Username">@<?= htmlspecialchars($user['username']) ?></td>
                            <td data-label="Role"><span class="badge <?= $roleDisplay[$user['role']]['class'] ?? 'bg-secondary' ?> px-3 py-2 shadow-sm"><?= mb_strtoupper($roleDisplay[$user['role']]['label'] ?? 'UNKNOWN') ?></span></td>
                            <td class="text-center" data-label="Actions">
                                
                                <button type="button" class="btn btn-sm btn-outline-primary shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openEditUserModal(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>', '<?= htmlspecialchars(addslashes($user['username'])) ?>', '<?= htmlspecialchars($user['role']) ?>')">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this user?');">
                                    <input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm"><i class="bi bi-trash3"></i></button>
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

<!-- EXTERNAL MODAL -->
<?php include 'components/user_modal.php'; ?>

<?php include 'layout/footer.php'; ?>