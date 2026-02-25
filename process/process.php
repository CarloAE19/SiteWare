<?php
session_start();

// === AUTHENTICATION CHECK ===
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login");
    exit;
}

require_once '../Connection/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // ==========================================
        // INVENTORY (MATERIALS) LOGIC
        // ==========================================
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO inventory (item_code, item_name, category, quantity, unit, unit_price, status) VALUES (:item_code, :item_name, :category, :quantity, :unit, :unit_price, :status)");
            $stmt->execute([
                ':item_code' => $_POST['item_code'], ':item_name' => $_POST['item_name'],
                ':category' => $_POST['category'], ':quantity' => $_POST['quantity'],
                ':unit' => $_POST['unit'], ':unit_price' => $_POST['unit_price'], ':status' => $_POST['status']
            ]);
            $_SESSION['message'] = "Material added successfully!";
            $_SESSION['msg_type'] = "success";
            header("Location: ../index"); 
            exit;

        } elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE inventory SET item_code = :item_code, item_name = :item_name, category = :category, quantity = :quantity, unit = :unit, unit_price = :unit_price, status = :status WHERE id = :id");
            $stmt->execute([
                ':item_code' => $_POST['item_code'], ':item_name' => $_POST['item_name'],
                ':category' => $_POST['category'], ':quantity' => $_POST['quantity'],
                ':unit' => $_POST['unit'], ':unit_price' => $_POST['unit_price'],
                ':status' => $_POST['status'], ':id' => $_POST['id']
            ]);
            $_SESSION['message'] = "Material updated successfully!";
            $_SESSION['msg_type'] = "success";
            header("Location: ../index"); 
            exit;

        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            $_SESSION['message'] = "Material deleted.";
            $_SESSION['msg_type'] = "danger";
            header("Location: ../index"); 
            exit;
        }

        // ==========================================
        // SUPPLIERS LOGIC
        // ==========================================
        elseif ($action === 'add_supplier') {
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

        // ==========================================
        // USER CRUD LOGIC (UPDATED FOR USERNAME)
        // ==========================================
        if (in_array($action, ['add_user', 'edit_user', 'delete_user'])) {
            if ($_SESSION['user_role'] !== 'admin') throw new Exception("Admin privileges required.");

            if ($action === 'add_user') {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$_POST['username']]);
                if ($check->rowCount() > 0) throw new Exception("This username is already taken.");

                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, username, role, password) VALUES (:name, :username, :role, :password)");
                $stmt->execute([':name' => $_POST['name'], ':username' => $_POST['username'], ':role' => $_POST['role'], ':password' => $hashed_password]);
                $_SESSION['message'] = "User created successfully!";
                $_SESSION['msg_type'] = "success";

            } elseif ($action === 'edit_user') {
                $userId = $_POST['user_id'];
                
                // Ensure they aren't changing their username to one that already exists
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $check->execute([$_POST['username'], $userId]);
                if ($check->rowCount() > 0) throw new Exception("This username is already taken by someone else.");

                if (!empty($_POST['password'])) {
                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = :name, username = :username, role = :role, password = :password WHERE id = :id");
                    $stmt->execute([':name' => $_POST['name'], ':username' => $_POST['username'], ':role' => $_POST['role'], ':password' => $hashed_password, ':id' => $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = :name, username = :username, role = :role WHERE id = :id");
                    $stmt->execute([':name' => $_POST['name'], ':username' => $_POST['username'], ':role' => $_POST['role'], ':id' => $userId]);
                }
                
                if ($userId == $_SESSION['user_id']) { 
                    $_SESSION['user_name'] = $_POST['name']; 
                    $_SESSION['user_role'] = $_POST['role']; 
                }
                $_SESSION['message'] = "User updated successfully!";
                $_SESSION['msg_type'] = "success";

            } elseif ($action === 'delete_user') {
                $userId = $_POST['user_id'];
                if ($userId == $_SESSION['user_id']) throw new Exception("You cannot delete your own account.");
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $userId]);
                $_SESSION['message'] = "User deleted permanently.";
                $_SESSION['msg_type'] = "danger";
            }
            header("Location: ../users");
            exit;
        }

        // ==========================================
        // REQUISITIONS (RS) LOGIC
        // ==========================================
        elseif ($action === 'create_rs') {
            $stmt = $pdo->prepare("INSERT INTO requisitions (rs_no, requestor_id, requestor_name, project_name, urgency, remarks, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending Approval')");
            $stmt->execute([$_POST['rs_no'], $_POST['requestor_id'], $_POST['requestor_name'], $_POST['project_name'], $_POST['urgency'], $_POST['remarks']]);
            $requisition_id = $pdo->lastInsertId();

            $items = $_POST['items'];
            $quantities = $_POST['quantities'];
            $itemStmt = $pdo->prepare("INSERT INTO requisition_items (requisition_id, item_code, quantity) VALUES (?, ?, ?)");
            for ($i = 0; $i < count($items); $i++) {
                if (!empty($items[$i]) && !empty($quantities[$i])) {
                    $itemStmt->execute([$requisition_id, $items[$i], $quantities[$i]]);
                }
            }
            
            // Notification: Alert Management
            $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'New Requisition Pending', ?)");
            $notif->execute(["{$_POST['requestor_name']} submitted {$_POST['rs_no']} for {$_POST['project_name']}."]);

            $_SESSION['message'] = "Requisition created successfully and sent to Management for approval.";
            $_SESSION['msg_type'] = "success";
            header("Location: ../requisitions");
            exit;
            
        } elseif ($action === 'approve_rs') {
            if ($_SESSION['user_role'] !== 'management') throw new Exception("Only Management can approve requisitions.");
            $stmt = $pdo->prepare("UPDATE requisitions SET status = 'Approved' WHERE id = ?");
            $stmt->execute([$_POST['rs_id']]);
            
            $rsData = $pdo->prepare("SELECT rs_no, requestor_id FROM requisitions WHERE id = ?");
            $rsData->execute([$_POST['rs_id']]);
            $rs = $rsData->fetch();

            // Notifications
            $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Approved', ?)")->execute([$rs['requestor_id'], "Your request {$rs['rs_no']} has been approved."]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'Ready for PO', ?)")->execute(["{$rs['rs_no']} was approved. Please generate a PO."]);

            $_SESSION['message'] = "Requisition Approved. Ready for Purchasing.";
            $_SESSION['msg_type'] = "success";
            header("Location: ../requisitions");
            exit;

        } elseif ($action === 'reject_rs') {
            if ($_SESSION['user_role'] !== 'management') throw new Exception("Only Management can reject requisitions.");
            $stmt = $pdo->prepare("UPDATE requisitions SET status = 'Rejected' WHERE id = ?");
            $stmt->execute([$_POST['rs_id']]);
            
            $rsData = $pdo->prepare("SELECT rs_no, requestor_id FROM requisitions WHERE id = ?");
            $rsData->execute([$_POST['rs_id']]);
            $rs = $rsData->fetch();

            // Notification
            $pdo->prepare("INSERT INTO notifications (target_user_id, title, message) VALUES (?, 'Requisition Rejected', ?)")->execute([$rs['requestor_id'], "Your request {$rs['rs_no']} was rejected."]);

            $_SESSION['message'] = "Requisition Rejected.";
            $_SESSION['msg_type'] = "danger";
            header("Location: ../requisitions");
            exit;
        }

        // ==========================================
        // PURCHASE ORDERS (PO) LOGIC
        // ==========================================
        elseif ($action === 'create_po') {
            if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) throw new Exception("Unauthorized action.");

            $po_no = $_POST['po_no'];
            $rs_id = $_POST['rs_id'];
            $supplier_id = $_POST['supplier_id'];
            $prepared_by = $_SESSION['user_id'];

            // 1. Create the PO Record
            $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_no, rs_id, supplier_id, prepared_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$po_no, $rs_id, $supplier_id, $prepared_by]);
            $po_id = $pdo->lastInsertId();

            // 2. Automatically copy items from the RS to the PO
            $rsItemsStmt = $pdo->prepare("
                SELECT ri.item_code, ri.quantity, i.unit_price 
                FROM requisition_items ri 
                LEFT JOIN inventory i ON ri.item_code = i.item_code 
                WHERE ri.requisition_id = ?
            ");
            $rsItemsStmt->execute([$rs_id]);
            $rsItems = $rsItemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $poItemStmt = $pdo->prepare("INSERT INTO po_items (po_id, item_code, quantity, unit_price) VALUES (?, ?, ?, ?)");
            foreach ($rsItems as $item) {
                $price = $item['unit_price'] ?? 0.00; 
                $poItemStmt->execute([$po_id, $item['item_code'], $item['quantity'], $price]);
            }

            // 3. Update the original RS status
            $pdo->prepare("UPDATE requisitions SET status = 'PO Created' WHERE id = ?")->execute([$rs_id]);

            // 4. Send Notification to Warehouse
            $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('warehouse', 'Incoming Delivery Expected', ?)");
            $notif->execute(["PO {$po_no} has been generated. Prepare space to receive materials."]);

            $_SESSION['message'] = "Purchase Order generated and sent to Supplier successfully!";
            $_SESSION['msg_type'] = "success";
            header("Location: ../po");
            exit;
        }

        // ==========================================
        // WITHDRAWALS LOGIC (AI DATA FEEDER)
        // ==========================================
        elseif ($action === 'create_withdrawal') {
            if (!in_array($_SESSION['user_role'], ['warehouse', 'admin'])) {
                throw new Exception("Only the Warehouse In-Charge can release materials.");
            }

            $withdrawal_no = $_POST['withdrawal_no'];
            $project_name = $_POST['project_name'];
            $remarks = $_POST['remarks'];
            $released_by = $_SESSION['user_id'];
            $items = $_POST['items'];
            $quantities = $_POST['quantities'];

            // 1. Verify we have enough stock FIRST (Prevent negative inventory)
            for ($i = 0; $i < count($items); $i++) {
                if (!empty($items[$i]) && !empty($quantities[$i])) {
                    $checkStmt = $pdo->prepare("SELECT quantity, item_name FROM inventory WHERE item_code = ?");
                    $checkStmt->execute([$items[$i]]);
                    $invItem = $checkStmt->fetch();
                    
                    if (!$invItem || $invItem['quantity'] < $quantities[$i]) {
                        throw new Exception("Insufficient stock for " . ($invItem['item_name'] ?? $items[$i]) . ". Available: " . ($invItem['quantity'] ?? 0));
                    }
                }
            }

            // 2. Create the Withdrawal Record
            $stmt = $pdo->prepare("INSERT INTO withdrawals (withdrawal_no, project_name, released_by, remarks) VALUES (?, ?, ?, ?)");
            $stmt->execute([$withdrawal_no, $project_name, $released_by, $remarks]);
            $withdrawal_id = $pdo->lastInsertId();

            // 3. Insert items AND subtract from Inventory!
            $wdItemStmt = $pdo->prepare("INSERT INTO withdrawal_items (withdrawal_id, item_code, quantity) VALUES (?, ?, ?)");
            $deductStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE item_code = ?");
            
            for ($i = 0; $i < count($items); $i++) {
                if (!empty($items[$i]) && !empty($quantities[$i])) {
                    // Record it
                    $wdItemStmt->execute([$withdrawal_id, $items[$i], $quantities[$i]]);
                    // Deduct it from master inventory
                    $deductStmt->execute([$quantities[$i], $items[$i]]);
                }
            }

            $_SESSION['message'] = "Materials successfully withdrawn and deducted from inventory.";
            $_SESSION['msg_type'] = "success";
            header("Location: ../withdrawals");
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['msg_type'] = "danger";
        $redirect = $_SERVER['HTTP_REFERER'] ?? '../index';
        header("Location: " . str_replace('.php', '', $redirect)); 
        exit;
    }
} else {
    header("Location: ../index");
    exit;
}
?>