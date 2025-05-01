<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a worker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: ../auth/login.php');
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

// Validate input parameters
if (!isset($_POST['order_id']) || !isset($_POST['status']) || !isset($_POST['current_status']) || !isset($_POST['order_status'])) {
    $_SESSION['error'] = "Missing required parameters";
    header('Location: assigned_orders.php');
    exit();
}

$order_id = $_POST['order_id'];
$new_status = $_POST['status'];
$current_status = $_POST['current_status'];
$order_status = $_POST['order_status'];

// Define valid status transitions
$valid_transitions = [
    'assigned' => ['picked_up'],
    'picked_up' => ['delivered']
];

// Validate the status transition
if (!isset($valid_transitions[$current_status]) || !in_array($new_status, $valid_transitions[$current_status])) {
    $_SESSION['error'] = "Invalid status transition from {$current_status} to {$new_status}";
    header('Location: assigned_orders.php');
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Get the latest assignment for this order and worker
    $stmt = $conn->prepare("
        SELECT oa.* 
        FROM order_assignments oa
        WHERE oa.order_id = ? 
        AND oa.worker_id = ?
        ORDER BY oa.assigned_at DESC, oa.id DESC
        LIMIT 1
    ");
    $stmt->execute([$order_id, $worker['id']]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        throw new Exception("Order assignment not found");
    }

    // Verify current status matches
    if ($assignment['status'] !== $current_status) {
        throw new Exception("Current status mismatch. Please refresh the page.");
    }

    // For pick up action, verify order is ready
    if ($new_status === 'picked_up') {
        // Get latest order status
        $stmt = $conn->prepare("
            SELECT status 
            FROM order_tracking 
            WHERE order_id = ? 
            ORDER BY status_changed_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$order_id]);
        $latest_order_status = $stmt->fetch(PDO::FETCH_COLUMN);

        if ($latest_order_status !== 'ready') {
            throw new Exception("Cannot pick up order. Order is not ready yet.");
        }
    }

    // Update assignment status
    $stmt = $conn->prepare("
        UPDATE order_assignments 
        SET status = ?,
            picked_up_at = CASE WHEN ? = 'picked_up' THEN CURRENT_TIMESTAMP ELSE picked_up_at END,
            delivered_at = CASE WHEN ? = 'delivered' THEN CURRENT_TIMESTAMP ELSE delivered_at END
        WHERE id = ?
    ");
    $stmt->execute([$new_status, $new_status, $new_status, $assignment['id']]);

    // If marking as delivered, also update the order status to completed
    if ($new_status === 'delivered') {
        $stmt = $conn->prepare("
            INSERT INTO order_tracking (
                order_id, 
                status, 
                updated_by,
                status_changed_at,
                notes
            ) VALUES (?, 'completed', ?, CURRENT_TIMESTAMP, ?)
        ");
        $stmt->execute([
            $order_id, 
            $_SESSION['user_id'],
            "Order marked as delivered by worker"
        ]);
    }

    $conn->commit();
    $_SESSION['success'] = "Order status updated successfully";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Failed to update order status: " . $e->getMessage();
}

header('Location: assigned_orders.php');
exit();
?> 