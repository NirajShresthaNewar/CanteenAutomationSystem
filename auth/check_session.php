<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    echo json_encode([
        'success' => true,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username']
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired or invalid'
    ]);
} 