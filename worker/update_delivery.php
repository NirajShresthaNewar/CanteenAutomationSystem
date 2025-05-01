<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a worker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: assigned_orders.php');
    exit();
}

// Get worker ID
$stmt = $conn->prepare("SELECT id FROM workers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$worker = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$worker) {
    $_SESSION['error'] = "Worker not found";
    header('Location: ../auth/logout.php');
    exit();
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    header('Location: assigned_orders.php');
    exit();
}

if (!isset($_POST['order_id']) || !isset($_POST['action'])) {
    $_SESSION['error'] = "Missing required parameters";
    header('Location: assigned_orders.php');
    exit();
}

$order_id = $_POST['order_id'];
$action = $_POST['action'];

try {
    // Start transaction
    $conn->beginTransaction();

    // Verify the order is assigned to this worker
    $stmt = $conn->prepare("
        SELECT o.*
        FROM orders o
        WHERE o.id = ? AND o.assigned_worker_id = ?
        AND o.status IN ('ready', 'in_delivery')
    ");
    $stmt->execute([$order_id, $worker['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found or not assigned to you");
    }

    // Validate action based on current status
    if ($action === 'pickup' && $order['status'] !== 'ready') {
        throw new Exception("Order is already picked up");
    }
    if ($action === 'deliver' && $order['status'] !== 'in_delivery') {
        throw new Exception("Order must be picked up first");
    }

    if ($action === 'pickup') {
        // Update order status to in_delivery
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = 'in_delivery',
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$order_id]);

        // Insert into order tracking
        $stmt = $conn->prepare("
            INSERT INTO order_tracking (
                order_id, status, updated_by, status_changed_at
            ) VALUES (?, 'in_delivery', ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$order_id, $_SESSION['user_id']]);

        $_SESSION['success'] = "Order picked up successfully";
    } else {
        // Mark order as completed
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = 'completed',
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$order_id]);

        // Insert into order tracking
        $stmt = $conn->prepare("
            INSERT INTO order_tracking (
                order_id, status, updated_by, status_changed_at
            ) VALUES (?, 'completed', ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$order_id, $_SESSION['user_id']]);

        $_SESSION['success'] = "Order delivered successfully";
    }

    // Commit transaction
    $conn->commit();

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back
header('Location: assigned_orders.php');
exit();
?> 