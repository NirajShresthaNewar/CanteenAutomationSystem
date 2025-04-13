<?php
require_once 'database.php';

function verifyToken($token) {
    try {
        $database = new Database();
        $db = $database->connect();
        
        // Get token from auth_tokens table
        $query = "SELECT user_id, expires_at FROM auth_tokens WHERE token = ? AND expires_at > NOW()";
        $stmt = $db->prepare($query);
        $stmt->execute([$token]);
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (object)[
                'user_id' => $result['user_id'],
                'expires_at' => $result['expires_at']
            ];
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
} 