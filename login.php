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
if (defined('DB_OFFLINE')) {
    $error = "Can't connect to database. You're offline.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $error = 'Invalid username or password.';
        } else {
            // 🛡️ SQL Injection Prevention (Prepared Statements)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // 🛡️ Bcrypt Password Verification + Session Hijacking Prevention
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                redirectUserByRole($user['role']);
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}

$bg_image = 'assets/img/default_login_bg.png';
if (!defined('DB_OFFLINE') && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'login_background'");
        $stmt->execute();
        $custom_bg = $stmt->fetchColumn();
        if ($custom_bg) {
            $bg_image = $custom_bg;
        }
    } catch (Exception $e) {
        // Fallback
    }
}
// Build a root-relative URL so it resolves correctly even with clean URLs (e.g. /CIMS/login vs /CIMS/login.php)
// dirname($_SERVER['PHP_SELF']) gives /CIMS when login.php is at /CIMS/login.php
$app_base = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$bg_version = file_exists($bg_image) ? filemtime($bg_image) : time();
$bg_image_url = $app_base . '/' . ltrim($bg_image, '/') . '?v=' . $bg_version;

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
        <div class="login-bg-container" style="background-image: url('<?= htmlspecialchars($bg_image_url) ?>');"></div>

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

            <form method="POST" action="" autocomplete="on">

                <div class="input-float <?= ($error === 'Special characters not allowed in username') ? 'has-error' : '' ?>"
                    id="usernameFloat">
                    <label for="usernameField">Username</label>
                    <input type="text" id="usernameField" name="username" placeholder="Enter you username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required>
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
                        autocomplete="current-password" required>
                    <i class="bi bi-lock field-icon"></i>
                    <button type="button" class="toggle-pass" onclick="togglePass()" aria-label="Toggle password">
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </button>
                </div>

                <!-- PWA Install Button (hidden until browser triggers beforeinstallprompt) -->
                <button type="button" id="installAppBtn" class="btn-install">
                    <i class="bi bi-android2" style="color:#3DDC84;"></i>
                    <i class="bi bi-apple" style="color:#555;"></i>
                    <i class="bi bi-windows" style="color:#0078D7;"></i>
                    Install GB Inventory App
                </button>

                <button type="submit" class="btn-signin" id="signInBtn" <?= (defined('DB_OFFLINE') || $error === 'Special characters not allowed in username') ? 'disabled' : '' ?>>
                    Login
                </button>

            </form>

        </div>

        <div class="form-footer">
            &copy; <?= date('Y') ?> Genetian Builders &amp; Enterprises Inc. &nbsp;|&nbsp; Powered by <a href="about"
                class="text-decoration-none fw-bold" style="color: var(--gb-blue) !important;">The Medyas</a>
        </div>
    </div>

    <!-- Login Scripts -->
    <script src="assets/js/login.js?v=<?= time() ?>"></script>
</body>

</html>