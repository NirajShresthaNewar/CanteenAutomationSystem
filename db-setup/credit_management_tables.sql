-- Credit Account Management Tables

-- Vendor credit settings
CREATE TABLE IF NOT EXISTS `vendor_credit_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `default_student_credit_limit` decimal(10,2) NOT NULL DEFAULT 1000.00,
  `default_staff_credit_limit` decimal(10,2) NOT NULL DEFAULT 2000.00,
  `allow_credit_requests` tinyint(1) NOT NULL DEFAULT 1,
  `payment_terms` text DEFAULT NULL,
  `late_payment_policy` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_id` (`vendor_id`),
  CONSTRAINT `fk_vendor_credit_settings_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Credit accounts for students and staff
CREATE TABLE IF NOT EXISTS `credit_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `account_type` enum('student','staff') NOT NULL,
  `credit_limit` decimal(10,2) NOT NULL,
  `current_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','blocked','pending') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_vendor` (`user_id`,`vendor_id`),
  KEY `fk_credit_accounts_vendor_idx` (`vendor_id`),
  CONSTRAINT `fk_credit_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_credit_accounts_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Credit transactions
CREATE TABLE IF NOT EXISTS `credit_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_type` enum('purchase','repayment') NOT NULL,
  `payment_method` enum('cash','esewa','credit') DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_credit_transactions_user_idx` (`user_id`),
  KEY `fk_credit_transactions_vendor_idx` (`vendor_id`),
  KEY `fk_credit_transactions_order_idx` (`order_id`),
  CONSTRAINT `fk_credit_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_credit_transactions_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_credit_transactions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Credit account requests
CREATE TABLE IF NOT EXISTS `credit_account_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `account_type` enum('student','staff') NOT NULL,
  `requested_limit` decimal(10,2) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_vendor` (`user_id`,`vendor_id`),
  KEY `fk_credit_requests_vendor_idx` (`vendor_id`),
  CONSTRAINT `fk_credit_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_credit_requests_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subscription plans
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `features` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User subscriptions
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_user_subscriptions_user_idx` (`user_id`),
  KEY `fk_user_subscriptions_plan_idx` (`plan_id`),
  CONSTRAINT `fk_user_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subscription transactions
CREATE TABLE IF NOT EXISTS `subscription_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','esewa') NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_subscription_transactions_user_idx` (`user_id`),
  KEY `fk_subscription_transactions_plan_idx` (`plan_id`),
  CONSTRAINT `fk_subscription_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscription_transactions_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alter orders table to add payment method
ALTER TABLE `orders` 
ADD COLUMN `payment_method` enum('cash','esewa','credit') NOT NULL DEFAULT 'cash' AFTER `status`,
ADD COLUMN `credit_account_id` int(11) DEFAULT NULL AFTER `payment_method`,
ADD KEY `fk_orders_credit_account_idx` (`credit_account_id`),
ADD CONSTRAINT `fk_orders_credit_account` FOREIGN KEY (`credit_account_id`) REFERENCES `credit_accounts` (`id`) ON DELETE SET NULL; 