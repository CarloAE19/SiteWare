<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
if ($_SESSION['user_role'] !== 'admin') { header("Location: index"); exit; }
require_once 'Connection/db.php';

// AUTO-SETUP: Create table and insert defaults if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_name VARCHAR(50) NOT NULL,
    abbreviation VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
if ($pdo->query("SELECT COUNT(*) FROM units")->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO units (unit_name, abbreviation) VALUES 
        ('Pieces', 'pcs'), ('Bags', 'bags'), ('Units', 'units'), 
        ('Kilograms', 'kg'), ('Liters', 'L'), ('Meters', 'm')");
}

$stmt = $pdo->query("SELECT * FROM units ORDER BY unit_name ASC");
$unitsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4"> <!-- Reduced padding on mobile -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="table-container shadow-sm border-0">
        
        <!-- FIXED: Bulletproof Bootstrap Grid Header -->
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-md-8">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-rulers me-2 text-primary"></i>Manage Unit Metrics</h4>
                <small class="text-muted">Customize the measurement units used in inventory.</small>
            </div>
            <div class="col-12 col-md-4 text-md-end">
                <!-- Button becomes 100% width on mobile, auto-width on PC -->
                <button class="btn btn-brand shadow-sm w-100" data-bs-toggle="modal" data-bs-target="#unitModal" onclick="openAddUnitModal()">
                    <i class="bi bi-plus-circle me-1"></i> Add New Unit
                </button>
            </div>
        </div>

        <div class="table-responsive border rounded">
            <!-- FIXED: Added text-nowrap so columns don't squish words -->
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Unit Name</th>
                        <th>Abbreviation</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unitsList as $u): ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($u['unit_name']) ?></td>
                            <td><span class="badge bg-secondary px-2 py-1"><?= htmlspecialchars($u['abbreviation']) ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" onclick="openEditUnitModal(<?= $u['id'] ?>, '<?= addslashes($u['unit_name']) ?>', '<?= addslashes($u['abbreviation']) ?>')">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this unit?');">
                                    <input type="hidden" name="action" value="delete_unit">
                                    <input type="hidden" name="unit_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'components/unit_modal.php'; ?>
<?php include 'layout/footer.php'; ?>