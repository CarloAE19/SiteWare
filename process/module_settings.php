<?php
// ==========================================
// DYNAMIC UNITS & CATEGORIES LOGIC
// ==========================================

// --- UNITS LOGIC ---
if ($action === 'add_unit') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("INSERT INTO units (unit_name, abbreviation, reorder_level) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['unit_name'], $_POST['abbreviation'], (int)($_POST['reorder_level'] ?? 10)]);
    $_SESSION['message'] = "Measurement unit added successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../units"); 
    exit;

} elseif ($action === 'edit_unit') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("UPDATE units SET unit_name = ?, abbreviation = ?, reorder_level = ? WHERE id = ?");
    $stmt->execute([$_POST['unit_name'], $_POST['abbreviation'], (int)($_POST['reorder_level'] ?? 10), $_POST['unit_id']]);
    $_SESSION['message'] = "Unit updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../units"); 
    exit;

} elseif ($action === 'delete_unit') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("DELETE FROM units WHERE id = ?");
    $stmt->execute([$_POST['unit_id']]);
    $_SESSION['message'] = "Unit deleted successfully.";
    $_SESSION['msg_type'] = "danger";
    header("Location: ../units"); 
    exit;
}

// --- CATEGORIES LOGIC ---
elseif ($action === 'add_category') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
    $stmt->execute([trim($_POST['category_name'])]);
    $_SESSION['message'] = "Category added successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../categories"); 
    exit;

} elseif ($action === 'edit_category') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("UPDATE categories SET category_name = ? WHERE id = ?");
    $stmt->execute([trim($_POST['category_name']), $_POST['category_id']]);
    $_SESSION['message'] = "Category updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../categories"); 
    exit;

} elseif ($action === 'delete_category') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$_POST['category_id']]);
    $_SESSION['message'] = "Category deleted successfully.";
    $_SESSION['msg_type'] = "danger";
    header("Location: ../categories"); 
    exit;
}

// --- PROJECTS LOGIC ---
elseif ($action === 'add_project') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("INSERT INTO projects (project_code, project_name, description, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([trim($_POST['project_code']), trim($_POST['project_name']), trim($_POST['description']), $_POST['status']]);
    $_SESSION['message'] = "Project added successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../projects"); 
    exit;

} elseif ($action === 'edit_project') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("UPDATE projects SET project_code = ?, project_name = ?, description = ?, status = ? WHERE id = ?");
    $stmt->execute([trim($_POST['project_code']), trim($_POST['project_name']), trim($_POST['description']), $_POST['status'], $_POST['project_id']]);
    $_SESSION['message'] = "Project updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../projects"); 
    exit;

} elseif ($action === 'delete_project') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$_POST['project_id']]);
    $_SESSION['message'] = "Project deleted successfully.";
    $_SESSION['msg_type'] = "danger";
    header("Location: ../projects"); 
    exit;

// --- LOGIN BACKGROUND LOGIC ---
} elseif ($action === 'update_login_bg') {
    if (!in_array($_SESSION['user_role'], ['admin', 'management'])) {
        throw new Exception("Unauthorized. Only Admins and Management can change settings.");
    }
    
    if (!isset($_FILES['login_bg']) || $_FILES['login_bg']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed or no file selected.");
    }

    $file = $_FILES['login_bg'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception("File size exceeds 5MB limit.");
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $fileMime = mime_content_type($file['tmp_name']);
    if (!in_array($fileMime, $allowedTypes)) {
        throw new Exception("Invalid file type. Only JPEG, PNG, WEBP, and GIF images are allowed.");
    }

    // Determine file extension
    $ext = 'jpg';
    if ($fileMime === 'image/png') $ext = 'png';
    elseif ($fileMime === 'image/webp') $ext = 'webp';
    elseif ($fileMime === 'image/gif') $ext = 'gif';

    // Target upload directory
    $uploadDir = '../assets/img/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Retrieve old background to delete it
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'login_background'");
    $stmt->execute();
    $oldBg = $stmt->fetchColumn();

    $newFilename = 'custom_login_bg_' . time() . '.' . $ext;
    $newPath = 'assets/img/' . $newFilename;
    $uploadPath = $uploadDir . $newFilename;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Delete old file if it is custom and exists
        if ($oldBg && $oldBg !== 'assets/img/default_login_bg.png' && file_exists('../' . $oldBg)) {
            unlink('../' . $oldBg);
        }

        // Save path to DB
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('login_background', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$newPath, $newPath]);

        $_SESSION['message'] = "Login background updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        throw new Exception("Failed to save uploaded file.");
    }

    header("Location: ../profile");
    exit;

} elseif ($action === 'reset_login_bg') {
    if (!in_array($_SESSION['user_role'], ['admin', 'management'])) {
        throw new Exception("Unauthorized. Only Admins and Management can change settings.");
    }

    // Retrieve old background to delete it
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'login_background'");
    $stmt->execute();
    $oldBg = $stmt->fetchColumn();

    // Delete custom bg if exists
    if ($oldBg && $oldBg !== 'assets/img/default_login_bg.png' && file_exists('../' . $oldBg)) {
        unlink('../' . $oldBg);
    }

    // Reset path in DB to default
    $defaultPath = 'assets/img/default_login_bg.png';
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('login_background', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$defaultPath, $defaultPath]);

    $_SESSION['message'] = "Login background reset to default successfully.";
    $_SESSION['msg_type'] = "success";
    header("Location: ../profile");
    exit;
}
?>