<?php
session_start();
require_once '../connection/db_connection.php';

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
        WHERE w.user_id = ? AND w.position = 'Kitchen_staff'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$worker) {
        echo json_encode(['success' => false, 'message' => 'Access denied. Only kitchen staff can update orders']);
        exit();
    }

    // Verify the order belongs to a vendor this worker is assigned to
    $stmt = $conn->prepare("
        SELECT o.* 
        FROM orders o
        WHERE o.id = ?
        AND o.vendor_id IN (
            SELECT vendor_id 
            FROM worker_vendor_assignments 
            WHERE worker_id = ?
        )
    ");
    $stmt->execute([$order_id, $worker['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
        exit();
    }

    // Start transaction
    $conn->beginTransaction();

    // Insert new status in order_tracking
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (order_id, status, status_changed_at, updated_by)
        VALUES (?, ?, NOW(), ?)
    ");
    $stmt->execute([$order_id, $new_status, $_SESSION['user_id']]);

    // If order is marked as ready, assign it to a delivery worker if it's a delivery order
    if ($new_status === 'ready') {
        // Check if it's a delivery order
        $stmt = $conn->prepare("
            SELECT order_type 
            FROM order_delivery_details 
            WHERE order_id = ?
        ");
        $stmt->execute([$order_id]);
        $delivery_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($delivery_details && $delivery_details['order_type'] === 'delivery') {
            // Find an available delivery worker
            $stmt = $conn->prepare("
                SELECT w.id 
                FROM workers w
                LEFT JOIN (
                    SELECT worker_id, COUNT(*) as active_deliveries
                    FROM order_assignments
                    WHERE status = 'assigned'
                    GROUP BY worker_id
                ) oa ON w.id = oa.worker_id
                WHERE w.position = 'Delivery_staff'
                AND (active_deliveries IS NULL OR active_deliveries < 5)
                ORDER BY active_deliveries ASC NULLS FIRST
                LIMIT 1
            ");
            $stmt->execute();
            $delivery_worker = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($delivery_worker) {
                // Assign the order to the delivery worker
                $stmt = $conn->prepare("
                    INSERT INTO order_assignments (order_id, worker_id, status, assigned_at)
                    VALUES (?, ?, 'assigned', NOW())
                ");
                $stmt->execute([$order_id, $delivery_worker['id']]);
            }
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error updating kitchen order: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating order status']);
}
?> 