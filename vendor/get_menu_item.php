<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    http_response_code(403);
    exit('Unauthorized');
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

// Check if item_id is provided
if (!isset($_GET['item_id'])) {
    http_response_code(400);
    exit('Item ID is required');
}

try {
    // Get menu item details
    $stmt = $conn->prepare("
        SELECT * FROM menu_items 
        WHERE item_id = ? AND vendor_id = ?
    ");
    $stmt->execute([$_GET['item_id'], $vendor_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        exit('Menu item not found or unauthorized');
    }

    // Return item details as JSON
    header('Content-Type: application/json');
    echo json_encode($item);
} catch (Exception $e) {
    http_response_code(500);
    exit('Error fetching menu item details');
} 