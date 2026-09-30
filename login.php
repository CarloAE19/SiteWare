<?php
require_once 'Connection/db.php';
init_secure_session();

// Smart Router: Decides where a user goes based on their role
function redirectUserByRole($role)
{
    // All roles now land on the unified role-aware dashboard
    header("Location: dashboard");
    exit;
}

// If already logged in, route them to their workspace
if (isset($_SESSION['user_id'])) {
    redirectUserByRole($_SESSION['user_role']);
}

$error = '';
$infoMsg = '';
if (!empty($_GET['deactivated'])) {
    $error = 'Your session has ended because your account was deactivated by an administrator.';
} elseif (!empty($_GET['timeout'])) {
    $infoMsg = 'Your session expired due to inactivity. Please log in again to continue.';
} elseif (!empty($_GET['cancel_mfa'])) {
    unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_user_name'], $_SESSION['mfa_pending_time'], $_SESSION['mfa_pending_attempts']);
    $infoMsg = 'Two-Factor Authentication cancelled. Please log in again.';
}
$is_locked_out = false;
$lockout_retry_after = 0;
$mfa_required = !empty($_SESSION['mfa_pending_user_id']) && (time() - ($_SESSION['mfa_pending_time'] ?? 0) <= 300);

$remembered_username = $_COOKIE['siteware_remember_user'] ?? '';
$initial_username = $_POST['username'] ?? $remembered_username;
$is_remembered = !empty($_COOKIE['siteware_remember_user']);

