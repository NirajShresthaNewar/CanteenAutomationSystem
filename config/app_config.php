<?php
// Include the functions file
require_once __DIR__ . '/../includes/functions.php';

// Base URL for QR code generation - Change this to your server's IP address or domain name
// For local network access, use your computer's IP address (e.g., http://192.168.1.100)
// For public access, use your domain name (e.g., https://example.com)

// Get all valid IP addresses
$server_ips = getServerIP();

// Select the first valid IP address that's not localhost
$server_ip = '127.0.0.1'; // default fallback
foreach ($server_ips as $ip) {
    if ($ip !== '127.0.0.1' && $ip !== '0.0.0.0') {
        $server_ip = $ip;
        break;
    }
}

// Define the base URL using the detected IP
define('BASE_URL', 'http://' . $server_ip . '/CanteenAutomationSystem');

// For debugging purposes
// error_log('Available IPs: ' . print_r($server_ips, true));
// error_log('Selected IP: ' . $server_ip);
?> 