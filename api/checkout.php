<?php
session_start();
require_once '../connection/db_connection.php';
require_once 'config/auth.php';

// Set JSON response headers
header('Content-Type: application/json');

// Get and validate token
$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'No authentication token provided']);
    exit();
}

// Verify token
$token_data = verifyToken($token);
if (!$token_data) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit();
}

// Get user role
try {
    $stmt = $conn->prepare("
        SELECT role 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$token_data->user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit();
    }

    if ($user['role'] !== 'student') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized access']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Authentication error']);
    exit();
}

// Get JSON input
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

// Check if data is valid JSON
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit();
}

// Validate required fields
$required_fields = ['user_id', 'vendor_id', 'payment_method', 'order_type', 'items'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

// Verify user_id matches token user_id
if ($data['user_id'] != $token_data->user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'User ID mismatch']);
    exit();
}

// Extract data
$user_id = $data['user_id'];
$vendor_id = $data['vendor_id'];
$payment_method = $data['payment_method'];
$order_type = $data['order_type'];
$items = $data['items'];

// Validate items array
if (empty($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cart is empty']);
    exit();
}

// Validate order type specific fields
if ($order_type === 'delivery') {
    if (empty($data['delivery_location']) || empty($data['contact_number'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Delivery orders require delivery location and contact number']);
        exit();
    }
}

if ($order_type === 'dine_in' && empty($data['table_number'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dine-in orders require table number']);
    exit();
}

try {
    // Verify items exist and belong to the vendor
    foreach ($items as $item) {
        if (!isset($item['menu_item_id'], $item['quantity'], $item['price'])) {
            throw new Exception('Invalid item data');
        }

        $stmt = $conn->prepare("
            SELECT item_id, price, is_available 
            FROM menu_items 
            WHERE item_id = ? AND vendor_id = ?
        ");
        $stmt->execute([$item['menu_item_id'], $vendor_id]);
        $menu_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$menu_item) {
            throw new Exception("Menu item not found: {$item['name']}");
        }

        if (!$menu_item['is_available']) {
            throw new Exception("Item not available: {$item['name']}");
        }

        if ($menu_item['price'] != $item['price']) {
            throw new Exception("Price mismatch for item: {$item['name']}");
        }
    }

    // Calculate total and check subscription
    $total_amount = 0;
    $discount_amount = 0;

    // Check for active subscription
    $stmt = $conn->prepare("
        SELECT us.*, sp.discount_percentage
        FROM user_subscriptions us
        JOIN subscription_plans sp ON us.plan_id = sp.id
        WHERE us.user_id = ? 
        AND sp.vendor_id = ?
        AND us.status = 'active'
        AND us.start_date <= NOW()
        AND us.end_date >= NOW()
    ");
    $stmt->execute([$user_id, $vendor_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate totals
    foreach ($items as $item) {
        $item_total = $item['price'] * $item['quantity'];
        $total_amount += $item_total;
        
        if ($subscription) {
            $item_discount = $item_total * ($subscription['discount_percentage'] / 100);
            $discount_amount += $item_discount;
        }
    }

    $final_total = $total_amount - $discount_amount;

    // Get student ID
    $stmt = $conn->prepare("SELECT id FROM staff_students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student record not found");
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
        $user_id,
        $student['id'],
        $vendor_id,
        $final_total,
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

    foreach ($items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $stmt->execute([
            $order_id,
            $item['menu_item_id'],
            $item['quantity'],
            $item['price'],
            $subtotal
        ]);
    }

    // Create order tracking entry
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (
            order_id, status, status_changed_at, updated_by
        ) VALUES (?, 'pending', CURRENT_TIMESTAMP, ?)
    ");
    $stmt->execute([$order_id, $user_id]);

    $conn->commit();

    // Return success response with order details
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order' => [
            'id' => $order_id,
            'receipt_number' => $receipt_number,
            'total_amount' => $final_total,
            'payment_method' => $payment_method,
            'payment_status' => 'pending'
        ]
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 