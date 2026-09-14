-- ==========================================================
-- SiteWare Enterprise Database Backup
-- Application: SiteWare Inventory & Management System
-- Generated: 2026-09-10 11:44:22 PST
-- Database: `construction_inventory`
-- Total Tables: 18
-- Architecture: Pure PDO Streamed Exporter (ISO 9001 Clause 7.5 Aligned)
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+08:00';
SET NAMES utf8mb4;

-- ----------------------------------------------------------
-- Table structure for table `audit_items`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `audit_items`;
CREATE TABLE `audit_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `system_qty` int(11) NOT NULL,
  `physical_qty` int(11) NOT NULL,
  `discrepancy` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_items_audit_id` (`audit_id`),
  KEY `idx_audit_items_item_code` (`item_code`),
  CONSTRAINT `audit_items_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `inventory_audits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `audit_items` (40 records)
INSERT INTO `audit_items` (`id`, `audit_id`, `item_code`, `system_qty`, `physical_qty`, `discrepancy`) VALUES
  (2, 3, 'ITM-9411', 1, 12, 11),
  (3, 3, 'ITM-8782', 100, 100, 0),
  (4, 3, 'ITM-3', 48, 48, 0),
  (5, 3, 'ITM-4', 110, 110, 0),
  (6, 3, 'ITM-4130', 700, 700, 0),
  (7, 3, 'ITM-2', 497, 497, 0),
  (8, 4, 'ITM-9411', 235, 235, 0),
  (9, 4, 'ITM-8782', 155, 155, 0),
  (10, 4, 'ITM-3', 48, 48, 0),
  (11, 4, 'ITM-4', 110, 110, 0),
  (12, 4, 'ITM-4130', 700, 700, 0),
  (13, 4, 'ITM-2', 497, 497, 0),
  (14, 5, 'ITM-9411', 296, 296, 0),
  (15, 5, 'ITM-8782', 155, 155, 0),
  (16, 5, 'ITM-4764', 24, 24, 0),
  (17, 5, 'ITM-3', 148, 148, 0),
  (18, 5, 'ITM-4', 110, 112, 2),
  (19, 5, 'ITM-4130', 700, 698, -2),
  (20, 5, 'ITM-2', 547, 545, -2),
  (21, 6, 'ITM-9411', 296, 296, 0),
  (22, 6, 'ITM-8782', 155, 155, 0),
  (23, 6, 'ITM-4764', 24, 24, 0),
  (24, 6, 'ITM-3', 148, 148, 0),
  (25, 6, 'ITM-4', 110, 112, 2),
  (26, 6, 'ITM-4130', 700, 698, -2),
  (27, 6, 'ITM-2', 547, 545, -2),
  (28, 7, 'ITM-9411', 296, 296, 0),
  (29, 7, 'ITM-8782', 155, 155, 0),
  (30, 7, 'ITM-4764', 24, 24, 0),
  (31, 7, 'ITM-3', 148, 148, 0),
  (32, 7, 'ITM-4', 110, 112, 2),
  (33, 7, 'ITM-4130', 700, 698, -2),
  (34, 7, 'ITM-2', 547, 545, -2),
  (35, 8, 'ITM-9411', 296, 300, 4),
  (36, 8, 'ITM-8782', 155, 155, 0),
  (37, 8, 'ITM-4764', 24, 24, 0),
  (38, 8, 'ITM-3', 148, 148, 0),
  (39, 8, 'ITM-4', 112, 112, 0),
  (40, 8, 'ITM-4130', 698, 695, -3),
  (41, 8, 'ITM-2', 545, 550, 5);

