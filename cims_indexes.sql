-- ============================================================
--  CIMS — TARGETED DATABASE INDEXES (FIXED)
--  GB Construction & Enterprise Smart Inventory System
--  Run this in phpMyAdmin → SQL tab
--  Safe to re-run: uses IF NOT EXISTS checks (MySQL compatible)
-- ============================================================

-- ============================================================
--  TABLE: users
--  WHY: Login query does WHERE username = ? on every page load
-- ============================================================
ALTER TABLE users
    ADD UNIQUE INDEX IF NOT EXISTS idx_users_username (username);

-- ============================================================
--  TABLE: inventory
--  WHY:
--    - ORDER BY item_name ASC (audit, index, requisitions pages)
--    - ORDER BY last_updated DESC (index page)
--    - WHERE status != 'Out of Stock' (withdrawals, requisitions)
--    - item_code is UNIQUE → already auto-indexed ✅
-- ============================================================
ALTER TABLE inventory
    ADD INDEX IF NOT EXISTS idx_inventory_item_name   (item_name),
    ADD INDEX IF NOT EXISTS idx_inventory_status       (status),
    ADD INDEX IF NOT EXISTS idx_inventory_last_updated (last_updated);

-- ============================================================
--  TABLE: inventory_audits
--  WHY:
--    - ORDER BY created_at DESC (audit history page)
--    - JOIN ON conducted_by = users.id
-- ============================================================
ALTER TABLE inventory_audits
    ADD INDEX IF NOT EXISTS idx_audits_created_at   (created_at),
    ADD INDEX IF NOT EXISTS idx_audits_conducted_by (conducted_by);

-- ============================================================
--  TABLE: audit_items
--  WHY:
--    - GROUP BY audit_id (loading audit trail details)
--    - JOIN ON item_code = inventory.item_code
-- ============================================================
ALTER TABLE audit_items
    ADD INDEX IF NOT EXISTS idx_audit_items_audit_id  (audit_id),
    ADD INDEX IF NOT EXISTS idx_audit_items_item_code (item_code);

-- ============================================================
--  TABLE: requisitions
--  WHY:
--    - WHERE rs_no = ? (QR scanner lookup — critical speed)
--    - WHERE status IN (...) (filtering by approval stage)
--    - WHERE id = ? (approve/reject actions)
-- ============================================================
ALTER TABLE requisitions
    ADD UNIQUE INDEX IF NOT EXISTS idx_req_rs_no  (rs_no),
    ADD        INDEX IF NOT EXISTS idx_req_status (status);

-- ============================================================
--  TABLE: requisition_items
--  WHY:
--    - WHERE requisition_id = ? (fetch items for a given RS)
--    - JOIN ON item_code = inventory.item_code
-- ============================================================
ALTER TABLE requisition_items
    ADD INDEX IF NOT EXISTS idx_req_items_req_id   (requisition_id),
    ADD INDEX IF NOT EXISTS idx_req_items_item_code (item_code);

-- ============================================================
--  TABLE: purchase_orders
--  WHY:
--    - WHERE id = ? (mark delivered, SMS sent, delay log)
--    - JOIN ON rs_id = requisitions.id
-- ============================================================
ALTER TABLE purchase_orders
    ADD INDEX IF NOT EXISTS idx_po_rs_id  (rs_id),
    ADD INDEX IF NOT EXISTS idx_po_status (status);

-- ============================================================
--  TABLE: po_items
--  WHY:
--    - WHERE po_id = ? (fetch items to auto-stock-in on delivery)
--    - JOIN ON item_code = inventory.item_code
-- ============================================================
ALTER TABLE po_items
    ADD INDEX IF NOT EXISTS idx_po_items_po_id     (po_id),
    ADD INDEX IF NOT EXISTS idx_po_items_item_code (item_code);

-- ============================================================
--  TABLE: withdrawals
--  WHY:
--    - ORDER BY date_withdrawn DESC (withdrawal history page)
--    NOTE: This table uses 'date_withdrawn', NOT 'created_at'
-- ============================================================
ALTER TABLE withdrawals
    ADD INDEX IF NOT EXISTS idx_withdrawals_date (date_withdrawn);

-- ============================================================
--  TABLE: withdrawal_items
--  WHY:
--    - WHERE withdrawal_id = ? (fetch items per slip)
--    - JOIN ON item_code = inventory.item_code
-- ============================================================
ALTER TABLE withdrawal_items
    ADD INDEX IF NOT EXISTS idx_wd_items_withdrawal_id (withdrawal_id),
    ADD INDEX IF NOT EXISTS idx_wd_items_item_code     (item_code);

-- ============================================================
--  TABLE: notifications
--  WHY:
--    - WHERE target_role = ? (loading role-based notifications)
--    - WHERE target_user_id = ? (personal notifications)
--    - ORDER BY created_at DESC (notification dropdown)
-- ============================================================
ALTER TABLE notifications
    ADD INDEX IF NOT EXISTS idx_notif_target_role    (target_role),
    ADD INDEX IF NOT EXISTS idx_notif_target_user_id (target_user_id),
    ADD INDEX IF NOT EXISTS idx_notif_created_at     (created_at);

-- ============================================================
--  TABLE: purchase_orders
--  WHY:
--    - ORDER BY created_at DESC (PO history page)
-- ============================================================
ALTER TABLE purchase_orders
    ADD INDEX IF NOT EXISTS idx_po_created_at (created_at);

-- ============================================================
--  TABLE: requisitions
--  WHY:
--    - WHERE requestor_id = ? (requestor sees only their RS)
--    - ORDER BY created_at DESC (all RS pages)
-- ============================================================
ALTER TABLE requisitions
    ADD INDEX IF NOT EXISTS idx_req_requestor_id (requestor_id),
    ADD INDEX IF NOT EXISTS idx_req_created_at   (created_at);

-- ============================================================
--  DONE! To verify, run in phpMyAdmin SQL tab:
--    SHOW INDEX FROM users;
--    SHOW INDEX FROM inventory;
--    SHOW INDEX FROM inventory_audits;
--    (etc. for each table)
-- ============================================================
