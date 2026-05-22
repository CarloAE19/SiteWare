-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 19, 2026 at 11:39 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.4.16

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
CREATE DATABASE IF NOT EXISTS `construction_inventory` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `construction_inventory`;

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

--
-- Dumping data for table `audit_items`
--

INSERT INTO `audit_items` (`id`, `audit_id`, `item_code`, `system_qty`, `physical_qty`, `discrepancy`) VALUES
(1, 1, 'ITM-3', 5, 5, 0),
(2, 1, 'ITM-4', 45, 45, 0),
(3, 1, 'ITM-8787', 0, 0, 0),
(4, 1, 'ITM-2', 500, 500, 0),
(5, 2, 'ITM-3', 5, 5, 0),
(6, 2, 'ITM-4', 45, 45, 0),
(7, 2, 'ITM-8787', 0, 0, 0),
(8, 2, 'ITM-2', 500, 500, 0),
(9, 3, 'ITM-3', 5, 6, 1),
(10, 3, 'ITM-4', 39, 39, 0),
(11, 3, 'ITM-8787', 5, 5, 0),
(12, 3, 'ITM-2', 500, 500, 0),
(13, 4, 'ITM-3', 6, 6, 0),
(14, 4, 'ITM-4', 39, 35, -4),
(15, 4, 'ITM-8787', 5, 5, 0),
(16, 4, 'ITM-2', 500, 500, 0),
(17, 5, 'ITM-3', 15, 14, -1),
(18, 5, 'ITM-4', 40, 40, 0),
(19, 5, 'ITM-8787', 5, 5, 0),
(20, 5, 'ITM-2', 500, 500, 0),
(21, 6, 'ITM-9411', 5, 4, -1),
(22, 6, 'ITM-3', 20, 20, 0),
(23, 6, 'ITM-4', 40, 40, 0),
(24, 6, 'ITM-4130', 501, 501, 0),
(25, 6, 'ITM-8787', 15, 15, 0),
(26, 6, 'ITM-2', 500, 500, 0),
(27, 7, 'ITM-9411', 4, 5, 1),
(28, 7, 'ITM-3', 30, 30, 0),
(29, 7, 'ITM-4', 40, 40, 0),
(30, 7, 'ITM-4130', 501, 500, -1),
(31, 7, 'ITM-8787', 25, 20, -5),
(32, 7, 'ITM-2', 500, 500, 0),
(33, 8, 'ITM-9411', 5, 10, 5),
(34, 8, 'ITM-3', 30, 30, 0),
(35, 8, 'ITM-4', 40, 40, 0),
(36, 8, 'ITM-4130', 500, 500, 0),
(37, 8, 'ITM-8787', 20, 20, 0),
(38, 8, 'ITM-2', 500, 500, 0),
(39, 9, 'ITM-9411', 10, 10, 0),
(40, 9, 'ITM-3', 30, 30, 0),
(41, 9, 'ITM-4', 40, 40, 0),
(42, 9, 'ITM-4130', 500, 500, 0),
(43, 9, 'ITM-8787', 20, 25, 5),
(44, 9, 'ITM-2', 500, 500, 0),
(45, 10, 'ITM-9411', 20, 20, 0),
(46, 10, 'ITM-3', 30, 30, 0),
(47, 10, 'ITM-4', 40, 40, 0),
(48, 10, 'ITM-4130', 500, 500, 0),
(49, 10, 'ITM-8787', 25, 25, 0),
(50, 10, 'ITM-2', 500, 500, 0),
(51, 11, 'ITM-9411', 20, 20, 0),
(52, 11, 'ITM-3', 30, 30, 0),
(53, 11, 'ITM-4', 40, 40, 0),
(54, 11, 'ITM-4130', 500, 500, 0),
(55, 11, 'ITM-8787', 25, 25, 0),
(56, 11, 'ITM-2', 500, 500, 0),
(57, 12, 'ITM-9411', 20, 20, 0),
(58, 12, 'ITM-3', 30, 30, 0),
(59, 12, 'ITM-4', 40, 40, 0),
(60, 12, 'ITM-4130', 500, 500, 0),
(61, 12, 'ITM-8787', 25, 25, 0),
(62, 12, 'ITM-2', 500, 500, 0),
(63, 13, 'ITM-9411', 20, 20, 0),
(64, 13, 'ITM-3', 30, 30, 0),
(65, 13, 'ITM-4', 40, 40, 0),
(66, 13, 'ITM-4130', 500, 500, 0),
(67, 13, 'ITM-8787', 25, 25, 0),
(68, 13, 'ITM-2', 500, 600, 100),
(69, 14, 'ITM-9411', 20, 20, 0),
(70, 14, 'ITM-3', 30, 30, 0),
(71, 14, 'ITM-4', 40, 40, 0),
(72, 14, 'ITM-4130', 500, 500, 0),
(73, 14, 'ITM-8787', 25, 25, 0),
(74, 14, 'ITM-2', 600, 600, 0),
(75, 15, 'ITM-9411', 20, 20, 0),
(76, 15, 'ITM-3', 30, 30, 0),
(77, 15, 'ITM-4', 40, 40, 0),
(78, 15, 'ITM-4130', 500, 500, 0),
(79, 15, 'ITM-8787', 25, 25, 0),
(80, 15, 'ITM-2', 600, 600, 0),
(81, 16, 'ITM-9411', 20, 20, 0),
(82, 16, 'ITM-3', 30, 30, 0),
(83, 16, 'ITM-4', 40, 40, 0),
(84, 16, 'ITM-4130', 500, 500, 0),
(85, 16, 'ITM-8787', 25, 25, 0),
(86, 16, 'ITM-2', 600, 600, 0),
(87, 17, 'ITM-9411', 20, 20, 0),
(88, 17, 'ITM-3', 30, 30, 0),
(89, 17, 'ITM-4', 40, 40, 0),
(90, 17, 'ITM-4130', 500, 500, 0),
(91, 17, 'ITM-8787', 25, 25, 0),
(92, 17, 'ITM-2', 600, 600, 0),
(93, 18, 'ITM-9411', 20, 20, 0),
(94, 18, 'ITM-3', 30, 30, 0),
(95, 18, 'ITM-4', 40, 40, 0),
(96, 18, 'ITM-4130', 500, 500, 0),
(97, 18, 'ITM-8787', 25, 25, 0),
(98, 18, 'ITM-2', 600, 600, 0),
(99, 19, 'ITM-9411', 20, 20, 0),
(100, 19, 'ITM-3', 30, 30, 0),
(101, 19, 'ITM-4', 40, 40, 0),
(102, 19, 'ITM-4130', 500, 500, 0),
(103, 19, 'ITM-8787', 25, 25, 0),
(104, 19, 'ITM-2', 600, 600, 0),
(105, 20, 'ITM-9411', 20, 20, 0),
(106, 20, 'ITM-3', 30, 30, 0),
(107, 20, 'ITM-4', 40, 40, 0),
(108, 20, 'ITM-4130', 500, 500, 0),
(109, 20, 'ITM-8787', 25, 25, 0),
(110, 20, 'ITM-2', 600, 600, 0),
(111, 21, 'ITM-9411', 20, 20, 0),
(112, 21, 'ITM-3', 30, 30, 0),
(113, 21, 'ITM-4', 40, 40, 0),
(114, 21, 'ITM-4130', 500, 500, 0),
(115, 21, 'ITM-8787', 25, 25, 0),
(116, 21, 'ITM-2', 600, 500, -100),
(117, 22, 'ITM-9411', 20, 20, 0),
(118, 22, 'ITM-3', 35, 35, 0),
(119, 22, 'ITM-4', 40, 40, 0),
(120, 22, 'ITM-4130', 500, 500, 0),
(121, 22, 'ITM-8787', 25, 25, 0),
(122, 22, 'ITM-2', 500, 500, 0),
(123, 23, 'ITM-9411', 20, 20, 0),
(124, 23, 'ITM-3', 35, 35, 0),
(125, 23, 'ITM-4', 40, 40, 0),
(126, 23, 'ITM-4130', 500, 500, 0),
(127, 23, 'ITM-8787', 25, 25, 0),
(128, 23, 'ITM-2', 500, 500, 0),
(129, 24, 'ITM-9411', 20, 20, 0),
(130, 24, 'ITM-3', 35, 35, 0),
(131, 24, 'ITM-4', 40, 40, 0),
(132, 24, 'ITM-4130', 500, 500, 0),
(133, 24, 'ITM-8787', 25, 25, 0),
(134, 24, 'ITM-2', 500, 500, 0),
(135, 25, 'ITM-9411', 19, 19, 0),
(136, 25, 'ITM-8782', 4, 4, 0),
(137, 25, 'ITM-3', 10, 10, 0),
(138, 25, 'ITM-4', 10, 10, 0),
(139, 25, 'ITM-4130', 500, 500, 0),
(140, 25, 'ITM-2', 500, 500, 0);

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
(1, 'Materials', '2026-03-07 01:08:24'),
(2, 'Tools', '2026-03-07 01:08:24'),
(3, 'Safety Equipment', '2026-03-07 01:08:24'),
(5, 'Electrical Supplies', '2026-03-07 01:11:14');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
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

