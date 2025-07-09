<?php
require_once __DIR__ . '/../config/cors.php';
configureCORS();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Log request details
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Raw Headers: " . json_encode(getallheaders()));

try {
    $database = new Database();
    $db = $database->connect();

    // Get and verify the token
    $token = getBearerToken();
    
    if (!$token) {
        throw new Exception('No valid Bearer token found');
    }
    
    $user_data = verifyToken($token);

    if (!$user_data) {
        throw new Exception('Invalid or expired token');
    }

    $user_id = $user_data->user_id;
    error_log("Processing request for user_id: " . $user_id);

    // First, get the user's school ID
    $school_query = "SELECT ss.school_id 
                    FROM staff_students ss 
                    WHERE ss.user_id = ? AND ss.approval_status = 'approved'";
    $school_stmt = $db->prepare($school_query);
    $school_stmt->execute([$user_id]);
    $school_result = $school_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$school_result) {
        throw new Exception('User not associated with any school');
    }

    $school_id = $school_result['school_id'];

    // Get vendors and their menu items for the school
    $query = "SELECT 
                v.id as vendor_id,
                u.username as vendor_name,
                u.email as vendor_email,
                v.opening_hours,
                mi.item_id,
                mi.name,
                mi.description,
                mi.price,
                mi.image_path,
                mi.is_vegetarian,
                mi.is_vegan,
                mi.is_gluten_free,
                mi.is_available,
                mc.name as category_name
            FROM vendors v
            JOIN users u ON v.user_id = u.id
            LEFT JOIN menu_items mi ON mi.vendor_id = v.id
            LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
            WHERE v.school_id = ? 
            AND v.approval_status = 'approved' 
            AND (mi.is_available = 1 OR mi.is_available IS NULL)
            ORDER BY v.id, mc.name, mi.name";

    $stmt = $db->prepare($query);
    $stmt->execute([$school_id]);

    $vendors = [];
    $current_vendor = null;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($current_vendor === null || $current_vendor['vendor_id'] !== $row['vendor_id']) {
            if ($current_vendor !== null) {
                $vendors[] = $current_vendor;
            }
            $current_vendor = [
                'vendor_id' => $row['vendor_id'],
                'vendor_name' => $row['vendor_name'],
                'vendor_email' => $row['vendor_email'],
                'opening_hours' => $row['opening_hours'],
                'items' => []
            ];
        }

        if ($row['item_id'] !== null) {
            $current_vendor['items'][] = [
                'item_id' => $row['item_id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'price' => (float)$row['price'],
                'image_path' => $row['image_path'],
                'is_vegetarian' => (bool)$row['is_vegetarian'],
                'is_vegan' => (bool)$row['is_vegan'],
                'is_gluten_free' => (bool)$row['is_gluten_free'],
                'category_name' => $row['category_name'] ?? 'Other'
            ];
        }
    }

    if ($current_vendor !== null) {
        $vendors[] = $current_vendor;
    }

    // Filter out vendors with no items
    $vendors = array_filter($vendors, function($vendor) {
        return !empty($vendor['items']);
    });

    echo json_encode([
        'status' => 'success',
        'data' => array_values($vendors), // Re-index array after filtering
        'debug' => [
            'user_id' => $user_id,
            'school_id' => $school_id,
            'vendor_count' => count($vendors)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in menu items API: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 