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
        SELECT mi.item_id, mi.vendor_id, mi.is_available, v.school_id
        FROM menu_items mi
        JOIN vendors v ON mi.vendor_id = v.id
        WHERE mi.item_id = ?
    ");
    $stmt->execute([$menu_item_id]);
    $menu_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$menu_item) {
        throw new Exception('Menu item not found');
    }

    if (!$menu_item['is_available']) {
        throw new Exception('This item is currently not available');
    }

    // Check if user has access to this vendor (same school)
    $stmt = $conn->prepare("
        SELECT school_id 
        FROM staff_students 
        WHERE user_id = ? AND role = 'staff'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_school = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_school || $user_school['school_id'] != $menu_item['school_id']) {
        throw new Exception('You do not have access to this vendor');
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
        
        $message = 'Item quantity updated in cart';
    } else {
        // Add new item to cart
        $stmt = $conn->prepare("
            INSERT INTO cart_items 
            (user_id, menu_item_id, quantity, created_at, updated_at) 
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$_SESSION['user_id'], $menu_item_id, $quantity]);
        
        $message = 'Item added to cart';
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 