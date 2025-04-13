<?php
// Base URL for QR code generation - Change this to your server's IP address or domain name
// For local network access, use your computer's IP address (e.g., http://192.168.1.100)
// For public access, use your domain name (e.g., https://example.com)

// Define the current server IP address
$server_ips = getServerIP();
$wifi_ip = !empty($server_ips) ? $server_ips[0] : '192.168.137.139';

// Uncomment the line below to use automatic IP detection
// define('BASE_URL', 'http://' . $wifi_ip . '/CanteenAutomationSystem');

// Manual setting for current network (192.168.137.x)
define('BASE_URL', 'http://192.168.137.139/CanteenAutomationSystem');

// Previous manual setting - comment out if not using
// define('BASE_URL', 'http://192.168.1.72/CanteenAutomationSystem');
?> 