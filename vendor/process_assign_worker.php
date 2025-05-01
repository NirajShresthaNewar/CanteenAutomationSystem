<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: ../auth/login.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    $_SESSION['error'] = "Vendor not found";
    header('Location: ../auth/logout.php');
    exit();
}

$vendor_id = $vendor['id'];

// Validate form submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    header('Location: manage_orders.php');
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['order_id']) || !isset($_POST['worker_id'])) {
    $_SESSION['error'] = "Missing required parameters";
    header('Location: manage_orders.php');
    exit();
}

$order_id = $_POST['order_id'];
$worker_id = $_POST['worker_id'];

try {
    // Start transaction
    $conn->beginTransaction();

    // Verify order belongs to vendor and check current status
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
        WHERE o.id = ? AND o.vendor_id = ?
    ");
    $stmt->execute([$order_id, $vendor_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found or access denied");
    }

    // Verify worker belongs to vendor
    $stmt = $conn->prepare("
        SELECT w.*, u.username 
        FROM workers w 
        JOIN users u ON w.user_id = u.id
        WHERE w.id = ? AND w.vendor_id = ? AND w.approval_status = 'approved'
    ");
    $stmt->execute([$worker_id, $vendor_id]);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$worker) {
        throw new Exception("Invalid worker selected");
    }

    // Verify order status is valid for assignment
    if (!in_array($order['current_status'], ['accepted', 'in_progress', 'ready'])) {
        throw new Exception("Worker can only be assigned to accepted, in-progress, or ready orders");
    }

    // Check if order is already assigned
    $stmt = $conn->prepare("
        SELECT id, worker_id 
        FROM order_assignments 
        WHERE order_id = ? AND status != 'delivered'
    ");
    $stmt->execute([$order_id]);
    $existing_assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_assignment) {
        if ($existing_assignment['worker_id'] == $worker_id) {
            throw new Exception("Order is already assigned to this worker");
        }
        // Update existing assignment
        $stmt = $conn->prepare("
            UPDATE order_assignments 
            SET worker_id = ?,
                status = 'assigned',
                assigned_at = CURRENT_TIMESTAMP,
                picked_up_at = NULL,
                delivered_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$worker_id, $existing_assignment['id']]);
    } else {
        // Create new assignment
        $stmt = $conn->prepare("
            INSERT INTO order_assignments (
                order_id,
                worker_id,
                status,
                assigned_at
            ) VALUES (?, ?, 'assigned', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$order_id, $worker_id]);
    }

    // Record assignment in order tracking
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (
            order_id,
            status,
            notes,
            updated_by,
            status_changed_at
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([
        $order_id,
        $order['current_status'],
        "Order assigned to worker: " . $worker['username'],
        $_SESSION['user_id']
    ]);

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Worker assigned successfully";

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Failed to assign worker: " . $e->getMessage();
}

// Redirect back
header('Location: manage_orders.php');
exit();
?> 