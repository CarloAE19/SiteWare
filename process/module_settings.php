<?php
// ==========================================
// DYNAMIC UNITS & CATEGORIES LOGIC
// ==========================================

// --- UNITS LOGIC ---
if ($action === 'add_unit') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    
    $unitName = trim($_POST['unit_name'] ?? '');
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    $reorderLevel = max(1, (int)($_POST['reorder_level'] ?? 10));

    if (empty($unitName) || empty($abbreviation)) {
        $_SESSION['message'] = "Unit name and abbreviation are required.";
        $_SESSION['msg_type'] = "warning";
        header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'units'));
        exit;
    }

    // Check for duplicate name or abbreviation (case-insensitive)
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM units WHERE LOWER(unit_name) = LOWER(?) OR LOWER(abbreviation) = LOWER(?)");
    $checkStmt->execute([$unitName, $abbreviation]);
    if ($checkStmt->fetchColumn() > 0) {
        $_SESSION['message'] = "A measurement unit with that name or abbreviation already exists.";
        $_SESSION['msg_type'] = "warning";
        header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'units'));
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO units (unit_name, abbreviation, reorder_level) VALUES (?, ?, ?)");
    $stmt->execute([$unitName, $abbreviation, $reorderLevel]);
    $_SESSION['message'] = "Measurement unit '{$unitName}' added successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'units')); 
    exit;

} elseif ($action === 'edit_unit') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");

    $unitId = (int)($_POST['unit_id'] ?? 0);
    $unitName = trim($_POST['unit_name'] ?? '');
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    $reorderLevel = max(1, (int)($_POST['reorder_level'] ?? 10));

    if ($unitId <= 0 || empty($unitName) || empty($abbreviation)) {
        $_SESSION['message'] = "Invalid unit details provided.";
        $_SESSION['msg_type'] = "warning";
        header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'units'));
        exit;
    }

    // Check for duplicate name or abbreviation excluding current record
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM units WHERE (LOWER(unit_name) = LOWER(?) OR LOWER(abbreviation) = LOWER(?)) AND id != ?");
    $checkStmt->execute([$unitName, $abbreviation, $unitId]);
    if ($checkStmt->fetchColumn() > 0) {
        $_SESSION['message'] = "Another unit with that name or abbreviation already exists.";
        $_SESSION['msg_type'] = "warning";
        header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'units'));
        exit;
    }

    // Fetch existing unit name to cascade to inventory table if renamed
    $fetchStmt = $pdo->prepare("SELECT unit_name FROM units WHERE id = ?");
    $fetchStmt->execute([$unitId]);
    $oldUnitName = $fetchStmt->fetchColumn();

    $stmt = $pdo->prepare("UPDATE units SET unit_name = ?, abbreviation = ?, reorder_level = ? WHERE id = ?");
    $stmt->execute([$unitName, $abbreviation, $reorderLevel, $unitId]);

    // Cascade rename to inventory table so low-stock joins and listings remain intact
    if ($oldUnitName && $oldUnitName !== $unitName) {
        $cascadeStmt = $pdo->prepare("UPDATE inventory SET unit = ? WHERE unit = ?");
        $cascadeStmt->execute([$unitName, $oldUnitName]);
    }

    $_SESSION['message'] = "Unit '{$unitName}' updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'units')); 
    exit;

} elseif ($action === 'delete_unit') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");

    $unitId = (int)($_POST['unit_id'] ?? 0);
    $fetchStmt = $pdo->prepare("SELECT unit_name FROM units WHERE id = ?");
    $fetchStmt->execute([$unitId]);
    $unitName = $fetchStmt->fetchColumn();

    if ($unitName) {
        // Safe check: verify if any inventory items are actively using this unit
        $usageStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE unit = ?");
        $usageStmt->execute([$unitName]);
        $usageCount = (int)$usageStmt->fetchColumn();

        if ($usageCount > 0) {
            $_SESSION['message'] = "Cannot delete unit '<b>" . htmlspecialchars($unitName) . "</b>': It is currently assigned to <b>{$usageCount}</b> inventory item(s). Please reassign those items before deleting.";
            $_SESSION['msg_type'] = "danger";
            header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'units'));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM units WHERE id = ?");
        $stmt->execute([$unitId]);
        $_SESSION['message'] = "Unit '<b>" . htmlspecialchars($unitName) . "</b>' deleted successfully.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Unit not found.";
        $_SESSION['msg_type'] = "warning";
    }

    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'units')); 
    exit;
}

