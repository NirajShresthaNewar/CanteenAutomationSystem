<?php
require_once __DIR__ . '/../config.php';

try {
    $email = 'student@gmail.com';
    
    // Check user table
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "User not found in users table\n";
        exit();
    }
    
    echo "User found:\n";
    echo json_encode($user, JSON_PRETTY_PRINT) . "\n\n";
    
    // Check staff_students table
    $stmt = $conn->prepare("
        SELECT ss.*, s.name as school_name 
        FROM staff_students ss 
        JOIN schools s ON ss.school_id = s.id 
        WHERE ss.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $staffStudent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staffStudent) {
        echo "User not found in staff_students table\n";
    } else {
        echo "Staff/Student record found:\n";
        echo json_encode($staffStudent, JSON_PRETTY_PRINT) . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 