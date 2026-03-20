<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { 
    header("Location: index"); 
    exit; 
}
require_once 'Connection/db.php';

// Database Auto-Patch: Automatically create the table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_name VARCHAR(150) NOT NULL UNIQUE,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert default project if the table is completely empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM projects");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO projects (project_name, description, status) VALUES ('Main Headquarters Construction', 'General construction of the main building', 'active')");
    }
} catch (PDOException $e) {}

// Fetch Projects
$projects = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>
<!-- Inject Page specific CSS -->
<link rel="stylesheet" href="assets/css/projects.css">

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
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-briefcase me-2 text-primary"></i>Manage Projects</h4>
            <small class="text-muted">Add, edit, or remove construction projects and alter their active status.</small>
        </div>
        <div class="col-12 col-md-6 text-md-end">
            <button class="btn btn-brand w-100 w-md-auto fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#projectModal" onclick="openAddProjectModal()">
                <i class="bi bi-plus-lg me-2"></i>New Project
            </button>
        </div>
    </div>

    <!-- Project Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 text-nowrap" id="projectsTable">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Project Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($projects) > 0): ?>
                            <?php foreach ($projects as $proj): ?>
                                <tr>
                                    <td class="text-muted fw-bold" data-label="ID">#<?= $proj['id'] ?></td>
                                    <td class="fw-bold text-primary" data-label="Project Name"><?= htmlspecialchars($proj['project_name']) ?></td>
                                    <td class="text-muted text-wrap" style="max-width: 250px;" data-label="Description"><?= htmlspecialchars($proj['description'] ?? 'No description provided.') ?></td>
                                    <td data-label="Status">
                                        <?php if ($proj['status'] === 'active'): ?>
                                            <span class="badge bg-success rounded-pill px-3 py-2">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill px-3 py-2">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end" data-label="Actions">
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick="openEditProjectModal(<?= $proj['id'] ?>, '<?= addslashes(htmlspecialchars($proj['project_name'])) ?>', '<?= addslashes(htmlspecialchars($proj['description'] ?? '')) ?>', '<?= $proj['status'] ?>')">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Delete this project?');">
                                            <input type="hidden" name="action" value="delete_project">
                                            <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No projects found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Project Modal -->
<div class="modal fade" id="projectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="projectModalTitle">Add Project</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="projectFormAction" value="add_project">
                    <input type="hidden" name="project_id" id="projectId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Project Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg fw-bold" name="project_name" id="projectName" placeholder="e.g. Phase 1 Building" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea class="form-control" name="description" id="projectDesc" rows="3" placeholder="Additional details..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Status</label>
                        <select class="form-select" name="status" id="projectStatus" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4">Save Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Inject Page Specific JS -->
<script src="assets/js/projects.js"></script>

<?php include 'layout/footer.php'; ?>
