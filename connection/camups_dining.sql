-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 20, 2025 at 07:31 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `camups_dining`
--

-- --------------------------------------------------------

--
-- Table structure for table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_tokens`
--

INSERT INTO `auth_tokens` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 3, '648e1eab884bdbf9ce6f96271700f94b4a64d8d3f68178d7842386f7feeeb7c4', '2025-06-12 14:47:53', '2025-05-13 12:47:53'),
(2, 3, 'f8fbef7aedd889054aeffc7dbacca7dc6799ab46df9c2f8aad051570c44027ad', '2025-06-12 15:07:32', '2025-05-13 13:07:32'),
(3, 3, '0a9a8d116dc743ace6fa286f260b157a357b46dedba0673a4334bfeae5d326af', '2025-06-12 15:14:32', '2025-05-13 13:14:32'),
(4, 3, 'ace28dbb968b37c060809ce850aed9449324ac77c469c463848fd1f70a191b44', '2025-06-12 15:46:57', '2025-05-13 13:46:57');

-- --------------------------------------------------------

--
-- Table structure for table `batch_sequences`
--

CREATE TABLE `batch_sequences` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `ingredient_id` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `day` int(32) NOT NULL,
  `current_sequence` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_sequences`
--

INSERT INTO `batch_sequences` (`id`, `vendor_id`, `ingredient_id`, `year`, `month`, `day`, `current_sequence`) VALUES
(1, 1, 25, 2025, 4, 0, 3),
(6, 1, 22, 2025, 4, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `menu_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `special_instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`id`, `user_id`, `menu_item_id`, `quantity`, `special_instructions`, `created_at`, `updated_at`) VALUES
(47, 3, 2, 3, NULL, '2025-05-19 15:12:27', '2025-06-18 04:48:33'),
(48, 3, 4, 2, NULL, '2025-05-19 15:12:28', '2025-06-18 04:48:29'),
(49, 3, 1, 1, NULL, '2025-06-18 04:48:32', '2025-06-18 04:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'Grains', NULL, NULL, NULL, '2025-04-15 12:09:39', NULL),
(2, 'Spices', NULL, NULL, NULL, '2025-04-15 12:09:39', NULL),
(3, 'Vegetables', NULL, NULL, NULL, '2025-04-15 12:09:39', NULL),
(4, 'Fruits', NULL, NULL, NULL, '2025-04-15 12:09:39', NULL),
(5, 'Dairy', NULL, NULL, NULL, '2025-04-15 12:09:39', NULL),
(6, 'Meat', NULL, NULL, NULL, '2025-04-15 12:09:39', NULL),
(7, 'Seafood', NULL, NULL, NULL, '2025-04-15 12:09:39', NULL),
(8, 'Condiments', NULL, NULL, NULL, '2025-04-15 12:09:39', NULL),
(9, 'Beverages', NULL, NULL, NULL, '2025-04-15 12:09:39', NULL),
(10, 'Others', NULL, NULL, NULL, '2025-04-15 12:09:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `credit_accounts`
--

CREATE TABLE `credit_accounts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `account_type` enum('student','staff') NOT NULL,
  `credit_limit` decimal(10,2) NOT NULL,
  `current_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','blocked','pending') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL,
  `is_blocked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credit_account_requests`
--

CREATE TABLE `credit_account_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `account_type` enum('student','staff') NOT NULL,
  `requested_limit` decimal(10,2) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credit_transactions`
--

CREATE TABLE `credit_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_type` enum('purchase','repayment') NOT NULL,
  `payment_method` enum('cash','esewa','credit') DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `due_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ingredients`
--

CREATE TABLE `ingredients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `minimum_order_quantity` decimal(10,2) DEFAULT NULL,
  `shelf_life_days` int(11) DEFAULT NULL,
  `storage_instructions` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `base_unit` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingredients`
--

INSERT INTO `ingredients` (`id`, `name`, `description`, `minimum_order_quantity`, `shelf_life_days`, `storage_instructions`, `unit`, `base_unit`, `created_at`, `updated_at`, `category_id`, `created_by`, `updated_by`) VALUES
(21, 'Rice', 'White Basmati Rice', 25.00, 365, 'gvfg', 'kg', 'kg', '2025-04-15 10:41:57', '2025-04-15 16:54:21', 1, NULL, 1),
(22, 'Black Pepper', 'Ground Black Pepper', 5.00, 80, 'keep in dry and cool place', 'kg', 'kg', '2025-04-15 10:41:57', '2025-04-15 16:46:51', 2, NULL, 1),
(23, 'Onions', 'Fresh Red Onions', 5.00, 9, 'store in dry and cool place', 'kg', 'kg', '2025-04-15 10:41:57', '2025-04-15 16:52:06', 3, NULL, 1),
(24, 'Tomatoes', 'Fresh Tomatoes', 5.00, 3, 'store in cold and dry place', 'kg', 'kg', '2025-04-15 10:41:57', '2025-04-15 16:54:32', 3, NULL, 1),
(25, 'Milk', 'Fresh Milk', 5.00, 5, 'store in cool place', 'L', 'L', '2025-04-15 10:41:57', '2025-04-15 16:53:11', 5, NULL, 1),
(28, 'Potato', 'AsianPotato', 10.00, 5, 'keep in cool and dry place', 'kg', 'kg', '2025-04-15 14:49:16', '2025-04-15 14:49:16', 3, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `current_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reserved_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `available_quantity` decimal(10,2) GENERATED ALWAYS AS (`current_quantity` - `reserved_quantity`) STORED,
  `batch_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `batch_sequence` int(11) DEFAULT NULL,
  `status` enum('active','expired','quarantine') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `vendor_id`, `ingredient_id`, `current_quantity`, `reserved_quantity`, `batch_number`, `expiry_date`, `last_updated`, `batch_sequence`, `status`) VALUES
(4, 1, 25, 40.00, 0.00, 'MIL202504-002', '2025-04-17', '2025-04-16 09:36:14', 2, 'active'),
(5, 1, 23, 20.00, 0.00, 'ONI20250415-695', '2025-04-23', '2025-04-15 11:44:47', NULL, 'active'),
(6, 1, 25, 0.00, 0.00, 'MIL202504-003', '2025-04-19', '2025-04-16 09:44:32', 3, 'active'),
(7, 1, 22, 300.00, 0.00, 'BLA202504-001', '2025-07-31', '2025-04-17 02:22:21', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_alerts`
--

CREATE TABLE `inventory_alerts` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','expiring_soon','expired') NOT NULL,
  `alert_message` text NOT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_alerts`
--

INSERT INTO `inventory_alerts` (`id`, `vendor_id`, `ingredient_id`, `alert_type`, `alert_message`, `is_resolved`, `resolved_at`, `resolved_by`, `created_at`) VALUES
(63, 1, 25, 'expired', 'Milk (40.00 L) has expired on 2025-04-17', 0, NULL, NULL, '2025-06-20 16:44:22'),
(64, 1, 23, 'expired', 'Onions (20.00 kg) has expired on 2025-04-23', 0, NULL, NULL, '2025-06-20 16:44:22');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_history`
--

CREATE TABLE `inventory_history` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `previous_quantity` decimal(10,2) NOT NULL,
  `new_quantity` decimal(10,2) NOT NULL,
  `change_type` enum('add','update','remove','expired','damaged','prep_used') NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_history`
--

INSERT INTO `inventory_history` (`id`, `inventory_id`, `vendor_id`, `ingredient_id`, `previous_quantity`, `new_quantity`, `change_type`, `reference_type`, `reference_id`, `batch_number`, `cost_per_unit`, `notes`, `changed_by`, `created_at`) VALUES
(4, 4, 1, 25, 0.00, 30.00, 'add', NULL, NULL, NULL, NULL, 'fresh milk', 2, '2025-04-15 11:30:25'),
(5, 5, 1, 23, 0.00, 20.00, 'add', NULL, NULL, NULL, NULL, 'onions', 2, '2025-04-15 11:44:47'),
(6, 4, 1, 25, 30.00, 40.00, 'add', NULL, NULL, NULL, NULL, 'good product', 2, '2025-04-16 09:12:44'),
(7, 4, 1, 25, 40.00, 140.00, 'add', NULL, NULL, 'MIL202504-001', NULL, 'ghy', 2, '2025-04-16 09:35:26'),
(8, 4, 1, 25, 40.00, 540.00, 'add', NULL, NULL, 'MIL202504-002', NULL, 'hgfgdf', 2, '2025-04-16 09:36:14'),
(9, 6, 1, 25, 0.00, 400.00, 'add', NULL, NULL, 'MIL202504-003', NULL, 'juyrt', 2, '2025-04-16 09:44:32'),
(10, 7, 1, 22, 0.00, 300.00, 'add', NULL, NULL, 'BLA202504-001', 50.00, 'wrtyturu', 2, '2025-04-17 02:22:21');

-- --------------------------------------------------------

--
-- Table structure for table `measurement_units`
--

CREATE TABLE `measurement_units` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `abbreviation` varchar(10) NOT NULL,
  `type` enum('weight','volume','unit','piece') NOT NULL,
  `base_unit` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `measurement_units`
--

INSERT INTO `measurement_units` (`id`, `name`, `abbreviation`, `type`, `base_unit`, `created_at`, `updated_at`) VALUES
(1, 'Kilogram', 'kg', 'weight', 1, '2025-04-17 02:56:25', '2025-04-17 02:56:25'),
(2, 'Gram', 'g', 'weight', 0, '2025-04-17 02:56:25', '2025-04-17 02:56:25'),
(3, 'Milligram', 'mg', 'weight', 0, '2025-04-17 02:56:25', '2025-04-17 02:56:25'),
(4, 'Liter', 'L', 'volume', 1, '2025-04-17 02:56:25', '2025-04-17 02:56:25'),
(5, 'Milliliter', 'ml', 'volume', 0, '2025-04-17 02:56:25', '2025-04-17 02:56:25'),
(6, 'Piece', 'pc', 'piece', 1, '2025-04-17 02:56:25', '2025-04-17 02:56:25'),
(7, 'Dozen', 'doz', 'piece', 0, '2025-04-17 02:56:25', '2025-04-17 02:56:25'),
(8, 'Unit', 'unit', 'unit', 1, '2025-04-17 02:56:25', '2025-04-17 02:56:25');

-- --------------------------------------------------------

--
-- Table structure for table `menu_categories`
--

CREATE TABLE `menu_categories` (
  `category_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_categories`
--

INSERT INTO `menu_categories` (`category_id`, `vendor_id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 'Momo & Dumplings', 'Steamed or fried Nepali dumplings packed with flavorful fillings — a must-have street food favorite.', '2025-04-15 06:44:27', '2025-04-15 06:44:27'),
(2, 1, 'Noodles & Chowmein', 'Stir-fried or soupy noodles made with a Nepali twist, full of bold spices and savory satisfaction.', '2025-04-15 06:44:51', '2025-04-15 06:44:51'),
(3, 1, 'Snacks & Sides', 'Light bites and quick fillers to keep you energized between meals — tasty, crunchy, and satisfying.', '2025-04-15 06:45:16', '2025-04-15 06:45:16'),
(4, 1, 'Hot Beverages', 'Warm and comforting drinks perfect for chilly mornings or mid-day break.', '2025-04-15 06:45:41', '2025-04-27 11:48:52');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `item_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `recipe_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_vegetarian` tinyint(1) DEFAULT 0,
  `is_vegan` tinyint(1) DEFAULT 0,
  `is_gluten_free` tinyint(1) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `availability_start` date DEFAULT NULL,
  `availability_end` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`item_id`, `vendor_id`, `category_id`, `recipe_id`, `name`, `description`, `price`, `image_path`, `is_vegetarian`, `is_vegan`, `is_gluten_free`, `is_available`, `availability_start`, `availability_end`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 3, 'Momo (Veg)', 'Soft dumplings filled with seasoned  meat, steamed to perfection. Served with spicy chutney.', 50.00, 'uploads/menu_items/67fe0114b1ec2.jpeg', 1, 0, 0, 1, '2025-04-15', '2025-04-30', '2025-04-15 06:47:48', '2025-04-17 01:10:52'),
(2, 1, 1, NULL, 'Chicken Momo', 'Juicy chicken-filled dumplings served with a tangy tomato-sesame achar. A Nepali classic.', 60.00, 'uploads/menu_items/67fe01ce253a2.jpg', 0, 0, 0, 1, '2025-04-15', '2025-07-30', '2025-04-15 06:50:54', '2025-04-17 01:03:30'),
(3, 1, 2, NULL, 'Chicken Chowmein', 'Juicy chicken-filled dumplings served with a tangy tomato-sesame achar. A Nepali classic.', 40.00, 'uploads/menu_items/67fe022066ea9.jpeg', 0, 0, 0, 1, '2025-04-15', '2025-07-15', '2025-04-15 06:52:16', '2025-04-15 06:52:16'),
(4, 1, 4, NULL, 'Milk Tea (Dudh Chiya)', 'Traditional Nepali milk tea brewed with black tea, milk, and a hint of cardamom or masala.', 20.00, 'uploads/menu_items/67fe026e54357.jpg', 0, 0, 0, 1, '2025-04-15', '2025-06-19', '2025-04-15 06:53:34', '2025-04-15 06:53:34'),
(6, 1, 4, 4, 'MilkTea', 'nepalistyle tea', 25.00, 'uploads/menu_items/67ffae8db1c4e.jpg', 0, 0, 0, 0, NULL, NULL, '2025-04-16 13:20:13', '2025-04-16 16:43:42');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL,
  `credit_account_id` int(11) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL,
  `cash_received` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('cash','esewa','credit','khalti') DEFAULT 'cash',
  `notes` text DEFAULT NULL,
  `preparation_time` int(11) DEFAULT NULL COMMENT 'Estimated preparation time in minutes',
  `pickup_time` datetime DEFAULT NULL COMMENT 'When order should be ready for pickup',
  `completed_at` datetime DEFAULT NULL COMMENT 'When order was actually completed',
  `cancelled_reason` text DEFAULT NULL COMMENT 'Reason if order is cancelled',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `amount_tendered` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `payment_received_at` timestamp NULL DEFAULT NULL,
  `payment_notes` text DEFAULT NULL,
  `payment_updated_at` timestamp NULL DEFAULT NULL,
  `order_type` enum('dine_in','pickup','delivery') NOT NULL DEFAULT 'pickup',
  `preferred_delivery_time` datetime DEFAULT NULL,
  `assigned_worker_id` int(11) DEFAULT NULL,
  `assignment_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `receipt_number`, `user_id`, `customer_id`, `vendor_id`, `credit_account_id`, `order_date`, `total_amount`, `cash_received`, `payment_method`, `notes`, `preparation_time`, `pickup_time`, `completed_at`, `cancelled_reason`, `payment_status`, `amount_tendered`, `change_amount`, `payment_received_at`, `payment_notes`, `payment_updated_at`, `order_type`, `preferred_delivery_time`, `assigned_worker_id`, `assignment_time`) VALUES
(22, 'ORD202504297649', 3, 1, 1, NULL, '2025-04-29 14:16:41', 130.00, NULL, 'cash', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 'pickup', NULL, NULL, NULL),
(23, 'ORD202504296052', 3, 1, 1, NULL, '2025-04-29 14:17:41', 90.00, NULL, 'cash', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 'pickup', NULL, NULL, NULL),
(24, 'ORD202505101804', 3, 1, 1, NULL, '2025-05-10 12:09:00', 170.00, NULL, 'cash', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 'pickup', NULL, NULL, NULL),
(25, 'ORD202505102532', 3, 1, 1, NULL, '2025-05-10 12:16:30', 60.00, NULL, 'cash', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 'pickup', NULL, NULL, NULL),
(26, 'ORD202505103731', 3, 1, 1, NULL, '2025-05-10 12:30:50', 20.00, NULL, 'cash', NULL, NULL, NULL, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, 'pickup', NULL, NULL, NULL),
(27, 'ORD-20250510-681f65d4cb8a4', 3, NULL, 1, NULL, '2025-05-10 14:42:28', 60.00, NULL, 'khalti', NULL, NULL, NULL, NULL, NULL, 'paid', NULL, NULL, NULL, NULL, NULL, 'pickup', NULL, NULL, NULL),
(28, 'ORD-20250510-681f66d1b520a', 3, NULL, 1, NULL, '2025-05-10 14:46:41', 60.00, NULL, 'khalti', NULL, NULL, NULL, NULL, NULL, 'paid', NULL, NULL, NULL, NULL, NULL, 'pickup', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_assignments`
--

CREATE TABLE `order_assignments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('assigned','picked_up','delivered') NOT NULL DEFAULT 'assigned',
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_assignments`
--

INSERT INTO `order_assignments` (`id`, `order_id`, `worker_id`, `assigned_at`, `status`, `picked_up_at`, `delivered_at`) VALUES
(10, 22, 1, '2025-04-29 14:20:03', 'assigned', NULL, NULL),
(11, 23, 1, '2025-04-29 14:29:58', 'assigned', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_delivery_details`
--

CREATE TABLE `order_delivery_details` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_type` enum('dine_in','pickup','delivery') NOT NULL,
  `table_number` varchar(20) DEFAULT NULL,
  `seat_number` varchar(20) DEFAULT NULL,
  `delivery_location` text DEFAULT NULL,
  `building_name` varchar(255) DEFAULT NULL,
  `floor_number` varchar(20) DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `delivery_instructions` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_delivery_details`
--

INSERT INTO `order_delivery_details` (`id`, `order_id`, `order_type`, `table_number`, `seat_number`, `delivery_location`, `building_name`, `floor_number`, `room_number`, `delivery_instructions`, `contact_number`) VALUES
(7, 15, 'delivery', '', NULL, 'BCA Office', 'BUILDING 1', '2', '25', 'shgd', '98256986588'),
(8, 16, 'pickup', '', NULL, '', '', '', '', '', ''),
(9, 17, 'pickup', '', NULL, '', '', '', '', '', ''),
(10, 18, 'pickup', '', NULL, '', '', '', '', '', ''),
(11, 19, 'dine_in', '15', NULL, '', '', '', '', '', ''),
(12, 20, 'dine_in', '9', NULL, 'Nulla laboris minus ', 'Xandra Lindsay', '80', '144', 'Beatae dicta blandit', '+1 (517) 786-9762'),
(13, 21, 'dine_in', '15', NULL, '', '', '', '', '', ''),
(14, 22, 'delivery', '', NULL, 'BCA Office', 'BUILDING 1', '2', '25', 'fast delivery ', '9822222222'),
(15, 23, 'pickup', '', NULL, '', '', '', '', '', ''),
(16, 24, 'pickup', '', NULL, '', '', '', '', '', ''),
(17, 25, 'pickup', '', NULL, '', '', '', '', '', ''),
(18, 26, 'pickup', '', NULL, '', '', '', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `order_inventory_deductions`
--

CREATE TABLE `order_inventory_deductions` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `quantity_used` decimal(10,3) NOT NULL,
  `deducted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `special_instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `quantity`, `unit_price`, `subtotal`, `special_instructions`, `created_at`) VALUES
(34, 22, 4, 1, 20.00, 20.00, NULL, '2025-04-29 14:16:41'),
(35, 22, 2, 1, 60.00, 60.00, NULL, '2025-04-29 14:16:41'),
(36, 22, 1, 1, 50.00, 50.00, NULL, '2025-04-29 14:16:41'),
(37, 23, 1, 1, 50.00, 50.00, NULL, '2025-04-29 14:17:42'),
(38, 23, 3, 1, 40.00, 40.00, NULL, '2025-04-29 14:17:42'),
(39, 24, 4, 1, 20.00, 20.00, NULL, '2025-05-10 12:09:00'),
(40, 24, 2, 1, 60.00, 60.00, NULL, '2025-05-10 12:09:00'),
(41, 24, 1, 1, 50.00, 50.00, NULL, '2025-05-10 12:09:00'),
(42, 24, 3, 1, 40.00, 40.00, NULL, '2025-05-10 12:09:00'),
(43, 25, 2, 1, 60.00, 60.00, NULL, '2025-05-10 12:16:30'),
(44, 26, 4, 1, 20.00, 20.00, NULL, '2025-05-10 12:30:50'),
(45, 27, 2, 1, 60.00, 60.00, NULL, '2025-05-10 14:42:28'),
(46, 28, 2, 1, 60.00, 60.00, NULL, '2025-05-10 14:46:41');

-- --------------------------------------------------------

--
-- Table structure for table `order_notifications`
--

CREATE TABLE `order_notifications` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('order_placed','order_accepted','order_ready','order_completed','order_cancelled') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_details_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_notifications`
--

INSERT INTO `order_notifications` (`id`, `order_id`, `user_id`, `message`, `type`, `is_read`, `created_at`, `delivery_details_id`) VALUES
(4, 26, 3, 'Your order #ORD202505103731 from vendor is ready!', 'order_ready', 0, '2025-05-19 15:20:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_ratings`
--

CREATE TABLE `order_ratings` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL CHECK (`rating` between 1 and 5),
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_tracking`
--

CREATE TABLE `order_tracking` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` enum('pending','accepted','in_progress','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status_changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL COMMENT 'Status update comments',
  `updated_by` int(11) DEFAULT NULL COMMENT 'User who changed the status'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_tracking`
--

INSERT INTO `order_tracking` (`id`, `order_id`, `status`, `updated_at`, `status_changed_at`, `notes`, `updated_by`) VALUES
(126, 22, 'pending', '2025-04-29 14:16:41', '2025-04-29 14:16:41', NULL, 3),
(127, 23, 'pending', '2025-04-29 14:17:42', '2025-04-29 14:17:42', NULL, 3),
(128, 22, 'accepted', '2025-04-29 14:18:50', '2025-04-29 14:18:50', NULL, 2),
(129, 23, 'accepted', '2025-04-29 14:19:30', '2025-04-29 14:19:30', NULL, 2),
(130, 22, 'in_progress', '2025-04-29 14:19:44', '2025-04-29 14:19:44', NULL, 2),
(131, 23, 'in_progress', '2025-04-29 14:19:46', '2025-04-29 14:19:46', NULL, 2),
(132, 23, 'in_progress', '2025-04-29 14:29:58', '2025-04-29 14:29:58', 'Order assigned to worker: waiter', 2),
(133, 24, 'pending', '2025-05-10 12:09:00', '2025-05-10 12:09:00', NULL, 3),
(134, 25, 'pending', '2025-05-10 12:16:30', '2025-05-10 12:16:30', NULL, 3),
(135, 26, 'pending', '2025-05-10 12:30:50', '2025-05-10 12:30:50', NULL, 3),
(136, 27, 'pending', '2025-05-10 14:42:28', '2025-05-10 14:42:28', 'Order placed via Khalti payment', 3),
(137, 28, 'pending', '2025-05-10 14:46:41', '2025-05-10 14:46:41', 'Order placed via Khalti payment', 3),
(138, 26, 'accepted', '2025-05-19 15:20:43', '2025-05-19 15:20:43', NULL, 2),
(139, 26, 'in_progress', '2025-05-19 15:20:48', '2025-05-19 15:20:48', NULL, 2),
(140, 26, 'ready', '2025-05-19 15:20:54', '2025-05-19 15:20:54', NULL, 2),
(141, 28, 'accepted', '2025-05-19 15:21:59', '2025-05-19 15:21:59', NULL, 2),
(142, 27, 'accepted', '2025-06-18 16:50:21', '2025-06-18 16:50:21', NULL, 2),
(143, 26, 'completed', '2025-06-18 16:50:25', '2025-06-18 16:50:25', NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `payment_history`
--

CREATE TABLE `payment_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `previous_status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `new_status` enum('pending','paid','cancelled') NOT NULL,
  `amount_received` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_history`
--

INSERT INTO `payment_history` (`id`, `order_id`, `previous_status`, `new_status`, `amount_received`, `notes`, `created_by`, `created_at`) VALUES
(9, 27, 'pending', 'paid', 60.00, 'Payment completed via Khalti. Transaction ID: tV6SkxQrV4qeSMm9U4MRCf', 3, '2025-05-10 14:42:28'),
(10, 28, 'pending', 'paid', 60.00, 'Payment completed via Khalti. Transaction ID: egjhbkGxoNCLMVthYCHt7K', 3, '2025-05-10 14:46:41');

-- --------------------------------------------------------

--
-- Table structure for table `prep_logs`
--

CREATE TABLE `prep_logs` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `prep_date` date NOT NULL,
  `prep_time` time NOT NULL,
  `prepared_by` int(11) NOT NULL,
  `quality_check_status` enum('pending','passed','failed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_batches`
--

CREATE TABLE `production_batches` (
  `id` int(11) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `quantity_produced` int(11) NOT NULL,
  `production_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `produced_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_batch_ingredients`
--

CREATE TABLE `production_batch_ingredients` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `quantity_used` decimal(10,3) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_inventory_deductions`
--

CREATE TABLE `production_inventory_deductions` (
  `id` int(11) NOT NULL,
  `production_batch_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `quantity_used` decimal(10,3) NOT NULL,
  `deducted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deducted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `qr_code` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_codes`
--

INSERT INTO `qr_codes` (`id`, `vendor_id`, `qr_code`, `created_at`) VALUES
(5, 1, 'uploads/qr_codes/vendor_1_6809bc4eb5baa.png', '2025-04-24 04:21:34');

-- --------------------------------------------------------

--
-- Table structure for table `recipes`
--

CREATE TABLE `recipes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `serving_size` int(11) NOT NULL DEFAULT 1,
  `preparation_time` int(11) DEFAULT NULL COMMENT 'in minutes',
  `instructions` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipes`
--

INSERT INTO `recipes` (`id`, `name`, `description`, `serving_size`, `preparation_time`, `instructions`, `category_id`, `created_by`, `updated_by`, `created_at`, `updated_at`, `is_active`) VALUES
(3, 'momo', 'dkjf', 10, 20, 'sdfgdsddsdkljdhdklhkhlkhlfad', NULL, 2, 2, '2025-04-16 16:32:46', '2025-04-17 01:10:52', 1),
(4, 'Tanner Pope', 'Aut ut veniam deser', 84, 19, 'Eligendi cum consequ', 4, 2, NULL, '2025-04-16 16:43:42', '2025-04-16 16:43:42', 1);

-- --------------------------------------------------------

--
-- Table structure for table `recipe_costs`
--

CREATE TABLE `recipe_costs` (
  `id` int(11) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `calculation_date` date NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `cost_per_serving` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipe_costs`
--

INSERT INTO `recipe_costs` (`id`, `recipe_id`, `calculation_date`, `total_cost`, `cost_per_serving`, `created_at`) VALUES
(2, 4, '2025-04-16', 13560.00, 161.43, '2025-04-16 16:43:42'),
(3, 3, '2025-04-17', 2450.00, 245.00, '2025-04-17 01:10:52');

-- --------------------------------------------------------

--
-- Table structure for table `recipe_ingredients`
--

CREATE TABLE `recipe_ingredients` (
  `id` int(11) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `preparation_notes` varchar(255) DEFAULT NULL,
  `is_optional` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipe_ingredients`
--

INSERT INTO `recipe_ingredients` (`id`, `recipe_id`, `ingredient_id`, `quantity`, `unit`, `preparation_notes`, `is_optional`) VALUES
(4, 4, 25, 27.000, '', NULL, 0),
(5, 4, 25, 59.000, '', NULL, 0),
(6, 4, 25, 42.000, '', NULL, 0),
(7, 4, 25, 84.000, '', NULL, 0),
(8, 4, 25, 14.000, '', NULL, 0),
(9, 3, 23, 25.000, '', NULL, 0),
(10, 3, 25, 20.000, '', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`id`, `name`, `address`, `created_at`) VALUES
(1, 'Itahari Namuna College', 'Itahari-sunsari', '2025-04-14 15:56:07');

-- --------------------------------------------------------

--
-- Table structure for table `staff_students`
--

CREATE TABLE `staff_students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `role` enum('staff','student') NOT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_students`
--

INSERT INTO `staff_students` (`id`, `user_id`, `school_id`, `role`, `approval_status`) VALUES
(1, 3, 1, 'student', 'approved'),
(2, 4, 1, 'staff', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `duration_days` int(11) NOT NULL,
  `features` text DEFAULT NULL COMMENT 'Simple comma-separated list of features',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `vendor_id`, `name`, `description`, `price`, `discount_percentage`, `duration_days`, `features`, `created_at`, `updated_at`) VALUES
(1, 1, 'Basic Monthly', 'Basic monthly subscription with 10% discount on all orders', 299.99, 10.00, 30, '10% discount on all orders,No delivery charges,Priority ordering', '2025-04-15 12:18:05', '2025-04-15 12:18:05'),
(2, 1, 'Premium Quarterly', 'Quarterly subscription with 15% discount on all orders', 799.99, 15.00, 90, '15% discount on all orders,No delivery charges,Priority ordering,Special event invitations', '2025-04-15 12:18:05', '2025-04-15 12:18:05'),
(3, 1, 'Ultimate Annual', 'Annual subscription with 20% discount on all orders', 2999.99, 20.00, 365, '20% discount on all orders,No delivery charges,Priority ordering,Special event invitations,Exclusive tastings', '2025-04-15 12:18:05', '2025-04-15 12:18:05');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_transactions`
--

CREATE TABLE `subscription_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','esewa') NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_transactions`
--

INSERT INTO `subscription_transactions` (`id`, `user_id`, `plan_id`, `amount`, `payment_method`, `status`, `created_at`) VALUES
(1, 3, 1, 299.99, 'cash', 'pending', '2025-06-20 22:24:22');

-- --------------------------------------------------------

--
-- Table structure for table `unit_conversions`
--

CREATE TABLE `unit_conversions` (
  `id` int(11) NOT NULL,
  `from_unit_id` int(11) NOT NULL,
  `to_unit_id` int(11) NOT NULL,
  `conversion_factor` decimal(15,6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unit_conversions`
--

INSERT INTO `unit_conversions` (`id`, `from_unit_id`, `to_unit_id`, `conversion_factor`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 1000.000000, '2025-04-17 02:56:25', '2025-04-17 02:56:25'),
(2, 2, 3, 1000.000000, '2025-04-17 02:56:25', '2025-04-17 02:56:25'),
(3, 4, 5, 1000.000000, '2025-04-17 02:56:25', '2025-04-17 02:56:25'),
(4, 7, 6, 12.000000, '2025-04-17 02:56:25', '2025-04-17 02:56:25');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `role` enum('admin','vendor','staff','student','worker') NOT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `contact_number`, `role`, `approval_status`, `password`, `profile_pic`, `created_at`) VALUES
(1, 'adminmain', 'adminmain@campus.com', '9800000000', 'admin', 'approved', '$2y$10$/r1BJQWWcHCa4vbA14ZcEOJwIVTVxK8ZyvXL2wbbdxT7DCas1Eyc2', 'admin_1_682b49544ec7a.jpg', '2025-04-14 12:50:29'),
(2, 'vendor', 'vendor@gmail.com', '9825346958', 'vendor', 'approved', '$2y$10$3mWTGhPx2LhBBjdqa5lDue6O5uIsNQwJ8sbn9hjFy8YwrZ6yX/52q', NULL, '2025-04-14 15:57:33'),
(3, 'student', 'student@gmail.com', '9825346908', 'student', 'approved', '$2y$10$5Vk3zLE5eTOF0mglmna6GO8I/CSAgo98w6HqZ4w2q.YWodc6wfdDO', '3_682b408aec71e.jpg', '2025-04-15 04:08:17'),
(4, 'staff', 'staff@gmail.com', '98256986588', 'staff', 'approved', '$2y$10$NwsLL6Wuo5TlwEYbGruoyu4Z9eEi2jfwhYVXwlOBqvbtMSm39zxpO', NULL, '2025-04-28 02:15:19'),
(5, 'waiter', 'waiter@gmail.com', '9825698655', 'worker', 'approved', '$2y$10$jATDHdrEnz0OzsMg9fR7tuKiUP8fEOW9RfW7LqkRqFZnNJ1erNNAC', NULL, '2025-04-28 15:53:20');

-- --------------------------------------------------------

--
-- Table structure for table `user_subscriptions`
--

CREATE TABLE `user_subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_subscriptions`
--

INSERT INTO `user_subscriptions` (`id`, `user_id`, `plan_id`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 1, '2025-06-20 22:24:22', '2025-07-20 22:24:22', 'active', '2025-06-20 22:24:22', '2025-06-20 22:24:22');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `opening_hours` varchar(255) DEFAULT NULL,
  `license_number` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `user_id`, `school_id`, `approval_status`, `opening_hours`, `license_number`) VALUES
(1, 2, 1, 'approved', '9:00 AM - 5:00 PM', '259546');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_credit_settings`
--

CREATE TABLE `vendor_credit_settings` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `default_student_credit_limit` decimal(10,2) NOT NULL DEFAULT 1000.00,
  `default_staff_credit_limit` decimal(10,2) NOT NULL DEFAULT 2000.00,
  `allow_credit_requests` tinyint(1) NOT NULL DEFAULT 1,
  `payment_terms` text DEFAULT NULL,
  `late_payment_policy` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_ingredients`
--

CREATE TABLE `vendor_ingredients` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `vendor_specific_name` varchar(255) DEFAULT NULL,
  `preferred_supplier` varchar(255) DEFAULT NULL,
  `reorder_point` decimal(10,2) DEFAULT NULL,
  `min_reorder_quantity` decimal(10,2) DEFAULT 0.00,
  `max_reorder_quantity` decimal(10,2) DEFAULT 0.00,
  `reorder_quantity` decimal(10,2) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `bulk_discount_info` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_ingredients`
--

INSERT INTO `vendor_ingredients` (`id`, `vendor_id`, `ingredient_id`, `vendor_specific_name`, `preferred_supplier`, `reorder_point`, `min_reorder_quantity`, `max_reorder_quantity`, `reorder_quantity`, `cost_per_unit`, `bulk_discount_info`, `created_at`) VALUES
(3, 1, 25, 'Doodh', 'Ramesh Supplyers', 20.00, 30.00, 60.00, NULL, 60.00, NULL, '2025-04-15 11:29:39'),
(4, 1, 23, 'Pyaz', 'Ramesh Supplyers', 10.00, 20.00, 30.00, NULL, 50.00, NULL, '2025-04-15 11:45:41'),
(5, 1, 25, NULL, 'Ramesh Supplyers', NULL, 0.00, 0.00, NULL, NULL, NULL, '2025-04-16 09:12:44'),
(6, 1, 25, NULL, 'Ramesh Supplyers', NULL, 0.00, 0.00, NULL, NULL, NULL, '2025-04-16 09:35:26'),
(7, 1, 25, NULL, 'Ramesh Supplyers', NULL, 0.00, 0.00, NULL, NULL, NULL, '2025-04-16 09:36:14'),
(8, 1, 22, NULL, 'Ram', NULL, 0.00, 0.00, NULL, 50.00, NULL, '2025-04-17 02:22:21');

-- --------------------------------------------------------

--
-- Table structure for table `workers`
--

CREATE TABLE `workers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `position` enum('kitchen_staff','waiter') NOT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workers`
--

INSERT INTO `workers` (`id`, `user_id`, `vendor_id`, `position`, `approval_status`) VALUES
(1, 5, 1, 'waiter', 'approved');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `batch_sequences`
--
ALTER TABLE `batch_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_batch_seq` (`vendor_id`,`ingredient_id`,`year`,`month`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `credit_accounts`
--
ALTER TABLE `credit_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `credit_account_requests`
--
ALTER TABLE `credit_account_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_vendor` (`user_id`,`vendor_id`),
  ADD KEY `fk_credit_requests_vendor_idx` (`vendor_id`);

--
-- Indexes for table `credit_transactions`
--
ALTER TABLE `credit_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `ingredient_id` (`ingredient_id`),
  ADD KEY `idx_batch` (`batch_number`);

--
-- Indexes for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `ingredient_id` (`ingredient_id`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `inventory_history`
--
ALTER TABLE `inventory_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `ingredient_id` (`ingredient_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `measurement_units`
--
ALTER TABLE `measurement_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_name` (`name`),
  ADD UNIQUE KEY `unique_abbreviation` (`abbreviation`);

--
-- Indexes for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `recipe_id` (`recipe_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `fk_orders_user_id` (`user_id`),
  ADD KEY `fk_orders_vendor_id` (`vendor_id`),
  ADD KEY `fk_orders_credit_account_id` (`credit_account_id`),
  ADD KEY `assigned_worker_id` (`assigned_worker_id`);

--
-- Indexes for table `order_assignments`
--
ALTER TABLE `order_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `worker_id` (`worker_id`);

--
-- Indexes for table `order_delivery_details`
--
ALTER TABLE `order_delivery_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `order_inventory_deductions`
--
ALTER TABLE `order_inventory_deductions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `order_notifications`
--
ALTER TABLE `order_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_delivery_details` (`delivery_details_id`);

--
-- Indexes for table `order_ratings`
--
ALTER TABLE `order_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_order_rating` (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `order_tracking_updated_by_fk` (`updated_by`);

--
-- Indexes for table `payment_history`
--
ALTER TABLE `payment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `prep_logs`
--
ALTER TABLE `prep_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `ingredient_id` (`ingredient_id`),
  ADD KEY `prepared_by` (`prepared_by`);

--
-- Indexes for table `production_batches`
--
ALTER TABLE `production_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch_number` (`batch_number`),
  ADD KEY `recipe_id` (`recipe_id`),
  ADD KEY `produced_by` (`produced_by`);

--
-- Indexes for table `production_batch_ingredients`
--
ALTER TABLE `production_batch_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- Indexes for table `production_inventory_deductions`
--
ALTER TABLE `production_inventory_deductions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `production_batch_id` (`production_batch_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `ingredient_id` (`ingredient_id`),
  ADD KEY `deducted_by` (`deducted_by`);

--
-- Indexes for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `recipes`
--
ALTER TABLE `recipes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipe_category` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `recipe_costs`
--
ALTER TABLE `recipe_costs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_recipe_date` (`recipe_id`,`calculation_date`);

--
-- Indexes for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipe_ingredient` (`recipe_id`,`ingredient_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_students`
--
ALTER TABLE `staff_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `subscription_transactions`
--
ALTER TABLE `subscription_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subscription_transactions_user_idx` (`user_id`),
  ADD KEY `fk_subscription_transactions_plan_idx` (`plan_id`),
  ADD KEY `idx_user_plan` (`user_id`,`plan_id`),
  ADD KEY `idx_status_date` (`status`,`created_at`);

--
-- Indexes for table `unit_conversions`
--
ALTER TABLE `unit_conversions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_conversion` (`from_unit_id`,`to_unit_id`),
  ADD KEY `to_unit_id` (`to_unit_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_subscriptions_user_idx` (`user_id`),
  ADD KEY `fk_user_subscriptions_plan_idx` (`plan_id`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `school_id` (`school_id`);

--
-- Indexes for table `vendor_credit_settings`
--
ALTER TABLE `vendor_credit_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `vendor_ingredients`
--
ALTER TABLE `vendor_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `ingredient_id` (`ingredient_id`);

--
-- Indexes for table `workers`
--
ALTER TABLE `workers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `batch_sequences`
--
ALTER TABLE `batch_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `credit_accounts`
--
ALTER TABLE `credit_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `credit_account_requests`
--
ALTER TABLE `credit_account_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `credit_transactions`
--
ALTER TABLE `credit_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ingredients`
--
ALTER TABLE `ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `inventory_history`
--
ALTER TABLE `inventory_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `measurement_units`
--
ALTER TABLE `measurement_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `menu_categories`
--
ALTER TABLE `menu_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `order_assignments`
--
ALTER TABLE `order_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `order_delivery_details`
--
ALTER TABLE `order_delivery_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `order_inventory_deductions`
--
ALTER TABLE `order_inventory_deductions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `order_notifications`
--
ALTER TABLE `order_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_ratings`
--
ALTER TABLE `order_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_tracking`
--
ALTER TABLE `order_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT for table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `prep_logs`
--
ALTER TABLE `prep_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_batches`
--
ALTER TABLE `production_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `production_batch_ingredients`
--
ALTER TABLE `production_batch_ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `production_inventory_deductions`
--
ALTER TABLE `production_inventory_deductions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `recipes`
--
ALTER TABLE `recipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `recipe_costs`
--
ALTER TABLE `recipe_costs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `staff_students`
--
ALTER TABLE `staff_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subscription_transactions`
--
ALTER TABLE `subscription_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `unit_conversions`
--
ALTER TABLE `unit_conversions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendor_credit_settings`
--
ALTER TABLE `vendor_credit_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_ingredients`
--
ALTER TABLE `vendor_ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `workers`
--
ALTER TABLE `workers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `batch_sequences`
--
ALTER TABLE `batch_sequences`
  ADD CONSTRAINT `batch_sequences_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `batch_sequences_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `credit_accounts`
--
ALTER TABLE `credit_accounts`
  ADD CONSTRAINT `credit_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `credit_accounts_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `credit_account_requests`
--
ALTER TABLE `credit_account_requests`
  ADD CONSTRAINT `fk_credit_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_credit_requests_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `credit_transactions`
--
ALTER TABLE `credit_transactions`
  ADD CONSTRAINT `credit_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `credit_transactions_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `credit_transactions_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD CONSTRAINT `ingredients_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `ingredients_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `ingredients_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- Constraints for table `inventory_alerts`
--
ALTER TABLE `inventory_alerts`
  ADD CONSTRAINT `inventory_alerts_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `inventory_alerts_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `inventory_alerts_ibfk_3` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory_history`
--
ALTER TABLE `inventory_history`
  ADD CONSTRAINT `inventory_history_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `inventory_history_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `inventory_history_ibfk_3` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `inventory_history_ibfk_4` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `menu_categories`
--
ALTER TABLE `menu_categories`
  ADD CONSTRAINT `menu_categories_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `menu_items_ibfk_3` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_credit_account_id` FOREIGN KEY (`credit_account_id`) REFERENCES `credit_accounts` (`id`),
  ADD CONSTRAINT `fk_orders_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_orders_vendor_id` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`assigned_worker_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_assignments`
--
ALTER TABLE `order_assignments`
  ADD CONSTRAINT `order_assignments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_assignments_ibfk_2` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_delivery_details`
--
ALTER TABLE `order_delivery_details`
  ADD CONSTRAINT `order_delivery_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_inventory_deductions`
--
ALTER TABLE `order_inventory_deductions`
  ADD CONSTRAINT `order_inventory_deductions_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_inventory_deductions_ibfk_2` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`),
  ADD CONSTRAINT `order_inventory_deductions_ibfk_3` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `order_inventory_deductions_ibfk_4` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_notifications`
--
ALTER TABLE `order_notifications`
  ADD CONSTRAINT `fk_delivery_details` FOREIGN KEY (`delivery_details_id`) REFERENCES `order_delivery_details` (`id`),
  ADD CONSTRAINT `order_notifications_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_ratings`
--
ALTER TABLE `order_ratings`
  ADD CONSTRAINT `order_ratings_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_ratings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD CONSTRAINT `order_tracking_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_tracking_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payment_history`
--
ALTER TABLE `payment_history`
  ADD CONSTRAINT `payment_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_history_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `prep_logs`
--
ALTER TABLE `prep_logs`
  ADD CONSTRAINT `prep_logs_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `prep_logs_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `prep_logs_ibfk_3` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `production_batches`
--
ALTER TABLE `production_batches`
  ADD CONSTRAINT `production_batches_ibfk_1` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`),
  ADD CONSTRAINT `production_batches_ibfk_2` FOREIGN KEY (`produced_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `production_batch_ingredients`
--
ALTER TABLE `production_batch_ingredients`
  ADD CONSTRAINT `production_batch_ingredients_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `production_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `production_batch_ingredients_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- Constraints for table `production_inventory_deductions`
--
ALTER TABLE `production_inventory_deductions`
  ADD CONSTRAINT `production_inventory_deductions_ibfk_1` FOREIGN KEY (`production_batch_id`) REFERENCES `production_batches` (`id`),
  ADD CONSTRAINT `production_inventory_deductions_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `production_inventory_deductions_ibfk_3` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `production_inventory_deductions_ibfk_4` FOREIGN KEY (`deducted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD CONSTRAINT `qr_codes_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recipes`
--
ALTER TABLE `recipes`
  ADD CONSTRAINT `recipes_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `recipes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `recipes_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `recipe_costs`
--
ALTER TABLE `recipe_costs`
  ADD CONSTRAINT `recipe_costs_ibfk_1` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`);

--
-- Constraints for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD CONSTRAINT `recipe_ingredients_ibfk_1` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recipe_ingredients_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- Constraints for table `staff_students`
--
ALTER TABLE `staff_students`
  ADD CONSTRAINT `staff_students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_students_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD CONSTRAINT `subscription_plans_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`);

--
-- Constraints for table `subscription_transactions`
--
ALTER TABLE `subscription_transactions`
  ADD CONSTRAINT `fk_subscription_transactions_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subscription_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `unit_conversions`
--
ALTER TABLE `unit_conversions`
  ADD CONSTRAINT `unit_conversions_ibfk_1` FOREIGN KEY (`from_unit_id`) REFERENCES `measurement_units` (`id`),
  ADD CONSTRAINT `unit_conversions_ibfk_2` FOREIGN KEY (`to_unit_id`) REFERENCES `measurement_units` (`id`);

--
-- Constraints for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD CONSTRAINT `fk_user_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendors`
--
ALTER TABLE `vendors`
  ADD CONSTRAINT `vendors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendors_ibfk_2` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_credit_settings`
--
ALTER TABLE `vendor_credit_settings`
  ADD CONSTRAINT `fk_vendor_credit_settings_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_ingredients`
--
ALTER TABLE `vendor_ingredients`
  ADD CONSTRAINT `vendor_ingredients_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `vendor_ingredients_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- Constraints for table `workers`
--
ALTER TABLE `workers`
  ADD CONSTRAINT `workers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `workers_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
