<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    $_SESSION['error'] = "Invalid request. Order ID is missing.";
    header('Location: cart.php');
    exit();
}

$order_id = $_GET['order_id'];

try {
    // Get order details
    $stmt = $conn->prepare("
        SELECT o.*, v.id as vendor_id
        FROM orders o
        JOIN vendors v ON o.vendor_id = v.id
        WHERE o.id = ? AND o.user_id = ? AND o.payment_method = 'credit'
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found or not eligible for credit payment.");
    }

    // Get credit account details
    $stmt = $conn->prepare("
        SELECT * FROM credit_accounts 
        WHERE user_id = ? AND vendor_id = ? AND status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id'], $order['vendor_id']]);
    $credit_account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$credit_account) {
        throw new Exception("No active credit account found.");
    }

    // Check if credit limit is sufficient
    $available_credit = $credit_account['credit_limit'] - $credit_account['current_balance'];
    if ($available_credit < $order['total_amount']) {
        throw new Exception("Insufficient credit balance.");
    }

    // Start transaction
    $conn->beginTransaction();

    // Update credit account balance
    $stmt = $conn->prepare("
        UPDATE credit_accounts 
        SET current_balance = current_balance + ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$order['total_amount'], $credit_account['id']]);

    // Record credit transaction
    $stmt = $conn->prepare("
        INSERT INTO credit_transactions (
            user_id, vendor_id, order_id, amount,
            transaction_type, payment_method, created_at,
            due_date
        ) VALUES (?, ?, ?, ?, 'purchase', 'credit', CURRENT_TIMESTAMP, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY))
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $order['vendor_id'],
        $order_id,
        $order['total_amount']
    ]);

    // Update order payment status
    $stmt = $conn->prepare("
        UPDATE orders 
        SET payment_status = 'paid',
            credit_account_id = ?,
            payment_received_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$credit_account['id'], $order_id]);

    // Add payment history record
    $stmt = $conn->prepare("
        INSERT INTO payment_history (
            order_id, previous_status, new_status,
            amount_received, notes, created_by
        ) VALUES (?, 'pending', 'paid', ?, 'Payment completed via credit account', ?)
    ");
    $stmt->execute([
        $order_id,
        $order['total_amount'],
        $_SESSION['user_id']
    ]);

    $conn->commit();

    $_SESSION['success'] = "Credit payment processed successfully! Your order has been placed.";
    header('Location: active_orders.php');
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Error processing credit payment: " . $e->getMessage();
    header('Location: cart.php');
    exit();
}
?> 