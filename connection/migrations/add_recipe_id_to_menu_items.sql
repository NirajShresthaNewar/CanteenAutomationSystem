-- Add recipe_id column if it doesn't exist
ALTER TABLE `menu_items`
ADD COLUMN IF NOT EXISTS `recipe_id` int(11) DEFAULT NULL AFTER `category_id`;

-- Add foreign key if it doesn't exist
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'menu_items'
    AND COLUMN_NAME = 'recipe_id'
    AND REFERENCED_TABLE_NAME = 'recipes'
);

SET @sql = IF(
    @constraint_exists = 0,
    'ALTER TABLE `menu_items` 
     ADD KEY `recipe_id` (`recipe_id`),
     ADD CONSTRAINT `menu_items_ibfk_3` 
     FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) 
     ON DELETE SET NULL',
    'SELECT "Foreign key already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 