<?php
require_once __DIR__ . '/../config.php';

// Enable error logging
error_log("Login attempt started");

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Method not allowed: " . $_SERVER['REQUEST_METHOD']);
    sendResponse('error', 'Method not allowed', null);
}

// Get raw POST data and decode JSON
$raw_data = file_get_contents('php://input');
if (!$raw_data) {
    error_log("No data received");
    sendResponse('error', 'No data received', null);
}

$data = json_decode($raw_data, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Invalid JSON data: " . json_last_error_msg());
    sendResponse('error', 'Invalid JSON data: ' . json_last_error_msg(), null);
}

if (!isset($data['email']) || !isset($data['password'])) {
    error_log("Missing email or password");
    sendResponse('error', 'Email and password are required', null);
}

$email = $data['email'];
$password = $data['password'];

error_log("Login attempt for email: " . $email);

try {
    // Get student user info with school details
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.username,
            u.email,
            u.password,
            u.contact_number,
            u.role,
            u.approval_status,
            u.profile_pic,
            ss.school_id,
            s.name as school_name
        FROM users u
        LEFT JOIN staff_students ss ON u.id = ss.user_id
        LEFT JOIN schools s ON ss.school_id = s.id
        WHERE u.email = ?
        AND u.role = 'student'
    ");
    
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("User not found or not a student: " . $email);
        sendResponse('error', 'Invalid credentials', null);
    }

    error_log("User found: " . json_encode($user));

    // Verify password
    if (!password_verify($password, $user['password'])) {
        error_log("Password verification failed for user: " . $email);
        sendResponse('error', 'Invalid credentials', null);
    }

    error_log("Password verified successfully");

    // Check approval status
    if ($user['approval_status'] !== 'approved') {
        error_log("User not approved: " . $email);
        sendResponse('error', 'Your account is not approved yet', null);
    }

    // Generate token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

    // Create auth_tokens table if it doesn't exist
    $conn->exec("
        CREATE TABLE IF NOT EXISTS auth_tokens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Store token in database
    $stmt = $conn->prepare("
        INSERT INTO auth_tokens (user_id, token, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $token, $expires_at]);

    // Prepare response data
    $responseData = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'contact_number' => $user['contact_number'],
        'role' => $user['role'],
        'approval_status' => $user['approval_status'],
        'school_id' => $user['school_id'],
        'school_name' => $user['school_name'],
        'profile_pic' => $user['profile_pic'],
        'token' => $token,
        'expires_at' => $expires_at
    ];

    error_log("Login successful for user: " . $email);
    sendResponse('success', 'Login successful', $responseData);
} catch (PDOException $e) {
    error_log("Database error in login.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendResponse('error', 'Database error occurred', null);
} catch (Exception $e) {
    error_log("General error in login.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendResponse('error', 'An error occurred', null);
}
?> 