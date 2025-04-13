<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

try {
    echo "Connecting to database...\n";
    $database = new Database();
    $db = $database->connect();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "Database connected successfully!\n\n";
    
    // Test query to check if menu items exist
    echo "Checking menu items...\n";
    $query = "SELECT COUNT(*) as count FROM menu_items";
    $stmt = $db->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total menu items: " . $result['count'] . "\n\n";
    
    // Test query to check vendors
    echo "Checking vendors...\n";
    $query = "SELECT COUNT(*) as count FROM vendors WHERE approval_status = 'approved'";
    $stmt = $db->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total approved vendors: " . $result['count'] . "\n\n";
    
    // Test full menu items query
    echo "Fetching menu items details...\n";
    $query = "SELECT 
                v.id as vendor_id,
                u.username as vendor_name,
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
            JOIN menu_items mi ON mi.vendor_id = v.id
            LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
            WHERE v.approval_status = 'approved' AND mi.is_available = 1
            ORDER BY v.id, mc.name, mi.name";
            
    $stmt = $db->query($query);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Menu items details:\n";
    print_r($items);
    
    // Add a test school if not exists
    $school_query = "INSERT INTO schools (name, address) VALUES ('Test School', 'Test Address') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $db->exec($school_query);
    $school_id = $db->lastInsertId();

    // Add a test vendor if not exists
    $vendor_query = "INSERT INTO users (username, email, contact_number, role, password) 
                    VALUES ('test_vendor', 'vendor@test.com', '1234567890', 'vendor', 'password') 
                    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $db->exec($vendor_query);
    $user_id = $db->lastInsertId();

    $vendor_query = "INSERT INTO vendors (user_id, school_id, approval_status) 
                    VALUES (?, ?, 'approved') 
                    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $stmt = $db->prepare($vendor_query);
    $stmt->execute([$user_id, $school_id]);
    $vendor_id = $db->lastInsertId();

    // Add test menu items
    $menu_items = [
        [
            'name' => 'Test Item 1',
            'description' => 'Description for test item 1',
            'price' => 10.00,
            'is_vegetarian' => 1,
            'is_available' => 1
        ],
        [
            'name' => 'Test Item 2',
            'description' => 'Description for test item 2',
            'price' => 15.00,
            'is_vegetarian' => 0,
            'is_available' => 1
        ]
    ];

    foreach ($menu_items as $item) {
        $query = "INSERT INTO menu_items (vendor_id, name, description, price, is_vegetarian, is_available) 
                 VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $vendor_id,
            $item['name'],
            $item['description'],
            $item['price'],
            $item['is_vegetarian'],
            $item['is_available']
        ]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Test data added successfully',
        'data' => [
            'school_id' => $school_id,
            'vendor_id' => $vendor_id,
            'menu_items' => $menu_items
        ]
    ]);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 