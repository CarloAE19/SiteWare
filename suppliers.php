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

<!-- Mobile Card Table CSS for Suppliers -->
<style>
    @media (max-width: 767.98px) {
        .table-responsive { overflow-x: hidden !important; border: none !important; box-shadow: none !important; background: transparent !important; }
        #suppliersTable { display: block; width: 100%; background: transparent !important; }
        #suppliersTable thead { display: none; }
        #suppliersTable tbody { display: block; width: 100%; }
        
        #suppliersTable tbody tr { 
            display: flex; flex-direction: column; border: 1px solid #e0e4e8; border-radius: 12px; 
            margin-bottom: 1rem; background: #fff; padding: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); 
        }
        
        #suppliersTable tbody td { 
            display: flex; justify-content: space-between; align-items: center; text-align: right; 
            padding: 10px 4px; border: none; border-bottom: 1px dashed #e9ecef; white-space: normal !important; word-break: break-word; 
        }
        
        /* Center the Actions button at the bottom of the card */
        #suppliersTable tbody td:last-child { 
            border-bottom: none; justify-content: center !important; gap: 10px; padding-top: 16px; margin-top: 4px; 
        }
        
        #suppliersTable tbody td::before { 
            content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #6c757d; 
            text-transform: uppercase; text-align: left; padding-right: 15px; flex-shrink: 0; 
        }
        
        #suppliersTable tbody td:last-child::before { display: none; }
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
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-buildings me-2 text-primary"></i>Suppliers</h4>
            </div>
            
            <?php if (in_array($role, ['admin', 'purchasing'])): ?>
            <div class="col-12 col-md-4 text-md-end">
                <button class="btn btn-brand shadow-sm w-100 w-md-auto fw-bold px-4" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="openAddSupplierModal()">
                    <i class="bi bi-plus-lg me-1"></i> Add New Supplier
                </button>
            </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive border rounded shadow-sm">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="suppliersTable">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3">Supplier Code</th>
                        <th class="py-3">Company Name</th>
                        <th class="py-3">Contact Details</th>
                        <th class="py-3">Contact Number</th>
                        <th class="py-3">Status</th>
                        <?php if (in_array($role, ['admin', 'purchasing'])): ?><th class="text-center py-3">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $sup): ?>
                        <tr>
                            <td class="text-muted fw-bold" data-label="Supplier Code"><?= htmlspecialchars($sup['supplier_code']) ?></td>
                            
                            <td class="fw-bold text-dark" data-label="Company Name"><?= htmlspecialchars($sup['company_name']) ?></td>
                            
                            <!-- Wrapped in span to prevent icons from breaking apart on mobile flexbox -->
                            <td data-label="Contact Details">
                                <span class="d-block text-dark"><i class="bi bi-person text-muted me-1"></i><?= htmlspecialchars($sup['contact_person']) ?></span>
                                <span class="d-block small text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($sup['email']) ?></span>
                            </td>
                            
                            <td class="text-primary fw-bold" data-label="Contact Number"><i class="bi bi-telephone text-muted me-1 d-none d-md-inline"></i><?= htmlspecialchars($sup['contact_number']) ?></td>
                            
                            <td data-label="Status">
                                <?php if($sup['status'] === 'Active'): ?>
                                    <span class="badge bg-success px-3 py-2 shadow-sm">ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge bg-danger px-3 py-2 shadow-sm">INACTIVE</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                            <td class="text-center" data-label="Actions">
                                <button class="btn btn-sm btn-outline-primary shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="openEditSupplierModal(<?= $sup['id'] ?>, '<?= $sup['supplier_code'] ?>', '<?= addslashes($sup['company_name']) ?>', '<?= addslashes($sup['contact_person']) ?>', '<?= $sup['contact_number'] ?>', '<?= addslashes($sup['email']) ?>', '<?= addslashes($sup['address']) ?>', '<?= $sup['status'] ?>')">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                
                                <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Are you sure you want to completely delete this supplier?');">
                                    <input type="hidden" name="action" value="delete_supplier"><input type="hidden" name="id" value="<?= $sup['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm"><i class="bi bi-trash3"></i></button>
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

<!-- EXTERNAL MODAL -->
<?php include 'components/supplier_modal.php'; ?>

<?php include 'layout/footer.php'; ?>