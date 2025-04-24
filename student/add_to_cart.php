<?php
session_start();
require_once '../connection/db_connection.php';

// Return JSON response
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get and validate input
$menu_item_id = $_POST['menu_item_id'] ?? null;
$quantity = $_POST['quantity'] ?? 1;

if (!$menu_item_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Menu item ID is required']);
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Check if item exists and is available
    $stmt = $conn->prepare("
        SELECT item_id, vendor_id, is_available 
        FROM menu_items 
        WHERE item_id = ?
    ");
    $stmt->execute([$menu_item_id]);
    $menu_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$menu_item) {
        throw new Exception('Menu item not found');
    }

    if (!$menu_item['is_available']) {
        throw new Exception('This item is currently not available');
    }

    // Check if user already has this item in cart
    $stmt = $conn->prepare("
        SELECT id, quantity 
        FROM cart_items 
        WHERE user_id = ? AND menu_item_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $menu_item_id]);
    $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_item) {
        // Update quantity if item exists
        $stmt = $conn->prepare("
            UPDATE cart_items 
            SET quantity = quantity + ?, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$quantity, $existing_item['id']]);
    } else {
        // Add new item to cart
        $stmt = $conn->prepare("
            INSERT INTO cart_items (
                user_id, menu_item_id, quantity
            ) VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $menu_item_id,
            $quantity
        ]);
    }

    // Commit transaction
    $conn->commit();

    // Get updated cart count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM cart_items 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart successfully',
        'cart_count' => $cart_count
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 