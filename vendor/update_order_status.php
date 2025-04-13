<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

// Check if required parameters are provided
if (!isset($_POST['order_id']) || empty($_POST['order_id']) || !isset($_POST['status']) || empty($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID and status are required']);
    exit();
}

$order_id = $_POST['order_id'];
$new_status = $_POST['status'];

// Validate status
$valid_statuses = ['pending', 'accepted', 'in_progress', 'ready', 'completed', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Check if order exists and belongs to the vendor
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$order_id, $vendor_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found or you do not have permission to update this order');
    }
    
    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    // Add to order tracking
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (order_id, status, updated_at, status_changed_at)
        VALUES (?, ?, NOW(), NOW())
    ");
    $stmt->execute([$order_id, $new_status]);
    
    // If status is completed or cancelled, perform additional actions if needed
    if ($new_status === 'completed') {
        // Could update inventory, add to sales records, etc.
    } elseif ($new_status === 'cancelled') {
        // Could handle refunds, etc.
    }
    
    // Commit transaction
    $conn->commit();
    
    // Send notification to user (this would be enhanced in a real system)
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, status, created_at) 
        VALUES (?, ?, 'unread', NOW())
    ");
    
    $message = '';
    switch ($new_status) {
        case 'accepted':
            $message = 'Your order #' . $order['receipt_number'] . ' has been accepted and will be prepared soon.';
            break;
        case 'in_progress':
            $message = 'Your order #' . $order['receipt_number'] . ' is now being prepared.';
            break;
        case 'ready':
            $message = 'Your order #' . $order['receipt_number'] . ' is ready for pickup.';
            break;
        case 'completed':
            $message = 'Your order #' . $order['receipt_number'] . ' has been completed. Thank you for your purchase!';
            break;
        case 'cancelled':
            $message = 'Your order #' . $order['receipt_number'] . ' has been cancelled. Please contact us for details.';
            break;
    }
    
    if (!empty($message)) {
        $stmt->execute([$order['user_id'], $message]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 