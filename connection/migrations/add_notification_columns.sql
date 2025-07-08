ALTER TABLE `notifications`
ADD COLUMN `type` enum('order_placed','order_accepted','order_ready','order_completed','order_cancelled') DEFAULT NULL AFTER `message`,
ADD COLUMN `link` varchar(255) DEFAULT NULL AFTER `type`; 