--
-- Dumping data for table `inventory_audits`
--

INSERT INTO `inventory_audits` (`id`, `audit_month`, `conducted_by`, `total_discrepancy_items`, `remarks`, `created_at`) VALUES
(1, 'February 2026', 1, 0, '', '2026-02-26 04:59:54'),
(2, 'February 2026', 2, 0, '', '2026-02-26 05:26:06'),
(3, 'February 2026', 1, 1, 'Subra isa', '2026-02-26 12:22:32'),
(4, 'February 2026', 1, 1, '5 missing', '2026-02-26 12:22:55'),
(5, 'February 2026', 1, 1, '', '2026-02-27 07:44:02'),
(6, 'February 2026', 10, 1, '', '2026-02-28 02:23:21'),
(7, 'March 2026', 2, 3, '', '2026-03-01 23:06:11'),
(8, 'March 2026', 2, 1, '', '2026-03-01 23:10:40'),
(9, 'March 2026', 2, 1, '', '2026-03-01 23:49:02'),
(10, 'March 2026', 2, 0, '', '2026-03-03 11:37:49'),
(11, 'March 2026', 2, 0, '', '2026-03-03 11:38:15'),
(12, 'March 2026', 2, 0, '', '2026-03-03 11:41:32'),
(13, 'March 2026', 2, 1, '', '2026-03-03 11:45:22'),
(14, 'March 2026', 2, 0, '', '2026-03-03 11:54:57'),
(15, 'March 2026', 2, 0, '', '2026-03-03 11:56:26'),
(16, 'March 2026', 2, 0, '', '2026-03-03 12:08:38'),
(17, 'March 2026', 2, 0, '', '2026-03-03 12:12:03'),
(18, 'March 2026', 2, 0, '', '2026-03-03 12:42:49'),
(19, 'March 2026', 2, 0, '', '2026-03-03 13:04:59'),
(20, 'March 2026', 2, 0, '', '2026-03-03 23:11:40'),
(21, 'March 2026', 2, 1, '', '2026-03-05 02:38:22'),
(22, 'March 2026', 2, 0, '', '2026-03-05 06:09:34'),
(23, 'March 2026', 2, 0, '', '2026-03-05 06:10:11'),
(24, 'March 2026', 2, 0, '', '2026-03-05 07:25:36'),
(25, 'Week 11 — Mar 9–15, 2026', 1, 0, '', '2026-03-15 22:46:43');

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
(113, NULL, 'admin', 'PO Discrepancy Alert', 'DISCREPANCY ALERT for PO-20260319-611: Order arrived physically with missing or excess items!\n- Concrete Nails [Code: ITM-9411]: Expected 15, Received 14', 0, '2026-03-19 03:18:45');

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
(1, 1, 'ITM-2', 15, 12.00),
(2, 2, 'ITM-8787', 10, 500.00),
(3, 2, 'ITM-3', 10, 120.00),
(4, 0, 'ITM-2', 50, 12.00),
(5, 4, 'ITM-9411', 15, 0.00),
(6, 4, 'ITM-4130', 200, 0.00);

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
  `delay_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_no`, `rs_id`, `supplier_id`, `prepared_by`, `status`, `created_at`, `delay_remarks`) VALUES
