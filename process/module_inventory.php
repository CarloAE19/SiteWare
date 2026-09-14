<?php
// ==========================================
// INVENTORY & LIVE SYNC LOGIC
// ==========================================

if ($action === 'live_sync') {
    $stmt = $pdo->query("SELECT item_code, quantity, status FROM inventory");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit; // Instantly return JSON and stop
}

elseif ($action === 'stock_in_scanned') {
    if (!in_array($_SESSION['user_role'], ['admin', 'warehouse'])) {
        if(isset($_POST['ajax'])) { echo json_encode(['status'=>'error', 'message'=>'Unauthorized']); exit; }
        throw new Exception("Unauthorized.");
    }
    
    $added_qty = (int)$_POST['added_qty'];
    if ($added_qty <= 0) {
         if(isset($_POST['ajax'])) { echo json_encode(['status'=>'error', 'message'=>'Quantity must be greater than zero.']); exit; }
         throw new Exception("Quantity must be greater than zero.");
    }

    // Add the new quantity
    $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE item_code = ?");
    $stmt->execute([$added_qty, $_POST['item_code']]);
    
    // Auto-calculate new status using dynamic reorder_level from units table!
    $pdo->prepare("
        UPDATE inventory i 
        JOIN units u ON i.unit = u.unit_name 
        SET i.status = CASE 
            WHEN i.quantity <= 0 THEN 'Out of Stock' 
            WHEN i.quantity <= u.reorder_level THEN 'Low Stock' 
            ELSE 'In Stock' 
        END 
        WHERE i.item_code = ?
    ")->execute([$_POST['item_code']]);
    
    // AJAX JSON RESPONSE
    if (isset($_POST['ajax'])) {
        $stmt = $pdo->prepare("SELECT quantity, status FROM inventory WHERE item_code = ?");
        $stmt->execute([$_POST['item_code']]);
        $updatedItem = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success', 
            'new_qty' => $updatedItem['quantity'],
            'new_status' => $updatedItem['status']
        ]);
        exit;
    }

    $_SESSION['message'] = "Stock updated successfully via QR scan!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../index"); 
    exit;
} 

elseif ($action === 'add') {
    if ($_SESSION['user_role'] !== 'admin') {
        throw new Exception("Unauthorized action. Direct material creation is restricted to Admin. Warehouse In-Charge personnel must submit a Restock Request instead.");
    }

    $clientCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validate_csrf_token($clientCsrf)) {
        throw new Exception("Security validation failed (Invalid or expired CSRF token). Please refresh and try again.");
    }

    $itemCode = trim($_POST['item_code'] ?? '');
    $itemName = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $unitPrice = isset($_POST['unit_price']) ? (float)$_POST['unit_price'] : 0.0;

    if ($itemCode === '' || $itemName === '' || $category === '' || $unit === '') {
        throw new Exception("Please fill in all required fields (Item Code, Item Name, Category, and Unit).");
    }
    if ($qty < 0) {
        throw new Exception("Quantity cannot be negative.");
    }
    if ($unitPrice < 0) {
        throw new Exception("Unit price cannot be negative.");
    }

    // Check duplicate item_code
    $chkStmt = $pdo->prepare("SELECT id FROM inventory WHERE item_code = ?");
    $chkStmt->execute([$itemCode]);
    if ($chkStmt->fetch()) {
        throw new Exception("An inventory item with Item Code '{$itemCode}' already exists.");
    }

    // Lookup the reorder_level for this unit type
    $reorderStmt = $pdo->prepare("SELECT reorder_level FROM units WHERE unit_name = ?");
    $reorderStmt->execute([$unit]);
    $reorderLevel = (int)($reorderStmt->fetchColumn() ?: 10);
    $status = ($qty <= 0) ? 'Out of Stock' : (($qty <= $reorderLevel) ? 'Low Stock' : 'In Stock');

    // Ensure audit_logs table exists (ISO 9001 Clause 8.5.2 & 7.5 Traceability)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NOT NULL,
            previous_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO inventory (item_code, item_name, category, quantity, unit, unit_price, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$itemCode, $itemName, $category, $qty, $unit, $unitPrice, $status]);
        $newItemId = (int)$pdo->lastInsertId();

        // Audit Trail
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $auditStmt->execute([
                $_SESSION['user_id'],
                'MATERIAL_CREATED',
                'material',
                $newItemId,
                null,
                json_encode([
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'category' => $category,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'unit_price' => $unitPrice,
                    'status' => $status
                ]),
                $ip
            ]);
        } catch (Exception $e) {}

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $msg = "Material '{$itemName}' added to inventory successfully!";
    if ($is_ajax || isset($_POST['ajax'])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => $msg,
            'data' => [
                'id' => $newItemId,
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'category' => $category,
                'quantity' => $qty,
                'unit' => $unit,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'status' => $status
            ]
        ]);
        exit;
    }

    $_SESSION['message'] = $msg;
    $_SESSION['msg_type'] = "success";
    header("Location: ../index"); 
    exit;
} 

