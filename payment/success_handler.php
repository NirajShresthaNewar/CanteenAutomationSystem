<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../config/khalti.php';
require_once 'khalti_errors.log.php';
require_once 'khalti_utils.php';

// Ensure proper error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/khalti_errors.log');

// Define constant to allow logging
define('ALLOW_KHALTI_LOG', true);

// Add detailed logging
function logDebug($message, $data = []) {
    if (defined('ALLOW_KHALTI_LOG') && ALLOW_KHALTI_LOG) {
        $log = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        if (!empty($data)) {
            $log .= json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
        $log .= "--------------------------------------------------------------------------------\n";
        error_log($log, 3, __DIR__ . '/logs/khalti_errors.log');
    }
}

logDebug("=== Starting Khalti Success Handler ===", [
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'get_params' => $_GET,
    'post_params' => $_POST
]);

// Function to redirect with error
function redirectWithError($message) {
    $_SESSION['payment_error'] = $message;
    header('Location: ../student/cart.php');
    exit;
}

// Function to end payment flow
function endPaymentFlow() {
    unset($_SESSION['in_payment_flow']);
    unset($_SESSION['khalti_payment']);
    unset($_SESSION['payment_token']);
}

// Verify we're still in payment flow
if (!isset($_SESSION['in_payment_flow'])) {
    error_log("Not in payment flow. Session may have expired.");
    redirectWithError('Payment session expired. Please try again.');
}

// Verify the payment token
if (!isset($_GET['token']) || !isset($_SESSION['khalti_order'])) {
    logKhaltiError("Invalid payment verification", [
        'get_params' => $_GET,
        'session' => $_SESSION
    ]);
    $_SESSION['error'] = "Invalid payment verification";
    header('Location: ../student/cart.php');
    exit();
}

try {
    // Get payment details from session
    $payment_details = $_SESSION['khalti_order'];
    
    // Verify the token matches
    if ($_GET['token'] !== $payment_details['token']) {
        throw new Exception('Invalid payment token');
    }

    // Verify the payment with Khalti
    $result = makeKhaltiRequest(KHALTI_VERIFY_URL, [
        'pidx' => $_GET['pidx']
    ]);

    logDebug("Khalti Payment Verification Result", [
        'pidx' => $_GET['pidx'],
        'result' => $result
    ]);

    if ($result['status_code'] !== 200) {
        throw new Exception("Payment verification failed");
    }

    $response_data = $result['response'];
    
    if ($response_data['status'] !== 'Completed') {
        throw new Exception("Payment " . strtolower($response_data['status']));
    }

    logDebug("Starting Database Transaction", [
        'payment_details' => $payment_details,
        'response_data' => $response_data
    ]);

    // Start database transaction
    $conn->beginTransaction();

    try {
        // Create order
        $stmt = $conn->prepare("
            INSERT INTO orders (
                user_id, vendor_id, order_type, 
                payment_method, total_amount, 
                payment_status, order_date,
                receipt_number
            ) VALUES (
                ?, ?, ?, 
                'khalti', ?, 
                'paid', NOW(),
                ?
            )
        ");

        $receipt_number = 'ORD-' . date('Ymd') . '-' . uniqid();
        $total_amount = $payment_details['amount'] / 100; // Convert paisa to rupees

        logDebug("Creating Order", [
            'user_id' => $_SESSION['user_id'],
            'vendor_id' => $payment_details['order_details']['vendor_id'],
            'order_type' => $payment_details['order_details']['order_type'],
            'total_amount' => $total_amount,
            'receipt_number' => $receipt_number
        ]);

        $stmt->execute([
            $_SESSION['user_id'],
            $payment_details['order_details']['vendor_id'],
            $payment_details['order_details']['order_type'],
            $total_amount,
            $receipt_number
        ]);

        $order_id = $conn->lastInsertId();
        logDebug("Order Created", ['order_id' => $order_id]);
        
        // Add order items
        $stmt = $conn->prepare("
            INSERT INTO order_items (
                order_id, menu_item_id, quantity, 
                unit_price, subtotal
            ) 
            SELECT 
                ?, menu_item_id, quantity,
                price, (quantity * price)
            FROM cart_items ci
            JOIN menu_items mi ON ci.menu_item_id = mi.item_id
            WHERE ci.user_id = ?
        ");
        $stmt->execute([$order_id, $_SESSION['user_id']]);

        // Record payment in payment_history
        $stmt = $conn->prepare("
            INSERT INTO payment_history (
                order_id, previous_status, new_status,
                amount_received, notes, created_by,
                created_at
            ) VALUES (
                ?, 'pending', 'paid',
                ?, ?, ?,
                CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            $order_id,
            $total_amount,
            'Payment completed via Khalti. Transaction ID: ' . $response_data['transaction_id'],
            $_SESSION['user_id']
        ]);

        // Add order tracking entry
        $stmt = $conn->prepare("
            INSERT INTO order_tracking (
                order_id, status, status_changed_at,
                updated_by, notes
            ) VALUES (
                ?, 'pending', CURRENT_TIMESTAMP,
                ?, 'Order placed via Khalti payment'
            )
        ");
        $stmt->execute([$order_id, $_SESSION['user_id']]);

        // Add delivery details if applicable
        if ($payment_details['order_details']['order_type'] === 'delivery') {
            $form_data = $payment_details['order_details']['form_data'];
            $stmt = $conn->prepare("
                INSERT INTO order_delivery_details (
                    order_id, order_type, delivery_location,
                    building_name, floor_number, room_number,
                    contact_number, delivery_instructions
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                'delivery',
                $form_data['delivery_location'],
                $form_data['building_name'],
                $form_data['floor_number'],
                $form_data['room_number'],
                $form_data['contact_number'],
                $form_data['delivery_instructions']
            ]);
        } elseif ($payment_details['order_details']['order_type'] === 'dine_in') {
            $form_data = $payment_details['order_details']['form_data'];
            $stmt = $conn->prepare("
                INSERT INTO order_delivery_details (
                    order_id, order_type, table_number
                ) VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                'dine_in',
                $form_data['table_number']
            ]);
        }

        // Clear cart
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);

        // Commit transaction
        $conn->commit();

        // Clear payment session data
        unset($_SESSION['khalti_order']);

        // Set success message
        $_SESSION['success'] = "Payment successful! Your order has been placed.";
        
        // Redirect to order confirmation
        header('Location: ../student/order_confirmation.php?order_id=' . $order_id);
        exit();

    } catch (PDOException $e) {
        logDebug("Database Error", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }

} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    logDebug("Payment Processing Error", [
        'error_message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'session_data' => $_SESSION,
        'get_params' => $_GET
    ]);

    // End payment flow
    endPaymentFlow();

    // Redirect with error
    $_SESSION['error'] = "Payment processing failed: " . $e->getMessage();
    header('Location: ../student/cart.php');
    exit();
}
?> 