<?php
require_once '../../connection/db_connection.php';

try {
    // Create auth_tokens table
    $sql = "CREATE TABLE IF NOT EXISTS auth_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_token (token)
    )";
    
    $conn->exec($sql);
    echo "Auth tokens table created successfully";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 