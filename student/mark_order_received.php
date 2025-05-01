<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

// Validate order_id
if (!isset($_GET['order_id'])) {
    $_SESSION['error'] = "Order ID is required";
    header('Location: active_orders.php');
    exit();
}

$order_id = $_GET['order_id'];

try {
    // Start transaction
    $conn->beginTransaction();

    // Get order details and verify ownership
    $stmt = $conn->prepare("
        SELECT o.*, COALESCE(ot.status, 'pending') as current_status
        FROM orders o
        LEFT JOIN (
            SELECT ot1.*
            FROM order_tracking ot1
            INNER JOIN (
                SELECT order_id, MAX(status_changed_at) as max_date
                FROM order_tracking
                GROUP BY order_id
            ) ot2 ON ot1.order_id = ot2.order_id AND ot1.status_changed_at = ot2.max_date
        ) ot ON o.id = ot.order_id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found or access denied");
    }

    // Verify order is in 'ready' status
    if ($order['current_status'] !== 'ready') {
        throw new Exception("Order must be ready before it can be marked as received");
    }

    // Update order status to completed
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (
            order_id,
            status,
            notes,
            updated_by,
            status_changed_at
        ) VALUES (?, 'completed', ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([
        $order_id,
        "Order received by customer",
        $_SESSION['user_id']
    ]);

    // Update order completion time
    $stmt = $conn->prepare("
        UPDATE orders 
        SET completed_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$order_id]);

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Order marked as received successfully";

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Failed to mark order as received: " . $e->getMessage();
}

// Redirect back
header('Location: active_orders.php');
exit();
?> 