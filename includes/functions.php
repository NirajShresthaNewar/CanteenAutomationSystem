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
 * Get server IP addresses with priority for current network (192.168.137.x)
 * @return array Array of server IP addresses with most relevant first
 */
function getServerIP() {
    $ip_addresses = [];

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows IP detection
        exec('ipconfig | findstr IPv4', $output);
        foreach ($output as $line) {
            if (preg_match('/\d+\.\d+\.\d+\.\d+/', $line, $matches)) {
                $ip = $matches[0];
                // Prioritize 192.168.137.x addresses (current network)
                if (preg_match('/^192\.168\.137\./', $ip)) {
                    array_unshift($ip_addresses, $ip);
                }
                // Then 192.168.1.x addresses
                elseif (preg_match('/^192\.168\.1\./', $ip)) {
                    if (!in_array($ip, $ip_addresses)) {
                        $ip_addresses[] = $ip;
                    }
                }
                // Then any other private network address
                elseif (preg_match('/^(192\.168\.|10\.|172\.16\.)/', $ip)) {
                    if (!in_array($ip, $ip_addresses)) {
                        $ip_addresses[] = $ip;
                    }
                }
            }
        }
    } else {
        // Unix/Linux IP detection
        exec('hostname -I', $output);
        if (!empty($output[0])) {
            $ips = explode(' ', trim($output[0]));
            foreach ($ips as $ip) {
                // Prioritize 192.168.137.x addresses (current network)
                if (preg_match('/^192\.168\.137\./', $ip)) {
                    array_unshift($ip_addresses, $ip);
                }
                // Then 192.168.1.x addresses
                elseif (preg_match('/^192\.168\.1\./', $ip)) {
                    if (!in_array($ip, $ip_addresses)) {
                        $ip_addresses[] = $ip;
                    }
                }
                // Then any other private network address
                elseif (preg_match('/^(192\.168\.|10\.|172\.16\.)/', $ip)) {
                    if (!in_array($ip, $ip_addresses)) {
                        $ip_addresses[] = $ip;
                    }
                }
            }
        }
    }

    // If no addresses found, return a fallback
    if (empty($ip_addresses)) {
        return array('192.168.137.139'); // Hardcoded fallback
    }

    return array_unique($ip_addresses);
} 