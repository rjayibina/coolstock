-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 23, 2026 at 03:59 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- This dump is the full baseline schema + seed data for a fresh install -
-- run this file alone against an empty database, nothing else needed.
-- It already includes the effect of what were previously 4 standalone
-- migration_*.sql files, now folded in and removed:
--   - migration_add_na_brand.sql            -> `brands` row 7 ('N/A')
--   - migration_add_user_id_to_transactions_reports.sql
--       -> `transactions`.user_id / `reports`.user_id (+ FKs, below)
--   - migration_add_item_type_to_categories.sql
--       -> `item_categories`.item_type_id (+ FK, below)
--   - migration_unlock_categories_item_type.sql
--       -> only `item_categories` row 5 ("Consumables & Spare Parts")
--          ships locked to Consumable; every other category is NULL
--          (unlocked) by default, same as a brand-new category gets.
-- An already-running installation that applied those 4 files by hand
-- does not need to run anything from this dump - it already has the
-- same schema and (for the lock) the same corrected data.

--
-- Database: `mister_aircon`
--

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `brand_id` int(11) NOT NULL,
  `brand_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`brand_id`, `brand_name`) VALUES
(1, 'Daikin'),
(2, 'Carrier'),
(3, 'Panasonic'),
(4, 'LG'),
(5, 'Samsung'),
(6, 'Mitsubishi Electric'),
(7, 'N/A');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `item_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `item_type_id` int(11) DEFAULT NULL,
  `model` varchar(100) NOT NULL,
  `energy_rating` varchar(20) DEFAULT NULL,
  `monthly_consumption` decimal(10,2) DEFAULT NULL,
  `cooling_capacity` varchar(50) DEFAULT NULL,
  `refrigerant` varchar(50) DEFAULT NULL,
  `installation_type` varchar(50) DEFAULT NULL,
  `power_input` varchar(50) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`item_id`, `category_id`, `brand_id`, `item_type_id`, `model`, `energy_rating`, `monthly_consumption`, `cooling_capacity`, `refrigerant`, `installation_type`, `power_input`, `year`, `image_path`) VALUES
