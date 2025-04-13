<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->connect();

    // 1. Add a test school
    $school_query = "INSERT INTO schools (name, address) 
                    VALUES ('Test School', '123 Test Street') 
                    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $db->exec($school_query);
    $school_id = $db->lastInsertId();

    // 2. Add a test vendor user
    $vendor_user_query = "INSERT INTO users (username, email, contact_number, role, password, approval_status) 
                         VALUES ('test_vendor', 'vendor@test.com', '1234567890', 'vendor', 'password', 'approved') 
                         ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $db->exec($vendor_user_query);
    $vendor_user_id = $db->lastInsertId();

    // 3. Add vendor record
    $vendor_query = "INSERT INTO vendors (user_id, school_id, approval_status) 
                    VALUES (?, ?, 'approved') 
                    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
    $stmt = $db->prepare($vendor_query);
    $stmt->execute([$vendor_user_id, $school_id]);
    $vendor_id = $db->lastInsertId();

    // 4. Update student's school association
    $student_query = "INSERT INTO staff_students (user_id, school_id, role, approval_status) 
                     VALUES (?, ?, 'student', 'approved') 
                     ON DUPLICATE KEY UPDATE school_id = VALUES(school_id)";
    $stmt = $db->prepare($student_query);
    $stmt->execute([$_GET['user_id'], $school_id]);

    // 5. Add test menu items if none exist
    $check_items = "SELECT COUNT(*) as count FROM menu_items WHERE vendor_id = ?";
    $stmt = $db->prepare($check_items);
    $stmt->execute([$vendor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] == 0) {
        $menu_items = [
            [
                'name' => 'Test Dish 1',
                'description' => 'A delicious test dish',
                'price' => 15.00,
                'is_vegetarian' => 1,
                'is_available' => 1
            ],
            [
                'name' => 'Test Dish 2',
                'description' => 'Another tasty test dish',
                'price' => 20.00,
                'is_vegetarian' => 0,
                'is_available' => 1
            ]
        ];

        $item_query = "INSERT INTO menu_items (vendor_id, name, description, price, is_vegetarian, is_available) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($item_query);

        foreach ($menu_items as $item) {
            $stmt->execute([
                $vendor_id,
                $item['name'],
                $item['description'],
                $item['price'],
                $item['is_vegetarian'],
                $item['is_available']
            ]);
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Test data setup completed',
        'data' => [
            'school_id' => $school_id,
            'vendor_id' => $vendor_id,
            'student_id' => $_GET['user_id']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 