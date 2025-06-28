<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit();
}

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    $_SESSION['error'] = "Order ID is required";
    header('Location: order_history.php');
    exit();
}

$order_id = $_GET['order_id'];

try {
    // Start transaction
    $conn->beginTransaction();

    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.menu_item_id, oi.quantity
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception("Order not found or no items in order");
    }

    // Clear existing cart items
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    // Add items to cart
    $stmt = $conn->prepare("
        INSERT INTO cart_items (user_id, menu_item_id, quantity)
        VALUES (?, ?, ?)
    ");

    foreach ($items as $item) {
        $stmt->execute([
            $_SESSION['user_id'],
            $item['menu_item_id'],
            $item['quantity']
        ]);
    }

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Items have been added to your cart";
    header('Location: cart.php');
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    $_SESSION['error'] = "Error reordering items: " . $e->getMessage();
    header('Location: order_history.php');
    exit();
}
?> 