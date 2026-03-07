<?php
// ==========================================
// SUPPLIERS LOGIC
// ==========================================

if ($action === 'add_supplier') {
    if (!in_array($_SESSION['user_role'], ['admin', 'purchasing'])) throw new Exception("Unauthorized action.");
    $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_code, company_name, contact_person, contact_number, email, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['supplier_code'], $_POST['company_name'], $_POST['contact_person'], $_POST['contact_number'], $_POST['email'], $_POST['address'], $_POST['status']]);
    $_SESSION['message'] = "Supplier added successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../suppliers"); 
    exit;

} elseif ($action === 'edit_supplier') {
    if (!in_array($_SESSION['user_role'], ['admin', 'purchasing'])) throw new Exception("Unauthorized action.");
    $stmt = $pdo->prepare("UPDATE suppliers SET supplier_code=?, company_name=?, contact_person=?, contact_number=?, email=?, address=?, status=? WHERE id=?");
    $stmt->execute([$_POST['supplier_code'], $_POST['company_name'], $_POST['contact_person'], $_POST['contact_number'], $_POST['email'], $_POST['address'], $_POST['status'], $_POST['id']]);
    $_SESSION['message'] = "Supplier updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../suppliers"); 
    exit;

} elseif ($action === 'delete_supplier') {
    if (!in_array($_SESSION['user_role'], ['admin', 'purchasing'])) throw new Exception("Unauthorized action.");
    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $_SESSION['message'] = "Supplier deleted.";
    $_SESSION['msg_type'] = "danger";
    header("Location: ../suppliers"); 
    exit;
}
?>