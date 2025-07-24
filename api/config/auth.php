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
        $database = new Database();
        $db = $database->connect();
        
        $stmt = $db->prepare("
            SELECT u.id as user_id, u.role 
            FROM auth_tokens a
            JOIN users u ON a.user_id = u.id
            WHERE a.token = ? AND a.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        
        error_log("Token verification result: " . ($result ? json_encode($result) : 'null'));
        return $result;
    } catch (Exception $e) {
        error_log("Token verification error: " . $e->getMessage());
        return null;
    }
} 