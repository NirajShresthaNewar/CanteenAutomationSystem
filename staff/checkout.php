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
    $original_total = $cart_data['total_amount'];
    
    // Check for active subscription
    $stmt = $conn->prepare("
        SELECT us.*, sp.discount_percentage 
        FROM user_subscriptions us
        JOIN subscription_plans sp ON us.plan_id = sp.id
        WHERE us.user_id = ? AND us.status = 'active'
        AND us.start_date <= CURRENT_TIMESTAMP
        AND us.end_date >= CURRENT_TIMESTAMP
        AND sp.vendor_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($subscription) {
        // Get menu categories for items in cart
        $menu_items = [];
        foreach ($cart_data['items'] as $item) {
            $stmt = $conn->prepare("
                SELECT mi.item_id, mi.name, mi.price, mc.category_id, mc.name as category_name
                FROM menu_items mi
                LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
                WHERE mi.item_id = ?
            ");
            $stmt->execute([$item['menu_item_id']]);
            $menu_items[$item['menu_item_id']] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Apply discount with limitations
        $discount_percentage = $subscription['discount_percentage'];
        $discounted_total = 0;
        $regular_total = 0;
        $items_discounted = [];

        foreach ($cart_data['items'] as $item) {
            $menu_item = $menu_items[$item['menu_item_id']];
            $subtotal = $item['price'] * $item['quantity'];
            
            // Discount rules:
            // 1. Only main meals (categories 1 and 2) get discount
            // 2. Maximum 2 items per category get discount
            // 3. Maximum quantity of 2 per discounted item
            if (in_array($menu_item['category_id'], [1, 2])) { // Main meals categories
                if (!isset($items_discounted[$menu_item['category_id']])) {
                    $items_discounted[$menu_item['category_id']] = 0;
                }
                
                if ($items_discounted[$menu_item['category_id']] < 2) {
                    // Apply discount to up to 2 quantities
                    $discount_qty = min(2, $item['quantity']);
                    $regular_qty = $item['quantity'] - $discount_qty;
                    
                    $discounted_amount = ($item['price'] * $discount_qty) * (1 - $discount_percentage/100);
                    $regular_amount = $item['price'] * $regular_qty;
                    
                    $discounted_total += $discounted_amount;
                    $regular_total += $regular_amount;
                    
                    $items_discounted[$menu_item['category_id']]++;
                } else {
                    $regular_total += $subtotal;
                }
            } else {
                // Non-main meal items don't get discount
                $regular_total += $subtotal;
            }
        }

        $cart_data['total_amount'] = $discounted_total + $regular_total;
        $_SESSION['cart_data'][$vendor_id] = $cart_data;
    }

    // Before creating the order, check credit availability if using credit payment
    if ($payment_method === 'credit') {
        // Verify credit account exists and has sufficient balance
        $stmt = $conn->prepare("
            SELECT credit_limit, current_balance, status
            FROM credit_accounts
            WHERE user_id = ? AND vendor_id = ? AND status = 'active'
        ");
        $stmt->execute([$_SESSION['user_id'], $vendor_id]);
        $credit_account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$credit_account) {
            throw new Exception("No active credit account found.");
        }

        $available_credit = $credit_account['credit_limit'] - $credit_account['current_balance'];
        if ($available_credit < $cart_data['total_amount']) {
            throw new Exception("Insufficient credit balance. Available: Rs. " . number_format($available_credit, 2));
        }
    }

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
        $cart_data['staff_id'],
        $vendor_id,
        $cart_data['total_amount'],
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
            'amount' => $cart_data['total_amount'] * 100, // Convert to paisa
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

    // If payment method is credit, record the transaction
    if ($payment_method === 'credit') {
        // Record credit transaction
        $stmt = $conn->prepare("
            INSERT INTO credit_transactions (
                user_id, vendor_id, transaction_type,
                amount, order_id, created_at
            ) VALUES (
                ?, ?, 'purchase',
                ?, ?, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $vendor_id,
            $cart_data['total_amount'],
            $order_id
        ]);

        // Update credit account balance
        $stmt = $conn->prepare("
            UPDATE credit_accounts 
            SET current_balance = current_balance + ?,
                last_payment_date = CURRENT_TIMESTAMP
            WHERE user_id = ? AND vendor_id = ?
        ");
        $stmt->execute([
            $cart_data['total_amount'],
            $_SESSION['user_id'],
            $vendor_id
        ]);
    }

    // Commit transaction
    $conn->commit();

    // After successful order creation and payment processing
    
    // Clear cart items from database
    $stmt = $conn->prepare("
        DELETE FROM cart_items 
        WHERE user_id = ? AND menu_item_id IN (
            SELECT item_id FROM menu_items WHERE vendor_id = ?
        )
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);

    // Clear cart data for this vendor from session
    unset($_SESSION['cart_data'][$vendor_id]);

    // Redirect based on payment method
    switch ($payment_method) {
        case 'khalti':
            header("Location: khalti_payment.php?order_id=" . $order_id);
            break;
        case 'credit':
            $_SESSION['success'] = "Order placed successfully using credit account.";
            header("Location: active_orders.php");
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