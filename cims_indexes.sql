-- ============================================================
--  CIMS — SAFE TARGETED DATABASE INDEXES
--  GB Construction & Enterprise Smart Inventory System
--  Run this in phpMyAdmin → SQL tab
--  Safe to re-run multiple times (automatically skips existing indexes)
--  Compatible with MySQL 5.7+, MySQL 8.0+, and MariaDB
-- ============================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS AddCimsIndexIfNotExists$$

CREATE PROCEDURE AddCimsIndexIfNotExists(
    IN t_name VARCHAR(64),
    IN i_name VARCHAR(64),
    IN i_cols VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics 
        WHERE table_schema = DATABASE() 
          AND table_name = t_name 
          AND index_name = i_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', t_name, '` ADD INDEX `', i_name, '` (', i_cols, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- ============================================================
--  EXECUTE INDEX CREATION (SAFE CHECKS)
-- ============================================================

-- Table: inventory
CALL AddCimsIndexIfNotExists('inventory', 'idx_inventory_item_name', 'item_name');
CALL AddCimsIndexIfNotExists('inventory', 'idx_inventory_status', 'status');
CALL AddCimsIndexIfNotExists('inventory', 'idx_inventory_last_updated', 'last_updated');

-- Table: inventory_audits
CALL AddCimsIndexIfNotExists('inventory_audits', 'idx_audits_created_at', 'created_at');
CALL AddCimsIndexIfNotExists('inventory_audits', 'idx_audits_conducted_by', 'conducted_by');

-- Table: audit_items
CALL AddCimsIndexIfNotExists('audit_items', 'idx_audit_items_audit_id', 'audit_id');
CALL AddCimsIndexIfNotExists('audit_items', 'idx_audit_items_item_code', 'item_code');

-- Table: requisitions
CALL AddCimsIndexIfNotExists('requisitions', 'idx_req_status', 'status');
CALL AddCimsIndexIfNotExists('requisitions', 'idx_req_type', 'type');
CALL AddCimsIndexIfNotExists('requisitions', 'idx_req_requestor_id', 'requestor_id');
CALL AddCimsIndexIfNotExists('requisitions', 'idx_req_created_at', 'created_at');

-- Table: requisition_items
CALL AddCimsIndexIfNotExists('requisition_items', 'idx_req_items_req_id', 'requisition_id');
CALL AddCimsIndexIfNotExists('requisition_items', 'idx_req_items_item_code', 'item_code');
CALL AddCimsIndexIfNotExists('requisition_items', 'idx_req_items_req_status', 'requisition_id, item_status');

-- Table: purchase_orders
CALL AddCimsIndexIfNotExists('purchase_orders', 'idx_po_rs_id', 'rs_id');
CALL AddCimsIndexIfNotExists('purchase_orders', 'idx_po_status', 'status');
CALL AddCimsIndexIfNotExists('purchase_orders', 'idx_po_created_at', 'created_at');

-- Table: po_items
CALL AddCimsIndexIfNotExists('po_items', 'idx_po_items_po_id', 'po_id');
CALL AddCimsIndexIfNotExists('po_items', 'idx_po_items_item_code', 'item_code');

-- Table: withdrawals
CALL AddCimsIndexIfNotExists('withdrawals', 'idx_withdrawals_date', 'date_withdrawn');

-- Table: withdrawal_items
CALL AddCimsIndexIfNotExists('withdrawal_items', 'idx_wd_items_withdrawal_id', 'withdrawal_id');
CALL AddCimsIndexIfNotExists('withdrawal_items', 'idx_wd_items_item_code', 'item_code');

-- Table: notifications
CALL AddCimsIndexIfNotExists('notifications', 'idx_notif_target_role', 'target_role');
CALL AddCimsIndexIfNotExists('notifications', 'idx_notif_target_user_id', 'target_user_id');
CALL AddCimsIndexIfNotExists('notifications', 'idx_notif_created_at', 'created_at');

-- Table: supplier_viber_logs
CALL AddCimsIndexIfNotExists('supplier_viber_logs', 'idx_viber_supplier_id', 'supplier_id');
CALL AddCimsIndexIfNotExists('supplier_viber_logs', 'idx_viber_po_id', 'po_id');

-- Cleanup temporary helper procedure
DROP PROCEDURE IF EXISTS AddCimsIndexIfNotExists;

-- ============================================================
--  DONE! All indexes verified and applied cleanly.
-- ============================================================