-- ----------------------------------------------------------
-- Table structure for table `audit_logs`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `previous_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Dumping data for table `audit_logs` (2 records)
INSERT INTO `audit_logs` (`id`, `user_id`, `action_type`, `entity_type`, `entity_id`, `previous_value`, `new_value`, `ip_address`, `timestamp`) VALUES
  (1, 1, 'USER_DEACTIVATED', 'user', 3, 'active', 'inactive', '::1', '2026-09-09 15:02:09'),
  (2, 1, 'USER_ACTIVATED', 'user', 3, 'inactive', 'active', '::1', '2026-09-09 15:02:41');

-- ----------------------------------------------------------
-- Table structure for table `categories`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `categories` (4 records)
INSERT INTO `categories` (`id`, `category_name`, `created_at`) VALUES
  (1, 'Materials', '2026-07-24 16:32:28'),
  (2, 'Tools', '2026-07-24 16:32:28'),
  (3, 'Safety Equipment', '2026-07-24 16:32:28'),
  (4, 'Heavy Machinery', '2026-07-24 16:32:28');

-- ----------------------------------------------------------
-- Table structure for table `inventory`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(50) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `idx_inventory_item_name` (`item_name`),
  KEY `idx_inventory_status` (`status`),
  KEY `idx_inventory_last_updated` (`last_updated`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `inventory` (9 records)
INSERT INTO `inventory` (`id`, `item_code`, `item_name`, `category`, `quantity`, `unit`, `unit_price`, `status`, `last_updated`) VALUES
  (2, 'ITM-2', 'Steel Rebar (12mm)', 'Materials', 550, 'Pieces', '12.00', 'In Stock', '2026-09-03 09:12:05'),
  (3, 'ITM-3', 'Makita Power Drill', 'Tools', 148, 'Units', '14.00', 'In Stock', '2026-08-05 09:40:59'),
  (4, 'ITM-4', 'Safety Helmets', 'Safety', 112, 'Pieces', '15.00', 'In Stock', '2026-09-03 09:03:50'),
  (7, 'ITM-4130', 'Sand', 'Materials', 695, 'Cubic Meters', '0.00', 'In Stock', '2026-09-03 09:12:05'),
  (8, 'ITM-9411', 'Concrete Nails', 'Materials', 300, 'Pieces', '0.00', 'In Stock', '2026-09-03 09:12:05'),
  (9, 'ITM-8782', 'Electrical Tape', 'Electrical Supplies', 155, 'Pieces', '0.00', 'In Stock', '2026-08-03 09:47:43'),
  (10, 'ITM-4764', 'Hydraulic oil', 'Heavy Machinery', 24, 'Liters', '0.00', 'In Stock', '2026-08-12 17:20:07'),
  (11, 'ITM-5056', 'Engine Oil', 'Heavy Machinery', 0, 'Liters', '0.00', 'Out of Stock', '2026-09-05 21:04:54'),
  (12, 'ITM-5616', 'Solar Panel', 'Materials', 13, 'Units', '0.00', 'In Stock', '2026-09-07 21:11:22');

-- ----------------------------------------------------------
-- Table structure for table `inventory_audits`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `inventory_audits`;
CREATE TABLE `inventory_audits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_month` varchar(50) NOT NULL,
  `conducted_by` int(11) NOT NULL,
  `total_discrepancy_items` int(11) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audits_created_at` (`created_at`),
  KEY `idx_audits_conducted_by` (`conducted_by`),
  CONSTRAINT `inventory_audits_ibfk_1` FOREIGN KEY (`conducted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `inventory_audits` (6 records)
INSERT INTO `inventory_audits` (`id`, `audit_month`, `conducted_by`, `total_discrepancy_items`, `remarks`, `created_at`) VALUES
  (3, 'Week 31 — Jul 27–Aug 2, 2026', 2, 1, '', '2026-07-30 16:12:09'),
  (4, 'Week 32 — Aug 3–9, 2026', 1, 0, '', '2026-08-03 09:59:51'),
  (5, 'Week 36 — Aug 31–Sep 6, 2026', 1, 3, '', '2026-09-03 09:03:50'),
  (6, 'Week 36 — Aug 31–Sep 6, 2026', 1, 3, '', '2026-09-03 09:04:16'),
  (7, 'Week 36 — Aug 31–Sep 6, 2026', 1, 3, '', '2026-09-03 09:06:31'),
  (8, 'Week 36 — Aug 31–Sep 6, 2026', 1, 3, 'Concrete nails kuwang', '2026-09-03 09:12:05');

-- ----------------------------------------------------------
-- Table structure for table `notifications`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `target_user_id` int(11) DEFAULT NULL,
  `target_role` varchar(50) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_target_role` (`target_role`),
  KEY `idx_notif_target_user_id` (`target_user_id`),
  KEY `idx_notif_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=280 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `notifications` (279 records)
INSERT INTO `notifications` (`id`, `target_user_id`, `target_role`, `title`, `message`, `is_read`, `created_at`) VALUES
  (1, 2, NULL, 'Requisition Rejected', 'Your request RS-2026-8415 was rejected by Management.', 1, '2026-02-24 16:57:21'),
  (2, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-8473 for City Hall.', 1, '2026-02-26 13:25:21'),
  (3, NULL, 'management', 'New Requisition Pending', 'jahz submitted RS-2026-6380 for City Hall.', 0, '2026-02-26 14:22:35'),
  (4, 5, NULL, 'Requisition Approved', 'Your request RS-2026-6380 has been approved.', 0, '2026-02-26 14:24:28'),
  (5, NULL, 'purchasing', 'Ready for PO', 'RS-2026-6380 was approved. Please generate a PO.', 1, '2026-02-26 14:24:28'),
  (6, NULL, 'management', 'Audit Discrepancy Alert', 'The February 2026 audit found 1 items with discrepancies. Please review the audit trail immediately.', 0, '2026-02-26 20:22:32'),
  (7, NULL, 'management', 'Audit Discrepancy Alert', 'The February 2026 audit found 1 items with discrepancies. Please review the audit trail immediately.', 0, '2026-02-26 20:22:55'),
  (8, NULL, 'management', 'Audit Discrepancy Alert', 'The February 2026 audit found 1 items with discrepancies. Please review the audit trail immediately.', 0, '2026-02-27 15:44:02'),
  (9, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260228-267 has been generated. Prepare space to receive materials.', 1, '2026-02-28 10:07:24'),
  (10, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to Holcim Philippines for PO-20260228-267.', 0, '2026-02-28 10:07:37'),
  (11, NULL, 'management', 'Audit Discrepancy Alert', 'The February 2026 audit found 1 items with discrepancies. Please review the audit trail immediately.', 0, '2026-02-28 10:23:21'),
  (12, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260301-344 has been generated. Prepare space to receive materials.', 1, '2026-03-01 20:09:34'),
  (13, NULL, 'purchasing', 'PO Delivered', 'Order PO-20260301-344 has arrived and was received at the warehouse.', 1, '2026-03-01 20:22:58'),
  (14, NULL, 'management', 'PO Delivered', 'Order PO-20260301-344 has arrived and was received at the warehouse.', 0, '2026-03-01 20:22:58'),
  (15, NULL, 'purchasing', 'PO Delivered', 'Order PO-20260228-267 has arrived and was received at the warehouse.', 1, '2026-03-01 20:23:01'),
  (16, NULL, 'management', 'PO Delivered', 'Order PO-20260228-267 has arrived and was received at the warehouse.', 0, '2026-03-01 20:23:01'),
  (17, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-5584 for SCC Buenavista Campus.', 0, '2026-03-02 06:23:12'),
  (18, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to Holcim Philippines for PO-20260228-267.', 0, '2026-03-02 07:01:08'),
  (19, NULL, 'management', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 3 items with discrepancies.', 0, '2026-03-02 07:06:11'),
  (20, NULL, 'admin', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 3 items with discrepancies.', 1, '2026-03-02 07:06:11'),
  (21, NULL, 'management', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 0, '2026-03-02 07:10:40'),
  (22, NULL, 'admin', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 1, '2026-03-02 07:10:40'),
  (23, NULL, 'management', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 0, '2026-03-02 07:49:03'),
  (24, NULL, 'admin', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 1, '2026-03-02 07:49:03'),
  (25, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - Bagyong Basyang', 0, '2026-03-02 10:22:50'),
  (26, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - Bagyong Basyang', 1, '2026-03-02 10:22:50'),
  (27, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Naay dagop!', 0, '2026-03-02 10:28:20'),
  (28, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Naay dagop!', 1, '2026-03-02 10:28:20'),
  (29, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Naay dagop!', 1, '2026-03-02 10:28:20'),
  (30, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to Holcim Philippines for PO-20260228-267.', 0, '2026-03-02 10:29:00'),
  (31, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 0, '2026-03-02 10:59:40'),
  (32, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 1, '2026-03-02 10:59:40'),
  (33, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 1, '2026-03-02 10:59:40'),
  (34, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 0, '2026-03-02 10:59:45'),
  (35, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 1, '2026-03-02 10:59:45'),
  (36, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 1, '2026-03-02 10:59:45'),
  (37, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 0, '2026-03-02 12:58:58'),
  (38, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 1, '2026-03-02 12:58:58'),
  (39, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 1, '2026-03-02 12:58:58'),
  (40, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 0, '2026-03-02 13:00:02'),
  (41, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 1, '2026-03-02 13:00:02'),
  (42, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 1, '2026-03-02 13:00:02'),
  (43, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop', 0, '2026-03-02 13:00:57'),
  (44, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop', 1, '2026-03-02 13:00:57'),
  (45, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop', 1, '2026-03-02 13:00:57'),
  (46, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 19:37:49'),
  (47, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 19:37:49'),
  (48, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 19:38:15'),
  (49, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 19:38:15'),
  (50, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 19:41:32'),
  (51, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 19:41:32'),
  (52, NULL, 'management', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. LJ Caballero found 1 items with discrepancies.', 0, '2026-03-03 19:45:22'),
  (53, NULL, 'admin', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. LJ Caballero found 1 items with discrepancies.', 1, '2026-03-03 19:45:22'),
  (54, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 19:54:57'),
  (55, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 19:54:57'),
  (56, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 19:56:26'),
  (57, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 19:56:26'),
  (58, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - LTO Dakop\r\n', 0, '2026-03-03 20:03:31'),
  (59, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - LTO Dakop\r\n', 1, '2026-03-03 20:03:31'),
  (60, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - LTO Dakop\r\n', 1, '2026-03-03 20:03:31'),
  (61, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 0, '2026-03-03 20:08:20'),
  (62, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 1, '2026-03-03 20:08:20'),
  (63, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 1, '2026-03-03 20:08:20'),
  (64, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 20:08:38'),
  (65, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 20:08:38'),
  (66, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-6602 for SCC Buenavista Campus.', 0, '2026-03-03 20:08:53'),
  (67, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-6474 for SCC Buenavista Campus.', 0, '2026-03-03 20:11:01'),
  (68, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 20:12:03'),
  (69, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 20:12:03'),
  (70, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 20:42:49'),
  (71, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 20:42:49'),
  (72, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 21:04:59'),
  (73, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 21:04:59'),
  (74, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 0, '2026-03-03 21:06:10'),
  (75, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 1, '2026-03-03 21:06:10'),
  (76, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 1, '2026-03-03 21:06:10'),
  (77, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 0, '2026-03-03 21:11:26'),
  (78, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 1, '2026-03-03 21:11:26'),
  (79, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 1, '2026-03-03 21:11:26'),
  (80, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-04 07:11:40'),
  (81, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-04 07:11:40'),
  (82, NULL, 'management', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 0, '2026-03-05 10:38:22'),
  (83, NULL, 'admin', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 1, '2026-03-05 10:38:22'),
  (84, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully. All physical stocks match the system records exactly.', 1, '2026-03-05 14:09:34'),
  (85, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully. All physical stocks match the system records exactly.', 1, '2026-03-05 14:10:11'),
  (86, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully. All physical stocks match the system records exactly.', 1, '2026-03-05 15:25:36'),
  (87, 2, NULL, 'Requisition Approved', 'Your request RS-2026-6474 has been approved.', 1, '2026-03-08 09:26:02'),
  (88, NULL, 'purchasing', 'Ready for PO', 'RS-2026-6474 was approved. Please generate a PO.', 0, '2026-03-08 09:26:02'),
  (89, 2, NULL, 'Requisition Rejected', 'Your request RS-2026-6602 was rejected.', 1, '2026-03-08 09:35:45'),
  (90, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-8532 for SCC Buenavista Campus.', 0, '2026-03-08 09:56:13'),
  (91, 2, NULL, 'Requisition Approved', 'Your request RS-2026-8532 has been approved.', 1, '2026-03-08 09:56:24'),
  (92, NULL, 'purchasing', 'Ready for PO', 'RS-2026-8532 was approved. Please generate a PO.', 0, '2026-03-08 09:56:24'),
  (93, NULL, 'management', 'New Requisition Pending', 'Coco Martin submitted RS-2026-1810 for SCC Buenavista Campus.', 0, '2026-03-08 09:58:23'),
  (94, 12, NULL, 'Requisition Approved', 'Your request RS-2026-1810 has been approved.', 0, '2026-03-08 09:58:32'),
  (95, NULL, 'purchasing', 'Ready for PO', 'RS-2026-1810 was approved. Please generate a PO.', 0, '2026-03-08 09:58:32'),
  (96, NULL, 'management', 'New Requisition Pending', 'Coco Martin submitted RS-2026-4721 for SCC Buenavista Campus.', 0, '2026-03-08 10:10:14'),
  (97, 12, NULL, 'Requisition Rejected', 'Your request RS-2026-4721 was rejected. Reason: Insufficient stock from electrical tape', 0, '2026-03-08 10:10:55'),
  (98, NULL, 'purchasing', 'PO Delivered & Stocked In', 'Order PO-20260228-267 has arrived. Items have been successfully STOCKED IN to the Master Inventory.', 0, '2026-03-09 07:49:34'),
  (99, NULL, 'management', 'PO Delivered & Stocked In', 'Order PO-20260228-267 has arrived. Items have been successfully STOCKED IN to the Master Inventory.', 0, '2026-03-09 07:49:34'),
  (100, NULL, 'admin', 'Weekly Audit Completed', 'The Week 11 — Mar 9–15, 2026 weekly recount was completed successfully. All physical stocks match the system records exactly.', 1, '2026-03-16 06:46:43'),
  (101, NULL, 'management', 'New Requisition Pending', 'System Admin submitted RS-2026-4947 for SCC Main.', 0, '2026-03-19 11:03:18'),
  (102, 1, NULL, 'Requisition Approved', 'Your request RS-2026-2875 has been approved.', 1, '2026-03-19 11:03:36'),
  (103, NULL, 'purchasing', 'Ready for PO', 'RS-2026-2875 was approved. Please generate a PO.', 0, '2026-03-19 11:03:36'),
  (104, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260319-295 with verified item list.', 0, '2026-03-19 11:03:44'),
  (105, 1, NULL, 'Requisition Approved', 'Your request RS-2026-4947 has been approved.', 1, '2026-03-19 11:04:37'),
  (106, NULL, 'purchasing', 'Ready for PO', 'RS-2026-4947 was approved. Please generate a PO.', 0, '2026-03-19 11:04:37'),
  (107, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-8546 for General Restocking.', 0, '2026-03-19 11:09:29'),
  (108, 2, NULL, 'Requisition Approved', 'Your request RS-2026-8546 has been approved.', 1, '2026-03-19 11:09:40'),
  (109, NULL, 'purchasing', 'Ready for PO', 'RS-2026-8546 was approved. Please generate a PO.', 0, '2026-03-19 11:09:40'),
  (110, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260319-611 has been generated. Prepare space to receive materials.', 1, '2026-03-19 11:10:44'),
  (111, NULL, 'purchasing', 'PO Discrepancy Found', 'DISCREPANCY ALERT for PO-20260319-611: Order arrived physically with missing or excess items!\n- Concrete Nails [Code: ITM-9411]: Expected 15, Received 14', 0, '2026-03-19 11:18:45'),
  (112, NULL, 'management', 'PO Receiving Discrepancy', 'DISCREPANCY ALERT for PO-20260319-611: Order arrived physically with missing or excess items!\n- Concrete Nails [Code: ITM-9411]: Expected 15, Received 14', 0, '2026-03-19 11:18:45'),
  (113, NULL, 'admin', 'PO Discrepancy Alert', 'DISCREPANCY ALERT for PO-20260319-611: Order arrived physically with missing or excess items!\n- Concrete Nails [Code: ITM-9411]: Expected 15, Received 14', 1, '2026-03-19 11:18:45'),
  (114, NULL, 'management', 'New Requisition Pending', 'System Admin submitted a Warehouse Restock request (RS-2026-1949).', 0, '2026-07-24 16:34:54'),
  (115, 1, NULL, 'Requisition Approved', 'Your request RS-2026-1949 has been approved.', 1, '2026-07-24 16:34:57'),
  (116, NULL, 'purchasing', 'Ready for PO', 'RS-2026-1949 was approved. Please generate a PO.', 0, '2026-07-24 16:34:57'),
  (117, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260724-574 has been generated. Prepare space to receive materials.', 1, '2026-07-24 16:35:44'),
  (118, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260724-574 with verified item list.', 0, '2026-07-24 16:40:12'),
  (119, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260724-574 with verified item list.', 0, '2026-07-24 16:41:19'),
  (120, NULL, 'purchasing', '📩 SMS Reply: Unknown Supplier', 'Supplier (+639098702199) sent: \"Hello CIMS, we have 40 bags of Holcim cement available for immediate delivery.\"', 0, '2026-07-24 16:51:22'),
  (121, NULL, 'admin', '📩 SMS Reply: Unknown Supplier', 'Supplier (+639098702199) sent: \"Hello CIMS, we have 40 bags of Holcim cement available for immediate delivery.\"', 1, '2026-07-24 16:51:22'),
  (122, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260724-574 with verified item list.', 0, '2026-07-24 16:54:28'),
  (123, NULL, 'purchasing', '📩 SMS Reply: Unknown Supplier', 'Supplier (+639098702199) sent: \"Hello CIMS, this is an automated supplier test response!\"', 0, '2026-07-24 17:11:17'),
  (124, NULL, 'admin', '📩 SMS Reply: Unknown Supplier', 'Supplier (+639098702199) sent: \"Hello CIMS, this is an automated supplier test response!\"', 1, '2026-07-24 17:11:17'),
  (125, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260724-574 with verified item list.', 0, '2026-07-24 17:13:37'),
  (126, NULL, 'purchasing', '📩 SMS Reply: City Hardware', 'Supplier (+639760167906) sent: \"Test\"', 0, '2026-07-24 17:20:09'),
  (127, NULL, 'admin', '📩 SMS Reply: City Hardware', 'Supplier (+639760167906) sent: \"Test\"', 1, '2026-07-24 17:20:09'),
  (128, NULL, 'purchasing', '📩 SMS Reply: City Hardware', 'Supplier (+639760167906) sent: \"Test\"', 0, '2026-07-24 17:25:38'),
  (129, NULL, 'admin', '📩 SMS Reply: City Hardware', 'Supplier (+639760167906) sent: \"Test\"', 1, '2026-07-24 17:25:38'),
  (130, NULL, 'warehouse', '🚚 Supply ETA Updated: PO-20260724-574', 'Delivery from City Hardware is now estimated to arrive at warehouse on Jul 30, 2026.', 1, '2026-07-29 08:33:41'),
  (131, NULL, 'management', '🚚 Supply ETA Updated: PO-20260724-574', 'Delivery from City Hardware is now estimated to arrive at warehouse on Jul 30, 2026.', 0, '2026-07-29 08:33:42'),
  (132, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted a Warehouse Restock request (RS-2026-8870).', 0, '2026-07-29 08:38:36'),
  (133, 2, NULL, 'Requisition Approved', 'Your request RS-2026-8870 has been approved.', 1, '2026-07-29 08:39:05'),
  (134, NULL, 'purchasing', 'Ready for PO', 'RS-2026-8870 was approved. Please generate a PO.', 0, '2026-07-29 08:39:05'),
  (135, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260729-744 generated. Target Warehouse ETA: Jul 30, 2026. Prepare space to receive materials.', 1, '2026-07-29 08:39:26'),
  (136, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to Holcim Philippines for PO-20260729-744 with verified item list.', 0, '2026-07-29 08:40:26'),
  (137, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260724-574 is delayed. Reason: Road / Traffic Conditions - Dakop', 0, '2026-07-29 08:51:31'),
  (138, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260724-574 is delayed. Reason: Road / Traffic Conditions - Dakop', 1, '2026-07-29 08:51:31'),
  (139, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260724-574 is delayed. Reason: Road / Traffic Conditions - Dakop', 1, '2026-07-29 08:51:31'),
  (140, NULL, 'management', 'New Requisition Pending', 'Angelo Carlo Pedrosa submitted a Warehouse Restock request (RS-2026-6640).', 0, '2026-07-30 15:25:05'),
  (141, 1, NULL, 'Requisition Approved', 'Your request RS-2026-6640 has been approved.', 1, '2026-07-30 15:25:08'),
  (142, NULL, 'purchasing', 'Ready for PO', 'RS-2026-6640 was approved. Please generate a PO.', 0, '2026-07-30 15:25:08'),
  (143, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260730-977 generated. Target Warehouse ETA: Aug 02, 2026. Prepare space to receive materials.', 1, '2026-07-30 15:25:21'),
  (144, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to SCC Enterprises for PO-20260730-977 with verified item list.', 0, '2026-07-30 15:25:38'),
  (145, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to SCC Enterprises for PO-20260730-977 with verified item list.', 0, '2026-07-30 15:28:09'),
  (146, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to SCC Enterprises for PO-20260730-977 with verified item list.', 0, '2026-07-30 15:32:44'),
  (147, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to SCC Enterprises for PO-20260730-977 with verified item list.', 0, '2026-07-30 15:35:28'),
  (148, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to SCC Enterprises for PO-20260730-977 with verified item list.', 0, '2026-07-30 15:36:58'),
  (149, NULL, 'purchasing', '📩 SMS Reply: SCC Enterprises', 'Supplier (+639516129602) sent: \"Ok\"', 0, '2026-07-30 15:39:55'),
  (150, NULL, 'admin', '📩 SMS Reply: SCC Enterprises', 'Supplier (+639516129602) sent: \"Ok\"', 1, '2026-07-30 15:39:55'),
  (151, NULL, 'management', 'New Requisition Pending', 'Jahzeel Jakosalem submitted RS-2026-8118 for Main Headquarters Construction.', 0, '2026-07-30 15:58:33'),
  (152, 4, NULL, 'Requisition Approved', 'Your request RS-2026-8118 has been approved.', 1, '2026-07-30 16:00:08'),
  (153, NULL, 'purchasing', 'Ready for PO', 'RS-2026-8118 was approved. Please generate a PO.', 0, '2026-07-30 16:00:08'),
  (154, 4, NULL, 'Requisition Released', 'Materials for your requisition RS-2026-8118 have been released from the warehouse.', 1, '2026-07-30 16:06:48'),
  (155, NULL, 'admin', 'Weekly Audit Discrepancy Alert', 'The Week 31 — Jul 27–Aug 2, 2026 weekly recount is complete. Found 1 item(s) with discrepancies. Inventory has been adjusted.', 1, '2026-07-30 16:12:09'),
  (156, NULL, 'management', 'Weekly Audit Discrepancy Alert', 'The Week 31 — Jul 27–Aug 2, 2026 weekly recount is complete. Found 1 item(s) with discrepancies. Inventory has been adjusted.', 0, '2026-07-30 16:12:09'),
  (157, NULL, 'warehouse', 'Weekly Audit Discrepancy Alert', 'The Week 31 — Jul 27–Aug 2, 2026 weekly recount is complete. Found 1 item(s) with discrepancies. Inventory has been adjusted.', 1, '2026-07-30 16:12:09'),
  (158, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted a Warehouse Restock request (RS-2026-6396).', 0, '2026-07-30 16:18:21'),
  (159, 2, NULL, 'Requisition Approved', 'Your request RS-2026-6396 has been approved.', 1, '2026-07-30 16:19:01'),
  (160, NULL, 'purchasing', 'Ready for PO', 'RS-2026-6396 was approved. Please generate a PO.', 0, '2026-07-30 16:19:01'),
  (161, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260730-903 generated. Target Warehouse ETA: Aug 02, 2026. Prepare space to receive materials.', 1, '2026-07-30 16:20:59'),
  (162, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to SCC Enterprises for PO-20260730-903 with verified item list.', 0, '2026-07-30 16:21:35'),
  (163, NULL, 'purchasing', '📩 SMS Reply: SCC Enterprises', 'Supplier (+639516129602) sent: \"Its not available\"', 0, '2026-07-30 16:22:14'),
  (164, NULL, 'admin', '📩 SMS Reply: SCC Enterprises', 'Supplier (+639516129602) sent: \"Its not available\"', 1, '2026-07-30 16:22:14'),
  (165, NULL, 'purchasing', 'PO Delivered & Verified', 'Order PO-20260730-903 has arrived complete. Exactly correct quantities successfully STOCKED IN to Master Inventory.', 0, '2026-07-30 16:32:30'),
  (166, NULL, 'management', 'PO Delivered & Verified', 'Order PO-20260730-903 has arrived complete. Exactly correct quantities successfully STOCKED IN to Master Inventory.', 0, '2026-07-30 16:32:30'),
  (167, NULL, 'admin', 'PO Delivered & Verified', 'Order PO-20260730-903 has arrived complete. Exactly correct quantities successfully STOCKED IN to Master Inventory.', 1, '2026-07-30 16:32:30'),
  (168, NULL, 'management', 'New Requisition Pending', 'Jahzeel Jakosalem submitted RS-2026-1960 for Main Headquarters Construction. ⚠️ Conflict alert: Stock deficit for Makita Power Drill.', 0, '2026-07-30 16:40:54'),
  (169, 4, NULL, 'Requisition Conflict Warning', 'Requisition submitted, but warning: stock conflicts detected for Makita Power Drill.', 1, '2026-07-30 16:40:55'),
  (170, NULL, 'admin', 'Requisition Conflict Alert', 'Conflict Alert: Requisition RS-2026-1960 submitted by Jahzeel Jakosalem for Main Headquarters Construction has stock conflicts: Makita Power Drill.', 1, '2026-07-30 16:40:55'),
  (171, NULL, 'purchasing', 'PO Delivered & Verified', 'Order PO-20260730-977 has arrived complete. Exactly correct quantities successfully STOCKED IN to Master Inventory.', 0, '2026-08-03 09:47:43'),
  (172, NULL, 'management', 'PO Delivered & Verified', 'Order PO-20260730-977 has arrived complete. Exactly correct quantities successfully STOCKED IN to Master Inventory.', 0, '2026-08-03 09:47:43'),
  (173, NULL, 'admin', 'PO Delivered & Verified', 'Order PO-20260730-977 has arrived complete. Exactly correct quantities successfully STOCKED IN to Master Inventory.', 1, '2026-08-03 09:47:43'),
  (174, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260729-744 is delayed. Reason: Port / Customs Hold. New ETA: Aug 04, 2026', 0, '2026-08-03 09:50:17'),
  (175, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260729-744 is delayed. Reason: Port / Customs Hold. New ETA: Aug 04, 2026', 0, '2026-08-03 09:50:17'),
  (176, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260729-744 is delayed. Reason: Port / Customs Hold. New ETA: Aug 04, 2026', 1, '2026-08-03 09:50:17'),
  (177, NULL, 'admin', 'Weekly Audit Completed', 'The Week 32 — Aug 3–9, 2026 weekly recount was completed successfully. All physical stocks match the system records exactly.', 1, '2026-08-03 09:59:51'),
  (178, NULL, 'management', 'Weekly Audit Completed', 'The Week 32 — Aug 3–9, 2026 weekly recount was completed successfully. All physical stocks match the system records exactly.', 0, '2026-08-03 09:59:51'),
  (179, NULL, 'warehouse', 'Weekly Audit Completed', 'The Week 32 — Aug 3–9, 2026 weekly recount was completed successfully. All physical stocks match the system records exactly.', 0, '2026-08-03 09:59:51'),
  (180, 1, NULL, 'Materials Staged (Ready for Pickup)', 'Your requested materials for RS-2026-4947 have been pre-picked & staged by the Warehouse In-Charge. Ready for express pickup!', 1, '2026-08-03 10:11:47'),
  (181, 4, NULL, 'Requisition Approved', 'Your request RS-2026-1960 has been approved.', 1, '2026-08-03 10:13:00'),
  (182, NULL, 'purchasing', 'Ready for PO', 'RS-2026-1960 was approved. Please generate a PO.', 0, '2026-08-03 10:13:00'),
  (183, 4, NULL, 'Materials Staged (Ready for Pickup)', 'Your requested materials for RS-2026-1960 have been pre-picked & staged by the Warehouse In-Charge. Ready for express pickup!', 1, '2026-08-03 10:13:15'),
  (184, NULL, 'management', 'New Requisition Pending', 'Jahzeel Jakosalem submitted RS-2026-3327 for Main Headquarters Construction.', 0, '2026-08-03 10:15:01'),
  (185, 4, NULL, 'Requisition Approved', 'Your request RS-2026-3327 has been approved.', 1, '2026-08-03 10:15:37'),
  (186, NULL, 'purchasing', 'Ready for PO', 'RS-2026-3327 was approved. Please generate a PO.', 0, '2026-08-03 10:15:37'),
  (187, NULL, 'management', 'New Requisition Pending', 'Jahzeel Jakosalem submitted RS-2026-9286 for Main Headquarters Construction.', 0, '2026-08-03 10:46:54'),
  (188, 4, NULL, 'Requisition Rejected', 'Your request RS-2026-9286 was rejected. Reason: Already approved', 1, '2026-08-03 10:48:14'),
  (189, 4, NULL, 'Materials Staged (Ready for Pickup)', 'Your requested materials for RS-2026-3327 have been pre-picked & staged by the Warehouse In-Charge. Ready for express pickup!', 1, '2026-08-03 11:06:18'),
  (190, 4, NULL, 'Requisition Released', 'Materials for your requisition RS-2026-3327 have been released from the warehouse.', 1, '2026-08-03 11:15:00'),
  (191, NULL, 'management', 'New Requisition Pending', 'Angelo Carlo Pedrosa submitted RS-2026-1686 for Balay Ni Maam.', 0, '2026-08-03 11:57:34'),
  (192, 1, NULL, 'Requisition Approved', 'Your request RS-2026-1686 has been approved.', 1, '2026-08-03 11:57:47'),
  (193, NULL, 'purchasing', 'Ready for PO', 'RS-2026-1686 was approved. Please generate a PO.', 0, '2026-08-03 11:57:47'),
  (194, 1, NULL, 'Materials Staged (Ready for Pickup)', 'Your requested materials for RS-2026-1686 have been pre-picked & staged by the Warehouse In-Charge. Ready for express pickup!', 1, '2026-08-03 11:57:56'),
  (195, 1, NULL, 'Requisition Released', 'Materials for your requisition RS-2026-1686 have been released from the warehouse.', 1, '2026-08-03 11:59:34'),
  (196, NULL, 'management', 'New Requisition Pending', 'Angelo Carlo Pedrosa submitted a Warehouse Restock request (RS-2026-4182).', 0, '2026-08-04 14:45:27'),
  (197, 1, NULL, 'Requisition Approved', 'Your request RS-2026-4182 has been approved.', 1, '2026-08-04 14:46:13'),
  (198, NULL, 'purchasing', 'Ready for PO', 'RS-2026-4182 was approved. Please generate a PO.', 0, '2026-08-04 14:46:13'),
  (199, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260804-791 generated. Target Warehouse ETA: Aug 07, 2026. Prepare space to receive materials.', 0, '2026-08-04 14:47:01'),
  (200, NULL, 'purchasing', 'PO Delivered & Verified', 'Order PO-20260729-744 has arrived complete. Exactly correct quantities and updated prices successfully STOCKED IN to Master Inventory.', 0, '2026-08-04 14:47:15'),
  (201, NULL, 'management', 'PO Delivered & Verified', 'Order PO-20260729-744 has arrived complete. Exactly correct quantities and updated prices successfully STOCKED IN to Master Inventory.', 0, '2026-08-04 14:47:15'),
  (202, NULL, 'admin', 'PO Delivered & Verified', 'Order PO-20260729-744 has arrived complete. Exactly correct quantities and updated prices successfully STOCKED IN to Master Inventory.', 1, '2026-08-04 14:47:15'),
  (203, NULL, 'purchasing', 'PO Delivered & Verified', 'Order PO-20260804-791 has arrived complete. Exactly correct quantities and updated prices successfully STOCKED IN to Master Inventory.', 0, '2026-08-05 09:40:59'),
  (204, NULL, 'management', 'PO Delivered & Verified', 'Order PO-20260804-791 has arrived complete. Exactly correct quantities and updated prices successfully STOCKED IN to Master Inventory.', 0, '2026-08-05 09:40:59'),
  (205, NULL, 'admin', 'PO Delivered & Verified', 'Order PO-20260804-791 has arrived complete. Exactly correct quantities and updated prices successfully STOCKED IN to Master Inventory.', 1, '2026-08-05 09:40:59'),
  (206, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260724-574 with verified item list.', 0, '2026-08-08 15:33:49'),
  (207, NULL, 'management', 'Viber Order Dispatched', 'Viber Order message was dispatched to City Hardware for PO-20260724-574.', 0, '2026-08-08 15:33:55'),
  (208, NULL, 'management', 'New Requisition Pending', 'Angelo Carlo Pedrosa submitted a Warehouse Restock request (RS-2026-3291).', 0, '2026-08-08 15:34:36'),
  (209, 1, NULL, 'Requisition Approved', 'Your request RS-2026-3291 has been approved.', 1, '2026-08-08 15:34:40'),
  (210, NULL, 'purchasing', 'Ready for PO', 'RS-2026-3291 was approved. Please generate a PO.', 0, '2026-08-08 15:34:40'),
  (211, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260808-590 generated. Target Warehouse ETA: Aug 11, 2026. Prepare space to receive materials.', 0, '2026-08-08 15:34:55'),
  (212, NULL, 'management', 'Viber Order Dispatched', 'Viber Order message was dispatched to City Hardware for PO-20260808-590.', 0, '2026-08-08 15:35:03'),
  (213, NULL, 'management', 'Viber Order Dispatched', 'Viber Order message was dispatched to City Hardware for PO-20260808-590.', 0, '2026-08-08 15:35:52'),
  (214, NULL, 'management', 'Viber Order Dispatched', 'Viber Order message was dispatched to City Hardware for PO-20260808-590.', 0, '2026-08-08 15:37:07'),
  (215, NULL, 'management', 'Viber Order Dispatched', 'Viber Order message was dispatched to City Hardware for PO-20260808-590.', 0, '2026-08-08 15:37:34'),
  (216, NULL, 'management', 'Viber Order Dispatched', 'Viber Order message was dispatched to City Hardware for PO-20260808-590.', 0, '2026-08-11 08:17:13'),
  (217, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted a Warehouse Restock request (RS-2026-6201).', 0, '2026-08-11 09:11:18'),
  (218, 2, NULL, 'Requisition Partially Approved', 'Your request RS-2026-6201 was partially approved — 1 of 2 items approved, 1 rejected.', 0, '2026-08-11 09:18:50'),
  (219, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted a Warehouse Restock request (RS-2026-1739).', 0, '2026-08-11 09:45:29'),
  (220, 2, NULL, 'Requisition Approved', 'Your request RS-2026-1739 has been fully approved. All items were approved by management.', 0, '2026-08-12 17:15:54'),
  (221, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260812-324 generated. Target Warehouse ETA: Aug 13, 2026. Prepare space to receive materials.', 0, '2026-08-12 17:19:03'),
  (222, NULL, 'purchasing', 'PO Discrepancy Found', 'DISCREPANCY ALERT for PO-20260812-324: Order arrived physically with missing or excess items!\n- Steel Rebar (12mm) [Code: ITM-2]: Expected 20, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', 0, '2026-08-12 17:20:05'),
  (223, NULL, 'management', 'PO Receiving Discrepancy', 'DISCREPANCY ALERT for PO-20260812-324: Order arrived physically with missing or excess items!\n- Steel Rebar (12mm) [Code: ITM-2]: Expected 20, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', 0, '2026-08-12 17:20:05'),
  (224, NULL, 'admin', 'PO Discrepancy Alert', 'DISCREPANCY ALERT for PO-20260812-324: Order arrived physically with missing or excess items!\n- Steel Rebar (12mm) [Code: ITM-2]: Expected 20, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', 1, '2026-08-12 17:20:05'),
  (225, NULL, 'purchasing', 'PO Discrepancy Found', 'DISCREPANCY ALERT for PO-20260812-324: Order arrived physically with missing or excess items!\n- Steel Rebar (12mm) [Code: ITM-2]: Expected 20, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', 0, '2026-08-12 17:20:07'),
  (226, NULL, 'management', 'PO Receiving Discrepancy', 'DISCREPANCY ALERT for PO-20260812-324: Order arrived physically with missing or excess items!\n- Steel Rebar (12mm) [Code: ITM-2]: Expected 20, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', 0, '2026-08-12 17:20:07'),
  (227, NULL, 'admin', 'PO Discrepancy Alert', 'DISCREPANCY ALERT for PO-20260812-324: Order arrived physically with missing or excess items!\n- Steel Rebar (12mm) [Code: ITM-2]: Expected 20, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', 1, '2026-08-12 17:20:07'),
  (228, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted a Warehouse Restock request (RS-2026-4145).', 0, '2026-08-13 07:35:29'),
  (229, 2, NULL, 'Requisition Approved', 'Your request RS-2026-4145 has been fully approved. All items were approved by management.', 0, '2026-08-13 07:36:13'),
  (230, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260813-165 generated. Target Warehouse ETA: Aug 15, 2026. Prepare space to receive materials.', 1, '2026-08-13 07:37:51'),
  (231, NULL, 'management', 'Viber Order Dispatched', 'Viber Order message was dispatched to SCC Enterprises for PO-20260813-165.', 0, '2026-08-13 07:40:58'),
  (232, NULL, 'management', 'New Requisition Pending', 'Jahzeel Jakosalem submitted RS-2026-7324 for Main Headquarters Construction.', 0, '2026-08-17 15:41:02'),
  (233, 4, NULL, 'Requisition Approved', 'Your request RS-2026-7324 has been fully approved. All items were approved by management.', 1, '2026-08-17 18:04:35'),
  (234, NULL, 'purchasing', 'Ready for PO', 'RS-2026-7324 was Approved. Please generate a PO for the approved items.', 0, '2026-08-17 18:04:37'),
  (235, NULL, 'management', 'New Requisition Pending', 'Angeleen Dy submitted RS-2026-8194 for Main Headquarters Construction.', 0, '2026-08-24 20:32:26'),
  (236, NULL, 'management', 'New Requisition Pending', 'Angeleen Dy submitted RS-2026-6976 for Main Headquarters Construction.', 0, '2026-08-24 20:32:48'),
  (237, NULL, 'management', 'New Requisition Pending', 'Angeleen Dy submitted RS-2026-1231 for Main Headquarters Construction.', 0, '2026-08-24 20:33:30'),
  (238, 5, NULL, 'Requisition Approved', 'Your request RS-2026-1231 has been fully approved. All items were approved by management.', 0, '2026-08-24 20:42:15'),
  (239, NULL, 'purchasing', 'Ready for PO', 'RS-2026-1231 was Approved. Please generate a PO for the approved items.', 0, '2026-08-24 20:42:18'),
  (240, 5, NULL, 'Requisition Approved', 'Your request RS-2026-1231 has been fully approved. All items were approved by management.', 0, '2026-08-24 20:42:21'),
  (241, NULL, 'purchasing', 'Ready for PO', 'RS-2026-1231 was Approved. Please generate a PO for the approved items.', 0, '2026-08-24 20:42:24'),
  (242, 5, NULL, 'Requisition Approved', 'Your request RS-2026-1231 has been fully approved. All items were approved by management.', 0, '2026-08-24 20:42:27'),
  (243, NULL, 'purchasing', 'Ready for PO', 'RS-2026-1231 was Approved. Please generate a PO for the approved items.', 0, '2026-08-24 20:42:28'),
  (244, 5, NULL, 'Requisition Released', 'Materials for your requisition RS-2026-1231 have been released from the warehouse.', 0, '2026-08-24 20:44:31'),
  (245, NULL, 'management', 'New Requisition Pending', 'Angeleen Dy submitted RS-2026-7245 for Main Headquarters Construction.', 0, '2026-08-24 20:46:31'),
  (246, 5, NULL, 'Requisition Approved', 'Your request RS-2026-7245 has been fully approved. All items were approved by management.', 0, '2026-08-24 20:50:20'),
  (247, NULL, 'purchasing', 'Ready for PO', 'RS-2026-7245 was Approved. Please generate a PO for the approved items.', 0, '2026-08-24 20:50:23'),
  (248, NULL, 'management', 'New Requisition Pending', 'Jahzeel Jakosalem submitted RS-2026-6518 for Main Headquarters Construction.', 0, '2026-08-26 15:02:54'),
  (249, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260901-391 generated. Target Warehouse ETA: Sep 04, 2026. Prepare space to receive materials.', 0, '2026-09-01 08:48:00'),
  (250, NULL, 'admin', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 1, '2026-09-03 09:03:50'),
  (251, NULL, 'management', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 0, '2026-09-03 09:03:50'),
  (252, NULL, 'warehouse', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 0, '2026-09-03 09:03:50'),
  (253, NULL, 'admin', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 1, '2026-09-03 09:04:16'),
  (254, NULL, 'management', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 0, '2026-09-03 09:04:16'),
  (255, NULL, 'warehouse', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 0, '2026-09-03 09:04:16'),
  (256, NULL, 'admin', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 1, '2026-09-03 09:06:31'),
  (257, NULL, 'management', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 0, '2026-09-03 09:06:31'),
  (258, NULL, 'warehouse', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 0, '2026-09-03 09:06:31'),
  (259, NULL, 'admin', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 1, '2026-09-03 09:12:05'),
  (260, NULL, 'management', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 0, '2026-09-03 09:12:05'),
  (261, NULL, 'warehouse', 'Weekly Audit Discrepancy Alert', 'The Week 36 — Aug 31–Sep 6, 2026 weekly recount is complete. Found 3 item(s) with discrepancies. Inventory has been adjusted.', 0, '2026-09-03 09:12:05'),
  (262, 5, NULL, 'Materials Staged (Ready for Pickup)', 'Your requested materials for RS-2026-7245 have been pre-picked & staged by the Warehouse In-Charge. Ready for express pickup!', 0, '2026-09-04 17:31:38'),
  (263, NULL, 'management', 'New Requisition Pending', 'Angelo Carlo Pedrosa submitted RS-2026-2605 for Main Headquarters Construction.', 0, '2026-09-05 20:57:04'),
  (264, NULL, 'purchasing', 'PO Discrepancy Found', 'DISCREPANCY ALERT for PO-20260901-391: Order arrived physically with missing or excess items!\n- Engine Oil [Code: ITM-5056]: Expected 15, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', 0, '2026-09-05 21:04:54'),
  (265, NULL, 'management', 'PO Receiving Discrepancy', 'DISCREPANCY ALERT for PO-20260901-391: Order arrived physically with missing or excess items!\n- Engine Oil [Code: ITM-5056]: Expected 15, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', 0, '2026-09-05 21:04:54'),
  (266, NULL, 'admin', 'PO Discrepancy Alert', 'DISCREPANCY ALERT for PO-20260901-391: Order arrived physically with missing or excess items!\n- Engine Oil [Code: ITM-5056]: Expected 15, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', 1, '2026-09-05 21:04:54'),
  (267, 1, NULL, 'Requisition Approved', 'Your request RS-2026-2605 has been fully approved. All items were approved by management.', 1, '2026-09-05 21:28:11'),
  (268, NULL, 'purchasing', 'Ready for PO', 'RS-2026-2605 was Approved. Please generate a PO for the approved items.', 0, '2026-09-05 21:28:12'),
  (269, NULL, 'purchasing', 'PO Partially Delivered', 'PARTIAL DELIVERY received for PO-20260808-590: 10 units physically stocked in. Remaining items are marked \'To Follow\' from supplier.', 0, '2026-09-07 17:29:58'),
  (270, NULL, 'management', 'PO Partially Delivered', 'PARTIAL DELIVERY received for PO-20260808-590: 10 units physically stocked in. Remaining items are marked \'To Follow\' from supplier.', 0, '2026-09-07 17:29:58'),
  (271, NULL, 'admin', 'PO Partially Delivered', 'PARTIAL DELIVERY received for PO-20260808-590: 10 units physically stocked in. Remaining items are marked \'To Follow\' from supplier.', 1, '2026-09-07 17:29:58'),
  (272, NULL, 'purchasing', 'PO Closed (Sold Out/Discrepancy)', 'DELIVERY CLOSED (SOLD OUT / DISCREPANCY) for PO-20260808-590: Order finalized with unfulfilled items marked Sold Out by supplier.\n- Solar Panel [Code: ITM-5616]: Received 3 units today (Total: 13/15) ⚠️ [SOLD OUT - 2 Remainder Cancelled by Supplier]', 0, '2026-09-07 21:11:22'),
  (273, NULL, 'management', 'PO Closed (Sold Out/Discrepancy)', 'DELIVERY CLOSED (SOLD OUT / DISCREPANCY) for PO-20260808-590: Order finalized with unfulfilled items marked Sold Out by supplier.\n- Solar Panel [Code: ITM-5616]: Received 3 units today (Total: 13/15) ⚠️ [SOLD OUT - 2 Remainder Cancelled by Supplier]', 0, '2026-09-07 21:11:22'),
  (274, NULL, 'admin', 'PO Closed (Sold Out/Discrepancy)', 'DELIVERY CLOSED (SOLD OUT / DISCREPANCY) for PO-20260808-590: Order finalized with unfulfilled items marked Sold Out by supplier.\n- Solar Panel [Code: ITM-5616]: Received 3 units today (Total: 13/15) ⚠️ [SOLD OUT - 2 Remainder Cancelled by Supplier]', 1, '2026-09-07 21:11:22'),
  (275, NULL, 'management', 'New Requisition Pending', 'Angelo Carlo Pedrosa submitted a Warehouse Restock request (RS-2026-7415).', 0, '2026-09-08 08:36:32'),
  (276, NULL, 'management', 'New Requisition Pending', 'Angelo Carlo Pedrosa submitted a Warehouse Restock request (RS-2026-2192).', 0, '2026-09-08 08:36:38'),
  (277, NULL, 'management', 'New Requisition Pending', 'Angelo Carlo Pedrosa submitted RS-2026-1812 for Main Headquarters Construction.', 0, '2026-09-08 08:38:32'),
  (278, 1, 'admin', 'User Account Deactivated', 'Admin Angelo Carlo Pedrosa inactive staff account \'Coco Martin\' from IP ::1.', 0, '2026-09-09 15:02:09'),
  (279, 1, 'admin', 'User Account Activated', 'Admin Angelo Carlo Pedrosa active staff account \'Coco Martin\' from IP ::1.', 0, '2026-09-09 15:02:41');

-- ----------------------------------------------------------
-- Table structure for table `po_items`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `po_items`;
CREATE TABLE `po_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_new_item` tinyint(1) DEFAULT 0,
  `custom_item_name` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `received_quantity` int(11) NOT NULL DEFAULT 0,
  `item_status` varchar(50) NOT NULL DEFAULT 'Pending',
  PRIMARY KEY (`id`),
  KEY `idx_po_items_po_id` (`po_id`),
  KEY `idx_po_items_item_code` (`item_code`),
  CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `po_items` (13 records)
INSERT INTO `po_items` (`id`, `po_id`, `item_code`, `quantity`, `unit_price`, `is_new_item`, `custom_item_name`, `category`, `unit`, `received_quantity`, `item_status`) VALUES
  (1, 1, 'ITM-8782', 50, '0.00', 0, NULL, NULL, NULL, 0, 'Pending'),
  (2, 2, 'ITM-9411', 100, '0.00', 0, NULL, NULL, NULL, 100, 'Complete'),
  (3, 2, 'ITM-2', 50, '12.00', 0, NULL, NULL, NULL, 50, 'Complete'),
  (4, 3, 'ITM-9411', 100, '0.00', 0, NULL, NULL, NULL, 100, 'Complete'),
  (5, 3, 'ITM-8782', 50, '0.00', 0, NULL, NULL, NULL, 50, 'Complete'),
  (6, 4, 'ITM-9411', 123, '0.00', 0, NULL, NULL, NULL, 123, 'Complete'),
  (7, 4, 'ITM-8782', 5, '0.00', 0, NULL, NULL, NULL, 5, 'Complete'),
  (8, 5, 'ITM-3', 100, '14.00', 0, NULL, NULL, NULL, 100, 'Complete'),
  (9, 6, 'ITM-5616', 15, '0.00', 1, 'Solar Panel', 'Materials', 'Units', 13, 'Sold Out'),
  (10, 7, 'ITM-2', 20, '12.00', 0, NULL, NULL, NULL, 0, 'Pending'),
  (11, 7, 'ITM-4764', 12, '0.00', 1, 'Hydraulic oil', 'Heavy Machinery', 'Liters', 0, 'Pending'),
  (12, 8, 'ITM-6726', 10, '0.00', 1, 'Brake Pad', 'Heavy Machinery', 'Pieces', 0, 'Pending'),
  (13, 9, 'ITM-5056', 15, '0.00', 1, 'Engine Oil', 'Heavy Machinery', 'Liters', 0, 'Pending');

-- ----------------------------------------------------------
-- Table structure for table `projects`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_code` varchar(50) DEFAULT NULL,
  `project_name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_name` (`project_name`),
  UNIQUE KEY `project_code` (`project_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `projects` (2 records)
INSERT INTO `projects` (`id`, `project_code`, `project_name`, `address`, `description`, `status`, `created_at`) VALUES
  (1, NULL, 'Main Headquarters Construction', NULL, 'General construction of the main building', 'active', '2026-07-24 16:29:27'),
  (2, 'PRJ-2026-002', 'Balay Ni Maam', 'San Jose, Pagadian City', '', 'active', '2026-07-29 09:25:53');

-- ----------------------------------------------------------
-- Table structure for table `purchase_orders`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_no` varchar(50) NOT NULL,
  `rs_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `prepared_by` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending Delivery',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delay_remarks` text DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `proof_of_receipt` varchar(255) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `prepared_signature` varchar(255) DEFAULT NULL,
  `approved_signature` varchar(255) DEFAULT NULL,
  `crypto_signature` text DEFAULT NULL,
  `document_hash` varchar(64) DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT 'Credit (30 Days Net)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_no` (`po_no`),
  KEY `supplier_id` (`supplier_id`),
  KEY `prepared_by` (`prepared_by`),
  KEY `idx_po_rs_id` (`rs_id`),
  KEY `idx_po_status` (`status`),
  KEY `idx_po_created_at` (`created_at`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`rs_id`) REFERENCES `requisitions` (`id`),
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `purchase_orders` (9 records)
INSERT INTO `purchase_orders` (`id`, `po_no`, `rs_id`, `supplier_id`, `prepared_by`, `status`, `created_at`, `delay_remarks`, `expected_delivery_date`, `proof_of_receipt`, `received_by`, `approved_by`, `prepared_signature`, `approved_signature`, `crypto_signature`, `document_hash`, `signed_at`, `payment_terms`) VALUES
  (1, 'PO-20260724-574', 14, 2, 1, 'Viber Order Sent', '2026-07-24 16:35:44', 'Road / Traffic Conditions - Dakop', '2026-07-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Credit (30 Days Net)'),
  (2, 'PO-20260729-744', 15, 1, 1, 'Delivered', '2026-07-29 08:39:26', 'Port / Customs Hold', '2026-08-04', 'uploads/receipts/camera_receipt_2_1785826035.jpg', 1, NULL, NULL, NULL, NULL, NULL, NULL, 'Credit (30 Days Net)'),
  (3, 'PO-20260730-977', 16, 3, 1, 'Delivered', '2026-07-30 15:25:21', NULL, '2026-08-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Credit (30 Days Net)'),
  (4, 'PO-20260730-903', 18, 3, 3, 'Delivered', '2026-07-30 16:20:59', NULL, '2026-08-02', NULL, NULL, NULL, 'uploads/signatures/user_sig_3_1786580598.png', NULL, 'jlRT07qGffNJMz4Uj4WvQtxO+A7AQTybGYtCnVuOy2q5jjtdZ+VEtMp9uvOcRViCIiQlU7uxnj7fe5lGUMLvE5CuKt3xmsF8nK3VBPsxx/rpat3g7ny+0djfrTqfEaD8IhOIg6EhCpJKMz1dlXNmZwn3Fp0fnMTXaG25O/cT0MrZlG4IoK/k9FoPu/bgWh7Z5jfu0RP6L6SrAjQQpi/8yUHH9UcXKBNqJKGY5aL2UYjwBwfUbO6QXOOb2K/pl7VaipCR72Q123tyPYPWdUmD1wtbGPPhNq01cu2dv0tGiv2fx1ed8MpA6BgPIC39I4/PXCSOm0EJE1kChFjUZCoh+Q==', 'b87c1c052832d5f845b871fa38092269616b2ccc6b18b0d0cbeeb2eebffa7e2c', '2026-08-24 16:31:59', 'Credit (30 Days Net)'),
  (5, 'PO-20260804-791', 23, 2, 1, 'Delivered', '2026-08-04 14:47:01', NULL, '2026-08-07', 'uploads/receipts/camera_receipt_5_1785894059.jpg', 3, NULL, NULL, NULL, NULL, NULL, NULL, 'Credit (30 Days Net)'),
  (6, 'PO-20260808-590', 25, 2, 1, 'Delivered (Discrepancy)', '2026-08-08 15:34:55', '[DELIVERY BATCH — Sep 07, 2026 5:29 PM by Angelo Carlo Pedrosa]:\n- Solar Panel [Code: ITM-5616]: Received 10 units today (Total: 10/15) ⏳ [5 Remainder To Follow from Supplier]\n\n[DELIVERY BATCH — Sep 07, 2026 9:11 PM by Angelo Carlo Pedrosa]:\n- Solar Panel [Code: ITM-5616]: Received 3 units today (Total: 13/15) ⚠️ [SOLD OUT - 2 Remainder Cancelled by Supplier]', '2026-08-11', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'Credit (30 Days Net)'),
  (7, 'PO-20260812-324', 27, 3, 1, 'Delivered (Discrepancy)', '2026-08-12 17:19:03', '\n\n[DELIVERY DISCREPANCY]:\n- Steel Rebar (12mm) [Code: ITM-2]: Expected 20, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]\n\n[DELIVERY DISCREPANCY]:\n- Steel Rebar (12mm) [Code: ITM-2]: Expected 20, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', '2026-08-13', NULL, 1, NULL, NULL, NULL, 'b2FLxhbZ6cl7GcjzB06Lt6aaRIYt37cB80f33JUQzVYCByPqo1Pxt9Eh/P9OcK3qxk49rNEHd7Ewe0Sic/P9wo11pWCrT/BX/dR/rD8YVTdOoZsewyjBapBguJmiWBJvgcrGay0cXyyayjHvnZPLojwd2bJDi/0uG8eGbLAv4jkito7tXTwFEOfJU6TTkE6cwlc5x+I74O0K3pdjF9hLV9YkZOyBlBbssgEFY7iKjh6QawgpVOEHL7PjjcbzgjNs+cFYWGslUsyAVpxoDWBTLdUw+k5JivMeq3tn0z6gziOZ1KznES41RN5bhpGm8m2167EHv6Ai6bTxRzYwav7adw==', '2e1239d3cfe3b365bdb3f17f849448e58d0d02083b2c871fd60b3fd64f4fa1d4', '2026-08-14 21:07:49', 'Credit (30 Days Net)'),
  (8, 'PO-20260813-165', 28, 3, 3, 'Viber Order Sent', '2026-08-13 07:37:51', NULL, '2026-08-15', NULL, NULL, NULL, 'uploads/signatures/user_sig_3_1786580598.png', 'uploads/signatures/user_sig_6_1786714335.png', 'NrFowj6QNKktLAte1utV/PLTyhOaJEzLbP6VuKsZsq9dkLbHYyvB1WAQ2JuUzYw04aCjPyuz9OBTqzGcBrO1LKhrhhGhLyMwalWKjScuPnKh0gNdwC56j8uexKUl180n0w7eSEiUGpax5hXTNb8Cw99KzqDEuizUuXQes7o1jSMKzBfUE+qxr3DMhYSlZEOx8Qa2IaQPk3LJidBedD04cljn1isjjOB+DCy17UhvbhoP+LtYp9jYBXMZklUPhWYjoKkjYE9zpZ2dHa7wd3aOIBFAp6fFDY982rJe2jjx8B/7Yco63uqnd052n9fdNYbO2JIx21IyPiFc8q16j/ZOaw==', 'b5dd53093c2630ca810547e123c1f6d99173b3dcb930b808ee9f10300b46afbb', '2026-08-14 21:02:04', 'Credit (30 Days Net)'),
  (9, 'PO-20260901-391', 26, 3, 3, 'Delivered (Discrepancy)', '2026-09-01 08:48:00', '[DELIVERY DISCREPANCY]:\n- Engine Oil [Code: ITM-5056]: Expected 15, Received 0 ⚠️ [UNSUPPLIED / SOLD OUT]', '2026-09-04', NULL, 1, NULL, 'uploads/signatures/user_sig_3_1786580598.png', NULL, 'RlVIf1M+02OqkXctiOqIRbzH+eVuby7LZ8nbsNizpgxnISHNft//CYB4MUJWquPxzoU6At4an4TB+WwygZCkgUJ1A57hO1fqQjaFKDn+pk1tSF7ZgdNEXROGv/tLjuU16iJUsVV53dyV0rJziDohTWIee0ipyz80qV2FGIVnYKfzqSrsT2SGcmXxN+St6AMVF7keif8RsF/oKW1RxrGv43C/GqMx28LC0TcHZ4gO5Zi+z6Od6nrfQhM3QefA81j6wVpB8HfR9/YvgctQTbPwkrcQ0DKJg7QctLiuvjMGSyBjXvVZV6gcw1Cz5lEyd0NLLpphxVpQ3YotrkMzNMQaWg==', 'b58e9320a5cbbfb36f596d6a67ad1e468d7eb1302aff65dba1578802545bb0d2', '2026-09-01 08:48:00', 'Credit (30 Days Net)');

-- ----------------------------------------------------------
-- Table structure for table `requisition_items`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `requisition_items`;
CREATE TABLE `requisition_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requisition_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `is_new_item` tinyint(1) DEFAULT 0,
  `new_item_name` varchar(255) DEFAULT NULL,
  `new_category` varchar(100) DEFAULT NULL,
  `new_unit` varchar(50) DEFAULT NULL,
  `item_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `item_remarks` text DEFAULT NULL,
  `item_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_req_items_req_id` (`requisition_id`),
  KEY `idx_req_items_item_code` (`item_code`),
  KEY `idx_req_items_req_status` (`requisition_id`,`item_status`),
  CONSTRAINT `requisition_items_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `requisition_items` (34 records)
INSERT INTO `requisition_items` (`id`, `requisition_id`, `item_code`, `quantity`, `is_new_item`, `new_item_name`, `new_category`, `new_unit`, `item_status`, `item_remarks`, `item_notes`) VALUES
  (14, 14, 'ITM-8782', 50, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (15, 15, 'ITM-9411', 100, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (16, 15, 'ITM-2', 50, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (17, 16, 'ITM-9411', 100, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (18, 16, 'ITM-8782', 50, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (19, 17, 'ITM-3', 12, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (20, 17, 'ITM-2', 3, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (21, 18, 'ITM-9411', 123, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (22, 18, 'ITM-8782', 5, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (23, 19, 'ITM-3', 50, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (24, 20, 'ITM-9411', 15, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (25, 21, 'ITM-9411', 15, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (26, 22, 'ITM-9411', 20, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (27, 23, 'ITM-3', 100, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (28, 25, 'ITM-5616', 15, 1, 'Solar Panel', 'Materials', 'Units', 'Pending', NULL, NULL),
  (29, 26, 'ITM-4', 5, 0, NULL, NULL, NULL, 'Rejected', 'Sobra na kaayo', NULL),
  (30, 26, 'ITM-5056', 15, 1, 'Engine Oil', 'Heavy Machinery', 'Liters', 'Approved', NULL, NULL),
  (31, 27, 'ITM-2', 20, 0, NULL, NULL, NULL, 'Approved', NULL, NULL),
  (32, 27, 'ITM-4764', 12, 1, 'Hydraulic oil', 'Heavy Machinery', 'Liters', 'Approved', NULL, NULL),
  (33, 28, 'ITM-6726', 10, 1, 'Brake Pad', 'Heavy Machinery', 'Pieces', 'Approved', NULL, NULL),
  (34, 29, 'ITM-2', 12, 0, NULL, NULL, NULL, 'Approved', NULL, 'Medium 3 meter'),
  (35, 29, 'ITM-8782', 11, 0, NULL, NULL, NULL, 'Approved', NULL, NULL),
  (36, 30, 'ITM-9411', 4, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (37, 31, 'ITM-9411', 4, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (38, 32, 'ITM-9411', 4, 0, NULL, NULL, NULL, 'Approved', NULL, NULL),
  (39, 33, 'ITM-4', 20, 0, NULL, NULL, NULL, 'Approved', 'Change to 10 pcs only', NULL),
  (40, 33, 'ITM-8782', 10, 0, NULL, NULL, NULL, 'Approved', NULL, NULL),
  (41, 34, 'ITM-4764', 1, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (42, 35, 'ITM-9411', 11, 0, NULL, NULL, NULL, 'Approved', NULL, NULL),
  (43, 36, 'ITM-5647', 5, 1, 'Sand Paper (Test)', 'Materials', 'Meters', 'Pending', NULL, NULL),
  (44, 36, 'ITM-9623', 1, 1, 'Driller (Test)', 'Heavy Machinery', 'Units', 'Pending', NULL, NULL),
  (47, 38, 'ITM-8782', 1, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (48, 38, 'ITM-9411', 20, 0, NULL, NULL, NULL, 'Pending', NULL, NULL),
  (49, 38, 'ITM-2', 12, 0, NULL, NULL, NULL, 'Pending', NULL, NULL);

-- ----------------------------------------------------------
-- Table structure for table `requisitions`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `requisitions`;
CREATE TABLE `requisitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rs_no` varchar(50) NOT NULL,
  `requestor_id` int(11) NOT NULL,
  `requestor_name` varchar(100) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `urgency` varchar(50) DEFAULT 'Normal',
  `remarks` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending Approval',
  `approved_by` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'project',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rs_no` (`rs_no`),
  UNIQUE KEY `idx_req_rs_no` (`rs_no`),
  KEY `idx_req_status` (`status`),
  KEY `idx_req_requestor_id` (`requestor_id`),
  KEY `idx_req_created_at` (`created_at`),
  KEY `idx_req_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `requisitions` (36 records)
INSERT INTO `requisitions` (`id`, `rs_no`, `requestor_id`, `requestor_name`, `project_name`, `urgency`, `remarks`, `status`, `approved_by`, `type`, `created_at`) VALUES
  (1, 'RS-2026-9297', 2, 'Jen', 'Phase 1', 'Normal', '', 'PO Created', NULL, 'project', '2026-02-24 12:36:46'),
  (2, 'RS-2026-8415', 2, 'Jen', 'basta', 'Urgent', 'Accept pls.', 'Rejected', NULL, 'project', '2026-02-24 13:21:20'),
  (3, 'RS-2026-8473', 2, 'LJ Caballero', 'City Hall', 'Normal', '', 'Approved', NULL, 'project', '2026-02-26 13:25:21'),
  (4, 'RS-2026-6380', 5, 'jahz', 'City Hall', 'High', '', 'PO Created', NULL, 'project', '2026-02-26 14:22:35'),
  (5, 'RS-2026-5584', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'Urgent', '', 'PO Created', NULL, 'project', '2026-03-02 06:23:12'),
  (6, 'RS-2026-6602', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'High', '', 'Rejected', NULL, 'project', '2026-03-03 20:08:53'),
  (7, 'RS-2026-6474', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'High', '', 'Approved', NULL, 'project', '2026-03-03 20:11:01'),
  (8, 'RS-2026-8532', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'Normal', '', 'Approved', NULL, 'project', '2026-03-08 09:56:13'),
  (9, 'RS-2026-1810', 12, 'Coco Martin', 'SCC Buenavista Campus', 'Normal', '', 'Approved', NULL, 'project', '2026-03-08 09:58:23'),
  (10, 'RS-2026-4721', 12, 'Coco Martin', 'SCC Buenavista Campus', 'Normal', '\n\n[MANAGEMENT REJECTED]: Insufficient stock from electrical tape', 'Rejected', NULL, 'project', '2026-03-08 10:10:14'),
  (11, 'RS-2026-2875', 1, 'System Admin', 'SCC Main', 'Normal', '', 'Approved', NULL, 'project', '2026-03-19 10:54:57'),
  (12, 'RS-2026-4947', 1, 'System Admin', 'SCC Main', 'High', '', 'Staged (Ready for Pickup)', NULL, 'project', '2026-03-19 11:03:18'),
  (13, 'RS-2026-8546', 2, 'LJ Caballero', 'General Restocking', 'Normal', '', 'PO Created', NULL, 'project', '2026-03-19 11:09:29'),
  (14, 'RS-2026-1949', 1, 'System Admin', 'Warehouse Restock', 'Normal', '', 'PO Created', NULL, 'restock', '2026-07-24 16:34:54'),
  (15, 'RS-2026-8870', 2, 'LJ Caballero', 'Warehouse Restock', 'Normal', '', 'PO Created', NULL, 'restock', '2026-07-29 08:38:36'),
  (16, 'RS-2026-6640', 1, 'Angelo Carlo Pedrosa', 'Warehouse Restock', 'Normal', '', 'PO Created', NULL, 'restock', '2026-07-30 15:25:05'),
  (17, 'RS-2026-8118', 4, 'Jahzeel Jakosalem', 'Main Headquarters Construction', 'Normal', '', 'Released', NULL, 'project', '2026-07-30 15:58:33'),
  (18, 'RS-2026-6396', 2, 'LJ Caballero', 'Warehouse Restock', 'Normal', '', 'PO Created', NULL, 'restock', '2026-07-30 16:18:21'),
  (19, 'RS-2026-1960', 4, 'Jahzeel Jakosalem', 'Main Headquarters Construction', 'Normal', '', 'Staged (Ready for Pickup)', NULL, 'project', '2026-07-30 16:40:54'),
  (20, 'RS-2026-3327', 4, 'Jahzeel Jakosalem', 'Main Headquarters Construction', 'Normal', '', 'Released', NULL, 'project', '2026-08-03 10:15:00'),
  (21, 'RS-2026-9286', 4, 'Jahzeel Jakosalem', 'Main Headquarters Construction', 'Normal', '\n\n[MANAGEMENT REJECTED]: Already approved', 'Rejected', NULL, 'project', '2026-08-03 10:46:54'),
  (22, 'RS-2026-1686', 1, 'Angelo Carlo Pedrosa', 'Balay Ni Maam', 'Normal', '', 'Released', NULL, 'project', '2026-08-03 11:57:34'),
  (23, 'RS-2026-4182', 1, 'Angelo Carlo Pedrosa', 'Warehouse Restock', 'Normal', '', 'PO Created', NULL, 'restock', '2026-08-04 14:45:27'),
  (25, 'RS-2026-3291', 1, 'Angelo Carlo Pedrosa', 'Warehouse Restock', 'Normal', '', 'PO Created', NULL, 'restock', '2026-08-08 15:34:36'),
  (26, 'RS-2026-6201', 2, 'LJ Caballero', 'Warehouse Restock', 'Normal', '', 'PO Created', NULL, 'restock', '2026-08-11 09:11:18'),
  (27, 'RS-2026-1739', 2, 'LJ Caballero', 'Warehouse Restock', 'Normal', '', 'PO Created', NULL, 'restock', '2026-08-11 09:45:29'),
  (28, 'RS-2026-4145', 2, 'LJ Caballero', 'Warehouse Restock', 'Normal', '', 'PO Created', 6, 'restock', '2026-08-13 07:35:29'),
  (29, 'RS-2026-7324', 4, 'Jahzeel Jakosalem', 'Main Headquarters Construction', 'Normal', '', 'Approved', 6, 'project', '2026-08-17 15:41:02'),
  (30, 'RS-2026-8194', 5, 'Angeleen Dy', 'Main Headquarters Construction', 'Normal', 'PROJ: ID-123\r\nBY: JUAN DELA CRUZ\r\nFOR: BUHOS SA SALOG', 'Pending Approval', NULL, 'project', '2026-08-24 20:32:26'),
  (31, 'RS-2026-6976', 5, 'Angeleen Dy', 'Main Headquarters Construction', 'Normal', 'PROJ: ID-123\r\nBY: JUAN DELA CRUZ\r\nFOR: BUHOS SA SALOG', 'Pending Approval', NULL, 'project', '2026-08-24 20:32:48'),
  (32, 'RS-2026-1231', 5, 'Angeleen Dy', 'Main Headquarters Construction', 'Normal', 'PROJ: ID-123\r\nBY: JUAN DELA CRUZ\r\nFOR: BUHOS SA SALOG', 'Released', 6, 'project', '2026-08-24 20:33:30'),
  (33, 'RS-2026-7245', 5, 'Angeleen Dy', 'Main Headquarters Construction', 'Normal', 'PROJ\r\nBY\r\nFOR', 'Staged (Ready for Pickup)', 6, 'project', '2026-08-24 20:46:31'),
  (34, 'RS-2026-6518', 4, 'Jahzeel Jakosalem', 'Main Headquarters Construction', 'Normal', '', 'Pending Approval', NULL, 'project', '2026-08-26 15:02:54'),
  (35, 'RS-2026-2605', 1, 'Angelo Carlo Pedrosa', 'Main Headquarters Construction', 'Normal', '', 'Approved', 1, 'project', '2026-09-05 20:57:04'),
  (36, 'RS-2026-7415', 1, 'Angelo Carlo Pedrosa', 'Warehouse Restock', 'Normal', 'Restock for upcoming maintenance batch', 'Pending Approval', NULL, 'restock', '2026-09-08 08:36:32'),
  (38, 'RS-2026-1812', 1, 'Angelo Carlo Pedrosa', 'Main Headquarters Construction', 'Normal', '', 'Pending Approval', NULL, 'project', '2026-09-08 08:38:32');

-- ----------------------------------------------------------
-- Table structure for table `supplier_viber_logs`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `supplier_viber_logs`;
CREATE TABLE `supplier_viber_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) DEFAULT NULL,
  `po_id` int(11) DEFAULT NULL,
  `direction` enum('inbound','outbound') NOT NULL DEFAULT 'inbound',
  `sender_number` varchar(50) NOT NULL,
  `receiver_number` varchar(50) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `po_id` (`po_id`),
  KEY `idx_viber_supplier_id` (`supplier_id`),
  KEY `idx_viber_po_id` (`po_id`),
  CONSTRAINT `supplier_sms_replies_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_sms_replies_ibfk_2` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `supplier_viber_logs` (17 records)
INSERT INTO `supplier_viber_logs` (`id`, `supplier_id`, `po_id`, `direction`, `sender_number`, `receiver_number`, `message_text`, `is_read`, `created_at`) VALUES
  (1, NULL, NULL, 'inbound', '+639098702199', '+639098702199', 'Hello CIMS, we have 40 bags of Holcim cement available for immediate delivery.', 1, '2026-07-24 16:51:22'),
  (2, NULL, NULL, 'outbound', '+639098702199', '+639098702199', 'Test', 1, '2026-07-24 17:08:37'),
  (3, NULL, NULL, 'inbound', '+639098702199', '+639098702199', 'Hello CIMS, this is an automated supplier test response!', 1, '2026-07-24 17:11:17'),
  (4, 2, 1, 'inbound', '+639760167906', '+639098702199', 'Test', 1, '2026-07-24 17:20:09'),
  (5, 2, 1, 'inbound', '+639760167906', '+639098702199', 'Test', 1, '2026-07-24 17:25:38'),
  (6, 2, NULL, 'outbound', '+639098702199', '+639760167906', 'How about the cornerstone ma\'am?', 1, '2026-07-24 17:33:39'),
  (7, 2, NULL, 'outbound', '+639098702199', '+639760167906', 'Test rani sya ha!!', 1, '2026-07-24 17:36:44'),
  (8, 3, 3, 'inbound', '+639516129602', '+639760167906', 'Ok', 1, '2026-07-30 15:39:55'),
  (9, 3, 4, 'inbound', '+639516129602', '+639760167906', 'Its not available', 1, '2026-07-30 16:22:14'),
  (10, 3, NULL, 'outbound', '+639760167906', '+639516129602', 'Pwede ra ma\'am sent nana karon', 1, '2026-07-30 16:22:46'),
  (11, 2, 1, 'outbound', 'VIBER', '639760167906', '[Viber Dispatched]\nGenetian Builders Construction PO: PO-20260724-574\r\nItems to purchase:\r\n- 50x Electrical Tape\r\nIf you have any concerns or clarifications text or email here', 1, '2026-08-08 15:33:55'),
  (12, 2, 6, 'outbound', 'VIBER', '639760167906', '[Viber Dispatched]\nGenetian Builders Construction PO: PO-20260808-590\r\nItems to purchase:\r\nIf you have any concerns or clarifications text or email here', 1, '2026-08-08 15:35:03'),
  (13, 2, 6, 'outbound', 'VIBER', '639098702199', '[Viber Dispatched]\nGenetian Builders Construction PO: PO-20260808-590\r\nItems to purchase:\r\nIf you have any concerns or clarifications text or email here', 1, '2026-08-08 15:35:52'),
  (14, 2, 6, 'outbound', 'VIBER', '639171066932', '[Viber Dispatched]\nGenetian Builders Construction PO: PO-20260808-590\r\nItems to purchase:\r\nIf you have any concerns or clarifications text or email here', 1, '2026-08-08 15:37:07'),
  (15, 2, 6, 'outbound', 'VIBER', '639171066932', '[Viber Dispatched]\nGenetian Builders Construction PO: PO-20260808-590\r\nItems to purchase:\r\nIf you have any concerns or clarifications text or email here', 1, '2026-08-08 15:37:34'),
  (16, 2, 6, 'outbound', 'VIBER', '639171066932', '[Viber Dispatched]\nGenetian Builders Construction PO: PO-20260808-590\r\nItems to purchase:\r\nIf you have any concerns or clarifications text or email here', 1, '2026-08-11 08:17:13'),
  (17, 3, 8, 'outbound', 'VIBER', '639516129602', '[Viber Dispatched]\nGenetian Builders Construction PO: PO-20260813-165\r\nItems to purchase:\r\n- 10x Brake Pad\r\nIf you have any concerns or clarifications text or email here', 1, '2026-08-13 07:40:58');

-- ----------------------------------------------------------
-- Table structure for table `suppliers`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_code` varchar(50) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_code` (`supplier_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `suppliers` (3 records)
INSERT INTO `suppliers` (`id`, `supplier_code`, `company_name`, `contact_person`, `contact_number`, `email`, `address`, `status`, `created_at`) VALUES
  (1, 'SUP-1055', 'Holcim Philippines', 'Jane', '09123456578', 'sales@holcim.com', 'F.S Pajares Ave. Pagadian City', 'Active', '2026-02-28 10:07:15'),
  (2, 'SUP-6986', 'City Hardware', 'Angelo Carlo Pedrosa', '639171066932', 'carlopedrosa14@gmail.com', '266 Consulation Santo Niño\r\nPurok Roxas', 'Active', '2026-03-13 16:15:22'),
  (3, 'SUP-5376', 'SCC Enterprises', 'Jahzeel Jakosalem', '639516129602', '', 'Saint Columban College', 'Active', '2026-07-30 15:24:22');

-- ----------------------------------------------------------
-- Table structure for table `system_settings`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `system_settings` (4 records)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('last_ai_prediction', '<!DOCTYPE html>\n<html>\n<head>\n	<title>Inventory Restock Recommendations</title>\n</head>\n<body>\n	<h1>Inventory Restock Recommendations</h1>\n	<ul>\n		<li style=\"margin-bottom: 15px; padding: 12px; border-left: 4px solid #dc3545;\">\n			<span style=\"font-size: 1.1rem;\">⚠️ <strong>Concrete Nails</strong></span> - Runs out in 227 days.\n			<br>🏢 <strong>Top Consumer:</strong> Balay Ni Maam (20) and Main Headquarters Construction (19)\n			<br>👉 <strong>Recommended Restock Date:</strong> IMMEDIATELY (Accounts for 5-day Lead Time)\n			<br>👉 <strong>Recommended Order Qty:</strong> 50 Pieces\n		</li>\n		<li style=\"margin-bottom: 15px; padding: 12px; border-left: 4px solid #dc3545;\">\n			<span style=\"font-size: 1.1rem;\">⚠️ <strong>Makita Power Drill</strong></span> - Runs out in 370 days.\n			<br>🏢 <strong>Top Consumer:</strong> Main Headquarters Construction (12)\n			<br>👉 <strong>Recommended Restock Date:</strong> IMMEDIATELY (Accounts for 5-day Lead Time)\n			<br>👉 <strong>Recommended Order Qty:</strong> 50 Units\n		</li>\n		<li style=\"margin-bottom: 15px; padding: 12px; border-left: 4px solid #dc3545;\">\n			<span style=\"font-size: 1.1rem;\">⚠️ <strong>Steel Rebar (12mm)</strong></span> - Runs out in 5470 days.\n			<br>🏢 <strong>Top Consumer:</strong> Main Headquarters Construction (3)\n			<br>👉 <strong>Recommended Restock Date:</strong> IMMEDIATELY (Accounts for 5-day Lead Time)\n			<br>👉 <strong>Recommended Order Qty:</strong> 50 Pieces\n		</li>\n	</ul>\n</body>\n</html>\n\nNote: The items that are not critical (i.e., have more than 20 days of stock left) are not included in the output. The recommended restock date and order quantity are calculated based on the given rules.', '2026-08-26 15:30:34'),
  ('last_ai_timestamp', '1787729434000', '2026-08-26 15:30:34'),
  ('login_background', 'assets/img/default_login_bg.png', '2026-07-24 16:29:27'),
  ('login_blur', '0', '2026-08-17 14:01:39');

-- ----------------------------------------------------------
-- Table structure for table `units`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `units`;
CREATE TABLE `units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_name` varchar(50) NOT NULL,
  `abbreviation` varchar(20) NOT NULL,
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `units` (6 records)
INSERT INTO `units` (`id`, `unit_name`, `abbreviation`, `reorder_level`, `created_at`) VALUES
  (1, 'Pieces', 'pcs', 10, '2026-07-24 16:32:28'),
  (2, 'Bags', 'bags', 10, '2026-07-24 16:32:28'),
  (3, 'Units', 'units', 5, '2026-07-24 16:32:28'),
  (4, 'Kilograms', 'kg', 20, '2026-07-24 16:32:28'),
  (5, 'Liters', 'L', 5, '2026-07-24 16:32:28'),
  (6, 'Meters', 'm', 15, '2026-07-24 16:32:28');

-- ----------------------------------------------------------
-- Table structure for table `users`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'requestor',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fcm_token` text DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `public_key` text DEFAULT NULL,
  `private_key` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `idx_users_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users` (7 records)
INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `status`, `created_at`, `fcm_token`, `signature_path`, `public_key`, `private_key`) VALUES
  (1, 'Angelo Carlo Pedrosa', 'admin', '$2y$12$iJVBLTEUBCbcsVJppnWoweZkOIddOITYIiLGZL7VX9QmJD0PVmZvS', 'admin', 'active', '2026-07-24 16:29:27', 'eoGCbgDtN3qRF3Cn0jbhPc:APA91bGJv2DpqAW6HP_57WzPC5Q0Ls6GnxLFyge8CNkER8IrpIlpKJdhpnZO6n1gMZ74RVnh6U6QqyyKxbzv_fTl17gHjRP-ccof_wtMqkeOtgKWrNKJIzg', NULL, '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAtxEZ1bqppf4kPrMqEzai\ncG+OO/bt9XHFZYUcNZuQYV3/amBXtKZ2XKvqsS3bFFBn18M5gFeO4kbUhEgk8NOS\nWUFYioGSvngfNL7wWdJaamI6gISnF6I4j2Prtpl4YyYRDIid+2CFQ2Mxgs5MWoRx\nyvGaPUcjwl/4CmV88jGAEG3+/hf/9lKQUK+fxRERGSilnQRKeN044SYE8PcWOC2G\nptj5mPZiKsA8WEs7K7sx5NE57twd2W7RXmnea4Spho0FGm3vYbXUwFisQT/C+sbf\n5qyGuhV/Ui0XyuLyQ8PYFF+83256OYvmWZ+vehH9Yn1xeupZ9yEpEVKteKWqi3zX\nSwIDAQAB\n-----END PUBLIC KEY-----\n', '-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC3ERnVuqml/iQ+\nsyoTNqJwb4479u31ccVlhRw1m5BhXf9qYFe0pnZcq+qxLdsUUGfXwzmAV47iRtSE\nSCTw05JZQViKgZK+eB80vvBZ0lpqYjqAhKcXojiPY+u2mXhjJhEMiJ37YIVDYzGC\nzkxahHHK8Zo9RyPCX/gKZXzyMYAQbf7+F//2UpBQr5/FEREZKKWdBEp43TjhJgTw\n9xY4LYam2PmY9mIqwDxYSzsruzHk0Tnu3B3ZbtFead5rhKmGjQUabe9htdTAWKxB\nP8L6xt/mrIa6FX9SLRfK4vJDw9gUX7zfbno5i+ZZn696Ef1ifXF66ln3ISkRUq14\npaqLfNdLAgMBAAECggEAPrJHo9A6+9OPPD4GIfBrL5C1KMpH8vCVB1DQOXfeucoE\niL0YLJF6JgDm7ulih5GGDvoYfmD+WXain+9JX4VGMPVVSpJX3I2tOlZOYKTPPHIJ\n0SwdQdXYAxvYhYAIIATQf/dmC8qhuVOGiGL4+WM0yal3BpePoqlVfNIHObdEQdul\nJ6rJhCae6gfKUdKHYZkQBksa/x09E5YJtb8Kq4OllVMXMHT0mP1Atfep11J+eGxZ\nA+4YaOI8Hb3eYP8aGrWuWh4JB/QjoXxTdCqaTOAVuEZvsnexw01y4c/ufgNITR0h\nrir44Ye/h3smRTGLiZ/By/gVKd4TOgtA44LmbiI52QKBgQDudtvNt80nmQQKjMf0\ncozGOO++r+2lAjsxMHm0VuMesmnmIjTuBcBbeGoG8ZpWhFa/Wd95vYjVxwVGCKsF\nzNJc3QwFBKtp8wilwFiI7+hbr8dRv+SbgbK9kYXpbBJfgj0wqCQz6Y5PEnmmDxXL\nolpj9+K9r2ka+0hA7/jNmVqTDwKBgQDEh19nR/9nOjBFtycHjITiFCAHOdChw84D\nWaXVXg20PHbfI8/kg4IuGYfyDTWD0/Z55PwipMJIdz2WJvQcZkbAjxaUVKfU2D1d\nzufn3uJ0voeW8QzysuOluf+hUGP3SH698+AQiluJ5X87XAANccPp05+cttGaRY0c\nWtOwR+iIBQKBgFZ27H4sHgw0lF2K7Fm7S0X4kR2QRtfk9jeAvzBfrNyNjo5uasi/\ndx7zi2ZXJkImnBmn6bsHuVziXAwnynNA8CnR0LDlH448HC+VjcShUJwmIVyH+slT\n/s5zvJ2FnSfaXnuNDAtyrTIInelTYPDEPogu8p0axD6PTISXPpy19TyLAoGBAIMh\n1L2kY72sLOOHpuo5j70OMqS/pf8aGI9RtP5eqIQ3yBVx3tiaCzXZYUVYHPoeZ5rD\n+JzhFKWnspdK3J1KfTElCKrmpam2s5OxaDnmFXJSY0SLCNm0FYPSTMiFTH6Gh9MV\nM8+1kgi78h5Yb8yIuXF+/ERkFA19FP/zdsZr5LNFAoGBAKX5gt8W7rxWGzw13Orx\nPyzSvCqT9hou31IugPGLAN+zhubZ5KDs7RU/Tl8dIDtITtMALPAKujyzVVSz9jjQ\nhQmGBLudSur4d4A9NGIEdne/WOxucpxhp4seIfjqD1QB+MfZU30aLkVMxlUmS4un\nW/X93I9tTjrsCt06ICHIqByy\n-----END PRIVATE KEY-----\n'),
  (2, 'LJ Caballero', 'ljwarehouse', '$2y$12$.ofQnT2HQK9i0kkGTisw.unIvZ./0a3XXRPYNi4GE7SPU3hqK1EDK', 'warehouse', 'active', '2026-07-29 08:26:11', 'fTr1aZLyPvMSOLHrt6rPXS:APA91bEEA35ia_aYITLkvMIvaL96IP0cy_oqe-z-l7IRpg7qM-ZCFyY6DV82ySXn-MNjRvaoxce3xKi6bvw8UQV3l7s-dqZiVyWWNBJtPwx0jVAVqnmGt5c', 'uploads/signatures/user_sig_2_1786581753.png', '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA2HkouoYgMS3n02KjUDhT\nXGOPosz841TjlDR6kWqYYfAPwVX9cDa4XNWqAzJmjHzmYFEGMoeJ9UoH2EkWElW3\nHn+GQXhxL1oMvGXpVnB9tq4h6fUoJAPjBGC2auOWRzdfHGrtQxHXK4v5BYJG2pAO\nBbqY7fSQhd2Dsz6RLDCWaMikIep2QAMc7TIMUM+WJJ4P2DZqrLMYB71cLSD0GM1J\nVPNgfM6+see6OXDSb2/iCwO1qcg3TglhAa6RTFLipKbfTbMbIPmA3UvBhonwhIJv\nh+YorZOuGVUy9ngepmS8MJ7qHOr5NCAgSLnJpMOsLLeVotwV2SW95oiZXMSTt5+8\nawIDAQAB\n-----END PUBLIC KEY-----\n', '-----BEGIN PRIVATE KEY-----\nMIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQDYeSi6hiAxLefT\nYqNQOFNcY4+izPzjVOOUNHqRaphh8A/BVf1wNrhc1aoDMmaMfOZgUQYyh4n1SgfY\nSRYSVbcef4ZBeHEvWgy8ZelWcH22riHp9SgkA+MEYLZq45ZHN18cau1DEdcri/kF\ngkbakA4Fupjt9JCF3YOzPpEsMJZoyKQh6nZAAxztMgxQz5Ykng/YNmqssxgHvVwt\nIPQYzUlU82B8zr6x57o5cNJvb+ILA7WpyDdOCWEBrpFMUuKkpt9Nsxsg+YDdS8GG\nifCEgm+H5iitk64ZVTL2eB6mZLwwnuoc6vk0ICBIucmkw6wst5Wi3BXZJb3miJlc\nxJO3n7xrAgMBAAECggEAYFyyzjFFpE7Ii1c11tBz/0UMnA5/Jl1T/1XLF+9pDPPV\nt50lN+4L7LtqNokZkEFLNiXrSdP/DBNb4aJLMnc4kFl5NKy+SbNexwDguYtS0t66\nFxD8QOgAByNcIMHV1DboXliU7I3FCEwDWrnu+30z2MYDLK35JbYBWemoqxCn69VO\n+3ycssmtOkdNoF0nrYQ6FVz9Fs4Udo3lUKobmWxP30I90c3robRv6E2y9QAu417K\nG7Sa7zDb8ZVkNLLK/8+o+FcW0f8HiyJWRwEaCWr3XCNCMC5g8a0z4+phTAhzrzLa\npdWT+O6bcTJt24+XUPq7bPu9qd5ifoYoSQPcSOl7jQKBgQD7h6L/HXaMuTS4ci0U\n9rGoQkdQeQ83FYBiAjYHjay7EChlzWcxQYFE0e7WSemD0ZJmbu1p3RX1JWDPwkd9\nn+TNl3gNLccYXfN8DM9UxYPDH7aUv5pYwc8QdPbewQo96xGYJfsO7x5bnIzY8WmL\ntQRF9BnSWgTSnZSmSSZ0EOf8rQKBgQDcUgdV3XbC7qhGoqeThI+LuH+ZBWX6FNAy\nr/z3t+BipIuvEMHx+oB/aQMB1MH7iy5VddNnZKQnoEC8Ulg7XwEKh4iV2nhT5p/O\ngjcrnirNCmWM8NWEScJKtBmDVm1U2hnLCYZ0E+w5/QjjJuYB1me298m9HkuKd0H6\nsR/z6w9odwKBgQCgeUTQpqd/2Jl+I7oHaeymgMKm4NWIOzuRS//Uidrt7b0YVhfE\nIRqsIZPTO4y0APz6RUNLCzZ7FMHTSwv5Zg2/7Sc2oUIolahGOJX+a5VI4+7EsAl1\nHxFQWo82RsqV/mdXPsQSHxSrNhHWRw8jhvWW+8mNnsj85nR0Mq9J1Y9scQKBgQDa\njjTgpNTPAtjDWU9LM1EClX9eWNCUiFkQLyyOwEVDFV/Lxp/eX1VhDtcA1gGoeqT3\n+e5AvsFo3bvaFQWZv+DUhSalIRgEgt88iEgaaMikpg+fBLmKhXDLkmVMuEu0xLaZ\nh1VtiOXpkG1kiI3afMpd4uipRohBT5SQD21XFnMueQKBgQCBgM0MuiFLapuSGvrr\nDbo6vBB7Ur1kpmvRkmqhrKkG3qdnPx6jT6eQmBMlSoF5Pj9PVB5c7EZtiAAjwdZG\nTT6txb4RrKVNh7HGX7vWblPJ6oSQixtgJxgkrh7LrjU0yt7URuo0awmIJdcBwYXf\nsWGjrSuR12hN9jqTX4J3gtvacw==\n-----END PRIVATE KEY-----\n'),
  (3, 'Coco Martin', 'cocomartin', '$2y$12$vBa78toh2HCrsspKt3wBGe7eLRJUkVTdUV653ZXkZOKdmMslFhZEO', 'purchasing', 'active', '2026-07-29 08:26:50', NULL, 'uploads/signatures/user_sig_3_1786580598.png', '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAn5Gr6jCbfRpGE2t/OwZw\nqYqhLCUi1lgXKVZRcydbshr5pFHCdY0zWk6nTi2yLtHSjYtAU6u19NUIfJ3hi11a\nVit5kFRmarF9HEEQHid/CbC1X51XKyXNTx6aI+/RRL4JCyavjRGcMmtT7noyJXQL\nQP7OYK73/EEs6NN3A0n98eh0B5UXp0diQILEv/N6B77QdttXjzjVqymDVaQxz31N\n7CR825XvLYmwyPrvVswZlQrmrcQDU4pKj5gOht4dYIr8zDW47yGbmwnkORdvpMxl\nXJg1u36o/01zsGitww75N+FFJLZ4+N1AG+34i05tpfIEBudnSFOpmDdx7a00zm9P\nZQIDAQAB\n-----END PUBLIC KEY-----\n', '-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCfkavqMJt9GkYT\na387BnCpiqEsJSLWWBcpVlFzJ1uyGvmkUcJ1jTNaTqdOLbIu0dKNi0BTq7X01Qh8\nneGLXVpWK3mQVGZqsX0cQRAeJ38JsLVfnVcrJc1PHpoj79FEvgkLJq+NEZwya1Pu\nejIldAtA/s5grvf8QSzo03cDSf3x6HQHlRenR2JAgsS/83oHvtB221ePONWrKYNV\npDHPfU3sJHzble8tibDI+u9WzBmVCuatxANTikqPmA6G3h1givzMNbjvIZubCeQ5\nF2+kzGVcmDW7fqj/TXOwaK3DDvk34UUktnj43UAb7fiLTm2l8gQG52dIU6mYN3Ht\nrTTOb09lAgMBAAECggEACHN/pmyin5KXqYmk+GxT5TbZaGwjFzUdLOxMbgvkmF9D\nX4eRBAbRdHP7+nEDIeWtACAi7QuIHIp345m9C1OLaErvKky+C+KQnMF5aA9xdALu\n6Dx+FGPxJsKZKVQXQkvKUNPgslj/a9AxZs0EAOXPfsbACXDa43pUNZSVlhACKiCv\nIE38fBT9IDcqb5Vxc/EAPRNBpsD+bLE1ooPAyEJuDQuswZu4xqKAsujWoU0wBh4l\nJ7FIqXOttL0PONK+UzHRPxNwxaLhwxxwc899tqCewseraVerw2UJJ6bJxIrViCor\nwgsq0FL0sal1A1OQpBHtAJ8qEnnvMLNrdhhXWXwBIQKBgQDb4kOvxlQI5/SDN02h\niFR6pzA/n5XWZ0dqkvEtWyGOlQlLc3nh3gXvTtLfqRZ6owfCFq8xEs48VzXNQHgZ\nfEJYMHjyDCBBL99bkNJjvsLX+VJ6BfrY0AfRngAVRc0P92URnyLWCASa3Y8sv/ps\n6LwNyxEJNb/IEcojALiVZckLSQKBgQC5x0X9TJiwp9HXIBLZ8cY4KBAVNNgWOc5O\neUkVtkdeUZhLWOGJaYd+HAG+Pm2c7nWpp38AOJi3O8s1tEthDCyVfyTa3k+AxW0X\nkvWARgpVv27fszjx9YmX/Z05ngiZTXze6QCnxbeaNW9XR2nWIuAuPxH7thb3YhhU\n73pOStOnPQKBgEwhfDQI5CGtRhCEfmF2VWGeL2tn8rYoTibNN6nvip/WZEB2e/XD\nLKTd0s9TuQ+/ELmXpxLDoxprS5qEPtD8H/Bu4AFWR3iqfZgzfVTBwK6MiYzsMx0M\nchiWrwquf0CO9LR0N9iJMCb6nU7uLWO19R6Fd6GLtZos5qLV5hL8Ce7ZAoGAKWDW\nGO3nkGlAlr7BFCQCt11M/7wuaPzlE5t2CMz5pmtcFWQtj9KeaBtK1BnJhkuij3AM\nHHt+oElEIKkQpQP2JjIUfl9Hq/HNM5P69GAlSyYBRvf/Nf0vcVf+neeyGJsmteuF\nxtiF5WYDb5grXZOVBRftJHhRMzZ5Hunb/vYxoC0CgYEAqM+6EWXhzNkh4WGhv2UX\nMfY1UgYnOqubodJU2EtfpoVNcqqGanKaeMpitZuHSIJ5CRtOB6dICSx5atrkXcLz\nAl2Bw4vv8TC50LQgzoFmRZcscq/L/vffRV0jyl/Om1HRyBW7KSd1X75Yg3DSReeW\nhsxr/dmyXtJsrhgakRjHx3Y=\n-----END PRIVATE KEY-----\n'),
  (4, 'Jahzeel Jakosalem', 'jahz', '$2y$12$7shcO/H1Vvm/7ihT1ywl..97x7BCBVnOUNxGZi6leo0N6W1cMp7Fy', 'requestor', 'active', '2026-07-29 09:12:16', 'fz4IDxlLrzsEOqEkwQM3Cf:APA91bHvj8UTa91fxl6BEZoIeX25e1iFv7efesarE_plw6z1knK3OUzKJpD1FQMXCS-G3DjITDnwHBC60C8DIL0aSKgBDcIAWr0C8s7z2nrXcKORbgezB0A', NULL, NULL, NULL),
  (5, 'Angeleen Dy', 'angeleen', '$2y$12$N1KCFs9CtuWLuSd/17dLwup5DpFa7uanrIbtGbnjeQ2Ie.nFhKMQu', 'purchasing', 'active', '2026-07-30 15:59:03', 'eLjIdoL6s7g7XDNdxN6WFr:APA91bERsmj8fRGKIaNP782k_EHpPGmmpNCD65Y4QkFtEtsVnFZkAKXnj506yRCdFe61eHUNvi2UsLnMpomR2KFg6q0WIxnBD7cVZZpmj2B4cqklkEw_dRw', NULL, NULL, NULL),
  (6, 'Taylor Swift', 'taylorswift', '$2y$12$ggtnWG3gXIxfVQFe8T91FeZmQyOLC9qqbpXMC8nLavgs8c3sireCy', 'management', 'active', '2026-08-13 07:33:39', 'dvQG_OKE09tfn8V-cMfsXA:APA91bHzI4BBIBJPLYGWmWuXBbR7c6YC7f06c84Y-1zE5WkTwSCZ5SlKShlRo2Yjg3wWpPLs1CNyZfpqz9BbQ0J4cQj2_5FjiL8lDMKvtuS__jSNGWJ6tKo', 'uploads/signatures/user_sig_6_1786714335.png', NULL, NULL),
  (7, 'Toto Dy', 'geneody', '$2y$12$sPXoRP/O129fY/skvDebEuiG4hTjBq9DjhPUjsyv8.B7foaXCbzWW', 'management', 'active', '2026-08-24 20:04:07', NULL, NULL, NULL, NULL);

-- ----------------------------------------------------------
-- Table structure for table `withdrawal_items`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `withdrawal_items`;
CREATE TABLE `withdrawal_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `withdrawal_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wd_items_withdrawal_id` (`withdrawal_id`),
  KEY `idx_wd_items_item_code` (`item_code`),
  CONSTRAINT `withdrawal_items_ibfk_1` FOREIGN KEY (`withdrawal_id`) REFERENCES `withdrawals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `withdrawal_items` (5 records)
INSERT INTO `withdrawal_items` (`id`, `withdrawal_id`, `item_code`, `quantity`) VALUES
  (2, 2, 'ITM-3', 12),
  (3, 2, 'ITM-2', 3),
  (4, 3, 'ITM-9411', 15),
  (5, 4, 'ITM-9411', 20),
  (6, 5, 'ITM-9411', 4);

-- ----------------------------------------------------------
-- Table structure for table `withdrawals`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `withdrawals`;
CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `withdrawal_no` varchar(50) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `released_by` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `date_withdrawn` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_by` varchar(100) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `photo_proof_path` varchar(255) DEFAULT NULL,
  `crypto_signature` text DEFAULT NULL,
  `document_hash` varchar(64) DEFAULT NULL,
  `signed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `withdrawal_no` (`withdrawal_no`),
  KEY `released_by` (`released_by`),
  KEY `idx_withdrawals_date` (`date_withdrawn`),
  CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `withdrawals` (4 records)
INSERT INTO `withdrawals` (`id`, `withdrawal_no`, `project_name`, `released_by`, `remarks`, `date_withdrawn`, `received_by`, `signature_path`, `photo_proof_path`, `crypto_signature`, `document_hash`, `signed_at`) VALUES
  (2, 'WD-2026-8241', 'Main Headquarters Construction', 2, 'Auto-filled via QR Scanner for RS-2026-8118', '2026-07-30 16:06:48', NULL, NULL, NULL, NULL, NULL, NULL),
  (3, 'WD-2026-7030', 'Main Headquarters Construction', 2, 'Pre-picked & Staged Express Pickup for RS-2026-3327', '2026-08-03 11:15:00', NULL, NULL, NULL, NULL, NULL, NULL),
  (4, 'WD-2026-7210', 'Balay Ni Maam', 2, 'Pre-picked & Staged Express Pickup for RS-2026-1686', '2026-08-03 11:59:34', 'Oscar', 'uploads/signatures/sig_WD-2026-7210_1785729574.png', 'uploads/proofs/proof_WD-2026-7210_1785729574.jpg', 'jFOBg9r1//P7vdPEXLI7KBleV/wUydop208O5vz52Rx2EVQiqxMsj8HU8+Wi3cpKug1SNXjSo0bjR3t5w/EBdXeSSkH0w1ukchbA8U3Wj2ylpG8GF24OPzRaJ9IY99tsj1zVO9d+8+UzSKYOam1uECLutKsdvFKocl7tU9I8IdXiDs/E+SSowRpzWrZjaX0f993irrYcVtskDJOW6c+1JQgPmvusPQetwpc8OULjzvMuE4BB04JUvfS6uuZKS+EwWdvFOYF5NAToSxNEWSpIs9leORnPeYZyE0gxgdNfYkV4iNiI18A0uI0uqRWD5IoA0cUSGxwTfKkHnDbijBN4oQ==', '7a04ed99158046813aca60335e0cc3681692aff7aa6cd104380e6ae75c3d63ce', '2026-08-14 21:05:18'),
  (5, 'WD-2026-4029', 'Main Headquarters Construction', 2, 'Auto-filled via RS Lookup for RS-2026-1231', '2026-08-24 20:44:31', 'Angeleen Dy', 'uploads/signatures/sig_WD-2026-4029_1787575471.png', 'uploads/proofs/proof_WD-2026-4029_1787575471.jpg', 'GYZPRpfyz+m+mn07Xnp1SaMk7zX95sJ1XB5XHEEzTv/qQF/8WuJr4YoeDZ0EtHObvnffhBIm35ZDit9KbU2/SPuem6H4PRR8YhtUrg/JO2NI1JPMJFgcoGUW8r1zHpYrL/1B/MyRgyaHykMaScIyWbjG+PQ8/ptY+F6i81I05l5f1h9gZWQRp8Sr5mbZViICGqtgjMPwE0NpPq88hHSEVjTw/Giny358McsPi/OQyJaV3kn//NtVQso7KYbeLkBugHfSPfaYgtM+ETVuPhg2/QFLPeGIxMKZTtdTTesU4yEhVbAFaPvtnzEFL8wyNJeZze11A4plfxrOtPdOPrO5Xw==', '2460222c5a8fdf56d548aa247856487279d70b77cd9ae1adda08d7fb6ffc3664', '2026-08-24 20:44:31');


-- ==========================================================
-- Re-enable Foreign Key Constraints
-- ==========================================================
SET FOREIGN_KEY_CHECKS = 1;
-- Backup completed successfully at 2026-09-10 11:44:22 PST
