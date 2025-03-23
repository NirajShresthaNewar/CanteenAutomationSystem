<?php
require_once 'db_connection.php';

try {
    // Check if admin already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['niraj@canteen.com']);
    
    if ($stmt->rowCount() == 0) {
        // Create admin user
        $name = 'Admin User';
        $email = 'niraj@canteen.com';
        $password = password_hash('Admin@123', PASSWORD_DEFAULT);
        $role = 'admin';
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $role]);
        
        echo "Admin user created successfully!<br>";
        echo "Email: niraj@canteen.com<br>";
        echo "Password: Admin@123";
    } else {
        echo "Admin user already exists!";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 