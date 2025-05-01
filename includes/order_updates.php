<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    http_response_code(403);
    exit();
}

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

// Function to send SSE data
function sendUpdate($data) {
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Keep connection alive and check for updates
while (true) {
    // Check for new order status changes
    $stmt = $conn->prepare("
        SELECT o.id, o.status, ot.status_changed_at
        FROM orders o
        JOIN order_tracking ot ON o.id = ot.order_id
        WHERE o.vendor_id = ?
        AND ot.status_changed_at > NOW() - INTERVAL 5 SECOND
        ORDER BY ot.status_changed_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($updates as $update) {
        sendUpdate([
            'type' => 'order_status',
            'order_id' => $update['id'],
            'status' => $update['status'],
            'timestamp' => $update['status_changed_at']
        ]);
    }

    // Sleep for a few seconds before checking again
    sleep(3);
} 