<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Validate required fields
if (!isset($_POST['vendor_id']) || !isset($_POST['order_type']) || !isset($_POST['payment_method'])) {
    $_SESSION['error'] = "Missing required fields.";
    header('Location: cart.php');
    exit();
}

$vendor_id = $_POST['vendor_id'];
$order_type = $_POST['order_type'];
$payment_method = $_POST['payment_method'];

try {
    // Start transaction
    $conn->beginTransaction();

    // Get cart items for this vendor
    $stmt = $conn->prepare("
        SELECT ci.*, mi.name, mi.price, mi.vendor_id
        FROM cart_items ci
        JOIN menu_items mi ON ci.menu_item_id = mi.item_id
        WHERE ci.user_id = ? AND mi.vendor_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        throw new Exception("No items in cart for this vendor.");
    }

    // Calculate total
    $total = 0;
    foreach ($cart_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    // Insert order
    $stmt = $conn->prepare("
        INSERT INTO orders (user_id, vendor_id, order_date, total_amount)
        VALUES (?, ?, CURRENT_TIMESTAMP, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id, $total]);
    $order_id = $conn->lastInsertId();

    // Insert order items
    $stmt = $conn->prepare("
        INSERT INTO order_items (order_id, menu_item_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($cart_items as $item) {
        $stmt->execute([
            $order_id,
            $item['menu_item_id'],
            $item['quantity'],
            $item['price']
        ]);
    }

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
        $_POST['table_number'] ?? null,
        $_POST['delivery_location'] ?? null,
        $_POST['building_name'] ?? null,
        $_POST['floor_number'] ?? null,
        $_POST['room_number'] ?? null,
        $_POST['delivery_instructions'] ?? null,
        $_POST['contact_number'] ?? null
    ]);

    // Insert initial order tracking status
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (order_id, status, updated_by)
        VALUES (?, 'pending', ?)
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);

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

    // Commit transaction
    $conn->commit();

    // Handle payment method
    switch ($payment_method) {
        case 'esewa':
            // Redirect to eSewa payment page
            $_SESSION['success'] = "Order placed successfully. Redirecting to eSewa...";
            header('Location: esewa_payment.php?order_id=' . $order_id);
            exit();
        case 'credit':
            // Handle credit payment
            $_SESSION['success'] = "Order placed successfully. Amount will be deducted from your credit.";
            header('Location: orders.php');
            exit();
        case 'cash':
        default:
            $_SESSION['success'] = "Order placed successfully. Please pay cash on delivery.";
            header('Location: orders.php');
            exit();
    }

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    $_SESSION['error'] = "Error processing order: " . $e->getMessage();
    header('Location: cart.php');
    exit();
}