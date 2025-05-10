<?php
// Khalti API Configuration
define('KHALTI_API_URL', 'https://a.khalti.com/api/v2/epayment/');
define('KHALTI_SECRET_KEY', 'live_secret_key_68791341fdd94846a146f0457ff7b455');
define('KHALTI_VERIFY_URL', KHALTI_API_URL . 'lookup/');
define('KHALTI_INITIATE_URL', KHALTI_API_URL . 'initiate/');

// Helper Functions
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . "://" . $host . "/CanteenAutomationSystem";
}

function getKhaltiReturnUrl() {
    return getBaseUrl() . '/payment/success_handler.php';
}

// Helper function to format amount for Khalti (converts to paisa)
function formatKhaltiAmount($amount) {
    return intval($amount * 100);
}

// Helper function to prepare customer info
function prepareCustomerInfo($user) {
    return [
        'name' => $user['username'] ?? 'Test User',
        'email' => $user['email'] ?? 'test@example.com',
        'phone' => $user['contact_no'] ?? '9800000001'
    ];
}

// Helper function to prepare payment data
function prepareKhaltiPaymentData($amount, $orderId, $orderName, $customerInfo) {
    return [
        'return_url' => getKhaltiReturnUrl(),
        'website_url' => getBaseUrl(),
        'amount' => formatKhaltiAmount($amount),
        'purchase_order_id' => $orderId,
        'purchase_order_name' => $orderName,
        'customer_info' => $customerInfo
    ];
}
?> 