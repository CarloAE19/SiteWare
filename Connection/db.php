<?php
// ==========================================
// DATABASE SETUP
// ==========================================
$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP password
$dbname = 'construction_inventory';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");

    // 1. Create Users Table (UPDATED to use 'username' instead of 'email')
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'requestor',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0) {
        $hashed_password = password_hash('password123', PASSWORD_DEFAULT);
        // Changed default admin login to 'admin'
        $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['System Admin', 'admin', $hashed_password, 'admin']);
    }

    // 2. Create Inventory Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_code VARCHAR(50) NOT NULL UNIQUE,
            item_name VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            unit VARCHAR(50) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(50) NOT NULL,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Create Suppliers Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_code VARCHAR(50) NOT NULL UNIQUE,
            company_name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(100),
            contact_number VARCHAR(50),
            email VARCHAR(100),
            address TEXT,
            status VARCHAR(50) DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 4. Create Requisitions (RS) Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS requisitions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rs_no VARCHAR(50) NOT NULL UNIQUE,
            requestor_id INT NOT NULL,
            requestor_name VARCHAR(100) NOT NULL,
            project_name VARCHAR(255) NOT NULL,
            urgency VARCHAR(50) DEFAULT 'Normal',
            remarks TEXT,
            status VARCHAR(50) DEFAULT 'Pending Approval',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 5. Create Requisition Items Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS requisition_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requisition_id INT NOT NULL,
            item_code VARCHAR(50) NOT NULL,
            quantity INT NOT NULL,
            FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 6. Create Notifications Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            target_user_id INT NULL, 
            target_role VARCHAR(50) NULL,
            title VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 7. Create Purchase Orders (PO) Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS purchase_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            po_no VARCHAR(50) NOT NULL UNIQUE,
            rs_id INT NOT NULL,
            supplier_id INT NOT NULL,
            prepared_by INT NOT NULL,
            status VARCHAR(50) DEFAULT 'Pending Delivery',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (rs_id) REFERENCES requisitions(id),
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            FOREIGN KEY (prepared_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 8. Create PO Items Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS po_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            po_id INT NOT NULL,
            item_code VARCHAR(50) NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 9. Create Withdrawals Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            withdrawal_no VARCHAR(50) NOT NULL UNIQUE,
            project_name VARCHAR(255) NOT NULL,
            released_by INT NOT NULL,
            remarks TEXT,
            date_withdrawn TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (released_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 10. Create Withdrawal Items Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS withdrawal_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            withdrawal_id INT NOT NULL,
            item_code VARCHAR(50) NOT NULL,
            quantity INT NOT NULL,
            FOREIGN KEY (withdrawal_id) REFERENCES withdrawals(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 11. Create Audit History Table (Monthly Recount)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory_audits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audit_month VARCHAR(50) NOT NULL,
            conducted_by INT NOT NULL,
            total_discrepancy_items INT DEFAULT 0,
            remarks TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conducted_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 12. Create Audit Items Table (Discrepancy Tracker)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audit_id INT NOT NULL,
            item_code VARCHAR(50) NOT NULL,
            system_qty INT NOT NULL,
            physical_qty INT NOT NULL,
            discrepancy INT NOT NULL,
            FOREIGN KEY (audit_id) REFERENCES inventory_audits(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>