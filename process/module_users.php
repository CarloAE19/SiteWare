<?php
// ==========================================
// USER CRUD AND PROFILE LOGIC
// ==========================================

function validate_password_strength($password) {
    if (strlen($password) < 8) {
        throw new Exception("Password must be at least 8 characters long.");
    }
    if (!preg_match('/[A-Z]/', $password)) {
        throw new Exception("Password must contain at least one uppercase letter (A-Z).");
    }
    if (!preg_match('/[a-z]/', $password)) {
        throw new Exception("Password must contain at least one lowercase letter (a-z).");
    }
    if (!preg_match('/[0-9]/', $password)) {
        throw new Exception("Password must contain at least one number (0-9).");
    }
    return true;
}

if (in_array($action, ['add_user', 'edit_user', 'delete_user', 'toggle_user_status'])) {
    if ($_SESSION['user_role'] !== 'admin') throw new Exception("Admin privileges required.");

    if ($action === 'add_user') {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$_POST['username']]);
        if ($check->rowCount() > 0) throw new Exception("This username is already taken.");

        validate_password_strength($_POST['password']);
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $status = (!empty($_POST['status']) && in_array($_POST['status'], ['active', 'inactive'])) ? $_POST['status'] : 'active';

        $stmt = $pdo->prepare("INSERT INTO users (name, username, role, password, status) VALUES (:name, :username, :role, :password, :status)");
        $stmt->execute([
            ':name' => $_POST['name'],
            ':username' => $_POST['username'],
            ':role' => $_POST['role'],
            ':password' => $hashed_password,
            ':status' => $status
        ]);
        $_SESSION['message'] = "User created successfully!";
        $_SESSION['msg_type'] = "success";

    } elseif ($action === 'edit_user') {
        $userId = $_POST['user_id'];
        
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$_POST['username'], $userId]);
        if ($check->rowCount() > 0) throw new Exception("This username is already taken by someone else.");

        $status = (!empty($_POST['status']) && in_array($_POST['status'], ['active', 'inactive'])) ? $_POST['status'] : 'active';
        if ($userId == $_SESSION['user_id']) {
            $status = 'active'; // Admin cannot deactivate themselves via edit
        }

        if (!empty($_POST['password'])) {
            validate_password_strength($_POST['password']);
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET name = :name, username = :username, role = :role, status = :status, password = :password WHERE id = :id");
            $stmt->execute([
                ':name' => $_POST['name'],
                ':username' => $_POST['username'],
                ':role' => $_POST['role'],
                ':status' => $status,
                ':password' => $hashed_password,
                ':id' => $userId
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = :name, username = :username, role = :role, status = :status WHERE id = :id");
            $stmt->execute([
                ':name' => $_POST['name'],
                ':username' => $_POST['username'],
                ':role' => $_POST['role'],
                ':status' => $status,
                ':id' => $userId
            ]);
        }
        
        if ($userId == $_SESSION['user_id']) { 
            $_SESSION['user_name'] = $_POST['name']; 
            $_SESSION['user_role'] = $_POST['role']; 
        }
        $_SESSION['message'] = "User updated successfully!";
        $_SESSION['msg_type'] = "success";

    } elseif ($action === 'toggle_user_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) throw new Exception("Invalid user specified.");
        if ($userId == $_SESSION['user_id']) throw new Exception("You cannot deactivate your own active account.");

        $fetchStmt = $pdo->prepare("SELECT name, status FROM users WHERE id = ?");
        $fetchStmt->execute([$userId]);
        $targetUser = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) throw new Exception("User not found.");

        $currentStatus = strtolower($targetUser['status'] ?? 'active');
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

        $updateStmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $updateStmt->execute([$newStatus, $userId]);

        $statusLabel = ($newStatus === 'active') ? 'activated' : 'deactivated';
        $_SESSION['message'] = "User <b>" . htmlspecialchars($targetUser['name']) . "</b> has been {$statusLabel} successfully.";
        $_SESSION['msg_type'] = ($newStatus === 'active') ? "success" : "warning";

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
                throw new Exception("<b>Data Protection Lock:</b> Cannot delete this user because their account is tied to past warehouse transactions or audits. To revoke access, please <b>Deactivate</b> the user instead.");
            }
            throw $e;
        }
    }
    header("Location: ../settings?tab=" . ($_POST['return_tab'] ?? 'users'));
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
        validate_password_strength($newPassword);
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

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['status' => 'error', 'message' => 'New passwords do not match.']);
        exit;
    }

    try {
        validate_password_strength($newPassword);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }

    $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->execute([$hashed_password, $userId]);

    echo json_encode(['status' => 'success', 'message' => 'Password updated successfully!']);
    exit;
}
?>