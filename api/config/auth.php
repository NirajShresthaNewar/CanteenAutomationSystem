<?php
require_once 'database.php';

function getAuthorizationHeader() {
    $headers = null;
    
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(
            array_map('strtolower', array_keys($requestHeaders)),
            array_values($requestHeaders)
        );
        if (isset($requestHeaders['authorization'])) {
            $headers = trim($requestHeaders['authorization']);
        }
    }
    
    error_log("Found Authorization header: " . ($headers ? $headers : 'none'));
    return $headers;
}

function getBearerToken() {
    $headers = getAuthorizationHeader();
    
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            error_log("Extracted Bearer token: " . $matches[1]);
            return $matches[1];
        }
    }
    error_log("No Bearer token found in headers");
    return null;
}

function verifyToken($token) {
    try {
        error_log("Attempting to verify token: " . $token);
        
        $database = new Database();
        $db = $database->connect();
        
        // Get token from auth_tokens table
        $query = "SELECT user_id, expires_at FROM auth_tokens WHERE token = ? AND expires_at > NOW()";
        $stmt = $db->prepare($query);
        $stmt->execute([$token]);
        
        error_log("Token query result count: " . $stmt->rowCount());
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Token verified for user_id: " . $result['user_id']);
            return (object)[
                'user_id' => $result['user_id'],
                'expires_at' => $result['expires_at']
            ];
        }
        
        error_log("Token verification failed - no matching valid token found");
        return false;
    } catch (Exception $e) {
        error_log("Token verification error: " . $e->getMessage());
        return false;
    }
} 