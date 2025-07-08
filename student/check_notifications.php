<?php
session_start();
require_once '../connection/db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Check for unread notifications
    $stmt = $conn->prepare("
        SELECT n.*, o.receipt_number
        FROM notifications n
        LEFT JOIN orders o ON n.message LIKE CONCAT('%#', o.receipt_number, '%')
        WHERE n.user_id = ? 
        AND n.status = 'unread' 
        AND n.type = 'order_ready'
        ORDER BY n.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($notification) {
        // Mark notification as read
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET status = 'read' 
            WHERE id = ?
        ");
        $stmt->execute([$notification['id']]);

        echo json_encode([
            'hasNotification' => true,
            'message' => $notification['message'],
            'link' => $notification['link'],
            'type' => $notification['type']
        ]);
    } else {
        echo json_encode(['hasNotification' => false]);
    }

} catch (Exception $e) {
    echo json_encode([
        'error' => 'Error checking notifications: ' . $e->getMessage()
    ]);
} 