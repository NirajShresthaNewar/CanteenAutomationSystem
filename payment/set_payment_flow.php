<?php
session_start();
header('Content-Type: application/json');

try {
    // Get JSON data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['start_flow'])) {
        throw new Exception('Invalid request');
    }

    if ($data['start_flow']) {
        // Set payment flow flag
        $_SESSION['in_payment_flow'] = true;
        // Set timestamp to track session
        $_SESSION['payment_flow_started'] = time();
    } else {
        // Clear payment flow
        unset($_SESSION['in_payment_flow']);
        unset($_SESSION['payment_flow_started']);
    }

    echo json_encode([
        'success' => true,
        'message' => $data['start_flow'] ? 'Payment flow initialized' : 'Payment flow cleared'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 