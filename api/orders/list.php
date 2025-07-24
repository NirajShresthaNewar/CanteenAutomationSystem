<?php
// Create a log file specifically for orders
$logFile = __DIR__ . '/orders_debug.log';
file_put_contents($logFile, "\n\n" . date('Y-m-d H:i:s') . " - New Request\n", FILE_APPEND);

function debugLog($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/cors.php';

// Configure CORS
configureCORS();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON response headers
header('Content-Type: application/json');

// Debug logging
debugLog("Orders list.php started");
debugLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
debugLog("Request Headers: " . json_encode(getallheaders()));

try {
    // Get database connection
    $database = new Database();
    $db = $database->connect();
    debugLog("Database connection successful");

    // Get and verify token
    $token = getBearerToken();
    debugLog("Token received: " . ($token ? substr($token, 0, 10) . '...' : 'no token'));
    
    if (!$token) {
        throw new Exception('No token provided');
    }

    $user_data = verifyToken($token);
    debugLog("Token verification result: " . json_encode($user_data));
    
    if (!$user_data) {
        throw new Exception('Invalid or expired token');
    }

    $userId = $user_data->user_id;
    $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] == '1';
    debugLog("User ID: $userId, Active Only: " . ($activeOnly ? 'true' : 'false'));

    // Check orders table structure
    $columnsQuery = "SHOW COLUMNS FROM orders";
    $columnsStmt = $db->query($columnsQuery);
    $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
    debugLog("Orders table columns: " . json_encode($columns));

    // Build the query based on actual columns
    $query = "
        SELECT 
            o.id as order_id,
            o.user_id,
            o.vendor_id,
            u.username as vendor_name,
            o.order_type,
            o.payment_method,
            o.payment_status as status,
            o.total_amount,
            o.order_date,
            o.preferred_delivery_time as estimated_delivery_time,
            od.delivery_location,
            od.building_name,
            od.floor_number,
            od.room_number,
            od.contact_number,
            od.delivery_instructions,
            od.table_number
        FROM orders o
        LEFT JOIN order_delivery_details od ON o.id = od.order_id
        LEFT JOIN users u ON o.vendor_id = u.id
        WHERE o.user_id = ?
    ";

    if ($activeOnly) {
        $query .= " AND o.payment_status IN ('pending', 'processing', 'preparing', 'ready', 'delivering')";
    }

    $query .= " ORDER BY o.order_date DESC";
    debugLog("Query: " . str_replace("\n", " ", $query));

    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    debugLog("Found " . count($orders) . " orders");

    // Get order items for each order
    foreach ($orders as &$order) {
        $itemsQuery = "
            SELECT 
                oi.menu_item_id,
                mi.name,
                oi.quantity,
                oi.unit_price as price,
                mi.image_path
            FROM order_items oi
            LEFT JOIN menu_items mi ON oi.menu_item_id = mi.item_id
            WHERE oi.order_id = ?
        ";
        $itemsStmt = $db->prepare($itemsQuery);
        $itemsStmt->execute([$order['order_id']]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        debugLog("Order #" . $order['order_id'] . " has " . count($order['items']) . " items");
    }

    $response = [
        'status' => 'success',
        'message' => 'Orders retrieved successfully',
        'data' => $orders
    ];
    debugLog("Sending success response");
    echo json_encode($response);

} catch (Exception $e) {
    debugLog("ERROR: " . $e->getMessage());
    debugLog("Stack trace: " . $e->getTraceAsString());
    http_response_code(400);
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null
    ];
    debugLog("Sending error response: " . json_encode($response));
    echo json_encode($response);
} 