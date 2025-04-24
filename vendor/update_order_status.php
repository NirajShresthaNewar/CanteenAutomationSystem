<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

$order_id = $_POST['order_id'] ?? null;
$new_status = $_POST['status'] ?? null;
$notes = $_POST['notes'] ?? null;
$preparation_time = $_POST['preparation_time'] ?? null;

if (!$order_id || !$new_status) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

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
    $update_query = "UPDATE orders SET status = ?";
    $params = [$new_status];

    // Add preparation time if provided
    if ($preparation_time && $new_status === 'accepted') {
        $update_query .= ", preparation_time = ?, pickup_time = DATE_ADD(NOW(), INTERVAL ? MINUTE)";
        $params[] = $preparation_time;
        $params[] = $preparation_time;
    }

    // Handle completed and cancelled states
    if ($new_status === 'completed') {
        $update_query .= ", completed_at = NOW()";
    } elseif ($new_status === 'cancelled') {
        $update_query .= ", cancelled_reason = ?";
        $params[] = $notes;
    }

    $update_query .= " WHERE id = ?";
    $params[] = $order_id;

    $stmt = $conn->prepare($update_query);
    $stmt->execute($params);
    
    // Record in order tracking
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (order_id, status, notes, updated_by)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$order_id, $new_status, $notes, $_SESSION['user_id']]);
    
    // If status is completed or cancelled, perform additional actions if needed
    if ($new_status === 'completed') {
        // Could update inventory, add to sales records, etc.
    } elseif ($new_status === 'cancelled') {
        // Could handle refunds, etc.
    }
    
    // Commit transaction
    $conn->commit();
    
    // Send success response with updated order details
    $stmt = $conn->prepare("
        SELECT o.*, ot.notes, ot.updated_by, u.username as updated_by_name
        FROM orders o
        LEFT JOIN order_tracking ot ON o.id = ot.order_id
        LEFT JOIN users u ON ot.updated_by = u.id
        WHERE o.id = ?
        ORDER BY ot.status_changed_at DESC
        LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully',
        'order' => $order
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update order status: ' . $e->getMessage()]);
}
?> 