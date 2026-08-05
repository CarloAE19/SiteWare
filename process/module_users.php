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
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->execute([$newUsername, $userId]);
    if ($check->rowCount() > 0) {
        throw new Exception("That username is already taken. Please choose another.");
    }

    if (!empty($newPassword)) {
        // Verify the current password first
        if (empty($currentPassword)) {
            throw new Exception("Please enter your current password to set a new one.");
        }
        $fetchHash = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $fetchHash->execute([$userId]);
        $storedHash = $fetchHash->fetchColumn();
        if (!password_verify($currentPassword, $storedHash)) {
            throw new Exception("Current password is incorrect. Password was not changed.");
        }
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

elseif ($action === 'verify_current_password') {
    $userId = $_SESSION['user_id'];
    $currentPassword = $_POST['current_password'] ?? '';

    if (empty($currentPassword)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter your current password.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $storedHash = $stmt->fetchColumn();

    if ($storedHash && password_verify($currentPassword, $storedHash)) {
        echo json_encode(['status' => 'success', 'message' => 'Current password verified successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect current password. Please try again.']);
    }
    exit;
}

elseif ($action === 'change_password_modal') {
    $userId = $_SESSION['user_id'];
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword)) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is required.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $storedHash = $stmt->fetchColumn();

    if (!$storedHash || !password_verify($currentPassword, $storedHash)) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect. Verification failed.']);
        exit;
    }

    if (empty($newPassword) || strlen($newPassword) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'New password must be at least 6 characters long.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['status' => 'error', 'message' => 'New passwords do not match.']);
        exit;
    }

    $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->execute([$hashed_password, $userId]);

    echo json_encode(['status' => 'success', 'message' => 'Password updated successfully!']);
    exit;
}
?>