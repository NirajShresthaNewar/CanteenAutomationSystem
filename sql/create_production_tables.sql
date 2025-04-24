-- Create production_batches table
CREATE TABLE IF NOT EXISTS `production_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_number` varchar(50) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `production_date` date NOT NULL,
  `quantity_produced` int(11) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `produced_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_number` (`batch_number`),
  KEY `recipe_id` (`recipe_id`),
  KEY `produced_by` (`produced_by`),
  CONSTRAINT `production_batches_ibfk_1` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_batches_ibfk_2` FOREIGN KEY (`produced_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create production_batch_ingredients table
CREATE TABLE IF NOT EXISTS `production_batch_ingredients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `quantity_used` decimal(10,3) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `batch_id` (`batch_id`),
  KEY `ingredient_id` (`ingredient_id`),
  CONSTRAINT `production_batch_ingredients_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `production_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_batch_ingredients_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 