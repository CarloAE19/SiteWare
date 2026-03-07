<?php
// ==========================================
// DYNAMIC UNITS LOGIC
// ==========================================

if ($action === 'add_unit') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("INSERT INTO units (unit_name, abbreviation) VALUES (?, ?)");
    $stmt->execute([$_POST['unit_name'], $_POST['abbreviation']]);
    $_SESSION['message'] = "Measurement unit added successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../units"); 
    exit;

} elseif ($action === 'edit_unit') {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("UPDATE units SET unit_name = ?, abbreviation = ? WHERE id = ?");
    $stmt->execute([$_POST['unit_name'], $_POST['abbreviation'], $_POST['unit_id']]);
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
?>