<?php
session_start();
require_once 'Connection/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            header("Location: index");
            exit;
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
    <title>Login - GB Inventory</title>
    <link rel="icon" type="image/png" href="assets/LogoGB.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">

    <div class="brand-logo text-center">
        <img src="assets/LogoGB.png" alt="GB Construction Logo" style="width: 100px; height: auto;" >
        <div class="mt-2">GB Construction & Enterprises</div>
    </div>

    <div class="login-card">
        <div class="login-title">Account Login</div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 fs-6 text-center"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-bold" style="font-size: 0.9rem;">Username <span class="required-asterisk">*</span></label>
                <input type="text" name="username" class="form-control" placeholder="Enter username" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold" style="font-size: 0.9rem;">Password <span class="required-asterisk">*</span></label>
                <div class="input-group">
                    <input type="password" name="password" id="passwordField" class="form-control border-end-0" placeholder="Your password" required>
                    <button class="btn btn-toggle-pass border-start-0" type="button" id="togglePassword">
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-login"><i class="bi bi-box-arrow-in-right me-2"></i>Sign in</button>
        </form>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>