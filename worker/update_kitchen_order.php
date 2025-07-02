<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Set the response header to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a kitchen staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate input
if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$order_id = $_POST['order_id'];
$new_status = $_POST['status'];

// Validate status
$valid_statuses = ['accepted', 'in_progress', 'ready'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Get worker details and verify they are kitchen staff
    $stmt = $conn->prepare("
        SELECT w.* 
        FROM workers w 
        WHERE w.user_id = ? AND LOWER(w.position) = 'kitchen_staff'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$worker) {
        echo json_encode(['success' => false, 'message' => 'Access denied. Only kitchen staff can update orders']);
        exit();
    }

    // Get the current order status
    $stmt = $conn->prepare("
        SELECT o.*, v.id as vendor_id,
               COALESCE(latest_status.status, 'pending') as current_status
        FROM orders o
        JOIN vendors v ON o.vendor_id = v.id
        LEFT JOIN (
            SELECT ot1.*
            FROM order_tracking ot1
            INNER JOIN (
                SELECT order_id, MAX(status_changed_at) as max_date
                FROM order_tracking
                GROUP BY order_id
            ) ot2 ON ot1.order_id = ot2.order_id AND ot1.status_changed_at = ot2.max_date
        ) latest_status ON o.id = latest_status.order_id
        WHERE o.id = ? AND o.vendor_id = (SELECT vendor_id FROM workers WHERE id = ?)
    ");
    $stmt->execute([$order_id, $worker['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
        exit();
    }

    // Validate status transition
    $current_status = $order['current_status'];
    $valid_transition = false;
    
    switch ($current_status) {
        case 'pending':
            $valid_transition = ($new_status === 'accepted');
            break;
        case 'accepted':
            $valid_transition = ($new_status === 'in_progress');
            break;
        case 'in_progress':
            $valid_transition = ($new_status === 'ready');
            break;
        default:
            $valid_transition = false;
    }

    if (!$valid_transition) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot change status from {$current_status} to {$new_status}"
        ]);
        exit();
    }

    // Start transaction
    $conn->beginTransaction();

    // Insert new status in order_tracking
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (order_id, status, status_changed_at, updated_by, notes)
        VALUES (?, ?, NOW(), ?, ?)
    ");
    $notes = "Order status updated to " . ucfirst($new_status) . " by kitchen staff";
    $stmt->execute([$order_id, $new_status, $_SESSION['user_id'], $notes]);

    // If order is marked as ready, create notification
    if ($new_status === 'ready') {
        // Create notification for the customer
        $stmt = $conn->prepare("
            INSERT INTO order_notifications (order_id, user_id, message, type, created_at)
            VALUES (?, ?, ?, 'order_ready', NOW())
        ");
        $message = "Your order #{$order['receipt_number']} from vendor is ready!";
        $stmt->execute([$order_id, $order['user_id'], $message]);

        // If it's a delivery order, try to assign it to a waiter
        $stmt = $conn->prepare("
            SELECT order_type 
            FROM order_delivery_details 
            WHERE order_id = ?
        ");
        $stmt->execute([$order_id]);
        $delivery_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($delivery_details && $delivery_details['order_type'] === 'delivery') {
            // Find an available waiter
            $stmt = $conn->prepare("
                SELECT w.id 
                FROM workers w
                LEFT JOIN (
                    SELECT worker_id, COUNT(*) as active_assignments
                    FROM order_assignments
                    WHERE status = 'assigned'
                    GROUP BY worker_id
                ) oa ON w.id = oa.worker_id
                WHERE w.vendor_id = ? 
                AND LOWER(w.position) = 'waiter'
                AND (oa.active_assignments IS NULL OR oa.active_assignments < 5)
                ORDER BY IFNULL(oa.active_assignments, 0) ASC
                LIMIT 1
            ");
            $stmt->execute([$order['vendor_id']]);
            $waiter = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($waiter) {
                // Assign the order to the waiter
                $stmt = $conn->prepare("
                    INSERT INTO order_assignments (order_id, worker_id, status, assigned_at)
                    VALUES (?, ?, 'assigned', NOW())
                ");
                $stmt->execute([$order_id, $waiter['id']]);

                // Update order tracking with assignment note
                $stmt = $conn->prepare("
                    INSERT INTO order_tracking (order_id, status, status_changed_at, updated_by, notes)
                    VALUES (?, ?, NOW(), ?, ?)
                ");
                $assignment_notes = "Order assigned to worker: waiter";
                $stmt->execute([$order_id, $new_status, $_SESSION['user_id'], $assignment_notes]);
            }
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Order status updated successfully to ' . ucfirst($new_status)
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error updating kitchen order: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating order status: ' . $e->getMessage()
    ]);
}
?> 