(1, 'PO-20260228-267', 1, 1, 1, 'Delivered', '2026-02-28 02:07:24', NULL),
(2, 'PO-20260301-344', 4, 1, 11, 'Delayed (Weather)', '2026-03-01 12:09:34', 'Weather / Typhoon - '),
(3, 'PO-20260319-295', 5, 2, 1, 'SMS Sent', '2026-03-19 02:52:38', NULL),
(4, 'PO-20260319-611', 13, 1, 1, 'Delivered (Discrepancy)', '2026-03-19 03:10:44', '\n\n[DELIVERY DISCREPANCY]:\n- Concrete Nails [Code: ITM-9411]: Expected 15, Received 14');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisitions`
--

INSERT INTO `requisitions` (`id`, `rs_no`, `requestor_id`, `requestor_name`, `project_name`, `urgency`, `remarks`, `status`, `created_at`) VALUES
(1, 'RS-2026-9297', 2, 'Jen', 'Phase 1', 'Normal', '', 'PO Created', '2026-02-24 04:36:46'),
(2, 'RS-2026-8415', 2, 'Jen', 'basta', 'Urgent', 'Accept pls.', 'Rejected', '2026-02-24 05:21:20'),
(3, 'RS-2026-8473', 2, 'LJ Caballero', 'City Hall', 'Normal', '', 'Approved', '2026-02-26 05:25:21'),
(4, 'RS-2026-6380', 5, 'jahz', 'City Hall', 'High', '', 'PO Created', '2026-02-26 06:22:35'),
(5, 'RS-2026-5584', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'Urgent', '', 'PO Created', '2026-03-01 22:23:12'),
(6, 'RS-2026-6602', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'High', '', 'Rejected', '2026-03-03 12:08:53'),
(7, 'RS-2026-6474', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'High', '', 'Approved', '2026-03-03 12:11:01'),
(8, 'RS-2026-8532', 2, 'LJ Caballero', 'SCC Buenavista Campus', 'Normal', '', 'Approved', '2026-03-08 01:56:13'),
(9, 'RS-2026-1810', 12, 'Coco Martin', 'SCC Buenavista Campus', 'Normal', '', 'Approved', '2026-03-08 01:58:23'),
(10, 'RS-2026-4721', 12, 'Coco Martin', 'SCC Buenavista Campus', 'Normal', '\n\n[MANAGEMENT REJECTED]: Insufficient stock from electrical tape', 'Rejected', '2026-03-08 02:10:14'),
(11, 'RS-2026-2875', 1, 'System Admin', 'SCC Main', 'Normal', '', 'Approved', '2026-03-19 02:54:57'),
(12, 'RS-2026-4947', 1, 'System Admin', 'SCC Main', 'High', '', 'Approved', '2026-03-19 03:03:18'),
(13, 'RS-2026-8546', 2, 'LJ Caballero', 'General Restocking', 'Normal', '', 'PO Created', '2026-03-19 03:09:29');

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
(1, 1, 'ITM-2', 15),
(2, 2, 'ITM-4', 55),
(3, 3, 'ITM-4', 1),
(4, 4, 'ITM-8787', 10),
(5, 4, 'ITM-3', 10),
(6, 5, 'ITM-2', 50),
(7, 6, 'ITM-9411', 50),
(8, 7, 'ITM-3', 1),
(9, 8, 'ITM-9411', 15),
(10, 9, 'ITM-9411', 15),
(11, 9, 'ITM-8782', 11),
(12, 10, 'ITM-8782', 15),
(13, 10, 'ITM-9411', 20),
(14, 0, 'ITM-9411', 15),
(15, 12, 'ITM-4130', 55),
(16, 13, 'ITM-9411', 15),
(17, 13, 'ITM-4130', 200);

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
(2, 'SUP-6986', 'City Hardware', 'Kate Bishop', '09551144866', 'pagadian@cityhardware.com', 'Purok Bagong Silang, Balangasan Pagadian City', 'Active', '2026-03-13 08:15:22');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `unit_name` varchar(50) NOT NULL,
  `abbreviation` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `unit_name`, `abbreviation`, `created_at`) VALUES
(1, 'Pieces', 'pcs', '2026-02-27 23:26:59'),
(2, 'Bags', 'bags', '2026-02-27 23:26:59'),
(3, 'Units', 'units', '2026-02-27 23:26:59'),
(4, 'Kilograms', 'kg', '2026-02-27 23:26:59'),
(5, 'Liters', 'L', '2026-02-27 23:26:59'),
(6, 'Meters', 'm', '2026-02-27 23:26:59'),
(7, 'Cubic Meters', 'm3', '2026-02-27 23:31:00');

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
(1, 'System Admin', 'admin', '$2y$12$ABwZhP5WzEm6OCsvISUhFeuPHxIuDv3Co9a3wsiRTqW6lmbUTAhZa', 'admin', '2026-02-25 10:04:47', 'dERNuLXm0kxYHhExgioRSd:APA91bGV4MhcFItT3uD1V7nkS9HBu5D7ShI4J7FBQ6WOF7kpITQ7FK1Xt_vxdBhIKUqdEhvQ9VGNNWV-m5ZmC9OVkSg7AMw8w4m38BSFs53XuERk9TDHu_c'),
(2, 'LJ Caballero', 'ljwarehouse', '$2y$12$MMfcGelpNubtownYTGP72OT1gqqnsUISpnxY0DxsFWjJlcSbDTklq', 'warehouse', '2026-02-25 22:04:41', 'dERNuLXm0kxYHhExgioRSd:APA91bGV4MhcFItT3uD1V7nkS9HBu5D7ShI4J7FBQ6WOF7kpITQ7FK1Xt_vxdBhIKUqdEhvQ9VGNNWV-m5ZmC9OVkSg7AMw8w4m38BSFs53XuERk9TDHu_c'),
(3, 'Vjqt', 'Qt', '$2y$12$UZuGBLbT/knVBWgQ0YDYSelSoOSRSZZlk7C0TbQesvKMZAel9cDgC', 'warehouse', '2026-02-26 03:30:36', NULL),
(10, 'Rexa Facto', 'rexafacto', '$2y$12$aJ0RySdCXMU1B4VR0zm/te1JtHEFgKL/F1JUiyZ2H1aIhkDTEJ1ki', 'warehouse', '2026-02-28 02:19:54', NULL),
(11, 'Taylor Swift', 'taylorswift', '$2y$12$LPmBYpqB/zK1fOivyeSlB.5s/x8qZYDqvpXg3uYauO9uhFwTvgjHq', 'purchasing', '2026-03-01 11:57:25', 'ftfK1-DnHjpVJTa3MEFPEW:APA91bGRp8lJT4JPhWU9WJ-5V1GVViiexhQny0_slkWAbXrfJiW9mkQwSWhMDt-cH4akIip6cIiULP_oQrCc3urdFXOhnE0XgunXpfHg-2A-15APGr7gtn4'),
(12, 'Coco Martin', 'cocomartin', '$2y$12$6s8Fk47Dd1s7ngPnijspfe67jDOPyde0/qOz374hqFrCUUzeNuTVG', 'requestor', '2026-03-01 22:20:13', 'dERNuLXm0kxYHhExgioRSd:APA91bGV4MhcFItT3uD1V7nkS9HBu5D7ShI4J7FBQ6WOF7kpITQ7FK1Xt_vxdBhIKUqdEhvQ9VGNNWV-m5ZmC9OVkSg7AMw8w4m38BSFs53XuERk9TDHu_c'),
(13, 'Kendrick Lamar', 'kendrick', '$2y$12$Pn9.2z5cFm/P3xS.IY4ote9b36Klo3V/mkzN.43sMIL9ZQjtLQY8e', 'management', '2026-03-01 22:24:57', 'dERNuLXm0kxYHhExgioRSd:APA91bGV4MhcFItT3uD1V7nkS9HBu5D7ShI4J7FBQ6WOF7kpITQ7FK1Xt_vxdBhIKUqdEhvQ9VGNNWV-m5ZmC9OVkSg7AMw8w4m38BSFs53XuERk9TDHu_c'),
(14, 'Requestor', 'requestor', '$2y$12$FGVLw5qacFkTzI1dHQNN9.EUZ3HyWnzIzO0gqA1bCw9FWkJiGIjOW', 'requestor', '2026-03-07 07:55:29', NULL),
(15, 'Jahzeel Jakosalem', 'jahz', '$2y$12$5rCGwogIu4txHvRxHQDo8.HVN.TZB547Z.nkBGAtx2LuisagclLc.', 'admin', '2026-03-10 07:52:08', NULL);

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

--
-- Dumping data for table `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `withdrawal_no`, `project_name`, `released_by`, `remarks`, `date_withdrawn`) VALUES
(1, 'WD-260224-945', 'Langapod', 3, 'Jorald', '2026-02-24 22:11:15'),
(2, 'WD-260225-638', 'City Hall', 1, 'Jake Rizal', '2026-02-25 09:33:02'),
(3, 'WD-260225-571', 'City Hall', 1, 'Jack', '2026-02-25 09:50:27'),
(4, 'WD-260226-837', 'City Hall', 1, 'Jacob', '2026-02-26 12:13:47'),
(5, 'WD-260226-448', 'Pagadian', 1, 'Rose', '2026-02-26 12:20:35'),
(6, 'WD-2026-5603', 'SCC Buenavista Campus', 1, '', '2026-03-09 10:26:07'),
(7, 'WD-2026-6678', 'City Hall', 1, '', '2026-03-09 11:00:28'),
(8, 'WD-2026-1892', 'SCC Buenavista Campus', 1, 'Auto-filled via QR Scanner for RS-2026-1810', '2026-03-10 02:17:10'),
(9, 'WD-2026-9794', 'SCC Buenavista Campus', 1, '', '2026-03-10 03:24:37'),
(10, 'WD-2026-6394', 'SCC Buenavista Campus', 1, '', '2026-03-10 03:25:16');

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
-- Dumping data for table `withdrawal_items`
--

