<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../config/khalti.php';
require_once 'khalti_errors.log.php';
require_once 'khalti_utils.php';

header('Content-Type: application/json');

try {
    // Get JSON data from request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        logKhaltiError('Invalid request data', ['raw_input' => $input]);
        throw new Exception('Invalid request data');
    }

    // Validate required fields
    if (!isset($data['amount'], $data['purchase_order_id'], $data['purchase_order_name'], 
               $data['customer_info'], $data['order_details'])) {
        logKhaltiError('Missing required fields', ['data' => $data]);
        throw new Exception('Missing required fields');
    }

    // Create a secure token for verification
    $token = bin2hex(random_bytes(32));
    $_SESSION['payment_token'] = $token;

    // Add token to return URL
    $return_url = getKhaltiReturnUrl() . "?token=" . urlencode($token);

    // Prepare payment data
    $paymentData = [
        'return_url' => $return_url,
        'website_url' => getBaseUrl(),
        'amount' => $data['amount'],
        'purchase_order_id' => $data['purchase_order_id'],
        'purchase_order_name' => $data['purchase_order_name'],
        'customer_info' => $data['customer_info']
    ];

    // Store order details in session
    $_SESSION['khalti_order'] = [
        'amount' => $data['amount'],
        'order_id' => $data['purchase_order_id'],
        'order_details' => $data['order_details'],
        'token' => $token
    ];

    // Make request to Khalti
    $result = makeKhaltiRequest(KHALTI_INITIATE_URL, $paymentData);

    if ($result['status_code'] === 200 && isset($result['response']['payment_url'])) {
        echo json_encode([
            'success' => true,
            'payment_url' => $result['response']['payment_url']
        ]);
    } else {
        throw new Exception($result['response']['detail'] ?? 'Failed to initiate payment');
    }

} catch (Exception $e) {
    logKhaltiError("Payment Processing Error", [
        'error_message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 