// --- CATEGORIES LOGIC ---
elseif ($action === 'add_category') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
    $stmt->execute([trim($_POST['category_name'])]);
    $_SESSION['message'] = "Category added successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'categories')); 
    exit;

} elseif ($action === 'edit_category') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("UPDATE categories SET category_name = ? WHERE id = ?");
    $stmt->execute([trim($_POST['category_name']), $_POST['category_id']]);
    $_SESSION['message'] = "Category updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'categories')); 
    exit;

} elseif ($action === 'delete_category') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$_POST['category_id']]);
    $_SESSION['message'] = "Category deleted successfully.";
    $_SESSION['msg_type'] = "danger";
    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'categories')); 
    exit;
}

// --- PROJECTS LOGIC ---
elseif ($action === 'add_project') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    
    $projectCode = trim($_POST['project_code'] ?? '');
    $projectName = trim($_POST['project_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if (empty($projectName)) {
        $_SESSION['message'] = "Project Name is required.";
        $_SESSION['msg_type'] = "warning";
        header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects'));
        exit;
    }

    // Check duplicate Project Name (case-insensitive)
    $dupNameStmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE LOWER(project_name) = LOWER(?)");
    $dupNameStmt->execute([$projectName]);
    if ($dupNameStmt->fetchColumn() > 0) {
        $_SESSION['message'] = "A project with the name '<b>" . htmlspecialchars($projectName) . "</b>' already exists.";
        $_SESSION['msg_type'] = "warning";
        header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects'));
        exit;
    }

    // Auto-generate Project Code if not provided
    if (empty($projectCode)) {
        $year = date('Y');
        $countStmt = $pdo->query("SELECT COUNT(*) FROM projects");
        $nextNum = ((int)$countStmt->fetchColumn()) + 1;
        $projectCode = sprintf("PRJ-%s-%03d", $year, $nextNum);

        // Guarantee uniqueness
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE project_code = ?");
        $checkStmt->execute([$projectCode]);
        if ($checkStmt->fetchColumn() > 0) {
            $projectCode = sprintf("PRJ-%s-%04d", $year, rand(1000, 9999));
        }
    } else {
        // Validate uniqueness of custom Project Code
        $dupCodeStmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE LOWER(project_code) = LOWER(?)");
        $dupCodeStmt->execute([$projectCode]);
        if ($dupCodeStmt->fetchColumn() > 0) {
            $_SESSION['message'] = "A project with the ID '<b>" . htmlspecialchars($projectCode) . "</b>' already exists.";
            $_SESSION['msg_type'] = "warning";
            header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects'));
            exit;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO projects (project_code, project_name, address, description, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$projectCode, $projectName, $address, $description, $status]);
    $_SESSION['message'] = "Project '<b>" . htmlspecialchars($projectName) . "</b>' (ID: {$projectCode}) added successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects')); 
    exit;

} elseif ($action === 'edit_project') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    
    $projectId = (int)($_POST['project_id'] ?? 0);
    $projectCode = trim($_POST['project_code'] ?? '');
    $projectName = trim($_POST['project_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if ($projectId <= 0 || empty($projectName)) {
        $_SESSION['message'] = "Invalid project information provided.";
        $_SESSION['msg_type'] = "warning";
        header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects'));
        exit;
    }

    // Check duplicate Project Name excluding current record
    $dupNameStmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE LOWER(project_name) = LOWER(?) AND id != ?");
    $dupNameStmt->execute([$projectName, $projectId]);
    if ($dupNameStmt->fetchColumn() > 0) {
        $_SESSION['message'] = "Another project with the name '<b>" . htmlspecialchars($projectName) . "</b>' already exists.";
        $_SESSION['msg_type'] = "warning";
        header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects'));
        exit;
    }

    if (empty($projectCode)) {
        $year = date('Y');
        $projectCode = sprintf("PRJ-%s-%03d", $year, $projectId);
    } else {
        // Check duplicate Project Code excluding current record
        $dupCodeStmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE LOWER(project_code) = LOWER(?) AND id != ?");
        $dupCodeStmt->execute([$projectCode, $projectId]);
        if ($dupCodeStmt->fetchColumn() > 0) {
            $_SESSION['message'] = "Another project with ID '<b>" . htmlspecialchars($projectCode) . "</b>' already exists.";
            $_SESSION['msg_type'] = "warning";
            header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects'));
            exit;
        }
    }

    // Fetch previous project name to cascade renames to requisitions and withdrawals
    $fetchOldStmt = $pdo->prepare("SELECT project_name FROM projects WHERE id = ?");
    $fetchOldStmt->execute([$projectId]);
    $oldProjectName = $fetchOldStmt->fetchColumn();

    $stmt = $pdo->prepare("UPDATE projects SET project_code = ?, project_name = ?, address = ?, description = ?, status = ? WHERE id = ?");
    $stmt->execute([$projectCode, $projectName, $address, $description, $status, $projectId]);

    // Cascade rename to linked requisitions and withdrawals so history remains connected
    if ($oldProjectName && $oldProjectName !== $projectName) {
        $cascadeRsStmt = $pdo->prepare("UPDATE requisitions SET project_name = ? WHERE project_name = ?");
        $cascadeRsStmt->execute([$projectName, $oldProjectName]);

        $cascadeWsStmt = $pdo->prepare("UPDATE withdrawals SET project_name = ? WHERE project_name = ?");
        $cascadeWsStmt->execute([$projectName, $oldProjectName]);
    }

    $_SESSION['message'] = "Project '<b>" . htmlspecialchars($projectName) . "</b>' updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects')); 
    exit;

} elseif ($action === 'toggle_project_status') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");

    $projectId = (int)($_POST['project_id'] ?? 0);
    $fetchStmt = $pdo->prepare("SELECT project_name, status FROM projects WHERE id = ?");
    $fetchStmt->execute([$projectId]);
    $project = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if ($project) {
        $newStatus = ($project['status'] === 'active') ? 'inactive' : 'active';
        $updateStmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
        $updateStmt->execute([$newStatus, $projectId]);

        $statusBadge = ($newStatus === 'active') ? 'Active' : 'Inactive';
        $_SESSION['message'] = "Project '<b>" . htmlspecialchars($project['project_name']) . "</b>' marked as <b>{$statusBadge}</b>.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Project not found.";
        $_SESSION['msg_type'] = "warning";
    }

    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects'));
    exit;

} elseif ($action === 'delete_project') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");

    $projectId = (int)($_POST['project_id'] ?? 0);
    $fetchStmt = $pdo->prepare("SELECT project_name FROM projects WHERE id = ?");
    $fetchStmt->execute([$projectId]);
    $projectName = $fetchStmt->fetchColumn();

    if ($projectName) {
        // Safe check: verify if any requisitions or withdrawals reference this project
        $rsStmt = $pdo->prepare("SELECT COUNT(*) FROM requisitions WHERE project_name = ?");
        $rsStmt->execute([$projectName]);
        $rsCount = (int)$rsStmt->fetchColumn();

        $wsStmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawals WHERE project_name = ?");
        $wsStmt->execute([$projectName]);
        $wsCount = (int)$wsStmt->fetchColumn();

        $totalUsage = $rsCount + $wsCount;

        if ($totalUsage > 0) {
            $_SESSION['message'] = "Cannot delete project '<b>" . htmlspecialchars($projectName) . "</b>': It has <b>{$rsCount}</b> linked Requisition(s) and <b>{$wsCount}</b> linked Withdrawal(s). Please set its status to <b>Inactive</b> instead of deleting to preserve audit history.";
            $_SESSION['msg_type'] = "danger";
            header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects'));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $_SESSION['message'] = "Project '<b>" . htmlspecialchars($projectName) . "</b>' deleted successfully.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['message'] = "Project not found.";
        $_SESSION['msg_type'] = "warning";
    }

    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'projects')); 
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

    $targetRedirect = !empty($_POST['return_tab']) ? "../settings?tab=" . urlencode($_POST['return_tab']) : "../profile";
    header("Location: " . $targetRedirect);
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
    $targetRedirect = !empty($_POST['return_tab']) ? "../settings?tab=" . urlencode($_POST['return_tab']) : "../profile";
    header("Location: " . $targetRedirect);
    exit;

// --- LOGIN BLUR INTENSITY ---
} elseif ($action === 'update_login_blur') {
    if (!in_array($_SESSION['user_role'], ['admin', 'management'])) {
        throw new Exception("Unauthorized. Only Admins and Management can change settings.");
    }

    $blur = isset($_POST['login_blur']) ? (int)$_POST['login_blur'] : 12;
    $blur = max(0, min(30, $blur)); // clamp 0–30

    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('login_blur', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$blur, $blur]);

    $_SESSION['message'] = "Blur intensity updated to {$blur}px successfully!";
    $_SESSION['msg_type'] = "success";
    $targetRedirect = !empty($_POST['return_tab']) ? "../settings?tab=" . urlencode($_POST['return_tab']) : "../profile";
    header("Location: " . $targetRedirect);
    exit;
}
?>