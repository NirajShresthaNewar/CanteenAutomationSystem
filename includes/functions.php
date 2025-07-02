<?php
function getAffiliatedVendors($user_id) {
    global $conn;
    
    try {
        // Get user's school_id from staff_students table
        $stmt = $conn->prepare("
            SELECT ss.school_id 
            FROM staff_students ss 
            WHERE ss.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $user_school = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_school) {
            return [];
        }
        
        // Get vendors from the same school
        $stmt = $conn->prepare("
            SELECT v.id, u.username as vendor_name, v.opening_hours
            FROM vendors v
            JOIN users u ON v.user_id = u.id
            WHERE v.school_id = ? AND v.approval_status = 'approved'
        ");
        $stmt->execute([$user_school['school_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting affiliated vendors: " . $e->getMessage());
        return [];
    }
}

/**
 * Get server IP addresses with priority for current network
 * @return array Array of server IP addresses with most relevant first
 */
function getServerIP() {
    $ip_addresses = [];
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows IP detection
        exec('ipconfig', $output);
        foreach ($output as $line) {
            // Skip lines without IPv4
            if (strpos($line, 'IPv4') === false) {
                continue;
            }
            
            // Extract IP address
            if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $line, $matches)) {
                $ip = $matches[0];
                
                // Skip localhost and virtual IPs
                if ($ip === '127.0.0.1' || $ip === '0.0.0.0' || strpos($ip, '192.168.56.') === 0) {
                    continue;
                }
                
                // Prioritize IPs based on network
                if (strpos($ip, '192.168.1.') === 0) {
                    // Home/office network IPs get highest priority
                    array_unshift($ip_addresses, $ip);
                } elseif (strpos($ip, '192.168.137.') === 0) {
                    // Secondary priority for specific network
                    if (!in_array($ip, $ip_addresses)) {
                        $ip_addresses[] = $ip;
                    }
                } elseif (!in_array($ip, $ip_addresses)) {
                    // Other valid IPs get lowest priority
                    $ip_addresses[] = $ip;
                }
            }
        }
    } else {
        // Unix/Linux IP detection
        exec('hostname -I', $output);
        if (!empty($output[0])) {
            $ips = explode(' ', trim($output[0]));
            foreach ($ips as $ip) {
                // Skip localhost and virtual IPs
                if ($ip === '127.0.0.1' || $ip === '0.0.0.0' || strpos($ip, '192.168.56.') === 0) {
                    continue;
                }
                
                // Prioritize IPs based on network
                if (strpos($ip, '192.168.1.') === 0) {
                    array_unshift($ip_addresses, $ip);
                } elseif (strpos($ip, '192.168.137.') === 0) {
                    if (!in_array($ip, $ip_addresses)) {
                        $ip_addresses[] = $ip;
                    }
                } elseif (!in_array($ip, $ip_addresses)) {
                    $ip_addresses[] = $ip;
                }
            }
        }
    }

    // If no addresses found, return localhost as fallback
    if (empty($ip_addresses)) {
        return array('127.0.0.1');
    }

    return array_values(array_unique($ip_addresses));
}

function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'pending':
            return 'warning';
        case 'accepted':
            return 'info';
        case 'in_progress':
            return 'primary';
        case 'ready':
            return 'success';
        case 'completed':
            return 'secondary';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
} 