elseif ($action === 'edit') {
    if ($_SESSION['user_role'] !== 'admin') {
        throw new Exception("Unauthorized action. Direct material edits are restricted to Admin.");
    }

    $clientCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validate_csrf_token($clientCsrf)) {
        throw new Exception("Security validation failed (Invalid or expired CSRF token). Please refresh and try again.");
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception("Invalid material ID specified.");

    $fetchOld = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $fetchOld->execute([$id]);
    $oldData = $fetchOld->fetch(PDO::FETCH_ASSOC);
    if (!$oldData) throw new Exception("Material not found in inventory.");

    $itemCode = trim($_POST['item_code'] ?? '');
    $itemName = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $unitPrice = isset($_POST['unit_price']) ? (float)$_POST['unit_price'] : 0.0;

    if ($itemCode === '' || $itemName === '' || $category === '' || $unit === '') {
        throw new Exception("Please fill in all required fields (Item Code, Item Name, Category, and Unit).");
    }
    if ($qty < 0) {
        throw new Exception("Quantity cannot be negative.");
    }
    if ($unitPrice < 0) {
        throw new Exception("Unit price cannot be negative.");
    }

    // Check duplicate item_code on other items
    $chkStmt = $pdo->prepare("SELECT id FROM inventory WHERE item_code = ? AND id != ?");
    $chkStmt->execute([$itemCode, $id]);
    if ($chkStmt->fetch()) {
        throw new Exception("An inventory item with Item Code '{$itemCode}' already exists.");
    }

    // Lookup the reorder_level for this unit type
    $reorderStmt = $pdo->prepare("SELECT reorder_level FROM units WHERE unit_name = ?");
    $reorderStmt->execute([$unit]);
    $reorderLevel = (int)($reorderStmt->fetchColumn() ?: 10);
    $status = ($qty <= 0) ? 'Out of Stock' : (($qty <= $reorderLevel) ? 'Low Stock' : 'In Stock');

    // Ensure audit_logs table exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NOT NULL,
            previous_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE inventory SET item_code=?, item_name=?, category=?, quantity=?, unit=?, unit_price=?, status=? WHERE id=?");
        $stmt->execute([$itemCode, $itemName, $category, $qty, $unit, $unitPrice, $status, $id]);

        // Audit Trail (ISO 9001 Clause 8.5.2 & 7.5 Traceability)
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $auditStmt->execute([
                $_SESSION['user_id'],
                'MATERIAL_UPDATED',
                'material',
                $id,
                json_encode($oldData),
                json_encode([
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'category' => $category,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'unit_price' => $unitPrice,
                    'status' => $status
                ]),
                $ip
            ]);
        } catch (Exception $e) {}

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $msg = "Material '{$itemName}' updated successfully!";
    if ($is_ajax || isset($_POST['ajax'])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => $msg,
            'data' => [
                'id' => $id,
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'category' => $category,
                'quantity' => $qty,
                'unit' => $unit,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'status' => $status
            ]
        ]);
        exit;
    }

    $_SESSION['message'] = $msg;
    $_SESSION['msg_type'] = "success";
    header("Location: ../index"); 
    exit;
} 

elseif ($action === 'delete') {
    if ($_SESSION['user_role'] !== 'admin') {
        throw new Exception("Unauthorized action. Material deletion is restricted to Admin.");
    }

    $clientCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validate_csrf_token($clientCsrf)) {
        throw new Exception("Security validation failed (Invalid or expired CSRF token). Please refresh and try again.");
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception("Invalid material ID specified.");

    $fetchOld = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $fetchOld->execute([$id]);
    $oldData = $fetchOld->fetch(PDO::FETCH_ASSOC);
    if (!$oldData) throw new Exception("Material not found in inventory.");

    // Ensure audit_logs table exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NOT NULL,
            previous_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {}

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->execute([$id]);

        // Audit Trail (ISO 9001 Clause 8.5.2 & 7.5 Traceability)
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, previous_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $auditStmt->execute([
                $_SESSION['user_id'],
                'MATERIAL_DELETED',
                'material',
                $id,
                json_encode($oldData),
                null,
                $ip
            ]);
        } catch (Exception $e) {}

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $msg = "Material '{$oldData['item_name']}' deleted from inventory.";
    if ($is_ajax || isset($_POST['ajax'])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => $msg,
            'data' => [
                'id' => $id
            ]
        ]);
        exit;
    }

    $_SESSION['message'] = $msg;
    $_SESSION['msg_type'] = "danger";
    header("Location: ../index"); 
    exit;
}
?>