<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Verify POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header('Location: checkout.php');
    exit();
}

// Get and validate the order ID and amount tendered
$order_id = $_POST['order_id'] ?? null;
$amount_tendered = floatval($_POST['amount_tendered'] ?? 0);

if (!$order_id || $amount_tendered <= 0) {
    $_SESSION['error'] = "Invalid payment details.";
    header('Location: checkout.php');
    exit();
}

try {
    $conn->beginTransaction();

    // Get order details
    $stmt = $conn->prepare("
        SELECT o.*, ss.user_id 
        FROM orders o
        JOIN staff_students ss ON o.student_id = ss.id
        WHERE o.id = ? AND ss.user_id = ? AND o.payment_method = 'cash'
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found or unauthorized access.");
    }

    // Validate amount tendered
    if ($amount_tendered < $order['total_amount']) {
        throw new Exception("Amount tendered cannot be less than the total amount.");
    }

    // Calculate change
    $change_amount = $amount_tendered - $order['total_amount'];

    // Update order with payment details
    $stmt = $conn->prepare("
        UPDATE orders 
        SET payment_status = 'paid',
            amount_tendered = ?,
            change_amount = ?,
            payment_received_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$amount_tendered, $change_amount, $order_id]);

    // Create order notification
    $stmt = $conn->prepare("
        INSERT INTO order_notifications (
            order_id, user_id, message, type
        ) VALUES (?, ?, ?, 'order_placed')
    ");
    $stmt->execute([
        $order_id, 
        $_SESSION['user_id'],
        "New order placed with cash payment. Amount: ₹" . number_format($order['total_amount'], 2)
    ]);

    $conn->commit();
    
    $_SESSION['success'] = "Payment processed successfully!";
    header("Location: order_confirmation.php?receipt=" . $order['receipt_number']);
    exit();

} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['error'] = "Error processing payment: " . $e->getMessage();
    header('Location: checkout.php');
    exit();
} 