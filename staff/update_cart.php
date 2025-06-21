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

// Check required parameters
if (!isset($_POST['cart_id']) || !isset($_POST['quantity'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Cart ID and quantity are required']);
    exit();
}

$cart_id = $_POST['cart_id'];
$new_quantity = (int)$_POST['quantity'];

try {
    $conn->beginTransaction();

    // Get current cart item
    $stmt = $conn->prepare("
        SELECT ci.*, mi.price 
        FROM cart_items ci
        JOIN menu_items mi ON ci.menu_item_id = mi.item_id
        WHERE ci.id = ? AND ci.user_id = ?
    ");
    $stmt->execute([$cart_id, $_SESSION['user_id']]);
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart_item) {
        throw new Exception('Cart item not found');
    }

    // Validate new quantity
    if ($new_quantity < 1) {
        throw new Exception('Quantity cannot be less than 1');
    }

    // Update quantity
    $stmt = $conn->prepare("
        UPDATE cart_items 
        SET quantity = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$new_quantity, $cart_id, $_SESSION['user_id']]);

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Cart updated successfully',
        'new_quantity' => $new_quantity,
        'new_subtotal' => $new_quantity * $cart_item['price']
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 