(1, 1, 1, 1, 'FTKC25XVM', '5 Star (Inverter)', 45.50, '9,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 1.5A', 2024, NULL),
(2, 2, 2, 1, '51QAC12', '3 Star', 68.00, '12,000 BTU/hr', 'R410A', 'Window Mounted', '220-240V, 50Hz, 5.8A', 2023, NULL),
(3, 3, 3, 1, 'S-24PU1U5B', '4 Star', 95.20, '24,000 BTU/hr', 'R32', 'Floor Standing', '220-240V, 50Hz, 9.5A', 2024, NULL),
(4, 4, 4, 1, 'ATNQ36GPLE0', '3 Star', 145.00, '36,000 BTU/hr', 'R410A', 'Ceiling Cassette', '380-415V, 3-Phase, 50Hz', 2022, NULL),
(5, 1, 5, 1, 'AR13AYHZAWK', '5 Star', 58.30, '13,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 2.3A', 2024, NULL),
(6, 1, 6, 1, 'MSY-GL25VF', '5 Star (Inverter)', 82.00, '25,000 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 3.8A', 2023, NULL),
(7, 5, NULL, 2, 'R32-CYL-10KG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 5, NULL, 2, 'INSUL-TAPE-CU', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 5, NULL, 2, 'PVC-DRAIN-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 5, NULL, 2, 'BRKT-WALL-SET', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 2, 1, 1, 'FACQ10', '3 Star', 52.00, '10,000 BTU/hr', 'R32', 'Window Mounted', '220-240V, 50Hz, 4.5A', 2022, NULL),
(12, 1, 4, 1, 'S4-Q09JA3AE', '5 Star (Inverter)', 48.00, '9,200 BTU/hr', 'R32', 'Wall Mounted', '220-240V, 50Hz, 1.6A', 2025, NULL),
(13, 5, NULL, 2, 'CU-PIPE-1-4IN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'assets/uploads/products/product_6ab3b98fc3df80.32150342.webp');

-- --------------------------------------------------------

--
-- Table structure for table `item_categories`
--

CREATE TABLE `item_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `item_type_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_categories`
--
-- item_type_id: NULL means the category doesn't auto-lock the Item Type
-- field (Add/Edit Product leaves it open). Only "Consumables & Spare
-- Parts" ships locked to Consumable by default - an admin can lock/unlock
-- any other category from the Categories page's "Locks Item Type" field.

INSERT INTO `item_categories` (`category_id`, `category_name`, `item_type_id`) VALUES
(1, 'Split Type AC', NULL),
(2, 'Window Type AC', NULL),
(3, 'Floor Mounted AC', NULL),
(4, 'Cassette Type AC', NULL),
(5, 'Consumables & Spare Parts', 2);

-- --------------------------------------------------------

--
-- Table structure for table `item_stock`
--

CREATE TABLE `item_stock` (
  `item_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_stock`
--

INSERT INTO `item_stock` (`item_id`, `location_id`, `quantity`) VALUES
(1, 1, 8),
(1, 2, 2),
(2, 1, 5),
(2, 2, 1),
(3, 1, 6),
(4, 2, 1),
(5, 1, 12),
(6, 2, 2),
(7, 1, 25),
(8, 1, 60),
(9, 2, 197),
(10, 2, 26),
(11, 1, 0),
(12, 1, 0),
(12, 2, 6),
(13, 1, 40);

-- --------------------------------------------------------

--
-- Table structure for table `item_types`
--

CREATE TABLE `item_types` (
  `item_type_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `requires_serial` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_types`
--

INSERT INTO `item_types` (`item_type_id`, `type_name`, `requires_serial`) VALUES
(1, 'Asset', 1),
(2, 'Consumable', 0);

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `location_name`) VALUES
(1, 'Main Store'),
(2, 'Warehouse');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `report_type` enum('stock_summary','usage_report','low_stock','transaction_log') NOT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `generated_by` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`report_id`, `report_type`, `date_from`, `date_to`, `generated_by`, `user_id`, `notes`, `generated_at`) VALUES
(1, 'stock_summary', NULL, NULL, 'Hyacinth Maris Betinol', 2, 'test stock summary report - 09/20/2026', '2026-09-19 16:09:15');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `to_location_id` int(11) DEFAULT NULL,
  `transaction_type` enum('item_request','borrow','return','stock_in','stock_out','delivery','transfer') NOT NULL,
  `reference_number` varchar(20) DEFAULT NULL,
  `manually_added` tinyint(1) NOT NULL DEFAULT 0,
  `quantity` int(11) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `technician_name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source` enum('manual','auto') NOT NULL DEFAULT 'manual',
  `status` enum('pending','active','completed','declined') NOT NULL DEFAULT 'completed',
  `related_transaction_id` int(11) DEFAULT NULL,
  `damaged_quantity` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `item_id`, `location_id`, `to_location_id`, `transaction_type`, `reference_number`, `manually_added`, `quantity`, `serial_number`, `transaction_date`, `technician_name`, `user_id`, `supplier_name`, `notes`, `source`, `status`, `related_transaction_id`, `damaged_quantity`, `created_at`) VALUES
(1, 1, 1, NULL, 'stock_in', NULL, 0, 10, NULL, '2026-06-15', 'Hyacinth Maris Betinol', 2, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(2, 1, 2, NULL, 'stock_in', NULL, 0, 3, NULL, '2026-06-15', 'Hyacinth Maris Betinol', 2, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(3, 1, 1, NULL, 'stock_out', NULL, 0, 2, NULL, '2026-07-20', 'Hyacinth Maris Betinol', 2, NULL, 'Installed at client site - Barangay Lahug.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(4, 1, 2, NULL, 'stock_out', NULL, 0, 1, NULL, '2026-07-25', 'Erjhon Dapiton', 3, NULL, 'Released for a warehouse-side installation job.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(5, 2, 2, NULL, 'stock_in', NULL, 0, 6, NULL, '2026-06-20', 'Rejames Augusto', 4, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(6, 3, 1, NULL, 'stock_in', NULL, 0, 4, NULL, '2026-07-01', 'Paul Steve Fajardo', 5, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(7, 4, 2, NULL, 'stock_in', NULL, 0, 3, NULL, '2026-05-10', 'Hyacinth Maris Betinol', 2, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(8, 4, 2, NULL, 'stock_out', NULL, 0, 1, NULL, '2026-08-01', 'Hyacinth Maris Betinol', 2, NULL, 'Released for a commercial installation - conference room unit.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(9, 4, 2, NULL, 'stock_out', NULL, 0, 1, NULL, '2026-09-08', 'Hyacinth Maris Betinol', 2, NULL, 'Released for a residential retrofit job.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(10, 5, 1, NULL, 'stock_in', NULL, 0, 15, NULL, '2026-06-01', 'Hyacinth Maris Betinol', 2, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(11, 5, 1, NULL, 'stock_out', NULL, 0, 3, NULL, '2026-07-10', 'Hyacinth Maris Betinol', 2, NULL, 'Released for a residential installation - client delivery.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(12, 6, 2, NULL, 'stock_in', NULL, 0, 5, NULL, '2026-06-25', 'Erjhon Dapiton', 3, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(13, 6, 2, NULL, 'stock_out', NULL, 0, 2, NULL, '2026-08-25', 'Erjhon Dapiton', 3, NULL, 'Released for a residential installation - client delivery.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(14, 6, 2, NULL, 'stock_out', NULL, 0, 1, NULL, '2026-09-10', 'Erjhon Dapiton', 3, NULL, 'Released for a follow-up unit swap.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(15, 7, 1, NULL, 'stock_in', NULL, 0, 25, NULL, '2026-06-05', 'Rejames Augusto', 4, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(16, 8, 1, NULL, 'stock_in', NULL, 0, 60, NULL, '2026-06-05', 'Paul Steve Fajardo', 5, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(17, 9, 2, NULL, 'stock_in', NULL, 0, 200, NULL, '2026-06-05', 'Erjhon Dapiton', 3, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(18, 10, 2, NULL, 'stock_in', NULL, 0, 30, NULL, '2026-06-05', 'Hyacinth Maris Betinol', 2, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(19, 11, 1, NULL, 'stock_in', NULL, 0, 3, NULL, '2026-04-15', 'Rejames Augusto', 4, NULL, 'Initial stock (seed data).', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(20, 11, 1, NULL, 'stock_out', NULL, 0, 3, NULL, '2026-06-25', 'Rejames Augusto', 4, NULL, 'Last unit sold - awaiting restock.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(21, 12, 1, NULL, 'delivery', 'DO-000001', 0, 6, NULL, '2026-08-28', 'Paul Steve Fajardo', 5, 'Aircon Parts Distribution Inc.', 'New LG split type units - Q3 restock order.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(22, 13, 1, NULL, 'delivery', 'DO-000001', 0, 40, NULL, '2026-08-28', 'Paul Steve Fajardo', 5, 'Aircon Parts Distribution Inc.', 'Copper pipe coil, same order as the LG units.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(23, 12, 1, 2, 'transfer', 'TR-000001', 0, 2, NULL, '2026-08-30', 'Hyacinth Maris Betinol', 2, NULL, 'Moved 2 units to Warehouse ahead of a scheduled commercial job.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(24, 12, 1, NULL, 'stock_out', NULL, 0, 1, 'LGSN-20250912-0007', '2026-09-01', 'Hyacinth Maris Betinol', 2, NULL, 'Installed at client site - serial logged for warranty tracking.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(25, 12, 2, NULL, 'stock_out', NULL, 0, 1, 'LGSN-20250912-0008', '2026-09-14', 'Erjhon Dapiton', 3, NULL, 'Released for an emergency repair job - unit swap.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(26, 12, 1, NULL, 'stock_out', NULL, 0, 1, 'LGSN-20250912-0009', '2026-09-16', 'Hyacinth Maris Betinol', 2, NULL, 'Installed at client site - follow-up unit.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(27, 7, NULL, NULL, 'item_request', NULL, 0, 5, NULL, '2026-09-17', 'Rejames Augusto', 4, NULL, 'Needed for a scheduled maintenance job.', 'manual', 'pending', NULL, NULL, '2026-09-18 08:21:52'),
(28, 8, NULL, NULL, 'item_request', NULL, 0, 10, NULL, '2026-09-10', 'Paul Steve Fajardo', 5, NULL, 'Declined - reserved for another scheduled job this week.', 'manual', 'declined', NULL, NULL, '2026-09-18 08:21:52'),
(29, 10, NULL, NULL, 'item_request', NULL, 0, 4, NULL, '2026-09-05', 'Erjhon Dapiton', 3, NULL, 'For an on-site bracket replacement.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(30, 10, 2, NULL, 'borrow', NULL, 0, 4, NULL, '2026-09-06', 'Hyacinth Maris Betinol', 2, NULL, NULL, 'manual', 'active', 29, NULL, '2026-09-18 08:21:52'),
(31, 9, NULL, NULL, 'item_request', NULL, 0, 20, NULL, '2026-08-20', 'Rejames Augusto', 4, NULL, 'For a multi-unit installation job.', 'manual', 'completed', NULL, NULL, '2026-09-18 08:21:52'),
(32, 9, 2, NULL, 'borrow', NULL, 0, 20, NULL, '2026-08-21', 'Hyacinth Maris Betinol', 2, NULL, NULL, 'manual', 'completed', 31, NULL, '2026-09-18 08:21:52'),
(33, 9, 2, NULL, 'return', NULL, 0, 20, NULL, '2026-08-30', 'Hyacinth Maris Betinol', 2, NULL, '2 units returned with cracked fittings - written off, not restocked.', 'manual', 'completed', 32, 2, '2026-09-18 08:21:52'),
(34, 9, NULL, NULL, 'item_request', NULL, 0, 1, NULL, '2026-09-18', 'Paul Steve Fajardo', 5, NULL, '', 'manual', 'completed', NULL, NULL, '2026-09-18 08:24:49'),
(35, 9, 2, NULL, 'borrow', NULL, 0, 1, NULL, '2026-09-18', 'Hyacinth Maris Betinol', 2, NULL, '', 'manual', 'active', 34, NULL, '2026-09-18 08:25:08'),
(36, 11, NULL, NULL, 'item_request', NULL, 0, 2, NULL, '2026-09-18', 'Erjhon Dapiton', 3, NULL, 'test request', 'manual', 'declined', NULL, NULL, '2026-09-18 08:26:03'),
(37, 12, NULL, NULL, 'item_request', NULL, 0, 2, NULL, '2026-09-18', 'Rejames Augusto', 4, NULL, 'test request', 'manual', 'completed', NULL, NULL, '2026-09-18 08:28:07'),
(38, 12, 1, NULL, 'borrow', NULL, 0, 2, NULL, '2026-09-18', 'Hyacinth Maris Betinol', 2, NULL, 'test request', 'manual', 'active', 37, NULL, '2026-09-18 08:28:36'),
(40, 13, NULL, NULL, 'item_request', 'RQ-000001', 0, 1, NULL, '2026-09-23', 'Paul Steve Fajardo', 5, NULL, '', 'manual', 'pending', NULL, NULL, '2026-09-23 07:53:34'),
(41, 12, NULL, NULL, 'item_request', 'RQ-000001', 0, 1, NULL, '2026-09-23', 'Paul Steve Fajardo', 5, NULL, '', 'manual', 'pending', NULL, NULL, '2026-09-23 07:53:34'),
(42, 10, NULL, NULL, 'item_request', 'RQ-000001', 0, 1, NULL, '2026-09-23', 'Paul Steve Fajardo', 5, NULL, '', 'manual', 'pending', NULL, NULL, '2026-09-23 07:53:34'),
(43, 9, NULL, NULL, 'item_request', 'RQ-000001', 0, 1, NULL, '2026-09-23', 'Paul Steve Fajardo', 5, NULL, '', 'manual', 'pending', NULL, NULL, '2026-09-23 07:53:34'),
(44, 8, NULL, NULL, 'item_request', 'RQ-000001', 0, 1, NULL, '2026-09-23', 'Paul Steve Fajardo', 5, NULL, '', 'manual', 'pending', NULL, NULL, '2026-09-23 07:53:34'),
(45, 4, NULL, NULL, 'item_request', NULL, 0, 5, NULL, '2026-09-23', 'Erjhon Dapiton', 3, NULL, '', 'manual', 'pending', NULL, NULL, '2026-09-23 07:57:40'),
(46, 3, NULL, NULL, 'item_request', 'RQ-000002', 0, 5, NULL, '2026-09-23', 'Rejames Augusto', 4, NULL, '', 'manual', 'pending', NULL, NULL, '2026-09-23 07:58:20'),
(47, 2, NULL, NULL, 'item_request', 'RQ-000002', 0, 5, NULL, '2026-09-23', 'Rejames Augusto', 4, NULL, '', 'manual', 'pending', NULL, NULL, '2026-09-23 07:58:20'),
(48, 2, 2, 1, 'transfer', 'TR-000002', 0, 5, NULL, '2026-09-23', 'Hyacinth Maris Betinol', 2, NULL, '', 'manual', 'completed', NULL, NULL, '2026-09-23 12:18:02'),
(49, 3, 1, NULL, 'delivery', 'DO-000002', 0, 2, NULL, '2026-09-23', 'Hyacinth Maris Betinol', 2, 'Panasonic', '', 'manual', 'completed', NULL, NULL, '2026-09-23 12:29:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','warehouse_staff','technician') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `is_active`, `created_at`) VALUES
(1, 'Carlito Montehermoso Go II', 'carlito.montehermoso@misteraircon.ph', '$2y$10$ex/ccWeOfwRYei.05Airxu8c5yRIOsvUK2yGoommewCuMIDBhKRCO', 'admin', 1, '2026-09-23 13:55:49'),
(2, 'Hyacinth Maris Betinol', 'hyacinth.betinol@misteraircon.ph', '$2y$10$n7jolUOaxU5zFUJspLTsLum/myIewPdfNOQklUBQxuKpkuOrh5Kli', 'warehouse_staff', 1, '2026-09-23 13:56:50'),
(3, 'Erjhon Dapiton', 'erjhon.dapiton@misteraircon.ph', '$2y$10$hoC7/poBKSQFJT4D1DCUz.XO7VeYdOCMKWNtxWzp875GaxgOaPQ.6', 'technician', 1, '2026-09-23 13:57:31'),
(4, 'Rejames Augusto', 'rejames.augusto@misteraircon.ph', '$2y$10$IA3VWqoH1tBl0.vbKFcFlePxnoJxsAmWxoC4oWG.SNzKvp08hznNO', 'technician', 1, '2026-09-23 13:57:39'),
(5, 'Paul Steve Fajardo', 'paul.fajardo@misteraircon.ph', '$2y$10$Jhaq/9N9Y5ljwh8PAoaJe.Yu4tiSHEeHZA.8mzLNfDMp9IvrJ1XFq', 'technician', 1, '2026-09-23 13:57:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`brand_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `fk_inventory_items_brand` (`brand_id`),
  ADD KEY `fk_inventory_items_item_type` (`item_type_id`);

--
-- Indexes for table `item_categories`
--
ALTER TABLE `item_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `fk_item_categories_item_type` (`item_type_id`);

--
-- Indexes for table `item_stock`
--
ALTER TABLE `item_stock`
  ADD PRIMARY KEY (`item_id`,`location_id`),
  ADD KEY `fk_item_stock_location` (`location_id`);

--
-- Indexes for table `item_types`
--
ALTER TABLE `item_types`
  ADD PRIMARY KEY (`item_type_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `fk_reports_user` (`user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `fk_transactions_item_id` (`item_id`),
  ADD KEY `fk_transactions_location` (`location_id`),
  ADD KEY `fk_transactions_to_location` (`to_location_id`),
  ADD KEY `idx_transactions_reference_number` (`reference_number`),
  ADD KEY `idx_transactions_related_transaction_id` (`related_transaction_id`),
  ADD KEY `fk_transactions_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `brand_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `item_categories`
--
ALTER TABLE `item_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `item_types`
--
ALTER TABLE `item_types`
  MODIFY `item_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `fk_inventory_items_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`brand_id`),
  ADD CONSTRAINT `fk_inventory_items_item_type` FOREIGN KEY (`item_type_id`) REFERENCES `item_types` (`item_type_id`),
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`category_id`) ON UPDATE CASCADE;

--
-- Constraints for table `item_categories`
--
ALTER TABLE `item_categories`
  ADD CONSTRAINT `fk_item_categories_item_type` FOREIGN KEY (`item_type_id`) REFERENCES `item_types` (`item_type_id`) ON DELETE SET NULL;

--
-- Constraints for table `item_stock`
--
ALTER TABLE `item_stock`
  ADD CONSTRAINT `fk_item_stock_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_stock_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_item_id` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`),
  ADD CONSTRAINT `fk_transactions_related_transaction` FOREIGN KEY (`related_transaction_id`) REFERENCES `transactions` (`transaction_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transactions_to_location` FOREIGN KEY (`to_location_id`) REFERENCES `locations` (`location_id`),
  ADD CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
