<?php
header('Content-Type: application/json');
require_once '../connection/db_connection.php';

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit();
}

$order_id = $_GET['order_id'];

try {
    // Get payment status
    $stmt = $conn->prepare("
        SELECT payment_status, cash_received, total_amount 
        FROM orders 
        WHERE id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit();
    }

    echo json_encode([
        'status' => $order['payment_status'],
        'cash_received' => $order['cash_received'],
        'change' => $order['cash_received'] ? ($order['cash_received'] - $order['total_amount']) : 0
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
}
?> 