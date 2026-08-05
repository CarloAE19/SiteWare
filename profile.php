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
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-5">
                    <div class="mb-3">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light text-primary rounded-circle shadow-sm"
                            style="width: 120px; height: 120px; font-size: 3rem;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                    <p class="text-muted mb-3">@<?= htmlspecialchars($user['username']) ?></p>

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
            <div class="card border-0 shadow-sm">
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
                                <input type="text" class="form-control" name="name"
                                    value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold">Username</label>
                                <input type="text" class="form-control" name="username"
                                    value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3 border-bottom pb-2">Security</h6>
                        <div class="mb-4">
                            <button type="button" class="btn btn-outline-primary fw-bold px-3" data-bs-toggle="modal" data-bs-target="#changePasswordModal" onclick="resetPasswordModal()">
                                <i class="bi bi-key-fill me-1"></i> Change Password
                            </button>
                        </div>

                        <div class="text-end mt-4">
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
                        if ($db_bg)
                            $bg_path = $db_bg;
                    } catch (Exception $e) {
                    }
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
                                <div class="position-relative border rounded overflow-hidden bg-light"
                                    style="height: 140px;">
                                    <img src="<?= htmlspecialchars($bg_preview_url) ?>" alt="Current Login Background"
                                        class="w-100 h-100" style="object-fit: cover;">
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
                                    <small class="text-muted d-block"><i class="bi bi-info-circle me-1"></i> Supported
                                        formats: JPG, PNG, WEBP, GIF. Max file size: 5MB.</small>
                                </form>

                                <?php if ($bg_path !== 'assets/img/default_login_bg.png'): ?>
                                    <form method="POST" action="process/process.php">
                                        <input type="hidden" name="action" value="reset_login_bg">
                                        <button class="btn btn-outline-danger btn-sm fw-bold w-100" type="submit"
                                            onclick="return confirm('Are you sure you want to reset the background image to default?');">
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

