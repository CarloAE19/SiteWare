-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 29, 2026 at 02:47 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.5.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `construction_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_items`
--

CREATE TABLE `audit_items` (
  `id` int(11) NOT NULL,
  `audit_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `system_qty` int(11) NOT NULL,
  `physical_qty` int(11) NOT NULL,
  `discrepancy` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `created_at`) VALUES
(1, 'Materials', '2026-07-24 08:32:28'),
(2, 'Tools', '2026-07-24 08:32:28'),
(3, 'Safety Equipment', '2026-07-24 08:32:28'),
(4, 'Heavy Machinery', '2026-07-24 08:32:28');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(50) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `item_code`, `item_name`, `category`, `quantity`, `unit`, `unit_price`, `status`, `last_updated`) VALUES
(2, 'ITM-2', 'Steel Rebar (12mm)', 'Materials', 500, 'Pieces', 12.00, 'In Stock', '2026-03-09 11:00:28'),
(3, 'ITM-3', 'Makita Power Drill', 'Tools', 60, 'Units', 120.00, 'In Stock', '2026-03-17 05:35:22'),
(4, 'ITM-4', 'Safety Helmets', 'Safety', 110, 'Pieces', 15.00, 'In Stock', '2026-03-17 05:35:58'),
(7, 'ITM-4130', 'Sand', 'Materials', 700, 'Cubic Meters', 0.00, 'In Stock', '2026-03-19 03:18:45'),
(8, 'ITM-9411', 'Concrete Nails', 'Materials', 133, 'Pieces', 0.00, 'In Stock', '2026-03-19 06:00:27'),
(9, 'ITM-8782', 'Electrical Tape', 'Electrical Supplies', 100, 'Pieces', 0.00, 'In Stock', '2026-03-17 05:40:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_audits`
--

CREATE TABLE `inventory_audits` (
  `id` int(11) NOT NULL,
  `audit_month` varchar(50) NOT NULL,
  `conducted_by` int(11) NOT NULL,
  `total_discrepancy_items` int(11) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `target_role` varchar(50) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `target_user_id`, `target_role`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, NULL, 'Requisition Rejected', 'Your request RS-2026-8415 was rejected by Management.', 1, '2026-02-24 08:57:21'),
(2, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-8473 for City Hall.', 1, '2026-02-26 05:25:21'),
(3, NULL, 'management', 'New Requisition Pending', 'jahz submitted RS-2026-6380 for City Hall.', 0, '2026-02-26 06:22:35'),
(4, 5, NULL, 'Requisition Approved', 'Your request RS-2026-6380 has been approved.', 0, '2026-02-26 06:24:28'),
(5, NULL, 'purchasing', 'Ready for PO', 'RS-2026-6380 was approved. Please generate a PO.', 1, '2026-02-26 06:24:28'),
(6, NULL, 'management', 'Audit Discrepancy Alert', 'The February 2026 audit found 1 items with discrepancies. Please review the audit trail immediately.', 0, '2026-02-26 12:22:32'),
(7, NULL, 'management', 'Audit Discrepancy Alert', 'The February 2026 audit found 1 items with discrepancies. Please review the audit trail immediately.', 0, '2026-02-26 12:22:55'),
(8, NULL, 'management', 'Audit Discrepancy Alert', 'The February 2026 audit found 1 items with discrepancies. Please review the audit trail immediately.', 0, '2026-02-27 07:44:02'),
(9, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260228-267 has been generated. Prepare space to receive materials.', 1, '2026-02-28 02:07:24'),
(10, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to Holcim Philippines for PO-20260228-267.', 0, '2026-02-28 02:07:37'),
(11, NULL, 'management', 'Audit Discrepancy Alert', 'The February 2026 audit found 1 items with discrepancies. Please review the audit trail immediately.', 0, '2026-02-28 02:23:21'),
(12, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260301-344 has been generated. Prepare space to receive materials.', 1, '2026-03-01 12:09:34'),
(13, NULL, 'purchasing', 'PO Delivered', 'Order PO-20260301-344 has arrived and was received at the warehouse.', 1, '2026-03-01 12:22:58'),
(14, NULL, 'management', 'PO Delivered', 'Order PO-20260301-344 has arrived and was received at the warehouse.', 0, '2026-03-01 12:22:58'),
(15, NULL, 'purchasing', 'PO Delivered', 'Order PO-20260228-267 has arrived and was received at the warehouse.', 1, '2026-03-01 12:23:01'),
(16, NULL, 'management', 'PO Delivered', 'Order PO-20260228-267 has arrived and was received at the warehouse.', 0, '2026-03-01 12:23:01'),
(17, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-5584 for SCC Buenavista Campus.', 0, '2026-03-01 22:23:12'),
(18, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to Holcim Philippines for PO-20260228-267.', 0, '2026-03-01 23:01:08'),
(19, NULL, 'management', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 3 items with discrepancies.', 0, '2026-03-01 23:06:11'),
(20, NULL, 'admin', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 3 items with discrepancies.', 1, '2026-03-01 23:06:11'),
(21, NULL, 'management', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 0, '2026-03-01 23:10:40'),
(22, NULL, 'admin', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 1, '2026-03-01 23:10:40'),
(23, NULL, 'management', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 0, '2026-03-01 23:49:03'),
(24, NULL, 'admin', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 1, '2026-03-01 23:49:03'),
(25, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - Bagyong Basyang', 0, '2026-03-02 02:22:50'),
(26, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - Bagyong Basyang', 0, '2026-03-02 02:22:50'),
(27, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Naay dagop!', 0, '2026-03-02 02:28:20'),
(28, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Naay dagop!', 0, '2026-03-02 02:28:20'),
(29, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Naay dagop!', 1, '2026-03-02 02:28:20'),
(30, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to Holcim Philippines for PO-20260228-267.', 0, '2026-03-02 02:29:00'),
(31, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 0, '2026-03-02 02:59:40'),
(32, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 0, '2026-03-02 02:59:40'),
(33, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 1, '2026-03-02 02:59:40'),
(34, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 0, '2026-03-02 02:59:45'),
(35, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 0, '2026-03-02 02:59:45'),
(36, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Supplier Out of Stock - Walay nay stock', 1, '2026-03-02 02:59:45'),
(37, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 0, '2026-03-02 04:58:58'),
(38, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 0, '2026-03-02 04:58:58'),
(39, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 1, '2026-03-02 04:58:58'),
(40, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 0, '2026-03-02 05:00:02'),
(41, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 0, '2026-03-02 05:00:02'),
(42, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop LTO', 1, '2026-03-02 05:00:02'),
(43, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop', 0, '2026-03-02 05:00:57'),
(44, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop', 0, '2026-03-02 05:00:57'),
(45, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - Dakop', 1, '2026-03-02 05:00:57'),
(46, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 11:37:49'),
(47, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 11:37:49'),
(48, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 11:38:15'),
(49, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 11:38:15'),
(50, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 11:41:32'),
(51, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 11:41:32'),
(52, NULL, 'management', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. LJ Caballero found 1 items with discrepancies.', 0, '2026-03-03 11:45:22'),
(53, NULL, 'admin', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. LJ Caballero found 1 items with discrepancies.', 1, '2026-03-03 11:45:22'),
(54, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 11:54:57'),
(55, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 11:54:57'),
(56, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 11:56:26'),
(57, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 11:56:26'),
(58, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - LTO Dakop\r\n', 0, '2026-03-03 12:03:31'),
(59, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - LTO Dakop\r\n', 0, '2026-03-03 12:03:31'),
(60, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Road / Traffic Conditions - LTO Dakop\r\n', 1, '2026-03-03 12:03:31'),
(61, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 0, '2026-03-03 12:08:20'),
(62, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 0, '2026-03-03 12:08:20'),
(63, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 1, '2026-03-03 12:08:20'),
(64, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 12:08:38'),
(65, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 12:08:38'),
(66, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-6602 for SCC Buenavista Campus.', 0, '2026-03-03 12:08:53'),
(67, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-6474 for SCC Buenavista Campus.', 0, '2026-03-03 12:11:01'),
(68, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 12:12:03'),
(69, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 12:12:03'),
(70, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 12:42:49'),
(71, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 12:42:49'),
(72, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 13:04:59'),
(73, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 13:04:59'),
(74, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 0, '2026-03-03 13:06:10'),
(75, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 0, '2026-03-03 13:06:10'),
(76, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 1, '2026-03-03 13:06:10'),
(77, NULL, 'management', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 0, '2026-03-03 13:11:26'),
(78, NULL, 'warehouse', 'Expected Delivery Delayed', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 0, '2026-03-03 13:11:26'),
(79, NULL, 'admin', 'Supply Chain Delay', 'ALERT: PO-20260301-344 is delayed. Reason: Weather / Typhoon - ', 1, '2026-03-03 13:11:26'),
(80, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 1, '2026-03-03 23:11:40'),
(81, NULL, 'management', 'Audit Completed', 'The March 2026 physical recount was completed successfully by LJ Caballero. All physical stocks match the system records exactly.', 0, '2026-03-03 23:11:40'),
(82, NULL, 'management', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 0, '2026-03-05 02:38:22'),
(83, NULL, 'admin', 'Audit Discrepancy Alert', 'The March 2026 physical recount is complete. Found 1 items with discrepancies.', 1, '2026-03-05 02:38:22'),
(84, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully. All physical stocks match the system records exactly.', 0, '2026-03-05 06:09:34'),
(85, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully. All physical stocks match the system records exactly.', 0, '2026-03-05 06:10:11'),
(86, NULL, 'admin', 'Audit Completed', 'The March 2026 physical recount was completed successfully. All physical stocks match the system records exactly.', 0, '2026-03-05 07:25:36'),
(87, 2, NULL, 'Requisition Approved', 'Your request RS-2026-6474 has been approved.', 0, '2026-03-08 01:26:02'),
(88, NULL, 'purchasing', 'Ready for PO', 'RS-2026-6474 was approved. Please generate a PO.', 0, '2026-03-08 01:26:02'),
(89, 2, NULL, 'Requisition Rejected', 'Your request RS-2026-6602 was rejected.', 0, '2026-03-08 01:35:45'),
(90, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-8532 for SCC Buenavista Campus.', 0, '2026-03-08 01:56:13'),
(91, 2, NULL, 'Requisition Approved', 'Your request RS-2026-8532 has been approved.', 0, '2026-03-08 01:56:24'),
(92, NULL, 'purchasing', 'Ready for PO', 'RS-2026-8532 was approved. Please generate a PO.', 0, '2026-03-08 01:56:24'),
(93, NULL, 'management', 'New Requisition Pending', 'Coco Martin submitted RS-2026-1810 for SCC Buenavista Campus.', 0, '2026-03-08 01:58:23'),
(94, 12, NULL, 'Requisition Approved', 'Your request RS-2026-1810 has been approved.', 0, '2026-03-08 01:58:32'),
(95, NULL, 'purchasing', 'Ready for PO', 'RS-2026-1810 was approved. Please generate a PO.', 0, '2026-03-08 01:58:32'),
(96, NULL, 'management', 'New Requisition Pending', 'Coco Martin submitted RS-2026-4721 for SCC Buenavista Campus.', 0, '2026-03-08 02:10:14'),
(97, 12, NULL, 'Requisition Rejected', 'Your request RS-2026-4721 was rejected. Reason: Insufficient stock from electrical tape', 0, '2026-03-08 02:10:55'),
(98, NULL, 'purchasing', 'PO Delivered & Stocked In', 'Order PO-20260228-267 has arrived. Items have been successfully STOCKED IN to the Master Inventory.', 0, '2026-03-08 23:49:34'),
(99, NULL, 'management', 'PO Delivered & Stocked In', 'Order PO-20260228-267 has arrived. Items have been successfully STOCKED IN to the Master Inventory.', 0, '2026-03-08 23:49:34'),
(100, NULL, 'admin', 'Weekly Audit Completed', 'The Week 11 — Mar 9–15, 2026 weekly recount was completed successfully. All physical stocks match the system records exactly.', 0, '2026-03-15 22:46:43'),
(101, NULL, 'management', 'New Requisition Pending', 'System Admin submitted RS-2026-4947 for SCC Main.', 0, '2026-03-19 03:03:18'),
(102, 1, NULL, 'Requisition Approved', 'Your request RS-2026-2875 has been approved.', 0, '2026-03-19 03:03:36'),
(103, NULL, 'purchasing', 'Ready for PO', 'RS-2026-2875 was approved. Please generate a PO.', 0, '2026-03-19 03:03:36'),
(104, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260319-295 with verified item list.', 0, '2026-03-19 03:03:44'),
(105, 1, NULL, 'Requisition Approved', 'Your request RS-2026-4947 has been approved.', 0, '2026-03-19 03:04:37'),
(106, NULL, 'purchasing', 'Ready for PO', 'RS-2026-4947 was approved. Please generate a PO.', 0, '2026-03-19 03:04:37'),
(107, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted RS-2026-8546 for General Restocking.', 0, '2026-03-19 03:09:29'),
(108, 2, NULL, 'Requisition Approved', 'Your request RS-2026-8546 has been approved.', 0, '2026-03-19 03:09:40'),
(109, NULL, 'purchasing', 'Ready for PO', 'RS-2026-8546 was approved. Please generate a PO.', 0, '2026-03-19 03:09:40'),
(110, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260319-611 has been generated. Prepare space to receive materials.', 0, '2026-03-19 03:10:44'),
(111, NULL, 'purchasing', 'PO Discrepancy Found', 'DISCREPANCY ALERT for PO-20260319-611: Order arrived physically with missing or excess items!\n- Concrete Nails [Code: ITM-9411]: Expected 15, Received 14', 0, '2026-03-19 03:18:45'),
(112, NULL, 'management', 'PO Receiving Discrepancy', 'DISCREPANCY ALERT for PO-20260319-611: Order arrived physically with missing or excess items!\n- Concrete Nails [Code: ITM-9411]: Expected 15, Received 14', 0, '2026-03-19 03:18:45'),
(113, NULL, 'admin', 'PO Discrepancy Alert', 'DISCREPANCY ALERT for PO-20260319-611: Order arrived physically with missing or excess items!\n- Concrete Nails [Code: ITM-9411]: Expected 15, Received 14', 0, '2026-03-19 03:18:45'),
(114, NULL, 'management', 'New Requisition Pending', 'System Admin submitted a Warehouse Restock request (RS-2026-1949).', 0, '2026-07-24 08:34:54'),
(115, 1, NULL, 'Requisition Approved', 'Your request RS-2026-1949 has been approved.', 0, '2026-07-24 08:34:57'),
(116, NULL, 'purchasing', 'Ready for PO', 'RS-2026-1949 was approved. Please generate a PO.', 0, '2026-07-24 08:34:57'),
(117, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260724-574 has been generated. Prepare space to receive materials.', 0, '2026-07-24 08:35:44'),
(118, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260724-574 with verified item list.', 0, '2026-07-24 08:40:12'),
(119, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260724-574 with verified item list.', 0, '2026-07-24 08:41:19'),
(120, NULL, 'purchasing', '📩 SMS Reply: Unknown Supplier', 'Supplier (+639098702199) sent: \"Hello CIMS, we have 40 bags of Holcim cement available for immediate delivery.\"', 0, '2026-07-24 08:51:22'),
(121, NULL, 'admin', '📩 SMS Reply: Unknown Supplier', 'Supplier (+639098702199) sent: \"Hello CIMS, we have 40 bags of Holcim cement available for immediate delivery.\"', 0, '2026-07-24 08:51:22'),
(122, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260724-574 with verified item list.', 0, '2026-07-24 08:54:28'),
(123, NULL, 'purchasing', '📩 SMS Reply: Unknown Supplier', 'Supplier (+639098702199) sent: \"Hello CIMS, this is an automated supplier test response!\"', 0, '2026-07-24 09:11:17'),
(124, NULL, 'admin', '📩 SMS Reply: Unknown Supplier', 'Supplier (+639098702199) sent: \"Hello CIMS, this is an automated supplier test response!\"', 0, '2026-07-24 09:11:17'),
(125, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to City Hardware for PO-20260724-574 with verified item list.', 0, '2026-07-24 09:13:37'),
(126, NULL, 'purchasing', '📩 SMS Reply: City Hardware', 'Supplier (+639760167906) sent: \"Test\"', 0, '2026-07-24 09:20:09'),
(127, NULL, 'admin', '📩 SMS Reply: City Hardware', 'Supplier (+639760167906) sent: \"Test\"', 0, '2026-07-24 09:20:09'),
(128, NULL, 'purchasing', '📩 SMS Reply: City Hardware', 'Supplier (+639760167906) sent: \"Test\"', 0, '2026-07-24 09:25:38'),
(129, NULL, 'admin', '📩 SMS Reply: City Hardware', 'Supplier (+639760167906) sent: \"Test\"', 0, '2026-07-24 09:25:38'),
(130, NULL, 'warehouse', '🚚 Supply ETA Updated: PO-20260724-574', 'Delivery from City Hardware is now estimated to arrive at warehouse on Jul 30, 2026.', 0, '2026-07-29 00:33:41'),
(131, NULL, 'management', '🚚 Supply ETA Updated: PO-20260724-574', 'Delivery from City Hardware is now estimated to arrive at warehouse on Jul 30, 2026.', 0, '2026-07-29 00:33:42'),
(132, NULL, 'management', 'New Requisition Pending', 'LJ Caballero submitted a Warehouse Restock request (RS-2026-8870).', 0, '2026-07-29 00:38:36'),
(133, 2, NULL, 'Requisition Approved', 'Your request RS-2026-8870 has been approved.', 0, '2026-07-29 00:39:05'),
(134, NULL, 'purchasing', 'Ready for PO', 'RS-2026-8870 was approved. Please generate a PO.', 0, '2026-07-29 00:39:05'),
(135, NULL, 'warehouse', 'Incoming Delivery Expected', 'PO PO-20260729-744 generated. Target Warehouse ETA: Jul 30, 2026. Prepare space to receive materials.', 0, '2026-07-29 00:39:26'),
(136, NULL, 'management', 'SMS Order Sent', 'Automated SMS was sent to Holcim Philippines for PO-20260729-744 with verified item list.', 0, '2026-07-29 00:40:26');

-- --------------------------------------------------------

--
-- Table structure for table `po_items`
--

CREATE TABLE `po_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_items`
--

INSERT INTO `po_items` (`id`, `po_id`, `item_code`, `quantity`, `unit_price`) VALUES
(1, 1, 'ITM-8782', 50, 0.00),
(2, 2, 'ITM-9411', 100, 0.00),
(3, 2, 'ITM-2', 50, 12.00);

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `project_code` varchar(50) DEFAULT NULL,
  `project_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_code`, `project_name`, `description`, `status`, `created_at`) VALUES
(1, NULL, 'Main Headquarters Construction', 'General construction of the main building', 'active', '2026-07-24 08:29:27');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_no` varchar(50) NOT NULL,
  `rs_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `prepared_by` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending Delivery',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delay_remarks` text DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_no`, `rs_id`, `supplier_id`, `prepared_by`, `status`, `created_at`, `delay_remarks`, `expected_delivery_date`) VALUES
(1, 'PO-20260724-574', 14, 2, 1, 'SMS Sent', '2026-07-24 08:35:44', NULL, '2026-07-30'),
(2, 'PO-20260729-744', 15, 1, 1, 'SMS Sent', '2026-07-29 00:39:26', NULL, '2026-07-30');

-- --------------------------------------------------------

--
-- Table structure for table `requisitions`
--

CREATE TABLE `requisitions` (
  `id` int(11) NOT NULL,
  `rs_no` varchar(50) NOT NULL,
  `requestor_id` int(11) NOT NULL,
  `requestor_name` varchar(100) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `urgency` varchar(50) DEFAULT 'Normal',
  `remarks` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending Approval',
  `type` varchar(50) DEFAULT 'project',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisitions`
--

INSERT INTO `requisitions` (`id`, `rs_no`, `requestor_id`, `requestor_name`, `project_name`, `urgency`, `remarks`, `status`, `type`, `created_at`) VALUES
(1, 'RS-2026-9297', 2, 'Jen', 'Phase 1', 'Normal', '', 'PO Created', 'project', '2026-02-24 04:36:46'),
(2, 'RS-2026-8415', 2, 'Jen', 'basta', 'Urgent', 'Accept pls.', 'Rejected', 'project', '2026-02-24 05:21:20'),
(3, 'RS-2026-8473', 2, 'LJ Caballero', 'City Hall', 'Normal', '', 'Approved', 'project', '2026-02-26 05:25:21'),
(4, 'RS-2026-6380', 5, 'jahz', 'City Hall', 'High', '', 'PO Created', 'project', '2026-02-26 06:22:35'),
(5, 'RS-2026-5584', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'Urgent', '', 'PO Created', 'project', '2026-03-01 22:23:12'),
(6, 'RS-2026-6602', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'High', '', 'Rejected', 'project', '2026-03-03 12:08:53'),
(7, 'RS-2026-6474', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'High', '', 'Approved', 'project', '2026-03-03 12:11:01'),
(8, 'RS-2026-8532', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'Normal', '', 'Approved', 'project', '2026-03-08 01:56:13'),
(9, 'RS-2026-1810', 12, 'Coco Martin', 'SCC Buenavista Campus', 'Normal', '', 'Approved', 'project', '2026-03-08 01:58:23'),
(10, 'RS-2026-4721', 12, 'Coco Martin', 'SCC Buenavista Campus', 'Normal', '\n\n[MANAGEMENT REJECTED]: Insufficient stock from electrical tape', 'Rejected', 'project', '2026-03-08 02:10:14'),
(11, 'RS-2026-2875', 1, 'System Admin', 'SCC Main', 'Normal', '', 'Approved', 'project', '2026-03-19 02:54:57'),
(12, 'RS-2026-4947', 1, 'System Admin', 'SCC Main', 'High', '', 'Approved', 'project', '2026-03-19 03:03:18'),
(13, 'RS-2026-8546', 2, 'LJ Caballero', 'General Restocking', 'Normal', '', 'PO Created', 'project', '2026-03-19 03:09:29'),
(14, 'RS-2026-1949', 1, 'System Admin', 'Warehouse Restock', 'Normal', '', 'PO Created', 'restock', '2026-07-24 08:34:54'),
(15, 'RS-2026-8870', 2, 'LJ Caballero', 'Warehouse Restock', 'Normal', '', 'PO Created', 'restock', '2026-07-29 00:38:36');

-- --------------------------------------------------------

--
-- Table structure for table `requisition_items`
--

CREATE TABLE `requisition_items` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisition_items`
--

INSERT INTO `requisition_items` (`id`, `requisition_id`, `item_code`, `quantity`) VALUES
(14, 14, 'ITM-8782', 50),
(15, 15, 'ITM-9411', 100),
(16, 15, 'ITM-2', 50);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_code` varchar(50) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `company_name`, `contact_person`, `contact_number`, `email`, `address`, `status`, `created_at`) VALUES
(1, 'SUP-1055', 'Holcim Philippines', 'Jane', '09123456578', 'sales@holcim.com', 'F.S Pajares Ave. Pagadian City', 'Active', '2026-02-28 02:07:15'),
(2, 'SUP-6986', 'City Hardware', 'Angelo Carlo Pedrosa', '639760167906', 'carlopedrosa14@gmail.com', '266 Consulation Santo Niño\r\nPurok Roxas', 'Active', '2026-03-13 08:15:22');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_sms_replies`
--

CREATE TABLE `supplier_sms_replies` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `po_id` int(11) DEFAULT NULL,
  `direction` enum('inbound','outbound') NOT NULL DEFAULT 'inbound',
  `sender_number` varchar(50) NOT NULL,
  `receiver_number` varchar(50) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_sms_replies`
--

INSERT INTO `supplier_sms_replies` (`id`, `supplier_id`, `po_id`, `direction`, `sender_number`, `receiver_number`, `message_text`, `is_read`, `created_at`) VALUES
(1, NULL, NULL, 'inbound', '+639098702199', '+639098702199', 'Hello CIMS, we have 40 bags of Holcim cement available for immediate delivery.', 1, '2026-07-24 08:51:22'),
(2, NULL, NULL, 'outbound', '+639098702199', '+639098702199', 'Test', 1, '2026-07-24 09:08:37'),
(3, NULL, NULL, 'inbound', '+639098702199', '+639098702199', 'Hello CIMS, this is an automated supplier test response!', 1, '2026-07-24 09:11:17'),
(4, 2, 1, 'inbound', '+639760167906', '+639098702199', 'Test', 1, '2026-07-24 09:20:09'),
(5, 2, 1, 'inbound', '+639760167906', '+639098702199', 'Test', 1, '2026-07-24 09:25:38'),
(6, 2, NULL, 'outbound', '+639098702199', '+639760167906', 'How about the cornerstone ma\'am?', 1, '2026-07-24 09:33:39'),
(7, 2, NULL, 'outbound', '+639098702199', '+639760167906', 'Test rani sya ha!!', 1, '2026-07-24 09:36:44');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('last_ai_prediction', '<!DOCTYPE html>\n<html>\n<head>\n	<title>Inventory Restock Recommendations</title>\n</head>\n<body>\n	<h1>Inventory Restock Recommendations</h1>\n	<ul>\n		<li style=\"margin-bottom: 15px; padding: 12px; border-left: 4px solid #dc3545;\">\n			<span style=\"font-size: 1.1rem;\">⚠️ <strong>Makita Power Drill</strong></span> - Runs out in 999 days.\n			<br>🏢 <strong>Top Consumer:</strong> General Stock\n			<br>👉 <strong>Recommended Restock Date:</strong> Not Applicable (999 days of stock left)\n			<br>👉 <strong>Recommended Order Qty:</strong> Not Applicable\n		</li>\n		<li style=\"margin-bottom: 15px; padding: 12px; border-left: 4px solid #dc3545;\">\n			<span style=\"font-size: 1.1rem;\">⚠️ <strong>Electrical Tape</strong></span> - Runs out in 999 days.\n			<br>🏢 <strong>Top Consumer:</strong> General Stock\n			<br>👉 <strong>Recommended Restock Date:</strong> Not Applicable (999 days of stock left)\n			<br>👉 <strong>Recommended Order Qty:</strong> Not Applicable\n		</li>\n		<li style=\"margin-bottom: 15px; padding: 12px; border-left: 4px solid #dc3545;\">\n			<span style=\"font-size: 1.1rem;\">⚠️ <strong>Safety Helmets</strong></span> - Runs out in 999 days.\n			<br>🏢 <strong>Top Consumer:</strong> General Stock\n			<br>👉 <strong>Recommended Restock Date:</strong> Not Applicable (999 days of stock left)\n			<br>👉 <strong>Recommended Order Qty:</strong> Not Applicable\n		</li>\n		<li style=\"margin-bottom: 15px; padding: 12px; border-left: 4px solid #dc3545;\">\n			<span style=\"font-size: 1.1rem;\">⚠️ <strong>Concrete Nails</strong></span> - Runs out in 999 days.\n			<br>🏢 <strong>Top Consumer:</strong> General Stock\n			<br>👉 <strong>Recommended Restock Date:</strong> Not Applicable (999 days of stock left)\n			<br>👉 <strong>Recommended Order Qty:</strong> Not Applicable\n		</li>\n		<li style=\"margin-bottom: 15px; padding: 12px; border-left: 4px solid #dc3545;\">\n			<span style=\"font-size: 1.1rem;\">⚠️ <strong>Steel Rebar (12mm)</strong></span> - Runs out in 999 days.\n			<br>🏢 <strong>Top Consumer:</strong> General Stock\n			<br>👉 <strong>Recommended Restock Date:</strong> Not Applicable (999 days of stock left)\n			<br>👉 <strong>Recommended Order Qty:</strong> Not Applicable\n		</li>\n		<li style=\"margin-bottom: 15px; padding: 12px; border-left: 4px solid #dc3545;\">\n			<span style=\"font-size: 1.1rem;\">⚠️ <strong>Sand</strong></span> - Runs out in 999 days.\n			<br>🏢 <strong>Top Consumer:</strong> General Stock\n			<br>👉 <strong>Recommended Restock Date:</strong> Not Applicable (999 days of stock left)\n			<br>👉 <strong>Recommended Order Qty:</strong> Not Applicable\n		</li>\n	</ul>\n</body>\n</html>\n\nHowever, since all items have 999 days of stock left, we can conclude that there are no critical items that require immediate restocking.', '2026-07-28 10:06:37'),
('last_ai_timestamp', '1785233197000', '2026-07-28 10:06:37'),
('login_background', 'assets/img/default_login_bg.png', '2026-07-24 08:29:27');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `unit_name` varchar(50) NOT NULL,
  `abbreviation` varchar(20) NOT NULL,
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `unit_name`, `abbreviation`, `reorder_level`, `created_at`) VALUES
(1, 'Pieces', 'pcs', 10, '2026-07-24 08:32:28'),
(2, 'Bags', 'bags', 10, '2026-07-24 08:32:28'),
(3, 'Units', 'units', 5, '2026-07-24 08:32:28'),
(4, 'Kilograms', 'kg', 20, '2026-07-24 08:32:28'),
(5, 'Liters', 'L', 5, '2026-07-24 08:32:28'),
(6, 'Meters', 'm', 15, '2026-07-24 08:32:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'requestor',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fcm_token` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `created_at`, `fcm_token`) VALUES
(1, 'Angelo Carlo Pedrosa', 'admin', '$2y$12$iJVBLTEUBCbcsVJppnWoweZkOIddOITYIiLGZL7VX9QmJD0PVmZvS', 'admin', '2026-07-24 08:29:27', 'ehnufQj-1bhv7vbkPC3VNs:APA91bFQgk6Qxk6atwBeG9dF3Kn5x12FKoaD7PluUWP-x2_lA7Ldvz0LpewP6daKRNkRhfuwrsTAkppnctdoxEye0kpU8aBO72p8Aj6ipUOoQQ6MxPFUKd4'),
(2, 'LJ Caballero', 'ljwarehouse', '$2y$12$l6ki9jOJC0XLqSbRAoo23u.o5w4IBuJJ7kWQCXiAtvYCMKughDJK2', 'warehouse', '2026-07-29 00:26:11', 'ehnufQj-1bhv7vbkPC3VNs:APA91bFQgk6Qxk6atwBeG9dF3Kn5x12FKoaD7PluUWP-x2_lA7Ldvz0LpewP6daKRNkRhfuwrsTAkppnctdoxEye0kpU8aBO72p8Aj6ipUOoQQ6MxPFUKd4'),
(3, 'Coco Martin', 'cocomartin', '$2y$12$vBa78toh2HCrsspKt3wBGe7eLRJUkVTdUV653ZXkZOKdmMslFhZEO', 'purchasing', '2026-07-29 00:26:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `withdrawal_no` varchar(50) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `released_by` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `date_withdrawn` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_items`
--

CREATE TABLE `withdrawal_items` (
  `id` int(11) NOT NULL,
  `withdrawal_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_items`
--
ALTER TABLE `audit_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_items_audit_id` (`audit_id`),
  ADD KEY `idx_audit_items_item_code` (`item_code`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `idx_inventory_item_name` (`item_name`),
  ADD KEY `idx_inventory_status` (`status`),
  ADD KEY `idx_inventory_last_updated` (`last_updated`);

--
-- Indexes for table `inventory_audits`
--
ALTER TABLE `inventory_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audits_created_at` (`created_at`),
  ADD KEY `idx_audits_conducted_by` (`conducted_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_target_role` (`target_role`),
  ADD KEY `idx_notif_target_user_id` (`target_user_id`),
  ADD KEY `idx_notif_created_at` (`created_at`);

--
-- Indexes for table `po_items`
--
ALTER TABLE `po_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po_items_po_id` (`po_id`),
  ADD KEY `idx_po_items_item_code` (`item_code`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_name` (`project_name`),
  ADD UNIQUE KEY `project_code` (`project_code`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_no` (`po_no`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `prepared_by` (`prepared_by`),
  ADD KEY `idx_po_rs_id` (`rs_id`),
  ADD KEY `idx_po_status` (`status`),
  ADD KEY `idx_po_created_at` (`created_at`);

--
-- Indexes for table `requisitions`
--
ALTER TABLE `requisitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rs_no` (`rs_no`),
  ADD UNIQUE KEY `idx_req_rs_no` (`rs_no`),
  ADD KEY `idx_req_status` (`status`),
  ADD KEY `idx_req_requestor_id` (`requestor_id`),
  ADD KEY `idx_req_created_at` (`created_at`);

--
-- Indexes for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_req_items_req_id` (`requisition_id`),
  ADD KEY `idx_req_items_item_code` (`item_code`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`);

--
-- Indexes for table `supplier_sms_replies`
--
ALTER TABLE `supplier_sms_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `po_id` (`po_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `idx_users_username` (`username`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `withdrawal_no` (`withdrawal_no`),
  ADD KEY `released_by` (`released_by`),
  ADD KEY `idx_withdrawals_date` (`date_withdrawn`);

--
-- Indexes for table `withdrawal_items`
--
ALTER TABLE `withdrawal_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wd_items_withdrawal_id` (`withdrawal_id`),
  ADD KEY `idx_wd_items_item_code` (`item_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_items`
--
ALTER TABLE `audit_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inventory_audits`
--
ALTER TABLE `inventory_audits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `po_items`
--
ALTER TABLE `po_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `requisitions`
--
ALTER TABLE `requisitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `requisition_items`
--
ALTER TABLE `requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_sms_replies`
--
ALTER TABLE `supplier_sms_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `withdrawal_items`
--
ALTER TABLE `withdrawal_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_items`
--
ALTER TABLE `audit_items`
  ADD CONSTRAINT `audit_items_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `inventory_audits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_audits`
--
ALTER TABLE `inventory_audits`
  ADD CONSTRAINT `inventory_audits_ibfk_1` FOREIGN KEY (`conducted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `po_items`
--
ALTER TABLE `po_items`
  ADD CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`rs_id`) REFERENCES `requisitions` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD CONSTRAINT `requisition_items_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_sms_replies`
--
ALTER TABLE `supplier_sms_replies`
  ADD CONSTRAINT `supplier_sms_replies_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_sms_replies_ibfk_2` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `withdrawal_items`
--
ALTER TABLE `withdrawal_items`
  ADD CONSTRAINT `withdrawal_items_ibfk_1` FOREIGN KEY (`withdrawal_id`) REFERENCES `withdrawals` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