INSERT INTO `withdrawal_items` (`id`, `withdrawal_id`, `item_code`, `quantity`) VALUES
(1, 1, 'ITM-5', 1),
(2, 2, 'ITM-1', 50),
(3, 3, 'ITM-5', 1),
(4, 4, 'ITM-4', 5),
(5, 5, 'ITM-4', 1),
(6, 6, 'ITM-9411', 20),
(7, 6, 'ITM-3', 10),
(8, 7, 'ITM-2', 15),
(9, 8, 'ITM-9411', 15),
(10, 8, 'ITM-8782', 11),
(11, 9, 'ITM-3', 20),
(12, 10, 'ITM-4', 30);

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
  ADD KEY `conducted_by` (`conducted_by`),
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
  ADD KEY `po_id` (`po_id`),
  ADD KEY `idx_po_items_po_id` (`po_id`),
  ADD KEY `idx_po_items_item_code` (`item_code`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_no` (`po_no`),
  ADD KEY `rs_id` (`rs_id`),
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
  ADD KEY `requisition_id` (`requisition_id`),
  ADD KEY `idx_req_items_req_id` (`requisition_id`),
  ADD KEY `idx_req_items_item_code` (`item_code`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`);

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
  ADD KEY `withdrawal_id` (`withdrawal_id`),
  ADD KEY `idx_wd_items_withdrawal_id` (`withdrawal_id`),
  ADD KEY `idx_wd_items_item_code` (`item_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_items`
--
ALTER TABLE `audit_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inventory_audits`
--
ALTER TABLE `inventory_audits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT for table `po_items`
--
ALTER TABLE `po_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `requisitions`
--
ALTER TABLE `requisitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `requisition_items`
--
ALTER TABLE `requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `withdrawal_items`
--
ALTER TABLE `withdrawal_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_items`
--
ALTER TABLE `audit_items`
  ADD CONSTRAINT `audit_items_ibfk_1` FOREIGN KEY (`audit_id`) REFERENCES `inventory_audits` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
