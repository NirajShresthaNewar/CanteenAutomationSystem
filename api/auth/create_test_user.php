<?php
require_once __DIR__ . '/../config.php';

try {
    // First, create a school
    $stmt = $conn->prepare("INSERT INTO schools (name, address) VALUES (?, ?)");
    $stmt->execute(['Test School', '123 Test Street']);
    $schoolId = $conn->lastInsertId();

    // Create a user with hashed password
    $hashedPassword = password_hash('Admin@1213', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, contact_number, role, approval_status, password)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'Test Student',
        'student@gmail.com',
        '1234567890',
        'student',
        'approved',
        $hashedPassword
    ]);
    $userId = $conn->lastInsertId();

    // Link user to school in staff_students table
    $stmt = $conn->prepare("
        INSERT INTO staff_students (user_id, school_id, role, approval_status)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $schoolId, 'student', 'approved']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Test user created successfully',
        'data' => [
            'email' => 'student@gmail.com',
            'password' => 'Admin@1213'
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error creating test user: ' . $e->getMessage()
    ]);
}
?> 