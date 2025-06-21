<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit();
}

// Verify POST request with required data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['vendor_id'], $_POST['payment_method'], $_POST['order_type'])) {
    $_SESSION['error'] = "Invalid checkout request. Please fill in all required fields.";
    header('Location: cart.php');
    exit();
}

$vendor_id = $_POST['vendor_id'];
$payment_method = $_POST['payment_method'];
$order_type = $_POST['order_type'];

// Validate delivery details based on order type
if ($order_type === 'delivery') {
    if (empty($_POST['delivery_location']) || empty($_POST['contact_number'])) {
        $_SESSION['error'] = "Please provide delivery location and contact number.";
        header('Location: cart.php');
        exit();
    }
}

if ($order_type === 'dine_in' && empty($_POST['table_number'])) {
    $_SESSION['error'] = "Please provide table number for dine-in orders.";
    header('Location: cart.php');
    exit();
}

try {
    // Get cart items and verify they exist in session
    if (!isset($_SESSION['cart_data'][$vendor_id])) {
        throw new Exception("Cart data not found. Please try again.");
    }

    $cart_data = $_SESSION['cart_data'][$vendor_id];
    $total_amount = $cart_data['total_amount'];
    $staff_id = $cart_data['staff_id'];

    // Start transaction
    $conn->beginTransaction();

    // Generate receipt number
    $receipt_number = 'ORD-' . date('Ymd') . '-' . uniqid();

    // Create order
    $stmt = $conn->prepare("
        INSERT INTO orders (
            receipt_number, user_id, customer_id, vendor_id,
            total_amount, payment_method, payment_status,
            order_type, order_date
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, CURRENT_TIMESTAMP
        )
    ");
    $stmt->execute([
        $receipt_number,
        $_SESSION['user_id'],
        $staff_id,
        $vendor_id,
        $total_amount,
        $payment_method,
        'pending',
        $order_type
    ]);

    $order_id = $conn->lastInsertId();

    // Insert order items
    $stmt = $conn->prepare("
        INSERT INTO order_items (
            order_id, menu_item_id, quantity,
            unit_price, subtotal
        ) VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($cart_data['items'] as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $stmt->execute([
            $order_id,
            $item['menu_item_id'],
            $item['quantity'],
            $item['price'],
            $subtotal
        ]);
    }

    // Insert order delivery details
    $stmt = $conn->prepare("
        INSERT INTO order_delivery_details (
            order_id,
            order_type,
            table_number,
            delivery_location,
            contact_number,
            delivery_instructions
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $order_id,
        $order_type,
        $_POST['table_number'] ?? null,
        $_POST['delivery_location'] ?? null,
        $_POST['contact_number'] ?? null,
        $_POST['delivery_instructions'] ?? null
    ]);

    // Create order tracking entry
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (
            order_id, status, status_changed_at,
            updated_by, notes
        ) VALUES (
            ?, 'pending', CURRENT_TIMESTAMP,
            ?, 'Order placed'
        )
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);

    // If payment method is Khalti, prepare payment data
    if ($payment_method === 'khalti') {
        // Store order details in session for Khalti payment
        $_SESSION['in_payment_flow'] = true;
        $_SESSION['khalti_order'] = [
            'amount' => $total_amount * 100, // Convert to paisa
            'order_id' => $order_id,
            'receipt_number' => $receipt_number,
            'order_details' => [
                'vendor_id' => $vendor_id,
                'order_type' => $order_type,
                'form_data' => [
                    'delivery_location' => $_POST['delivery_location'] ?? null,
                    'contact_number' => $_POST['contact_number'] ?? null,
                    'delivery_instructions' => $_POST['delivery_instructions'] ?? null,
                    'table_number' => $_POST['table_number'] ?? null
                ]
            ]
        ];
    }

    // Commit transaction
    $conn->commit();

    // Clear cart data for this vendor
    unset($_SESSION['cart_data'][$vendor_id]);

    // Redirect based on payment method
    switch ($payment_method) {
        case 'khalti':
            header("Location: khalti_payment.php?order_id=" . $order_id);
            break;
        case 'cash':
            header("Location: cash_payment.php?order_id=" . $order_id);
            break;
        default:
            throw new Exception("Invalid payment method.");
    }
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Error processing checkout: " . $e->getMessage();
    header('Location: cart.php');
    exit();
}
?> 