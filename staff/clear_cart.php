<?php
session_start();
require_once '../connection/db_connection.php';

// Return JSON response
header('Content-Type: application/json');

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    // Delete all cart items for the user
    $stmt = $conn->prepare("
        DELETE FROM cart_items 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Cart cleared successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 