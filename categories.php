<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { 
    header("Location: index"); 
    exit; 
}
require_once 'Connection/db.php';

// Database Auto-Patch: Automatically create the table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert default categories if the table is completely empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO categories (category_name) VALUES ('Materials'), ('Tools'), ('Safety Equipment'), ('Heavy Machinery')");
    }
} catch (PDOException $e) {}

// Fetch Categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <!-- Mobile-Responsive Header -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-12 col-md-6">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-tags me-2 text-primary"></i>Manage Categories</h4>
            <small class="text-muted">Add, edit, or remove inventory classifications.</small>
        </div>
        <div class="col-12 col-md-6 text-md-end">
            <button class="btn btn-brand w-100 w-md-auto fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openAddCategoryModal()">
                <i class="bi bi-plus-lg me-2"></i>Add Category
            </button>
        </div>
    </div>

    <!-- Category Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 text-nowrap" id="categoriesTable">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Category Name</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($categories) > 0): ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="text-muted fw-bold">#<?= $cat['id'] ?></td>
                                    <td class="fw-bold text-primary fs-5"><?= htmlspecialchars($cat['category_name']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="openEditCategoryModal(<?= $cat['id'] ?>, '<?= addslashes($cat['category_name']) ?>')">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Delete this category?');">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">No categories found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="categoryModalTitle">Add Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="categoryFormAction" value="add_category">
                    <input type="hidden" name="category_id" id="categoryId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg fw-bold" name="category_name" id="categoryName" placeholder="e.g. Electrical Supplies" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SPA Pagination Script & Modal Handlers -->
<script>
function initCategoryPagination() {
    function setupPagination(tableId, rowsPerPage) {
        const table = document.getElementById(tableId);
        if (!table || table.parentElement.querySelector('.pagination-wrapper')) return;
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
        prevBtn.type = 'button'; prevBtn.innerHTML = '<i class="bi bi-chevron-left me-1"></i> Prev';
        const pageIndicator = document.createElement('button');
        pageIndicator.className = 'btn btn-sm btn-brand fw-bold px-3 pe-none'; pageIndicator.type = 'button';
        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        nextBtn.type = 'button'; nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right ms-1"></i>';
        btnGroup.appendChild(prevBtn); btnGroup.appendChild(pageIndicator); btnGroup.appendChild(nextBtn);
        paginationWrapper.appendChild(infoText); paginationWrapper.appendChild(btnGroup);
        table.parentElement.appendChild(paginationWrapper);
        function showPage(page) {
            currentPage = page;
            const start = (page - 1) * rowsPerPage; const end = start + rowsPerPage;
            rows.forEach((row, index) => { row.style.display = (index >= start && index < end) ? '' : 'none'; });
            infoText.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, rows.length)}</b>`;
            pageIndicator.innerText = `Page ${page} / ${totalPages}`;
            prevBtn.disabled = page === 1; nextBtn.disabled = page === totalPages;
        }
        prevBtn.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
        nextBtn.addEventListener('click', () => { if (currentPage < totalPages) showPage(currentPage + 1); });
        showPage(1);
    }
    setupPagination('categoriesTable', 10);
}

if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", initCategoryPagination); } else { initCategoryPagination(); }

window.openAddCategoryModal = function() {
    document.getElementById('categoryModalTitle').innerText = 'Add New Category';
    document.getElementById('categoryFormAction').value = 'add_category';
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryName').value = '';
};

window.openEditCategoryModal = function(id, name) {
    document.getElementById('categoryModalTitle').innerText = 'Edit Category';
    document.getElementById('categoryFormAction').value = 'edit_category';
    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = name;
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
};
</script>

<?php include 'layout/footer.php'; ?>