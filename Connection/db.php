<?php
// ==========================================
// SECURE DATABASE SETUP
// ==========================================

// 1. Load the secure environment variables (.env)
if (!function_exists('loadEnv')) {
    function loadEnv($filePath) {
        if (!file_exists($filePath)) {
            die("Critical Error: .env file is missing. The system cannot start securely.");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) continue;

            // Split "KEY=VALUE"
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Inject into PHP's global environment variables
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Load .env automatically from the root folder
loadEnv(__DIR__ . '/../.env');

// 3. Define the AI Key globally so analytics.php can see it securely
if (!defined('AI_API_KEY') && isset($_ENV['AI_API_KEY'])) {
    define('AI_API_KEY', $_ENV['AI_API_KEY']);
}
if (!defined('AI_SYSTEM_PROMPT') && isset($_ENV['AI_SYSTEM_PROMPT'])) {
    define('AI_SYSTEM_PROMPT', trim($_ENV['AI_SYSTEM_PROMPT'], '"\''));
}
if (!defined('SMS_API_KEY') && isset($_ENV['SMS_API_KEY'])) {
    define('SMS_API_KEY', $_ENV['SMS_API_KEY']);
}

try {
    // Connect to MySQL server (Without DB name first, to allow creation)
    $pdo = new PDO("mysql:host=" . $_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $_ENV['DB_NAME'] . "`");
    $pdo->exec("USE `" . $_ENV['DB_NAME'] . "`");

    // 1. Create Users Table
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
            type VARCHAR(50) DEFAULT 'project',
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

    // 13. Create Projects Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_code VARCHAR(50) UNIQUE,
            project_name VARCHAR(150) NOT NULL UNIQUE,
            description TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if ($pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO projects (project_name, description, status) VALUES ('Main Headquarters Construction', 'General construction of the main building', 'active')");
    }

    // 14. Create System Settings Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Seed default login background if not exists
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('login_background', 'assets/img/default_login_bg.png')");
    $stmt->execute();

    // AUTO-PATCH: Ensure the requisitions table has the type column and migrate existing restocking records
    try {
        $pdo->exec("ALTER TABLE requisitions ADD COLUMN type VARCHAR(50) DEFAULT 'project'");
        $pdo->exec("UPDATE requisitions SET type = 'restock' WHERE project_name = 'General Restocking'");
    } catch (PDOException $e) { /* Column already exists or table is not populated */
    }

} catch (PDOException $e) {
    // 1. Define global constant to signify DB offline status
    if (!defined('DB_OFFLINE')) {
        define('DB_OFFLINE', true);
    }
    $pdo = null;

    // 2. Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 3. Detect AJAX / API / process requests
    $is_ajax_or_process = (
        (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/process/') !== false) ||
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );

    if ($is_ajax_or_process) {
        // If it's a standard Form POST in process/ (not AJAX), redirect back with flash message
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest')) {
            $_SESSION['message'] = "Can't connect to the database. You're offline.";
            $_SESSION['msg_type'] = "danger";
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../index'));
            exit;
        }
        
        // Otherwise, it is an AJAX query expecting JSON
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => "Can't connect to the database. You're offline."
        ]);
        exit;
    }

    // 4. Check if the user is logged in
    if (isset($_SESSION['user_id'])) {
        $rootDir = dirname(__DIR__);
        
        // Include layout/header.php which knows DB_OFFLINE is true and will render sidebar + top navigation
        include_once $rootDir . '/layout/header.php';
        ?>
        <div class="container-fluid px-3 px-md-4 py-5 text-center">
            <div class="card border-0 shadow-sm p-5 mx-auto bg-white" style="max-width: 600px; border-radius: 16px;">
                <div class="icon-wrap mb-4 d-flex justify-content-center">
                    <div class="d-flex align-items-center justify-content-center bg-danger-subtle rounded-circle" style="width: 80px; height: 80px;">
                        <i class="bi bi-database-exclamation text-danger fs-1 animate-pulse-db"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-dark mb-2">Can't Connect, You're Offline</h3>
                <p class="text-muted mb-4">
                    The database server is currently offline. You can still navigate using the sidebar to other sections, but database read/write actions are disabled.
                </p>
                <button class="btn btn-brand fw-bold px-4 py-2 shadow-sm" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Retry Connection
                </button>
            </div>
        </div>
        <style>
            @keyframes pulseDb {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.08); }
            }
            .animate-pulse-db {
                animation: pulseDb 2s infinite ease-in-out;
                display: inline-block;
            }
        </style>
        <?php
        include_once $rootDir . '/layout/footer.php';
        exit;
    }
    
    // If not logged in, return cleanly and let login.php handle its own error reporting
    return;
}
?>