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
    
    // Auto-calculate new status!
    $pdo->prepare("UPDATE inventory SET status = CASE WHEN quantity <= 0 THEN 'Out of Stock' WHEN quantity <= 10 THEN 'Low Stock' ELSE 'In Stock' END WHERE item_code = ?")->execute([$_POST['item_code']]);
    
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
    if (!in_array($_SESSION['user_role'], ['admin', 'warehouse'])) throw new Exception("Unauthorized.");
    
    $qty = (int)$_POST['quantity'];
    $status = ($qty <= 0) ? 'Out of Stock' : (($qty <= 10) ? 'Low Stock' : 'In Stock');

    // FIXED: Added 'category' to the INSERT statement!
    $stmt = $pdo->prepare("INSERT INTO inventory (item_code, item_name, category, quantity, unit, unit_price, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['item_code'], $_POST['item_name'], $_POST['category'], $qty, $_POST['unit'], $_POST['unit_price'], $status]);
    
    $_SESSION['message'] = "Material added to inventory successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../index"); 
    exit;
} 

elseif ($action === 'edit') {
    if (!in_array($_SESSION['user_role'], ['admin', 'warehouse'])) throw new Exception("Unauthorized.");
    
    $qty = (int)$_POST['quantity'];
    $status = ($qty <= 0) ? 'Out of Stock' : (($qty <= 10) ? 'Low Stock' : 'In Stock');

    // FIXED: Added 'category=?' to the UPDATE statement!
    $stmt = $pdo->prepare("UPDATE inventory SET item_code=?, item_name=?, category=?, quantity=?, unit=?, unit_price=?, status=? WHERE id=?");
    $stmt->execute([$_POST['item_code'], $_POST['item_name'], $_POST['category'], $qty, $_POST['unit'], $_POST['unit_price'], $status, $_POST['id']]);
    
    $_SESSION['message'] = "Material updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../index"); 
    exit;
} 

elseif ($action === 'delete') {
    if (!in_array($_SESSION['user_role'], ['admin', 'warehouse'])) throw new Exception("Unauthorized.");
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $_SESSION['message'] = "Material deleted from inventory.";
    $_SESSION['msg_type'] = "danger";
    header("Location: ../index"); 
    exit;
}
?>