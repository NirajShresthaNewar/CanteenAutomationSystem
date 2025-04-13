-- Alter vendor_credit_settings table
ALTER TABLE `vendor_credit_settings`
    MODIFY COLUMN `default_credit_limit` decimal(10,2) NOT NULL DEFAULT 1000.00,
    ADD COLUMN `default_staff_credit_limit` decimal(10,2) NOT NULL DEFAULT 2000.00 AFTER `default_credit_limit`,
    RENAME COLUMN `default_credit_limit` TO `default_student_credit_limit`,
    RENAME COLUMN `allow_student_credit_requests` TO `allow_credit_requests`;

-- Alter credit_accounts table
ALTER TABLE `credit_accounts`
    ADD COLUMN `account_type` enum('student','staff') NOT NULL AFTER `vendor_id`,
    MODIFY COLUMN `status` enum('active','blocked','pending') NOT NULL DEFAULT 'pending',
    ADD COLUMN `updated_at` datetime NOT NULL AFTER `created_at`;

-- Alter credit_transactions table
ALTER TABLE `credit_transactions`
    MODIFY COLUMN `payment_method` enum('cash','esewa','credit') DEFAULT NULL,
    MODIFY COLUMN `created_at` datetime NOT NULL;

-- Alter credit_account_requests table
ALTER TABLE `credit_account_requests`
    ADD COLUMN `account_type` enum('student','staff') NOT NULL AFTER `vendor_id`,
    ADD COLUMN `updated_at` datetime NOT NULL AFTER `created_at`,
    DROP COLUMN `admin_notes`;

-- Create subscription_plans table
CREATE TABLE IF NOT EXISTS `subscription_plans` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `vendor_id` int(11) NOT NULL,
    `name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `price` decimal(10,2) NOT NULL,
    `duration_days` int(11) NOT NULL,
    `features` text DEFAULT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk_subscription_plans_vendor_idx` (`vendor_id`),
    CONSTRAINT `fk_subscription_plans_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create user_subscriptions table
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

-- Create subscription_transactions table
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

-- Insert default subscription plans
INSERT INTO `subscription_plans` (`name`, `description`, `price`, `duration_days`, `features`, `created_at`, `updated_at`) VALUES
('Monthly Basic', 'Basic monthly meal plan with standard benefits', 2000.00, 30, '{"daily_meals": 2, "priority_service": false, "discount": 5}', NOW(), NOW()),
('Monthly Premium', 'Premium monthly meal plan with enhanced benefits', 3500.00, 30, '{"daily_meals": 3, "priority_service": true, "discount": 10}', NOW(), NOW()),
('Quarterly Basic', 'Basic quarterly meal plan with standard benefits', 5500.00, 90, '{"daily_meals": 2, "priority_service": false, "discount": 8}', NOW(), NOW()),
('Quarterly Premium', 'Premium quarterly meal plan with enhanced benefits', 9500.00, 90, '{"daily_meals": 3, "priority_service": true, "discount": 15}', NOW(), NOW()); 