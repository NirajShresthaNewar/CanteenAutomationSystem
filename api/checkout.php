<?php
header('Content-Type: application/json');
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit();
}

// Validate required fields
if (!isset($data['vendor_id'], $data['payment_method'], $data['order_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$vendor_id = $data['vendor_id'];
$payment_method = $data['payment_method'];
$order_type = $data['order_type'];

// Validate delivery details based on order type
if ($order_type === 'delivery') {
    if (empty($data['delivery_location']) || empty($data['contact_number'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Delivery location and contact number are required for delivery orders']);
        exit();
    }
}

if ($order_type === 'dine_in' && empty($data['table_number'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Table number is required for dine-in orders']);
    exit();
}

try {
    // Get cart items for this vendor
    $stmt = $conn->prepare("
        SELECT ci.*, mi.name, mi.price
        FROM cart_items ci
        JOIN menu_items mi ON ci.menu_item_id = mi.item_id
        WHERE ci.user_id = ? AND mi.vendor_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        throw new Exception("Your cart is empty.");
    }

    // Calculate total amount
    $total_amount = 0;
    foreach ($cart_items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }

    // Get student ID
    $stmt = $conn->prepare("SELECT id FROM staff_students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student record not found.");
    }

    // Start transaction
    $conn->beginTransaction();

    // Generate receipt number
    $receipt_number = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

    // Create order
    $stmt = $conn->prepare("
        INSERT INTO orders (
            receipt_number, user_id, customer_id, vendor_id,
            total_amount, payment_method, payment_status,
            order_type
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?,
            ?
        )
    ");
    $stmt->execute([
        $receipt_number,
        $_SESSION['user_id'],
        $student['id'],
        $vendor_id,
        $total_amount,
        $payment_method,
        'pending',
        $order_type
    ]);

    $order_id = $conn->lastInsertId();

    // Insert order delivery details
    $stmt = $conn->prepare("
        INSERT INTO order_delivery_details (
            order_id,
            order_type,
            table_number,
            delivery_location,
            building_name,
            floor_number,
            room_number,
            delivery_instructions,
            contact_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $order_id,
        $order_type,
        $data['table_number'] ?? null,
        $data['delivery_location'] ?? null,
        $data['building_name'] ?? null,
        $data['floor_number'] ?? null,
        $data['room_number'] ?? null,
        $data['delivery_instructions'] ?? null,
        $data['contact_number'] ?? null
    ]);

    // Create order items
    $stmt = $conn->prepare("
        INSERT INTO order_items (
            order_id, menu_item_id, quantity,
            unit_price, subtotal
        ) VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($cart_items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $stmt->execute([
            $order_id,
            $item['menu_item_id'],
            $item['quantity'],
            $item['price'],
            $subtotal
        ]);
    }

    // Clear cart items for this vendor
    $stmt = $conn->prepare("
        DELETE FROM cart_items 
        WHERE user_id = ? 
        AND menu_item_id IN (
            SELECT item_id 
            FROM menu_items 
            WHERE vendor_id = ?
        )
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);

    // Create order tracking entry
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (
            order_id, status, status_changed_at, updated_by
        ) VALUES (?, 'pending', CURRENT_TIMESTAMP, ?)
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $order_id,
        'receipt_number' => $receipt_number
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 