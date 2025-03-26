<?php
require_once 'db_connection.php';

$username = 'adminmain';
$email = 'adminmain@campus.com';
$contact_number = '9800000000'; // Added required contact number
$password = 'Admin@123'; // Change this to a secure password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO users (
        username, 
        email, 
        contact_number,
        password, 
        role,
        approval_status
    ) VALUES (?, ?, ?, ?, 'admin', 'approved')");
    
    $stmt->execute([
        $username, 
        $email, 
        $contact_number,
        $hashedPassword
    ]);
    
    echo "Admin user created successfully!";
} catch (PDOException $e) {
    echo "Error creating admin user: " . $e->getMessage();
}
?>