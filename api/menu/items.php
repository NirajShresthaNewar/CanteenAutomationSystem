<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/cors.php';

// Configure CORS
configureCORS();

// Set JSON response headers
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable HTML error output

try {
    $database = new Database();
    $db = $database->connect();

    // Get and verify token
    $token = getBearerToken();
    if (!$token) {
        throw new Exception('No token provided');
    }

    $user_data = verifyToken($token);
    if (!$user_data) {
        throw new Exception('Invalid or expired token');
    }

    // Get user's school ID
    $schoolQuery = "SELECT ss.school_id 
                   FROM staff_students ss 
                   WHERE ss.user_id = ? AND ss.approval_status = 'approved'";
    $schoolStmt = $db->prepare($schoolQuery);
    $schoolStmt->execute([$user_data->user_id]);
    $schoolResult = $schoolStmt->fetch(PDO::FETCH_ASSOC);

    if (!$schoolResult) {
        throw new Exception('User not associated with any school');
    }

    $school_id = $schoolResult['school_id'];

    // Get vendors and their menu items
    $query = "
        SELECT 
            v.id as vendor_id,
            (SELECT username FROM users WHERE id = v.user_id) as vendor_name,
            v.opening_hours,
            mi.item_id,
            mi.name,
            COALESCE(mi.description, '') as description,
            mi.price,
            mi.image_path,
            mi.is_vegetarian,
            mi.is_vegan,
            mi.is_gluten_free,
            mi.is_available,
            mc.name as category_name
        FROM vendors v
        INNER JOIN menu_items mi ON mi.vendor_id = v.id
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
        WHERE v.school_id = ?
        AND v.approval_status = 'approved'
        AND mi.is_available = 1
        ORDER BY v.id, mi.name
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([$school_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group items by vendor
    $vendors = [];
    foreach ($results as $row) {
        $vendorId = $row['vendor_id'];
        if (!isset($vendors[$vendorId])) {
            $vendors[$vendorId] = [
                'vendor_id' => $vendorId,
                'name' => $row['vendor_name'],
                'opening_hours' => $row['opening_hours'],
                'items' => []
            ];
        }
        
        $vendors[$vendorId]['items'][] = [
            'item_id' => $row['item_id'],
            'name' => $row['name'],
            'description' => $row['description'] ?: 'No description available',
            'price' => floatval($row['price']),
            'image_path' => $row['image_path'],
            'is_vegetarian' => (bool)$row['is_vegetarian'],
            'is_vegan' => (bool)$row['is_vegan'],
            'is_gluten_free' => (bool)$row['is_gluten_free'],
            'category_name' => $row['category_name'] ?: 'Other'
        ];
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Menu items retrieved successfully',
        'data' => array_values($vendors)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null
    ]);
} 