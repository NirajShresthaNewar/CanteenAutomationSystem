<?php
require_once 'config/app_config.php';

echo "<h2>IP Detection Results:</h2>";
echo "<pre>";
echo "Available IPs:\n";
print_r($server_ips);
echo "\nSelected IP: " . $server_ip;
echo "\nBASE_URL: " . BASE_URL;
echo "</pre>";
?> 