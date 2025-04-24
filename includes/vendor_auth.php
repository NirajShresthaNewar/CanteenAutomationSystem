<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../auth/login.php');
    exit();
}

// Get vendor ID from session or fetch from database if not set
if (!isset($_SESSION['vendor_id'])) {
    require_once dirname(__FILE__) . '/../connection/db_connection.php';
    
    try {
        $stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vendor) {
            $_SESSION['vendor_id'] = $vendor['id'];
        } else {
            // If no vendor record found, redirect to error page
            header('Location: ../auth/error.php?message=No vendor account found');
            exit();
        }
    } catch (PDOException $e) {
        // Log error and redirect to error page
        error_log("Vendor auth error: " . $e->getMessage());
        header('Location: ../auth/error.php?message=Database error');
        exit();
    }
}

// Define vendor-specific constants
define('VENDOR_ID', $_SESSION['vendor_id']);
?> 