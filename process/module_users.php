<?php
// ==========================================
// USER CRUD AND PROFILE LOGIC
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
        
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $_SESSION['message'] = "User deleted permanently.";
            $_SESSION['msg_type'] = "success";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                throw new Exception("<b>Data Protection Lock:</b> Cannot delete this user because their account is tied to past warehouse transactions or audits. To revoke access, please <b>Edit</b> the user and change their password instead.");
            }
            throw $e;
        }
    }
    header("Location: ../users");
    exit;
}

elseif ($action === 'update_profile') {
    $userId = $_SESSION['user_id'];
    $newName = trim($_POST['name']);
    $newUsername = trim($_POST['username']);
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->execute([$newUsername, $userId]);
    if ($check->rowCount() > 0) {
        throw new Exception("That username is already taken. Please choose another.");
    }

    if (!empty($newPassword)) {
        if ($newPassword !== $confirmPassword) {
            throw new Exception("New passwords do not match!");
        }
        $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, password = ? WHERE id = ?");
        $stmt->execute([$newName, $newUsername, $hashed_password, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ? WHERE id = ?");
        $stmt->execute([$newName, $newUsername, $userId]);
    }

    $_SESSION['user_name'] = $newName;
    $_SESSION['message'] = "Profile updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: ../profile");
    exit;
}
?>