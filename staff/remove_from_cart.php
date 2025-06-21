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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['item_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Item ID is required']);
    exit();
}

$item_id = $input['item_id'];

try {
    // Delete cart item
    $stmt = $conn->prepare("
        DELETE FROM cart_items 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$item_id, $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Item not found in cart');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Item removed from cart'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 