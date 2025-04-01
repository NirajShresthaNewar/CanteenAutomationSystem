<?php
session_start();
require_once 'connection/db_connection.php';

echo "<h2>Testing Student Login:</h2>";

$email = "student@gmail.com";
$password = "test123"; // This is a guess, replace with actual password if needed

// Check user exists and is approved
$stmt = $conn->prepare("
    SELECT u.*, 
        CASE 
            WHEN u.role = 'vendor' THEN v.approval_status
            WHEN u.role IN ('staff', 'student') THEN ss.approval_status
            WHEN u.role = 'worker' THEN w.approval_status
            ELSE u.approval_status
        END as final_approval_status
    FROM users u
    LEFT JOIN vendors v ON u.id = v.user_id AND u.role = 'vendor'
    LEFT JOIN staff_students ss ON u.id = ss.user_id AND u.role IN ('staff', 'student')
    LEFT JOIN workers w ON u.id = w.user_id AND u.role = 'worker'
    WHERE u.email = ?
");

$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>User Data:</h3>";
echo "<pre>";
print_r($user);
echo "</pre>";

if (!$user) {
    echo "<p>Error: User not found</p>";
} else {
    echo "<p>User found</p>";
    
    // Check if password would be verified (without needing to know it)
    echo "<p>Password hash in database: " . $user['password'] . "</p>";
    echo "<p>Test if 'test123' matches: " . (password_verify('test123', $user['password']) ? 'Yes' : 'No') . "</p>";
    
    // Check approval status
    echo "<p>Approval status: " . $user['final_approval_status'] . "</p>";
    
    if ($user['final_approval_status'] !== 'approved') {
        echo "<p>Error: Account is not approved</p>";
    } else {
        echo "<p>Account is approved</p>";
    }
    
    // Check role
    echo "<p>Role: " . $user['role'] . "</p>";
    
    if ($user['role'] !== 'student') {
        echo "<p>Error: Account is not a student account</p>";
    } else {
        echo "<p>Account is a student account</p>";
    }
}
?> 