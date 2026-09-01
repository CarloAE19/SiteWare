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
$is_locked_out = false;
$lockout_retry_after = 0;

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
    if (defined('DB_OFFLINE')) {
        $error = "Can't connect to database. You're offline.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } elseif (preg_match('/[^a-zA-Z0-9]/', $username)) {
            $error = 'Special characters not allowed in username';
        } elseif (strlen($password) < 8) {
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

            // 🛡️ Bcrypt Password Verification + Session Hijacking Prevention
            if ($user && password_verify($password, $user['password'])) {
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
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    redirectUserByRole($user['role']);
                }
            } else {
                record_rate_limit_attempt($rlKey, true);
                $newRl = check_rate_limit($rlKey, 5, 900, true);
                if (!$newRl['allowed']) {
                    $is_locked_out = true;
                    $lockout_retry_after = $newRl['retry_after'];
                    $mins = ceil($lockout_retry_after / 60);
                    $error = "Too many failed login attempts. Please wait {$mins} minute(s) before trying again.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign In — GB Inventory System</title>
    <meta name="description" content="GB Construction & Enterprise Smart Inventory & Logistics System — Secure Login">

    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
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
            <?php elseif ($error && $error !== 'Special characters not allowed in username'): ?>
                <div class="login-error" id="phpErrorBlock">
                    <i class="bi bi-exclamation-circle-fill"
                        style="font-size:1.1rem; color:var(--gb-red); flex-shrink:0;"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on" id="loginForm">

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
                        <span class="remember-label-text">Remember username</span>
                    </label>
                    <a href="javascript:void(0)" class="forgot-pass-link" data-bs-toggle="modal" data-bs-target="#helpModal" <?= $is_locked_out ? 'tabindex="-1"' : '' ?>>
                        Forgot password?
                    </a>
                </div>

                <!-- PWA Install Button (hidden until browser triggers beforeinstallprompt) -->
                <button type="button" id="installAppBtn" class="btn-install">
                    <i class="bi bi-android2" style="color:#3DDC84;"></i>
                    <i class="bi bi-apple" style="color:#555;"></i>
                    <i class="bi bi-windows" style="color:#0078D7;"></i>
                    Install GB Inventory App
                </button>

                <button type="submit" class="btn-signin" id="signInBtn" <?= (defined('DB_OFFLINE') || $error === 'Special characters not allowed in username' || $is_locked_out) ? 'disabled' : '' ?>>
                    <?php if ($is_locked_out): ?>
                        <i class="bi bi-lock-fill"></i> Locked (<span id="lockTimer">--:--</span>)
                    <?php else: ?>
                        Login
                    <?php endif; ?>
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
                        <div class="d-flex align-items-center justify-content-center bg-primary-subtle rounded-3 text-primary" style="width: 42px; height: 42px; font-size: 1.25rem;">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="helpModalLabel" style="font-size: 1.1rem;">Need Help Signing In?</h5>
                            <small class="text-muted" style="font-size: 0.8rem;">Account Support</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <p class="text-secondary small mb-3" style="line-height: 1.6;">
                        If you forgot your password or are having trouble accessing your account, please reach out directly to your <strong>system administrator</strong> or <strong>IT support</strong>.
                    </p>

                    <div class="bg-light p-3 rounded-3 mb-0 border">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-info-circle-fill text-primary flex-shrink-0 mt-1" style="font-size: 0.95rem;"></i>
                            <small class="text-muted" style="font-size: 0.8rem; line-height: 1.45;">
                                For your security, credential resets and account unlocks must be verified and issued by an authorized administrator.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2 bg-white">
                    <button type="button" class="btn btn-brand w-100 fw-bold py-2 shadow-sm" data-bs-dismiss="modal" style="border-radius: 10px;">
                        <i class="bi bi-check2 me-1"></i> Got It
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Login Scripts -->
    <script src="assets/js/login.js?v=<?= time() ?>"></script>

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