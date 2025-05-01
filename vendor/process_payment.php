<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: manage_payments.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    $_SESSION['error'] = "Vendor not found";
    header('Location: manage_payments.php');
    exit();
}

// Validate form submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    header('Location: manage_payments.php');
    exit();
}

// Validate required fields
if (!isset($_POST['order_id']) || !isset($_POST['total_amount']) || !isset($_POST['cash_received'])) {
    $_SESSION['error'] = "Missing required fields";
    header('Location: manage_payments.php');
    exit();
}

$order_id = $_POST['order_id'];
$total_amount = floatval($_POST['total_amount']);
$cash_received = floatval($_POST['cash_received']);
$payment_notes = $_POST['payment_notes'] ?? '';

// Validate cash received amount
if ($cash_received < $total_amount) {
    $_SESSION['error'] = "Cash received must be at least equal to the total amount";
    header('Location: update_payment.php');
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Verify order belongs to this vendor and is pending payment
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
        WHERE o.id = ? AND o.vendor_id = ? AND o.payment_status = 'pending'
    ");
    $stmt->execute([$order_id, $vendor['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found or payment already processed");
    }

    // Calculate change amount
    $change_amount = $cash_received - $total_amount;

    // Update order payment status
    $stmt = $conn->prepare("
        UPDATE orders 
        SET payment_status = 'paid',
            cash_received = ?,
            payment_notes = ?,
            payment_received_at = CURRENT_TIMESTAMP,
            payment_updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$cash_received, $payment_notes, $order_id]);

    // Record in payment history
    $stmt = $conn->prepare("
        INSERT INTO payment_history (
            order_id,
            previous_status,
            new_status,
            amount_received,
            notes,
            created_by,
            created_at
        ) VALUES (?, 'pending', 'paid', ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([
        $order_id,
        $cash_received,
        $payment_notes,
        $_SESSION['user_id']
    ]);

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Payment processed successfully";

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Failed to process payment: " . $e->getMessage();
}

// Redirect back to manage payments
header('Location: manage_payments.php');
exit();
?> 