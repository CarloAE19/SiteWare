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
                            <button type="button" class="btn btn-outline-primary fw-bold px-3" data-bs-toggle="modal"
                                data-bs-target="#changePasswordModal" onclick="resetPasswordModal()">
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
                // Fetch current login background + blur settings
                $bg_path = 'assets/img/default_login_bg.png';
                $cur_blur = 12;
                if (isset($pdo)) {
                    try {
                        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('login_background','login_blur')");
                        $stmt->execute();
                        $lc_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        if (!empty($lc_settings['login_background']))
                            $bg_path = $lc_settings['login_background'];
                        if (isset($lc_settings['login_blur']) && $lc_settings['login_blur'] !== '')
                            $cur_blur = (int) $lc_settings['login_blur'];
                    } catch (Exception $e) {
                    }
                }
                $bg_version = file_exists($bg_path) ? filemtime($bg_path) : time();
                // Build a root-relative URL to the background image
                $profile_app_base = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
                $bg_preview_url = $profile_app_base . '/' . ltrim($bg_path, '/') . '?v=' . $bg_version;
                $cur_blur = max(0, min(30, $cur_blur));
                $cur_scale = round(1 + ($cur_blur * 0.006), 3);
                ?>
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white fw-bold py-3">
                        <i class="bi bi-image text-primary me-2"></i>Login Page Customization
                    </div>
                    <div class="card-body p-4">

                        <!-- ===== LIVE PREVIEW ===== -->
                        <h6 class="fw-bold mb-3 border-bottom pb-2">
                            <i class="bi bi-eye me-1 text-primary"></i>Live Preview
                        </h6>
                        <div id="loginPreviewWrap" style="
                            position: relative;
                            width: 100%;
                            height: 320px;
                            border-radius: 12px;
                            overflow: hidden;
                            border: 1.5px solid #e2e8f0;
                            background: #0f172a;
                            margin-bottom: 1.5rem;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                        ">
                            <!-- Blurred background layer -->
                            <div id="previewBgLayer" style="
                                position: absolute; inset: 0;
                                background-image: url('<?= htmlspecialchars($bg_preview_url) ?>');
                                background-size: cover;
                                background-position: center;
                                filter: blur(<?= $cur_blur ?>px);
                                transform: scale(<?= $cur_scale ?>);
                                transform-origin: center;
                                transition: filter 0.25s ease, transform 0.25s ease;
                            "></div>
                            <!-- Dark overlay -->
                            <div style="position: absolute; inset: 0; background: rgba(15,23,42,0.45);"></div>

                            <!-- ★ Scaled accurate login page replica ★ -->
                            <div style="
                                position: absolute; inset: 0;
                                display: flex; flex-direction: column;
                                align-items: center; justify-content: center;
                                padding-bottom: 18px;
                            ">
                                <!-- Login card — full real size, then CSS-scaled down -->
                                <div style="
                                    transform: scale(0.52);
                                    transform-origin: center center;
                                    background: #ffffff;
                                    width: 440px;
                                    border-radius: 20px;
                                    padding: 36px 40px 32px;
                                    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);
                                    text-align: center;
                                    flex-shrink: 0;
                                    pointer-events: none;
                                    user-select: none;
                                ">
                                    <!-- Logo -->
                                    <img src="<?= $profile_app_base ?>/assets/LogoGB.png" alt="Logo" style="width:68px;height:68px;object-fit:contain;border-radius:14px;
                                                background:#fff;padding:5px;
                                                box-shadow:0 4px 12px rgba(0,0,0,0.08);
                                                margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;">
                                    <!-- Brand name -->
                                    <div
                                        style="font-size:1.45rem;font-weight:800;color:#0f172a;letter-spacing:-0.02em;margin-bottom:2px;font-family:'Inter',sans-serif;">
                                        SiteWare</div>
                                    <!-- Heading -->
                                    <div
                                        style="font-size:1.55rem;font-weight:700;color:#0f172a;letter-spacing:-0.02em;margin-bottom:24px;font-family:'Inter',sans-serif;">
                                        Login</div>

                                    <!-- Username field -->
                                    <div style="margin-bottom:18px;text-align:left;">
                                        <div
                                            style="font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:6px;letter-spacing:0.02em;">
                                            Username</div>
                                        <div style="position:relative;">
                                            <i class="bi bi-person"
                                                style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#64748b;font-size:1.05rem;z-index:1;"></i>
                                            <div
                                                style="width:100%;padding:11px 14px 11px 42px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.92rem;color:#94a3b8;background:#f1f5f9;font-family:'Inter',sans-serif;">
                                                Enter you username
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Password field -->
                                    <div style="margin-bottom:18px;text-align:left;">
                                        <div
                                            style="font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:6px;letter-spacing:0.02em;">
                                            Password</div>
                                        <div style="position:relative;">
                                            <i class="bi bi-lock"
                                                style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#64748b;font-size:1.05rem;z-index:1;"></i>
                                            <i class="bi bi-eye-slash"
                                                style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#64748b;font-size:1.05rem;z-index:1;"></i>
                                            <div
                                                style="width:100%;padding:11px 42px 11px 42px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.92rem;color:#94a3b8;background:#f1f5f9;font-family:'Inter',sans-serif;">
                                                Enter your password
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Login button -->
                                    <div
                                        style="background:linear-gradient(135deg,#4f46e5 0%,#3730a3 100%);color:#fff;font-size:0.95rem;font-weight:700;border-radius:10px;padding:13px;text-align:center;letter-spacing:0.02em;font-family:'Inter',sans-serif;box-shadow:0 4px 12px rgba(79,70,229,0.25);">
                                        Login
                                    </div>
                                </div>
                            </div>

                            <!-- Page footer replica -->
                            <div
                                style="position:absolute;bottom:8px;left:0;right:0;text-align:center;font-size:0.62rem;color:rgba(255,255,255,0.85);text-shadow:0 1px 3px rgba(0,0,0,0.7);letter-spacing:0.01em;pointer-events:none;">
                                &copy; 2026 Genetian Builders &amp; Enterprises Inc. &nbsp;|&nbsp; Powered by <span
                                    style="font-weight:700;text-decoration:underline;">The Medyas</span>
                            </div>

                            <!-- Blur badge -->
                            <div style="
                                position: absolute; top: 10px; right: 10px;
                                background: rgba(0,0,0,0.55); backdrop-filter: blur(6px);
                                color: #fff; font-size: 0.72rem; font-weight: 700;
                                padding: 3px 9px; border-radius: 20px; letter-spacing: 0.02em;
                            ">
                                Blur: <span id="blurBadge"><?= $cur_blur ?>px</span>
                            </div>
                        </div>

                        <div class="row g-4">
                            <!-- LEFT: Background image upload -->
                            <div class="col-12 col-md-6">
                                <h6 class="fw-bold mb-3 border-bottom pb-2">
                                    <i class="bi bi-image-fill me-1 text-primary"></i>Background Image
                                </h6>
                                <form method="POST" action="process/process.php" enctype="multipart/form-data" class="mb-3"
                                    id="bgUploadForm">
                                    <input type="hidden" name="action" value="update_login_bg">
                                    <label class="form-label fw-bold small">Upload New Background</label>
                                    <div class="input-group mb-2">
                                        <input type="file" class="form-control form-control-sm" name="login_bg"
                                            accept="image/jpeg,image/png,image/webp,image/gif" id="bgFileInput" required>
                                        <button class="btn btn-brand btn-sm fw-bold px-3" type="submit">
                                            <i class="bi bi-upload me-1"></i>Upload
                                        </button>
                                    </div>
                                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>JPG, PNG, WEBP, GIF ·
                                        Max 5MB</small>
                                </form>

                                <?php if ($bg_path !== 'assets/img/default_login_bg.png'): ?>
                                    <form method="POST" action="process/process.php">
                                        <input type="hidden" name="action" value="reset_login_bg">
                                        <button class="btn btn-outline-danger btn-sm fw-bold w-100" type="submit"
                                            onclick="return confirm('Reset the background image to default?');">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Default Image
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- RIGHT: Blur intensity slider -->
                            <div class="col-12 col-md-6">
                                <h6 class="fw-bold mb-3 border-bottom pb-2">
                                    <i class="bi bi-sliders me-1 text-primary"></i>Blur Intensity
                                </h6>
                                <form method="POST" action="process/process.php" id="blurForm">
                                    <input type="hidden" name="action" value="update_login_blur">

                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-bold small mb-0" for="blurSlider">Blur Amount</label>
                                        <span class="badge bg-primary" id="blurValueBadge"><?= $cur_blur ?>px</span>
                                    </div>

                                    <input type="range" class="form-range" id="blurSlider" name="login_blur" min="0"
                                        max="30" step="1" value="<?= $cur_blur ?>" style="accent-color: #4f46e5;">

                                    <div class="d-flex justify-content-between text-muted"
                                        style="font-size: 0.72rem; margin-top: -2px;">
                                        <span>0px (No blur)</span>
                                        <span>30px (Max blur)</span>
                                    </div>

                                    <!-- Preset buttons -->
                                    <div class="d-flex gap-2 flex-wrap mt-3 mb-3">
                                        <button type="button" class="btn btn-outline-secondary btn-sm blur-preset"
                                            data-val="0">None</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm blur-preset"
                                            data-val="6">Light</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm blur-preset"
                                            data-val="12">Medium</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm blur-preset"
                                            data-val="20">Heavy</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm blur-preset"
                                            data-val="30">Max</button>
                                    </div>

                                    <button type="submit" class="btn btn-brand fw-bold w-100" id="saveBlurBtn">
                                        <i class="bi bi-save me-1"></i>Save Blur Setting
                                    </button>
                                </form>
                            </div>
                        </div><!-- /row -->

                    </div><!-- /card-body -->
                </div><!-- /card -->

                <script>
                    (function () {
                        const slider = document.getElementById('blurSlider');
                        const valueBadge = document.getElementById('blurValueBadge');
                        const blurBadge = document.getElementById('blurBadge');
                        const bgLayer = document.getElementById('previewBgLayer');
                        const bgFileInput = document.getElementById('bgFileInput');

                        function applyBlur(val) {
                            val = Math.max(0, Math.min(30, parseInt(val)));
                            const scale = (1 + val * 0.006).toFixed(3);
                            bgLayer.style.filter = `blur(${val}px)`;
                            bgLayer.style.transform = `scale(${scale})`;
                            valueBadge.textContent = val + 'px';
                            blurBadge.textContent = val + 'px';
                        }

                        slider.addEventListener('input', () => applyBlur(slider.value));

                        // Preset buttons
                        document.querySelectorAll('.blur-preset').forEach(btn => {
                            btn.addEventListener('click', () => {
                                slider.value = btn.dataset.val;
                                applyBlur(btn.dataset.val);
                            });
                        });

                        // Image file picker → live preview
                        bgFileInput.addEventListener('change', function () {
                            const file = this.files[0];
                            if (!file) return;
                            const reader = new FileReader();
                            reader.onload = e => {
                                bgLayer.style.backgroundImage = `url('${e.target.result}')`;
                            };
                            reader.readAsDataURL(file);
                        });
                    })();
                </script>
            <?php endif; ?>


        </div>
    </div>
