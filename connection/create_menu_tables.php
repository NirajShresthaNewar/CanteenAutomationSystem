<?php
require_once 'db_connection.php';

try {
    // Create menu_categories table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS menu_categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT NOT NULL,
            name VARCHAR(50) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
        )
    ");

    // Create menu_items table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS menu_items (
            item_id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT NOT NULL,
            category_id INT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            image_path VARCHAR(255),
            is_vegetarian BOOLEAN DEFAULT FALSE,
            is_vegan BOOLEAN DEFAULT FALSE,
            is_gluten_free BOOLEAN DEFAULT FALSE,
            is_available BOOLEAN DEFAULT TRUE,
            availability_start DATE,
            availability_end DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES menu_categories(category_id) ON DELETE SET NULL
        )
    ");

    echo "Menu tables created successfully!";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?> 