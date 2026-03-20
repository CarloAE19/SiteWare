<?php
session_start();
require_once 'Connection/db.php';

// Smart Router: Decides where a user goes based on their role
function redirectUserByRole($role)
{
    switch ($role) {
        case 'admin':
        case 'management':
            header("Location: analytics");
            break;
        case 'purchasing':
            header("Location: po");
            break;
        case 'requestor':
            header("Location: requisitions");
            break;
        case 'warehouse':
        default:
            header("Location: index");
            break;
    }
    exit;
}

// If already logged in, route them to their workspace
if (isset($_SESSION['user_id'])) {
    redirectUserByRole($_SESSION['user_role']);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (preg_match('/[^a-zA-Z0-9]/', $username)) {
        $error = 'Special characters not allowed in username';
    } else {
        // 🛡️ SQL Injection Prevention (Prepared Statements)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 🛡️ Bcrypt Password Verification + Session Hijacking Prevention
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            redirectUserByRole($user['role']);
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Login Styles -->
    <link rel="stylesheet" href="assets/css/login.css?v=<?= time() ?>">
</head>

<body>

    <div class="login-wrapper">

        <!-- ============ LEFT: BRAND PANEL ============ -->
        <div class="brand-panel">
            <div class="top-stripe"></div>

            <div class="brand-logo-wrap">
                <img src="assets/LogoGB.png" alt="GB Construction Logo">
                <div class="brand-name">
                    <span>GB Construction &amp;</span>
                    <strong>Enterprise Inc.</strong>
                </div>
            </div>

            <div class="brand-headline">
                <h1>Smart <span>Inventory</span></h1>
                <p>A real-time enterprise platform built for the construction supply chain — from warehouse to project site.</p>
            </div>

            <ul class="brand-features">
                <li>
                    <div class="feat-icon blue"><i class="bi bi-qr-code-scan"></i></div>
                    QR Code Scanning for rapid stock-in/out
                </li>
                <li>
                    <div class="feat-icon yellow"><i class="bi bi-graph-up-arrow"></i></div>
                    AI-powered analytics &amp; restock predictions
                </li>
                <li>
                    <div class="feat-icon red"><i class="bi bi-bell-fill"></i></div>
                    Live push notifications via Firebase FCM
                </li>
                <li>
                    <div class="feat-icon green"><i class="bi bi-clipboard-check-fill"></i></div>
                    Weekly physical recount &amp; audit trails
                </li>
            </ul>
        </div>

        <!-- ============ RIGHT: FORM PANEL ============ -->
        <div class="form-panel">
            <div class="login-card">

                <div class="card-eyebrow"><i class="bi bi-shield-lock-fill me-1"></i> Secure Access</div>
                <h2>Welcome back</h2>
                <p class="subtitle">Sign in to your workspace to continue.</p>

                <?php if ($error && $error !== 'Special characters not allowed in username'): ?>
                    <div class="login-error" id="phpErrorBlock">
                        <i class="bi bi-exclamation-circle-fill" style="font-size:1.1rem; color:var(--gb-red); flex-shrink:0;"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="on">

                    <div class="input-float <?= ($error === 'Special characters not allowed in username') ? 'has-error' : '' ?>" id="usernameFloat">
                        <label for="usernameField">Username</label>
                        <input type="text" id="usernameField" name="username"
                            placeholder="Enter your username"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            autocomplete="username" required>
                        <i class="bi bi-person-fill field-icon"></i>
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
                        <input type="password" id="passwordField" name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password" required>
                        <i class="bi bi-lock-fill field-icon"></i>
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

                    <button type="submit" class="btn-signin" id="signInBtn" <?= ($error === 'Special characters not allowed in username') ? 'disabled' : '' ?>>
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </button>

                </form>

                <div class="form-footer">
                    &copy; <?= date('Y') ?> Genetian Builders &amp; Enterprises Inc. &nbsp;|&nbsp; Powered by The MedYas
                </div>

            </div>
        </div>
    </div>

    <!-- Login Scripts -->
    <script src="assets/js/login.js?v=<?= time() ?>"></script>
</body>

</html>