if (defined('DB_OFFLINE')) {
    $error = "Can't connect to database. You're offline.";
} else {
    // 🛡️ Brute-Force Check (5 failed attempts per 15 minutes per IP/username)
    $entered_username = trim($_POST['username'] ?? '');
    $rlKey = 'login_' . (!empty($entered_username) ? strtolower($entered_username) : 'anon');
    $rateLimit = check_rate_limit($rlKey, 5, 900, true);

    if (!$rateLimit['allowed']) {
        $is_locked_out = true;
        $lockout_retry_after = $rateLimit['retry_after'];
        $mins = ceil($lockout_retry_after / 60);
        $error = "Too many failed login attempts. Please wait {$mins} minute(s) before trying again.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked_out) {
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($submitted_token)) {
        $error = 'Security session expired or invalid token. Please refresh the page and try again.';
    } elseif (defined('DB_OFFLINE')) {
        $error = "Can't connect to database. You're offline.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } elseif (preg_match('/[^a-zA-Z0-9]/', $username)) {
            $error = 'Special characters not allowed in username';
        } elseif (strlen($password) < 8) {
            // 🛡️ Constant-time dummy verification for length check edge case
            password_verify($password, '$2y$12$KENzSxOKE94984d5ZXfNSeN00crr/yfGyCn6xEP1Za5IFR6kdlx/i');
            record_rate_limit_attempt($rlKey, true);
            $newRl = check_rate_limit($rlKey, 5, 900, true);
            if (!$newRl['allowed']) {
                $is_locked_out = true;
                $lockout_retry_after = $newRl['retry_after'];
                $mins = ceil($lockout_retry_after / 60);
                $error = "Too many failed login attempts. Please wait {$mins} minute(s) before trying again.";
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            // 🛡️ SQL Injection Prevention (Prepared Statements)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // 🛡️ Constant-Time Password Verification + Timing-Attack Mitigation (Username Enumeration Prevention)
            // If user is not found, verify against a realistic cost-12 dummy bcrypt hash so response time is identical.
            $dummyHash = '$2y$12$KENzSxOKE94984d5ZXfNSeN00crr/yfGyCn6xEP1Za5IFR6kdlx/i';
            $hashToVerify = $user ? $user['password'] : $dummyHash;
            $passwordMatches = password_verify($password, $hashToVerify);

            // 🛡️ Bcrypt Password Verification + Session Hijacking Prevention
            if ($user && $passwordMatches) {
                if (isset($user['status']) && strtolower($user['status']) === 'inactive') {
                    $error = 'Your account has been deactivated. Please contact an administrator.';
                } else {
                    // Handle "Remember Username" persistent cookie (30 days)
                    $is_secure_conn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                    if (!empty($_POST['remember_username'])) {
                        setcookie('siteware_remember_user', $user['username'], [
                            'expires' => time() + (86400 * 30), // 30 days
                            'path' => '/',
                            'secure' => $is_secure_conn,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    } else {
                        if (isset($_COOKIE['siteware_remember_user'])) {
                            setcookie('siteware_remember_user', '', [
                                'expires' => time() - 3600,
                                'path' => '/',
                                'secure' => $is_secure_conn,
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]);
                        }
                    }

                    clear_rate_limit($rlKey, true);

                    // 🛡️ Multi-Factor Authentication Check
                    if (!empty($user['mfa_enabled'])) {
                        $_SESSION['mfa_pending_user_id'] = $user['id'];
                        $_SESSION['mfa_pending_user_name'] = $user['name'];
                        $_SESSION['mfa_pending_time'] = time();
                        $_SESSION['mfa_pending_attempts'] = 0;
                        $mfa_required = true;
                    } else {
                        session_regenerate_id(true);
                        // Rotate CSRF token on privilege level change
                        unset($_SESSION['csrf_token']);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['last_activity'] = time();
                        unset($_SESSION['screen_locked']);
                        $_SESSION['fresh_login'] = true;
                        redirectUserByRole($user['role']);
                    }
                }
            } else {
                record_rate_limit_attempt($rlKey, true);
                $newRl = check_rate_limit($rlKey, 5, 900, true);
                if (!$newRl['allowed']) {
                    $is_locked_out = true;
                    $lockout_retry_after = $newRl['retry_after'];
                    $mins = ceil($lockout_retry_after / 60);
                    $error = "Too many failed login attempts. Please wait {$mins} minute(s) before trying again.";

                    // 🛡️ Automated Attack-Detection: Freeze database state into a Security Incident Snapshot
                    try {
                        require_once __DIR__ . '/classes/BackupService.php';
                        $bs = new BackupService($pdo);
                        $bs->triggerSecurityIncidentSnapshot('Brute-force lockout on username: ' . $username, get_client_ip());
                    } catch (Throwable $secErr) {
                        // Silent failover so security exception never breaks the lockout response
                    }
                } else {
                    $remaining = $newRl['remaining'];
                    if ($remaining <= 2 && $remaining > 0) {
                        $error = "Invalid username or password. ({$remaining} attempt(s) remaining before temporary lockout)";
                    } else {
                        $error = 'Invalid username or password.';
                    }
                }
            }
        }
    }
}

$bg_image = 'assets/img/default_login_bg.png';
$bg_blur = 12; // default blur in px
if (!defined('DB_OFFLINE') && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('login_background','login_blur')");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($settings['login_background']))
            $bg_image = $settings['login_background'];
        if (isset($settings['login_blur']) && $settings['login_blur'] !== '')
            $bg_blur = (int) $settings['login_blur'];
    } catch (Exception $e) {
        // Fallback
    }
}
// Build a root-relative URL so it resolves correctly even with clean URLs (e.g. /CIMS/login vs /CIMS/login.php)
// dirname($_SERVER['PHP_SELF']) gives /CIMS when login.php is at /CIMS/login.php
$app_base = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$bg_version = file_exists($bg_image) ? filemtime($bg_image) : time();
$bg_image_url = $app_base . '/' . ltrim($bg_image, '/') . '?v=' . $bg_version;
$bg_blur = max(0, min(30, $bg_blur)); // clamp 0–30
// Scale factor: more blur needs more scale to hide edge artifacts
$bg_scale = 1 + ($bg_blur * 0.006);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Sign In — GB Inventory System</title>
    <meta name="description" content="GB Construction & Enterprise Smart Inventory & Logistics System — Secure Login">
    <meta name="csrf-token" content="<?= htmlspecialchars(generate_csrf_token()) ?>">

    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="assets/LogoGB.png">
    <link rel="icon" type="image/png" href="assets/LogoGB.png">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Login Styles -->
    <link rel="stylesheet" href="assets/css/login.css?v=<?= time() ?>">
</head>

<body>
    <?php // include_once 'components/splash_screen.php'; ?>

    <div class="login-wrapper">
        <!-- Full Screen Blurred Background -->
        <div class="login-bg-container"
            style="background-image: url('<?= htmlspecialchars($bg_image_url) ?>'); filter: blur(<?= $bg_blur ?>px); transform: scale(<?= $bg_scale ?>);">
        </div>

        <!-- Centered Login Card -->
        <div class="login-card">

            <!-- Logo + Brand Name -->
            <div class="brand-logo-wrap">
                <img src="assets/LogoGB.png" alt="GB Construction Logo">
                <div class="brand-name">SiteWare</div>
            </div>

            <h2>Login</h2>

            <?php if (defined('DB_OFFLINE')): ?>
                <div class="alert alert-danger d-flex align-items-center gap-3 border-0 shadow-sm mb-4 px-3 py-3"
                    style="background-color: #fef2f2; border-left: 4px solid var(--gb-red) !important; border-radius: 8px;">
                    <i class="bi bi-wifi-off text-danger fs-5 animate-pulse-login"></i>
                    <div class="text-start">
                        <strong class="text-danger d-block">Can't Connect, You're Offline</strong>
                        <small class="text-muted d-block" style="font-size: 0.75rem; line-height: 1.3;">Database connection
                            is offline. Sign-in is temporarily disabled.</small>
                    </div>
                </div>
                <style>
                    @keyframes pulseLogin {

                        0%,
                        100% {
                            opacity: 1;
                        }

                        50% {
                            opacity: 0.4;
                        }
                    }

                    .animate-pulse-login {
                        animation: pulseLogin 2s infinite ease-in-out;
                    }
                </style>
            <?php elseif (!empty($infoMsg)): ?>
                <div class="login-error" id="phpInfoBlock"
                    style="background: rgba(13, 110, 253, 0.08); border-color: rgba(13, 110, 253, 0.25); color: #0033cc;">
                    <i class="bi bi-clock-history" style="font-size:1.1rem; color: #0d6efd; flex-shrink:0;"></i>
                    <?= htmlspecialchars($infoMsg) ?>
                </div>
            <?php elseif ($error && $error !== 'Special characters not allowed in username'): ?>
                <div class="login-error" id="phpErrorBlock">
                    <i class="bi bi-exclamation-circle-fill"
                        style="font-size:1.1rem; color:var(--gb-red); flex-shrink:0;"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on" id="loginForm">
                <!-- CSRF Protection Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                <div class="input-float <?= ($error === 'Special characters not allowed in username') ? 'has-error' : '' ?>"
                    id="usernameFloat">
                    <label for="usernameField">Username</label>
                    <input type="text" id="usernameField" name="username" placeholder="Enter your username"
                        value="<?= htmlspecialchars($initial_username) ?>" autocomplete="username" <?= $is_locked_out ? 'disabled' : '' ?> required>
                    <i class="bi bi-person field-icon"></i>
                </div>

                <div class="field-error-msg" id="jsErrorBlock" style="display: none;">
                    <i class="bi bi-exclamation-diamond-fill"></i>
                    <span id="jsErrorMessage"></span>
                </div>

                <?php if ($error && $error === 'Special characters not allowed in username'): ?>
                    <div class="field-error-msg" id="phpUsernameErrorBlock">
                        <i class="bi bi-exclamation-diamond-fill"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <div class="input-float">
                    <label for="passwordField">Password</label>
                    <input type="password" id="passwordField" name="password" placeholder="Enter your password"
                        autocomplete="current-password" <?= $is_locked_out ? 'disabled' : '' ?> required>
                    <i class="bi bi-lock field-icon"></i>
                    <button type="button" class="toggle-pass" onclick="togglePass()" aria-label="Toggle password"
                        <?= $is_locked_out ? 'disabled' : '' ?>>
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </button>
                </div>

                <div class="caps-warning" id="capsWarningBlock" style="display: none;" role="status" aria-live="polite">
                    <i class="bi bi-capslock-fill"></i>
                    <span>Caps Lock is ON</span>
                </div>

                <!-- Remember Username & Forgot Password Options -->
                <div class="login-options-row">
                    <label class="remember-checkbox" for="rememberMeCheckbox">
                        <input type="checkbox" name="remember_username" id="rememberMeCheckbox" value="1"
                            <?= ($is_remembered || !empty($_POST['remember_username'])) ? 'checked' : '' ?>
                            <?= $is_locked_out ? 'disabled' : '' ?>>
                        <span class="custom-check-indicator"><i class="bi bi-check"></i></span>
                        <span class="remember-label-text">Remember me</span>
                    </label>
                    <a href="javascript:void(0)" class="forgot-pass-link" data-bs-toggle="modal"
                        data-bs-target="#helpModal" <?= $is_locked_out ? 'tabindex="-1"' : '' ?>>
                        Forgot password?
                    </a>
                </div>

                <!-- PWA Install Button (hidden until browser triggers beforeinstallprompt) -->
                <button type="button" id="installAppBtn" class="btn-install">
                    <i class="bi bi-android2" style="color:#3DDC84;"></i>
                    <i class="bi bi-apple" style="color:#555;"></i>
                    <i class="bi bi-windows" style="color:#0078D7;"></i>
                    Install SiteWare App
                </button>

                <button type="submit" class="btn-signin" id="signInBtn" <?= (defined('DB_OFFLINE') || $error === 'Special characters not allowed in username' || $is_locked_out) ? 'disabled' : '' ?>>
                    <?php if ($is_locked_out): ?>
                        <i class="bi bi-lock-fill"></i> Locked (<span id="lockTimer">--:--</span>)
                    <?php else: ?>
                        Login
                    <?php endif; ?>
                </button>

                <!-- Passkey / Biometric Login Option -->
                <div class="login-divider my-3 d-flex align-items-center">
                    <hr class="flex-grow-1 border-secondary-subtle my-0">
                    <span class="px-2 text-muted small fw-semibold" style="font-size: 0.75rem;">OR QUICK ACCESS</span>
                    <hr class="flex-grow-1 border-secondary-subtle my-0">
                </div>

                <button type="button"
                    class="btn btn-outline-primary w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm rounded-3"
                    id="passkeySignInBtn" style="border-width: 1.5px; transition: all 0.2s ease;">
                    <i class="bi bi-fingerprint fs-5"></i>
                    <span>Sign in with Passkey</span>
                </button>

            </form>

        </div>

        <div class="form-footer">
            &copy; <?= date('Y') ?> Genetian Builders &amp; Enterprises Inc. &nbsp;|&nbsp; Powered by <a href="about"
                class="text-decoration-none fw-bold" style="color: var(--gb-blue) !important;">The Medyas</a>
        </div>
    </div>

    <!-- Help & Password Recovery Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-header border-0 pb-0 pt-4 px-4 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-primary-subtle rounded-3 text-primary"
                            style="width: 42px; height: 42px; font-size: 1.25rem;">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="helpModalLabel"
                                style="font-size: 1.1rem;">Need Help Signing In?</h5>
                            <small class="text-muted" style="font-size: 0.8rem;">Account Support</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <p class="text-secondary small mb-3" style="line-height: 1.6;">
                        If you forgot your password or are having trouble accessing your account, please reach out
                        directly to your <strong>system administrator</strong> or <strong>IT support</strong>.
                    </p>

                    <div class="bg-light p-3 rounded-3 mb-0 border">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-info-circle-fill text-primary flex-shrink-0 mt-1"
                                style="font-size: 0.95rem;"></i>
                            <small class="text-muted" style="font-size: 0.8rem; line-height: 1.45;">
                                For your security, credential resets and account unlocks must be verified and issued by
                                an authorized administrator.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2 bg-white">
                    <button type="button" class="btn btn-brand w-100 fw-bold py-2 shadow-sm" data-bs-dismiss="modal"
                        style="border-radius: 10px;">
                        <i class="bi bi-check2 me-1"></i> Got It
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: TWO-FACTOR AUTHENTICATION (TOTP / BACKUP CODE)    -->
    <!-- ======================================================== -->
    <div class="modal fade" id="mfaModal" tabindex="-1" aria-labelledby="mfaModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down"
            style="max-width: 440px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
                <div class="modal-header border-0 pb-0 pt-4 px-4 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-primary-subtle rounded-3 text-primary shadow-sm"
                            style="width: 46px; height: 46px; font-size: 1.35rem;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="mfaModalLabel"
                                style="font-size: 1.15rem;">Two-Factor Verification</h5>
                            <small class="text-muted" style="font-size: 0.8rem;">SiteWare Enhanced Security</small>
                        </div>
                    </div>
                    <a href="login?cancel_mfa=1" class="btn-close shadow-none" aria-label="Cancel verification"></a>
                </div>
                <div class="modal-body px-4 py-3">
                    <p class="text-secondary small mb-3" id="mfaInstructionText" style="line-height: 1.55;">
                        Hello, <strong><?= htmlspecialchars($_SESSION['mfa_pending_user_name'] ?? 'User') ?></strong>!
                        Please enter the 6-digit authentication code generated by your Authenticator app.
                    </p>

                    <div id="mfaAlertBox" class="alert alert-danger d-none py-2 px-3 small border-0 shadow-sm mb-3"
                        style="border-radius: 8px;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <span id="mfaAlertMessage"></span>
                    </div>

                    <form id="mfaVerifyForm" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                        <div class="mb-3 text-center">
                            <label for="mfaCodeInput"
                                class="form-label fw-bold text-secondary small text-uppercase mb-2" id="mfaInputLabel">
                                6-Digit Verification Code
                            </label>
                            <input type="text" class="form-control form-control-lg text-center fw-bold shadow-none"
                                id="mfaCodeInput" name="code" placeholder="000000" maxlength="9" inputmode="numeric"
                                style="font-size: 1.6rem; letter-spacing: 0.25em; border-radius: 12px; height: 56px;"
                                required autofocus>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <a href="javascript:void(0)" id="toggleBackupCodeLink"
                                class="text-decoration-none small text-primary fw-semibold">
                                <i class="bi bi-key-fill me-1"></i>Use a backup code instead
                            </a>
                            <small class="text-muted" id="mfaCodeHint"><i class="bi bi-clock-history me-1"></i>Expires
                                in 5m</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-brand py-2 fw-bold shadow-sm" id="mfaSubmitBtn"
                                style="border-radius: 10px; min-height: 44px;">
                                <i class="bi bi-shield-lock-fill me-1"></i> Verify &amp; Sign In
                            </button>
                            <a href="login?cancel_mfa=1" class="btn btn-light py-2 text-secondary fw-semibold border"
                                style="border-radius: 10px; min-height: 44px;">
                                Cancel &amp; Back to Login
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 px-4 pb-3 pt-0 bg-light text-center justify-content-center">
                    <small class="text-muted" style="font-size: 0.75rem;">
                        <i class="bi bi-lock me-1"></i> Protected by Multi-Factor Authentication (ISO/IEC 25010)
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 for polished enterprise alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Login Scripts -->
    <script src="assets/js/login.js?v=<?= time() ?>"></script>
    <script>
        // Clean up stale idle lock and active tracker keys from prior sessions
        try {
            Object.keys(localStorage).forEach(function (k) {
                if (k.indexOf('cims_screen_locked_') === 0 || k.indexOf('cims_last_active_') === 0) {
                    localStorage.removeItem(k);
                }
            });
        } catch (e) { }

        // =========================================================================
        // MULTI-FACTOR AUTHENTICATION (TOTP / BACKUP CODE) AJAX HANDLER
        // (Standards: cims-modal-ajax-handler & quality-standards)
        // =========================================================================
        document.addEventListener('DOMContentLoaded', function () {
            const mfaModalEl = document.getElementById('mfaModal');
            const mfaForm = document.getElementById('mfaVerifyForm');
            const mfaCodeInput = document.getElementById('mfaCodeInput');
            const mfaSubmitBtn = document.getElementById('mfaSubmitBtn');
            const mfaAlertBox = document.getElementById('mfaAlertBox');
            const mfaAlertMsg = document.getElementById('mfaAlertMessage');
            const toggleBackupLink = document.getElementById('toggleBackupCodeLink');
            const mfaInputLabel = document.getElementById('mfaInputLabel');
            const mfaInstruction = document.getElementById('mfaInstructionText');

            let isBackupMode = false;
            let mfaModalInstance = null;

            if (mfaModalEl) {
                mfaModalInstance = new bootstrap.Modal(mfaModalEl, { backdrop: 'static', keyboard: false });

                // Auto-show modal if MFA is required on page load
                <?php if ($mfa_required): ?>
                    mfaModalInstance.show();
                <?php endif; ?>

                mfaModalEl.addEventListener('shown.bs.modal', function () {
                    if (mfaCodeInput) mfaCodeInput.focus();
                });
            }

            // Toggle between 6-digit TOTP and 8-character backup recovery code
            if (toggleBackupLink) {
                toggleBackupLink.addEventListener('click', function () {
                    isBackupMode = !isBackupMode;
                    if (isBackupMode) {
                        mfaInputLabel.textContent = '8-Character Backup Code';
                        mfaCodeInput.placeholder = 'XXXX-XXXX';
                        mfaCodeInput.maxLength = 9;
                        mfaCodeInput.inputMode = 'text';
                        mfaInstruction.innerHTML = 'Enter one of your emergency <strong>backup recovery codes</strong> to sign in.';
                        toggleBackupLink.innerHTML = '<i class="bi bi-phone me-1"></i>Use 6-digit Authenticator code';
                    } else {
                        mfaInputLabel.textContent = '6-Digit Verification Code';
                        mfaCodeInput.placeholder = '000000';
                        mfaCodeInput.maxLength = 6;
                        mfaCodeInput.inputMode = 'numeric';
                        mfaInstruction.innerHTML = 'Please enter the 6-digit authentication code generated by your Authenticator app.';
                        toggleBackupLink.innerHTML = '<i class="bi bi-key-fill me-1"></i>Use a backup code instead';
                    }
                    if (mfaAlertBox) mfaAlertBox.classList.add('d-none');
                    mfaCodeInput.value = '';
                    mfaCodeInput.focus();
                });
            }

            // AJAX Form Submission
            if (mfaForm) {
                mfaForm.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const codeVal = mfaCodeInput.value.trim();
                    if (!codeVal) {
                        mfaCodeInput.focus();
                        return;
                    }

                    // Prevent duplicate submissions & show loading spinner
                    const originalBtnHtml = mfaSubmitBtn.innerHTML;
                    mfaSubmitBtn.disabled = true;
                    mfaSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Verifying...';
                    if (mfaAlertBox) mfaAlertBox.classList.add('d-none');

                    try {
                        const formData = new FormData(mfaForm);
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                        const response = await fetch('process/verify_mfa_login.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-Token': csrfToken
                            }
                        });

                        const result = await response.json();

                        if (result.success || result.status === 'success') {
                            if (typeof Swal !== 'undefined') {
                                await Swal.fire({
                                    icon: 'success',
                                    title: 'Identity Verified!',
                                    text: result.message || 'Redirecting to your dashboard...',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                            window.location.href = result.redirect || 'dashboard';
                        } else {
                            throw new Error(result.message || 'Verification failed. Please try again.');
                        }
                    } catch (err) {
                        console.error('MFA Verification Error:', err);
                        if (mfaAlertBox && mfaAlertMsg) {
                            mfaAlertMsg.textContent = err.message || 'Verification failed. Please try again.';
                            mfaAlertBox.classList.remove('d-none');
                        } else if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Verification Failed',
                                text: err.message || 'Invalid verification code.'
                            });
                        }
                        mfaCodeInput.select();
                        mfaCodeInput.focus();
                    } finally {
                        mfaSubmitBtn.disabled = false;
                        mfaSubmitBtn.innerHTML = originalBtnHtml;
                    }
                });
            }

            // =========================================================================
            // WEBAUTHN / FIDO2 PASSKEY BIOMETRIC SIGN-IN HANDLER
            // =========================================================================
            const passkeyBtn = document.getElementById('passkeySignInBtn');
            if (passkeyBtn) {
                // Utility: Base64URL to ArrayBuffer
                function base64UrlToBuffer(base64Url) {
                    let padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
                    let base64 = (base64Url + padding).replace(/\-/g, '+').replace(/_/g, '/');
                    let rawData = window.atob(base64);
                    let outputArray = new Uint8Array(rawData.length);
                    for (let i = 0; i < rawData.length; ++i) {
                        outputArray[i] = rawData.charCodeAt(i);
                    }
                    return outputArray.buffer;
                }

                // Utility: ArrayBuffer to Base64URL
                function bufferToBase64Url(buffer) {
                    let binary = '';
                    let bytes = new Uint8Array(buffer);
                    for (let i = 0; i < bytes.byteLength; i++) {
                        binary += String.fromCharCode(bytes[i]);
                    }
                    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
                }

                passkeyBtn.addEventListener('click', async function () {
                    // Check browser WebAuthn support
                    if (!window.PublicKeyCredential) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'info',
                                title: 'Passkeys Unsupported',
                                text: 'Your browser or device does not currently support Passkeys or WebAuthn biometrics.'
                            });
                        } else {
                            alert('Passkeys are not supported on this browser.');
                        }
                        return;
                    }

                    const originalPasskeyHtml = passkeyBtn.innerHTML;
                    passkeyBtn.disabled = true;
                    passkeyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Awaiting biometric scan...';

                    try {
                        // 1. Fetch challenge from server
                        const optRes = await fetch('process/passkey_handler.php?action=get_login_options');
                        const optData = await optRes.json();

                        if (!optData.success || !optData.options) {
                            throw new Error(optData.message || 'Failed to initialize biometric challenge.');
                        }

                        const options = optData.options;
                        const publicKeyCredentialRequestOptions = {
                            challenge: base64UrlToBuffer(options.challenge),
                            rpId: options.rpId,
                            timeout: options.timeout || 60000,
                            userVerification: options.userVerification || 'preferred'
                        };

                        // 2. Launch native browser biometric prompt
                        const assertion = await navigator.credentials.get({
                            publicKey: publicKeyCredentialRequestOptions
                        });

                        if (!assertion) {
                            throw new Error('Biometric verification cancelled.');
                        }

                        // 3. Prepare payload for backend verification
                        const verifyPayload = new FormData();
                        verifyPayload.append('action', 'verify_login');
                        verifyPayload.append('id', assertion.id);
                        verifyPayload.append('clientDataJSON', bufferToBase64Url(assertion.response.clientDataJSON));
                        verifyPayload.append('authenticatorData', bufferToBase64Url(assertion.response.authenticatorData));
                        verifyPayload.append('signature', bufferToBase64Url(assertion.response.signature));

                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                        const verRes = await fetch('process/passkey_handler.php', {
                            method: 'POST',
                            body: verifyPayload,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-Token': csrfToken
                            }
                        });

                        const verData = await verRes.json();

                        if (verData.success || verData.status === 'success') {
                            if (typeof Swal !== 'undefined') {
                                await Swal.fire({
                                    icon: 'success',
                                    title: 'Biometric Authenticated!',
                                    text: verData.message || 'Redirecting to your dashboard...',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                            window.location.href = verData.redirect || 'dashboard';
                        } else {
                            throw new Error(verData.message || 'Biometric authentication failed.');
                        }
                    } catch (err) {
                        console.error('Passkey Error:', err);
                        // Do not show error alert if user simply dismissed the system prompt
                        if (err.name !== 'NotAllowedError' && err.name !== 'AbortError') {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Sign In Failed',
                                    text: err.message || 'Could not verify biometric credential.'
                                });
                            } else {
                                alert(err.message || 'Passkey verification failed.');
                            }
                        }
                    } finally {
                        passkeyBtn.disabled = false;
                        passkeyBtn.innerHTML = originalPasskeyHtml;
                    }
                });
            }
        });
    </script>

    <?php if ($is_locked_out && $lockout_retry_after > 0): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let timeLeft = <?= (int) $lockout_retry_after ?>;
                const btn = document.getElementById('signInBtn');
                const timerSpan = document.getElementById('lockTimer');
                const userField = document.getElementById('usernameField');
                const passField = document.getElementById('passwordField');

                function formatTimer(sec) {
                    const m = Math.floor(sec / 60);
                    const s = sec % 60;
                    return `${m}:${s < 10 ? '0' : ''}${s}`;
                }

                if (timerSpan) timerSpan.innerText = formatTimer(timeLeft);

                const countdownInterval = setInterval(function () {
                    timeLeft--;
                    if (timeLeft <= 0) {
                        clearInterval(countdownInterval);
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = 'Login';
                        }
                        if (userField) userField.disabled = false;
                        if (passField) passField.disabled = false;
                        const errBlock = document.getElementById('phpErrorBlock');
                        if (errBlock) {
                            errBlock.style.transition = 'opacity 0.5s ease';
                            errBlock.style.opacity = '0';
                            setTimeout(() => errBlock.remove(), 500);
                        }
                    } else {
                        if (timerSpan) timerSpan.innerText = formatTimer(timeLeft);
                    }
                }, 1000);
            });
        </script>
    <?php endif; ?>
</body>

</html>