<!-- Change Password Multi-Step Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold text-dark fs-6" id="changePasswordModalLabel">
                    <i class="bi bi-shield-lock-fill text-primary me-2"></i>Change Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Step Navigation Visual Indicator -->
                <div class="d-flex justify-content-center align-items-center mb-4">
                    <div class="d-flex align-items-center me-3" id="step1IndicatorWrapper">
                        <span class="badge rounded-circle bg-primary text-white me-2 d-inline-flex align-items-center justify-content-center" id="step1Num" style="width: 28px; height: 28px;">1</span>
                        <span class="fw-bold small text-primary" id="step1Text">Verify Current</span>
                    </div>
                    <div class="border-top me-3" style="width: 40px; border-color: #dee2e6 !important;" id="stepDivider"></div>
                    <div class="d-flex align-items-center" id="step2IndicatorWrapper">
                        <span class="badge rounded-circle bg-secondary text-white me-2 d-inline-flex align-items-center justify-content-center" id="step2Num" style="width: 28px; height: 28px;">2</span>
                        <span class="fw-bold small text-muted" id="step2Text">New Password</span>
                    </div>
                </div>

                <!-- Alert Message -->
                <div id="modalPassAlert" class="alert d-none shadow-sm mb-3"></div>

                <!-- STEP 1 FORM: Verify Current Password -->
                <form id="step1Form" onsubmit="handleVerifyStep1(event)">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control border-end-0" id="modalCurrPass" placeholder="Enter your current password" required>
                            <button class="btn border border-start-0 bg-white" type="button" onclick="togglePass('modalCurrPass', 'eyeModalCurr')">
                                <i class="bi bi-eye-slash text-muted" id="eyeModalCurr"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted small"><i class="bi bi-info-circle me-1"></i>Please enter your existing password to verify your identity.</div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light border btn-sm fw-bold px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-4" id="btnVerifyStep1">
                            Verify & Next <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>

                <!-- STEP 2 FORM: Set New Password -->
                <form id="step2Form" class="d-none" onsubmit="handleUpdateStep2(event)">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control border-end-0" id="modalNewPass" placeholder="Enter new password (min. 6 chars)" required>
                            <button class="btn border border-start-0 bg-white" type="button" onclick="togglePass('modalNewPass', 'eyeModalNew')">
                                <i class="bi bi-eye-slash text-muted" id="eyeModalNew"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control border-end-0" id="modalConfPass" placeholder="Retype new password" required>
                            <button class="btn border border-start-0 bg-white" type="button" onclick="togglePass('modalConfPass', 'eyeModalConf')">
                                <i class="bi bi-eye-slash text-muted" id="eyeModalConf"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <button type="button" class="btn btn-outline-secondary btn-sm fw-bold" onclick="goToStep1()">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <button type="submit" class="btn btn-success btn-sm fw-bold px-4" id="btnUpdateStep2">
                            <i class="bi bi-check-circle me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let verifiedCurrentPassword = "";

    function showPassAlert(msg, type) {
        const alert = document.getElementById('modalPassAlert');
        alert.className = `alert alert-${type} shadow-sm mb-3`;
        alert.innerHTML = msg;
        alert.classList.remove('d-none');
    }

    function hidePassAlert() {
        const alert = document.getElementById('modalPassAlert');
        alert.classList.add('d-none');
    }

    function resetPasswordModal() {
        verifiedCurrentPassword = "";
        document.getElementById('step1Form').reset();
        document.getElementById('step2Form').reset();
        goToStep1();
        hidePassAlert();
    }

    function goToStep1() {
        document.getElementById('step1Form').classList.remove('d-none');
        document.getElementById('step2Form').classList.add('d-none');
        
        document.getElementById('step1Num').className = 'badge rounded-circle bg-primary text-white me-2 d-inline-flex align-items-center justify-content-center';
        document.getElementById('step1Num').innerHTML = '1';
        document.getElementById('step1Text').className = 'fw-bold small text-primary';
        
        document.getElementById('step2Num').className = 'badge rounded-circle bg-secondary text-white me-2 d-inline-flex align-items-center justify-content-center';
        document.getElementById('step2Text').className = 'fw-bold small text-muted';
    }

    function goToStep2() {
        document.getElementById('step1Form').classList.add('d-none');
        document.getElementById('step2Form').classList.remove('d-none');
        
        document.getElementById('step1Num').className = 'badge rounded-circle bg-success text-white me-2 d-inline-flex align-items-center justify-content-center';
        document.getElementById('step1Num').innerHTML = '<i class="bi bi-check"></i>';
        document.getElementById('step1Text').className = 'fw-bold small text-success';
        
        document.getElementById('step2Num').className = 'badge rounded-circle bg-primary text-white me-2 d-inline-flex align-items-center justify-content-center';
        document.getElementById('step2Text').className = 'fw-bold small text-primary';
        
        hidePassAlert();
    }

    async function handleVerifyStep1(e) {
        e.preventDefault();
        hidePassAlert();
        
        const currentPassInput = document.getElementById('modalCurrPass').value;
        const btn = document.getElementById('btnVerifyStep1');
        
        if (!currentPassInput) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Please enter your current password.', 'danger');
            return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying...';
        
        try {
            const formData = new FormData();
            formData.append('action', 'verify_current_password');
            formData.append('current_password', currentPassInput);
            
            const res = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                verifiedCurrentPassword = currentPassInput;
                goToStep2();
            } else {
                showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + (data.message || 'Incorrect password.'), 'danger');
            }
        } catch (err) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Verification failed. Please try again.', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Verify & Next <i class="bi bi-arrow-right ms-1"></i>';
        }
    }

    async function handleUpdateStep2(e) {
        e.preventDefault();
        hidePassAlert();
        
        const newPass = document.getElementById('modalNewPass').value;
        const confPass = document.getElementById('modalConfPass').value;
        const btn = document.getElementById('btnUpdateStep2');
        
        if (newPass.length < 6) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> New password must be at least 6 characters.', 'danger');
            return;
        }
        
        if (newPass !== confPass) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Passwords do not match.', 'danger');
            return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Updating...';
        
        try {
            const formData = new FormData();
            formData.append('action', 'change_password_modal');
            formData.append('current_password', verifiedCurrentPassword);
            formData.append('new_password', newPass);
            formData.append('confirm_password', confPass);
            
            const res = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                showPassAlert('<i class="bi bi-check-circle-fill me-1"></i> ' + data.message, 'success');
                setTimeout(() => {
                    const modalEl = document.getElementById('changePasswordModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    window.location.reload();
                }, 1200);
            } else {
                showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + (data.message || 'Failed to update password.'), 'danger');
            }
        } catch (err) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Server error. Please try again.', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Update Password';
        }
    }

    // Simple Password Toggle for Profile Page
    window.togglePass = function (inputId, iconId) {
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