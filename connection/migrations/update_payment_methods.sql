-- Update orders table payment_method enum
ALTER TABLE `orders` 
MODIFY COLUMN `payment_method` enum('cash','khalti','credit') DEFAULT 'cash';

-- Update credit_transactions table payment_method enum
ALTER TABLE `credit_transactions` 
MODIFY COLUMN `payment_method` enum('cash','khalti','credit') DEFAULT NULL; 