<?php
// =========================================================================
// SUPPLIERS CONTROLLER & VIBER LOGISTICS
// Complies with CIMS Modal AJAX Handler & Enterprise Quality Standards
// =========================================================================

// Enforce Server-Side Authorization (Rule 5 RBAC)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'purchasing'])) {
    throw new Exception("Unauthorized action: Insufficient permissions to manage suppliers.");
}

// Enforce CSRF Token Verification (Rule 5)
$clientCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validate_csrf_token($clientCsrf)) {
    throw new Exception("Security validation failed (Invalid or expired CSRF token). Please refresh and try again.");
}

if ($action === 'add_supplier') {
    $code = trim($_POST['supplier_code'] ?? '');
    $company = trim($_POST['company_name'] ?? '');
    $person = trim($_POST['contact_person'] ?? '');
    $rawPhone = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    if (empty($code) || empty($company) || empty($person) || empty($rawPhone)) {
        throw new Exception("Supplier code, company name, contact person, and contact number are required.");
    }

    // Validate and normalize Philippine phone number for Viber compatibility
    $normalizedViber = normalizeViberPhone($rawPhone);
    if (!$normalizedViber) {
        throw new Exception("Invalid contact number. Please provide a valid Philippine mobile number (e.g., 0917-123-4567 or +63 917 123 4567) so Viber direct messaging functions properly.");
    }

    // Store in clean display format (+63 9XX XXX XXXX)
    $formattedContact = formatPhilippinePhone($normalizedViber, true);

    $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_code, company_name, contact_person, contact_number, email, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code, $company, $person, $formattedContact, $email, $address, $status]);

    $msg = "Supplier '{$company}' added successfully!";
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => $msg,
            'data' => [
                'supplier_code' => $code,
                'company_name' => $company,
                'viber_phone' => $normalizedViber
            ]
        ]);
        exit;
    }

    $_SESSION['message'] = $msg;
    $_SESSION['msg_type'] = "success";
    header("Location: ../suppliers");
    exit;

} elseif ($action === 'edit_supplier') {
    $id = (int) ($_POST['id'] ?? 0);
    $code = trim($_POST['supplier_code'] ?? '');
    $company = trim($_POST['company_name'] ?? '');
    $person = trim($_POST['contact_person'] ?? '');
    $rawPhone = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    if ($id <= 0 || empty($code) || empty($company) || empty($person) || empty($rawPhone)) {
        throw new Exception("Invalid parameters: All required supplier fields must be provided.");
    }

    // Validate and normalize Philippine phone number for Viber compatibility
    $normalizedViber = normalizeViberPhone($rawPhone);
    if (!$normalizedViber) {
        throw new Exception("Invalid contact number. Please provide a valid Philippine mobile number (e.g., 0917-123-4567 or +63 917 123 4567) so Viber direct messaging functions properly.");
    }

    $formattedContact = formatPhilippinePhone($normalizedViber, true);

    $stmt = $pdo->prepare("UPDATE suppliers SET supplier_code=?, company_name=?, contact_person=?, contact_number=?, email=?, address=?, status=? WHERE id=?");
    $stmt->execute([$code, $company, $person, $formattedContact, $email, $address, $status, $id]);

    $msg = "Supplier '{$company}' updated successfully!";
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => $msg,
            'data' => [
                'id' => $id,
                'company_name' => $company,
                'viber_phone' => $normalizedViber
            ]
        ]);
        exit;
    }

    $_SESSION['message'] = $msg;
    $_SESSION['msg_type'] = "success";
    header("Location: ../suppliers");
    exit;

} elseif ($action === 'delete_supplier') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception("Invalid supplier ID specified.");

    // Fetch supplier info first to confirm existence & get company name
    $supCheck = $pdo->prepare("SELECT company_name FROM suppliers WHERE id = ?");
    $supCheck->execute([$id]);
    $supRow = $supCheck->fetch(PDO::FETCH_ASSOC);
    if (!$supRow) {
        throw new Exception("Supplier not found or has already been deleted.");
    }
    $companyName = $supRow['company_name'];

    // Check for linked purchase orders (ISO 9001 / Traceability & Foreign Key Protection)
    $poCheck = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ?");
    $poCheck->execute([$id]);
    $poCount = (int) $poCheck->fetchColumn();

    if ($poCount > 0) {
        throw new Exception("Cannot delete '{$companyName}' because {$poCount} purchase order(s) are linked to this supplier. Please set their status to 'Inactive' instead to preserve audit traceability.");
    }

    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);

    $msg = "Supplier '{$companyName}' was deleted successfully.";
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => $msg,
            'data' => ['id' => $id, 'company_name' => $companyName]
        ]);
        exit;
    }

    $_SESSION['message'] = $msg;
    $_SESSION['msg_type'] = "success";
    header("Location: ../suppliers");
    exit;
}