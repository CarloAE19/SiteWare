<?php
// ============================================================================
// SUPPLIER INQUIRY ACTIONS
// Handles Pre-PO supplier stock inquiries, token link generation, and responses
// ============================================================================

if (!isset($pdo)) {
    require_once __DIR__ . '/../../Connection/db.php';
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// CSRF Validation for state-modifying requests
if (in_array($_POST['action'] ?? '', ['create_supplier_inquiry', 'cancel_supplier_inquiry'])) {
    $clientCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($clientCsrf) && function_exists('validate_csrf_token') && !validate_csrf_token($clientCsrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Security token invalid or expired. Please refresh the page.']);
        exit;
    }
}

$action = $_POST['action'] ?? '';

// --- 1. CREATE SUPPLIER INQUIRY & GENERATE SECURE TOKEN LINK ---
if ($action === 'create_supplier_inquiry') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized permission.']);
        exit;
    }

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $rs_id = !empty($_POST['rs_id']) ? (int)$_POST['rs_id'] : null;
    $destination = trim($_POST['delivery_destination'] ?? 'Central Warehouse (Main Storage)');
    $expected_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $itemsJson = $_POST['items'] ?? '[]';
    $items = is_array($itemsJson) ? $itemsJson : json_decode($itemsJson, true);

    if ($supplier_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Valid supplier is required.']);
        exit;
    }

    if (empty($items) || !is_array($items)) {
        echo json_encode(['status' => 'error', 'message' => 'At least one material item is required for the inquiry.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Generate unique inquiry number
        $datePrefix = date('Ymd');
        $randomSuffix = strtoupper(bin2hex(random_bytes(2)));
        $inquiry_no = "INQ-{$datePrefix}-{$randomSuffix}";

        // Cryptographically random 48-char hex token
        $token = bin2hex(random_bytes(24));
        $expires_at = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $stmt = $pdo->prepare("
            INSERT INTO supplier_inquiries (
                inquiry_no, token, supplier_id, rs_id, created_by, 
                status, notes, delivery_destination, expected_delivery_date, 
                expires_at
            ) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $inquiry_no,
            $token,
            $supplier_id,
            $rs_id,
            $_SESSION['user_id'],
            $notes,
            $destination,
            $expected_date,
            $expires_at
        ]);
        $inquiry_id = (int)$pdo->lastInsertId();

        // Insert Inquiry Items
        $itemStmt = $pdo->prepare("
            INSERT INTO supplier_inquiry_items (
                inquiry_id, item_code, item_name, unit, 
                requested_qty, estimated_price, availability_status
            ) VALUES (?, ?, ?, ?, ?, ?, 'Pending')
        ");

        foreach ($items as $item) {
            $code = trim($item['item_code'] ?? 'ITEM');
            $name = trim($item['item_name'] ?? $code);
            $unit = trim($item['unit'] ?? 'pcs');
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $price = max(0, (float)($item['price'] ?? 0));

            $itemStmt->execute([
                $inquiry_id,
                $code,
                $name,
                $unit,
                $qty,
                $price
            ]);
        }

        $pdo->commit();

        // Build absolute public portal URL
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $scheme = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Compute base directory relative to webroot
        $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $baseDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
        // Handle when scriptDir is /process or nested
        if (substr($baseDir, -8) === '/process') {
            $baseDir = substr($baseDir, 0, -8);
        }
        $portalUrl = $scheme . $host . $baseDir . '/supplier_inquiry/' . $token;

        echo json_encode([
            'status' => 'success',
            'inquiry_id' => $inquiry_id,
            'inquiry_no' => $inquiry_no,
            'token' => $token,
            'portal_url' => $portalUrl,
            'expires_at' => date('M d, Y h:i A', strtotime($expires_at))
        ]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Failed to create inquiry: ' . $e->getMessage()]);
        exit;
    }
}

// --- 2. FETCH SUPPLIER INQUIRY DETAILS (FOR CIMS PURCHASING DASHBOARD) ---
elseif ($action === 'fetch_supplier_inquiry_details') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin', 'management'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
        exit;
    }

    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
    $token = trim($_POST['token'] ?? '');

    $query = "
        SELECT 
            si.*, 
            s.company_name, 
            s.contact_person, 
            s.contact_number, 
            s.email AS supplier_email,
            r.rs_no,
            r.project_name,
            u.name AS created_by_name
        FROM supplier_inquiries si
        LEFT JOIN suppliers s ON si.supplier_id = s.id
        LEFT JOIN requisitions r ON si.rs_id = r.id
        LEFT JOIN users u ON si.created_by = u.id
    ";

    if ($inquiry_id > 0) {
        $stmt = $pdo->prepare($query . " WHERE si.id = ? LIMIT 1");
        $stmt->execute([$inquiry_id]);
    } elseif (!empty($token)) {
        $stmt = $pdo->prepare($query . " WHERE si.token = ? LIMIT 1");
        $stmt->execute([$token]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Inquiry identifier required.']);
        exit;
    }

    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$inquiry) {
        echo json_encode(['status' => 'error', 'message' => 'Inquiry not found.']);
        exit;
    }

    $itemsStmt = $pdo->prepare("
        SELECT * FROM supplier_inquiry_items 
        WHERE inquiry_id = ? 
        ORDER BY id ASC
    ");
    $itemsStmt->execute([$inquiry['id']]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'inquiry' => $inquiry,
        'items' => $items
    ]);
    exit;
}

// --- 3. FETCH ACTIVE INQUIRIES LIST FOR PO CREATION MODAL ---
elseif ($action === 'fetch_active_inquiries') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin', 'management'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
        exit;
    }

    $rs_id = !empty($_POST['rs_id']) ? (int)$_POST['rs_id'] : null;
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;

    $where = [];
    $params = [];

    if ($rs_id) {
        $where[] = "si.rs_id = ?";
        $params[] = $rs_id;
    }
    if ($supplier_id) {
        $where[] = "si.supplier_id = ?";
        $params[] = $supplier_id;
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "WHERE si.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)";

    $stmt = $pdo->prepare("
        SELECT 
            si.*, 
            s.company_name, 
            s.contact_person, 
            s.contact_number,
            r.rs_no,
            (SELECT COUNT(*) FROM supplier_inquiry_items WHERE inquiry_id = si.id) AS total_items,
            (SELECT COUNT(*) FROM supplier_inquiry_items WHERE inquiry_id = si.id AND availability_status = 'Available') AS available_items,
            (SELECT COUNT(*) FROM supplier_inquiry_items WHERE inquiry_id = si.id AND availability_status = 'Unavailable') AS unavailable_items,
            (SELECT COUNT(*) FROM supplier_inquiry_items WHERE inquiry_id = si.id AND availability_status = 'Partial') AS partial_items
        FROM supplier_inquiries si
        LEFT JOIN suppliers s ON si.supplier_id = s.id
        LEFT JOIN requisitions r ON si.rs_id = r.id
        {$whereSql}
        ORDER BY si.id DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'inquiries' => $inquiries
    ]);
    exit;
}

// --- 4. CANCEL / CLOSE SUPPLIER INQUIRY ---
elseif ($action === 'cancel_supplier_inquiry') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized permission.']);
        exit;
    }

    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
    if ($inquiry_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Valid inquiry ID is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE supplier_inquiries SET status = 'Cancelled' WHERE id = ?");
        $stmt->execute([$inquiry_id]);

        echo json_encode(['status' => 'success', 'message' => 'Inquiry has been cancelled successfully.']);
        exit;
    } catch (PDOException $e) {
        error_log("Cancel inquiry error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error while cancelling inquiry.']);
        exit;
    }
}
