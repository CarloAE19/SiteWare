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
}
?>