<?php
session_start();
require_once '../connection/db_connection.php';

// Debug log
error_log("Update Order Status Request: " . print_r($_POST, true));

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    error_log("Unauthorized access attempt");
    $_SESSION['error'] = "Unauthorized access";
    header('Location: ../auth/login.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    error_log("Vendor not found for user_id: " . $_SESSION['user_id']);
    $_SESSION['error'] = "Vendor not found";
    header('Location: ../auth/logout.php');
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
    error_log("Missing parameters in request");
    $_SESSION['error'] = "Missing required parameters";
    header('Location: manage_orders.php');
    exit();
}

$order_id = $_POST['order_id'];
$new_status = $_POST['status'];

error_log("Processing status update - Order ID: $order_id, New Status: $new_status");

try {
    // Start transaction
    $conn->beginTransaction();

    // First, verify the order belongs to this vendor
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$order_id, $vendor['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("Order not found or access denied - Order ID: $order_id, Vendor ID: {$vendor['id']}");
        throw new Exception("Order not found or access denied");
    }

    error_log("Order found, proceeding with status update");

    // Record in order tracking with current timestamp
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (
            order_id, 
            status, 
            updated_by,
            status_changed_at,
            notes
        ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)
    ");
    $result = $stmt->execute([
        $order_id,
        $new_status,
        $_SESSION['user_id'],
        $_POST['notes'] ?? null
    ]);
    
    if (!$result) {
        error_log("Failed to insert into order_tracking: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Failed to update order tracking");
    }
    error_log("Order tracking updated successfully");

    // If status is ready, create notification
    if ($new_status === 'ready') {
        error_log("Creating ready notification");
        // Get order details for notification
        $stmt = $conn->prepare("
            SELECT o.receipt_number, o.user_id, odd.order_type,
                   u.username as vendor_name
            FROM orders o
            JOIN vendors v ON o.vendor_id = v.id
            JOIN users u ON v.user_id = u.id
            LEFT JOIN order_delivery_details odd ON o.id = odd.order_id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        // Create notification
        $message = "Your order #{$orderDetails['receipt_number']} from {$orderDetails['vendor_name']} is ready!";
        
        $stmt = $conn->prepare("
            INSERT INTO order_notifications (
                order_id,
                user_id,
                message,
                type,
                is_read,
                created_at
            ) VALUES (?, ?, ?, 'order_ready', 0, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $order_id,
            $orderDetails['user_id'],
            $message
        ]);
        error_log("Order notification created successfully");
    }

    $conn->commit();
    error_log("Transaction committed successfully");
    $_SESSION['success'] = "Order status updated to " . ucfirst($new_status);

} catch (Exception $e) {
    error_log("Error in update_order_status.php: " . $e->getMessage());
    if ($conn->inTransaction()) {
        $conn->rollBack();
        error_log("Transaction rolled back");
    }
    $_SESSION['error'] = $e->getMessage();
}

// Clear any output buffers and session messages from other pages
if (isset($_SESSION['payment_error'])) {
    unset($_SESSION['payment_error']);
}

// Redirect back to manage orders page with a timestamp to prevent caching
header('Location: manage_orders.php?t=' . time());
exit();
?> 