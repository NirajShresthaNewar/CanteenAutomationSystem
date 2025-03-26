<?php
require_once 'db_connection.php';

$username = 'adminmain';
$email = 'adminmain@campus.com';
$password = 'Admin@123'; // Change this to a secure password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO users (username, email, address, password, role, verified) 
                           VALUES (?, ?, ?, ?, 'admin', 1)");
    $stmt->execute([$username, $email, 'Campus Admin Office', $hashedPassword]);
    echo "Admin user created successfully!";
} catch (PDOException $e) {
    echo "Error creating admin user: " . $e->getMessage();
}
?>