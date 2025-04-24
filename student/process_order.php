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
    header('Location: cart.php');
    exit();
}

// Get form data
$vendor_id = $_POST['vendor_id'] ?? null;
$payment_method = $_POST['payment_method'] ?? 'cash';
$special_instructions = $_POST['special_instructions'] ?? '';

// Validate payment method
if (!in_array($payment_method, ['cash', 'esewa', 'credit'])) {
    $_SESSION['error'] = "Invalid payment method.";
    header('Location: cart.php');
    exit();
}

try {
    $conn->beginTransaction();

    // Get student ID first
    $stmt = $conn->prepare("
        SELECT id FROM staff_students 
        WHERE user_id = ? AND role = 'student' AND approval_status = 'approved'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student record not found or not approved.");
    }

    // Get cart items and calculate total
    $stmt = $conn->prepare("
        SELECT ci.*, mi.price, mi.name
        FROM cart_items ci
        JOIN menu_items mi ON ci.menu_item_id = mi.item_id
        WHERE ci.user_id = ? AND mi.vendor_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        throw new Exception("Cart is empty.");
    }

    $total_amount = 0;
    foreach ($cart_items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }

    // Handle credit payment method
    $credit_account_id = null;
    if ($payment_method === 'credit') {
        $stmt = $conn->prepare("
            SELECT ca.id, ca.credit_limit, ca.current_balance, ca.status
            FROM credit_accounts ca
            WHERE ca.user_id = ? AND ca.vendor_id = ? AND ca.status = 'active'
        ");
        $stmt->execute([$_SESSION['user_id'], $vendor_id]);
        $credit_account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$credit_account) {
            throw new Exception("No active credit account found.");
        }
        
        $available_credit = $credit_account['credit_limit'] - $credit_account['current_balance'];
        if ($available_credit < $total_amount) {
            throw new Exception("Insufficient credit available. Your available credit is ₹" . number_format($available_credit, 2));
        }
        
        $credit_account_id = $credit_account['id'];
    }

    // Generate receipt number
    $receipt_number = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

    // Create order
    $stmt = $conn->prepare("
        INSERT INTO orders (
            receipt_number, user_id, student_id, vendor_id, credit_account_id,
            total_amount, payment_method, payment_status, notes,
            status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            'pending', CURRENT_TIMESTAMP
        )
    ");
    $stmt->execute([
        $receipt_number,
        $_SESSION['user_id'],
        $student['id'],  // Use the student ID from staff_students table
        $vendor_id,
        $credit_account_id,
        $total_amount,
        $payment_method,
        $payment_method === 'credit' ? 'paid' : 'pending',
        $special_instructions
    ]);

    $order_id = $conn->lastInsertId();

    // Create order items
    $stmt = $conn->prepare("
        INSERT INTO order_items (
            order_id, menu_item_id, quantity, unit_price, subtotal,
            special_instructions
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($cart_items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $stmt->execute([
            $order_id,
            $item['menu_item_id'],
            $item['quantity'],
            $item['price'],
            $subtotal,
            $item['special_instructions'] ?? null
        ]);
    }

    // Handle credit payment
    if ($payment_method === 'credit') {
        // Update credit account balance
        $stmt = $conn->prepare("
            UPDATE credit_accounts
            SET current_balance = current_balance + ?
            WHERE id = ?
        ");
        $stmt->execute([$total_amount, $credit_account_id]);
        
        // Record credit transaction
        $stmt = $conn->prepare("
            INSERT INTO credit_transactions (
                user_id, vendor_id, order_id, amount,
                transaction_type, payment_method, created_at
            ) VALUES (?, ?, ?, ?, 'purchase', 'credit', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$_SESSION['user_id'], $vendor_id, $order_id, $total_amount]);
    }

    // Clear cart
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    // Create order tracking entry
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (
            order_id, status, status_changed_at, updated_by
        ) VALUES (?, 'pending', CURRENT_TIMESTAMP, ?)
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);

    $conn->commit();

    // Create notification for vendor
    require_once '../includes/order_functions.php';
    createOrderNotification($conn, $order_id, $vendor_id, $receipt_number, $payment_method);

    // Redirect based on payment method
    if ($payment_method === 'cash') {
        header("Location: cash_payment.php?order_id=" . $order_id);
    } else if ($payment_method === 'esewa') {
        // Redirect to eSewa payment page
        header("Location: esewa_payment.php?order_id=" . $order_id);
    } else {
        // Credit payment is already processed
        header("Location: order_confirmation.php?receipt=" . $receipt_number);
    }
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Error processing order: " . $e->getMessage();
    header('Location: cart.php');
    exit();
}