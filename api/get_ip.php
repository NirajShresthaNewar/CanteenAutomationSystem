<?php
header('Content-Type: text/plain');
header('Access-Control-Allow-Origin: *');

// Get the server's IP address
$ip = $_SERVER['SERVER_ADDR'];

// If we're running on localhost, try to get the actual IP
if ($ip === '127.0.0.1' || $ip === '::1') {
    // Try to get the IP from the network interface
    $output = shell_exec('ipconfig');
    if (preg_match('/IPv4 Address.*?(\d+\.\d+\.\d+\.\d+)/s', $output, $matches)) {
        $ip = $matches[1];
    }
}

echo $ip; 