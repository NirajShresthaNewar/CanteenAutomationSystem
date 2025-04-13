<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../connection/db_connection.php';

// Function to generate JSON response
function sendResponse($status, $message, $data = null) {
    http_response_code($status === 'success' ? 200 : 400);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Function to verify JWT token
function verifyToken() {
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        sendResponse('error', 'No token provided', null);
    }

    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    
    try {
        global $conn;
        $stmt = $conn->prepare("SELECT user_id, role FROM auth_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            sendResponse('error', 'Invalid or expired token', null);
        }
        
        return $result;
    } catch (Exception $e) {
        sendResponse('error', 'Token verification failed', null);
    }
}
?> 