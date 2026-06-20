<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
require_once 'Connection/db.php';

$userId = $_SESSION['user_id'];

// Fetch current user's data
$stmt = $pdo->prepare("SELECT name, username, role, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Role Aesthetics
$roleDisplay = [
    'admin' => ['label' => 'System Admin', 'class' => 'bg-danger'],
    'warehouse' => ['label' => 'Warehouse In-Charge', 'class' => 'bg-success'],
    'management' => ['label' => 'Management / Approver', 'class' => 'bg-warning text-dark'],
    'purchasing' => ['label' => 'Purchasing Officer', 'class' => 'bg-info text-dark'],
    'requestor' => ['label' => 'Requestor', 'class' => 'bg-secondary']
];

$roleClass = $roleDisplay[$user['role']]['class'] ?? 'bg-secondary';
$roleLabel = $roleDisplay[$user['role']]['label'] ?? 'Unknown Role';

include 'layout/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4"> <!-- FIXED: Reduced padding on mobile -->
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h4>
    </div>

    <!-- FIXED: Added g-4 for consistent gap spacing when stacked on mobile -->
    <div class="row g-4">
        <!-- LEFT COLUMN: Profile Overview -->
        <!-- FIXED: Added col-12 so it takes full width on phones -->
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-5">
                    <div class="mb-3">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light text-primary rounded-circle shadow-sm" style="width: 120px; height: 120px; font-size: 3rem;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                    <p class="text-muted mb-3">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="badge <?= $roleClass ?> px-3 py-2" style="font-size: 0.85rem;">
                        <?= mb_strtoupper($roleLabel) ?>
                    </span>
                    
                    <hr class="my-4 mx-3">
                    
                    <div class="text-start px-3">
                        <small class="text-muted fw-bold text-uppercase d-block mb-1">Account Details</small>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Role Access:</span>
                            <span class="fw-bold"><?= ucwords($user['role']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Member Since:</span>
                            <span class="fw-bold"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Edit Settings -->
        <!-- FIXED: Added col-12 so it takes full width on phones -->
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-gear-fill text-primary me-2"></i>Account Settings
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="process/process.php">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Basic Information</h6>
                        <div class="row mb-4">
                            <!-- FIXED: Added col-12 for mobile stacking -->
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold">Username</label>
                                <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3 border-bottom pb-2">Change Password</h6>
                        <div class="alert alert-light border text-muted small mb-3">
                            <i class="bi bi-info-circle me-1"></i> Leave the password fields blank if you do not want to change your current password.
                        </div>
                        
                        <div class="row mb-4">
                            <!-- FIXED: Added col-12 for mobile stacking -->
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control border-end-0" name="new_password" id="newPass">
                                    <button class="btn border border-start-0 bg-white" type="button" onclick="togglePass('newPass', 'eyeNew')">
                                        <i class="bi bi-eye-slash text-muted" id="eyeNew"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control border-end-0" name="confirm_password" id="confPass">
                                    <button class="btn border border-start-0 bg-white" type="button" onclick="togglePass('confPass', 'eyeConf')">
                                        <i class="bi bi-eye-slash text-muted" id="eyeConf"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4 mt-md-0">
                            <!-- FIXED: Button is 100% width on phone, auto-width on PC -->
                            <button type="submit" class="btn btn-brand px-4 w-100 w-md-auto fw-bold py-2 py-md-1">
                                <i class="bi bi-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (in_array($_SESSION['user_role'], ['admin', 'management'])): ?>
                <?php
                // Fetch current login background setting
                $bg_path = 'assets/img/default_login_bg.png';
                if (isset($pdo)) {
                    try {
                        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'login_background'");
                        $stmt->execute();
                        $db_bg = $stmt->fetchColumn();
                        if ($db_bg) $bg_path = $db_bg;
                    } catch (Exception $e) {}
                }
                $bg_version = file_exists($bg_path) ? filemtime($bg_path) : time();
                // Build a root-relative URL to the background image
                $profile_app_base = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
                $bg_preview_url = $profile_app_base . '/' . ltrim($bg_path, '/') . '?v=' . $bg_version;
                ?>
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white fw-bold py-3">
                        <i class="bi bi-image text-primary me-2"></i>Login Page Customization
                    </div>
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Custom Login Background</h6>
                        
                        <div class="row align-items-center">
                            <div class="col-12 col-md-5 mb-3 mb-md-0">
                                <label class="d-block fw-bold mb-2">Current Background Preview</label>
                                <div class="position-relative border rounded overflow-hidden bg-light" style="height: 140px;">
                                    <img src="<?= htmlspecialchars($bg_preview_url) ?>" alt="Current Login Background" class="w-100 h-100" style="object-fit: cover;">
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-7">
                                <form method="POST" action="process/process.php" enctype="multipart/form-data" class="mb-3">
                                    <input type="hidden" name="action" value="update_login_bg">
                                    <label class="form-label fw-bold">Upload New Background Image</label>
                                    <div class="input-group mb-2">
                                        <input type="file" class="form-control" name="login_bg" accept="image/*" required>
                                        <button class="btn btn-brand fw-bold px-3" type="submit">
                                            <i class="bi bi-upload me-1"></i> Upload
                                        </button>
                                    </div>
                                    <small class="text-muted d-block"><i class="bi bi-info-circle me-1"></i> Supported formats: JPG, PNG, WEBP, GIF. Max file size: 5MB.</small>
                                </form>
                                
                                <?php if ($bg_path !== 'assets/img/default_login_bg.png'): ?>
                                    <form method="POST" action="process/process.php">
                                        <input type="hidden" name="action" value="reset_login_bg">
                                        <button class="btn btn-outline-danger btn-sm fw-bold w-100" type="submit" onclick="return confirm('Are you sure you want to reset the background image to default?');">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset to Default Image
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
// Simple Password Toggle for Profile Page
window.togglePass = function(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("bi-eye-slash", "bi-eye");
    } else {
        input.type = "password";
        icon.classList.replace("bi-eye", "bi-eye-slash");
    }
}
</script>

<?php include 'layout/footer.php'; ?>