</div>

<!-- Change Password Multi-Step Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
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
                        <span
                            class="badge rounded-circle bg-primary text-white me-2 d-inline-flex align-items-center justify-content-center"
                            id="step1Num" style="width: 28px; height: 28px;">1</span>
                        <span class="fw-bold small text-primary" id="step1Text">Verify Current</span>
                    </div>
                    <div class="border-top me-3" style="width: 40px; border-color: #dee2e6 !important;"
                        id="stepDivider"></div>
                    <div class="d-flex align-items-center" id="step2IndicatorWrapper">
                        <span
                            class="badge rounded-circle bg-secondary text-white me-2 d-inline-flex align-items-center justify-content-center"
                            id="step2Num" style="width: 28px; height: 28px;">2</span>
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
                            <input type="password" class="form-control border-end-0" id="modalCurrPass"
                                placeholder="Enter your current password" required>
                            <button class="btn border border-start-0 bg-white" type="button"
                                onclick="togglePass('modalCurrPass', 'eyeModalCurr')">
                                <i class="bi bi-eye-slash text-muted" id="eyeModalCurr"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted small"><i class="bi bi-info-circle me-1"></i>Please enter your
                            existing password to verify your identity.</div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light border btn-sm fw-bold px-3"
                            data-bs-dismiss="modal">Cancel</button>
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
                            <input type="password" class="form-control border-end-0" id="modalNewPass"
                                placeholder="Enter new password" required oninput="checkPasswordStrength(this.value)">
                            <button class="btn border border-start-0 bg-white" type="button"
                                onclick="togglePass('modalNewPass', 'eyeModalNew')">
                                <i class="bi bi-eye-slash text-muted" id="eyeModalNew"></i>
                            </button>
                        </div>
                        <!-- Password Strength Meter -->
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-secondary" role="progressbar" id="strengthMeter"
                                style="width: 0%; transition: width 0.3s ease;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted fw-bold" id="strengthText" style="font-size: 0.75rem;">Password
                                Strength: Empty</small>
                        </div>

                        <!-- Requirements checklist -->
                        <div class="bg-light p-2 rounded border mt-2" style="font-size: 0.75rem;">
                            <div class="fw-bold text-secondary mb-1">Must contain:</div>
                            <div class="d-flex flex-wrap gap-2">
                                <span id="reqLen" class="text-muted"><i class="bi bi-dot"></i>8+ characters</span>
                                <span id="reqUpper" class="text-muted"><i class="bi bi-dot"></i>1 Uppercase (A-Z)</span>
                                <span id="reqLower" class="text-muted"><i class="bi bi-dot"></i>1 Lowercase (a-z)</span>
                                <span id="reqNum" class="text-muted"><i class="bi bi-dot"></i>1 Number (0-9)</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control border-end-0" id="modalConfPass"
                                placeholder="Retype new password" required>
                            <button class="btn border border-start-0 bg-white" type="button"
                                onclick="togglePass('modalConfPass', 'eyeModalConf')">
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

    function checkPasswordStrength(val) {
        const meter = document.getElementById('strengthMeter');
        const text = document.getElementById('strengthText');
        const reqLen = document.getElementById('reqLen');
        const reqUpper = document.getElementById('reqUpper');
        const reqLower = document.getElementById('reqLower');
        const reqNum = document.getElementById('reqNum');

        if (!meter || !text) return;

        const hasLen = val.length >= 8;
        const hasUpper = /[A-Z]/.test(val);
        const hasLower = /[a-z]/.test(val);
        const hasNum = /[0-9]/.test(val);
        const hasSpecial = /[^A-Za-z0-9]/.test(val);

        if (reqLen) reqLen.className = hasLen ? 'text-success fw-bold' : 'text-muted';
        if (reqUpper) reqUpper.className = hasUpper ? 'text-success fw-bold' : 'text-muted';
        if (reqLower) reqLower.className = hasLower ? 'text-success fw-bold' : 'text-muted';
        if (reqNum) reqNum.className = hasNum ? 'text-success fw-bold' : 'text-muted';

        if (val.length === 0) {
            meter.style.width = '0%';
            meter.className = 'progress-bar bg-secondary';
            text.innerText = 'Password Strength: Empty';
            text.className = 'text-muted fw-bold';
            return;
        }

        let score = 0;
        if (hasLen) score++;
        if (hasUpper) score++;
        if (hasLower) score++;
        if (hasNum) score++;
        if (hasSpecial) score++;

        if (score <= 2) {
            meter.style.width = '33%';
            meter.className = 'progress-bar bg-danger';
            text.innerText = 'Password Strength: Weak';
            text.className = 'text-danger fw-bold';
        } else if (score === 3 || score === 4) {
            meter.style.width = '66%';
            meter.className = 'progress-bar bg-warning';
            text.innerText = 'Password Strength: Medium';
            text.className = 'text-warning text-dark fw-bold';
        } else {
            meter.style.width = '100%';
            meter.className = 'progress-bar bg-success';
            text.innerText = 'Password Strength: Strong';
            text.className = 'text-success fw-bold';
        }
    }

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
        checkPasswordStrength("");
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

        if (newPass.length < 8) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Password must be at least 8 characters long.', 'danger');
            return;
        }
        if (!/[A-Z]/.test(newPass)) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Password must contain at least one uppercase letter (A-Z).', 'danger');
            return;
        }
        if (!/[a-z]/.test(newPass)) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Password must contain at least one lowercase letter (a-z).', 'danger');
            return;
        }
        if (!/[0-9]/.test(newPass)) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Password must contain at least one number (0-9).', 'danger');
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