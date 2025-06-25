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

if (!$vendor) {
    echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    exit();
}

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit();
}

try {
    // Get order details
    $stmt = $conn->prepare("
        SELECT o.*, u.username as customer_name, u.email as customer_email,
            COALESCE(ot.status, 'pending') as current_status
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN (
            SELECT order_id, status
            FROM order_tracking
            WHERE id IN (
                SELECT MAX(id)
                FROM order_tracking
                GROUP BY order_id
            )
        ) ot ON o.id = ot.order_id
        WHERE o.id = ? AND o.vendor_id = ?
    ");
    $stmt->execute([$_GET['order_id'], $vendor['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }

    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, mi.name, mi.price as unit_price
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.item_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$_GET['order_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get order timeline
    $stmt = $conn->prepare("
        SELECT status, status_changed_at as created_at, notes
        FROM order_tracking
        WHERE order_id = ?
        ORDER BY status_changed_at ASC
    ");
    $stmt->execute([$_GET['order_id']]);
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items,
        'timeline' => $timeline
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching order details: ' . $e->getMessage()
    ]);
} 