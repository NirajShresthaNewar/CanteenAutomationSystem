<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->connect();

    // Start transaction
    $db->beginTransaction();

    // 1. Add a school
    $school_query = "INSERT INTO schools (name, address) VALUES (?, ?)";
    $school_stmt = $db->prepare($school_query);
    $school_stmt->execute(['Sample School', '123 School Street']);
    $school_id = $db->lastInsertId();

    // 2. Add a vendor user
    $vendor_user_query = "INSERT INTO users (username, email, contact_number, role, approval_status, password) 
                         VALUES (?, ?, ?, 'vendor', 'approved', ?)";
    $vendor_stmt = $db->prepare($vendor_user_query);
    $vendor_stmt->execute(['Sample Vendor', 'vendor@example.com', '1234567890', password_hash('vendor123', PASSWORD_DEFAULT)]);
    $vendor_user_id = $db->lastInsertId();

    // 3. Add vendor record
    $vendor_query = "INSERT INTO vendors (user_id, school_id, approval_status, opening_hours) 
                    VALUES (?, ?, 'approved', '8:00 AM - 5:00 PM')";
    $vendor_stmt = $db->prepare($vendor_query);
    $vendor_stmt->execute([$vendor_user_id, $school_id]);
    $vendor_id = $db->lastInsertId();

    // 4. Add menu category
    $category_query = "INSERT INTO menu_categories (vendor_id, name, description) 
                      VALUES (?, ?, ?)";
    $category_stmt = $db->prepare($category_query);
    $category_stmt->execute([$vendor_id, 'Main Dishes', 'Our signature main courses']);
    $category_id = $db->lastInsertId();

    // 5. Add sample menu items
    $menu_items = [
        [
            'name' => 'Chicken Rice',
            'description' => 'Delicious chicken served with fragrant rice',
            'price' => 8.50,
            'is_available' => 1
        ],
        [
            'name' => 'Nasi Goreng',
            'description' => 'Malaysian style fried rice with vegetables',
            'price' => 7.00,
            'is_available' => 1
        ],
        [
            'name' => 'Roti Canai',
            'description' => 'Flaky flatbread served with curry sauce',
            'price' => 3.50,
            'is_available' => 1
        ]
    ];

    $menu_query = "INSERT INTO menu_items (vendor_id, category_id, name, description, price, is_available) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    $menu_stmt = $db->prepare($menu_query);

    foreach ($menu_items as $item) {
        $menu_stmt->execute([
            $vendor_id,
            $category_id,
            $item['name'],
            $item['description'],
            $item['price'],
            $item['is_available']
        ]);
    }

    // 6. Add a student user for testing
    $student_query = "INSERT INTO users (username, email, contact_number, role, approval_status, password) 
                     VALUES (?, ?, ?, 'student', 'approved', ?)";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->execute(['Test Student', 'student@example.com', '0987654321', password_hash('student123', PASSWORD_DEFAULT)]);
    $student_id = $db->lastInsertId();

    // 7. Link student to school
    $student_school_query = "INSERT INTO staff_students (user_id, school_id, role, approval_status) 
                            VALUES (?, ?, 'student', 'approved')";
    $student_school_stmt = $db->prepare($student_school_query);
    $student_school_stmt->execute([$student_id, $school_id]);

    // Commit transaction
    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Sample data added successfully',
        'test_credentials' => [
            'student' => [
                'email' => 'student@example.com',
                'password' => 'student123'
            ],
            'vendor' => [
                'email' => 'vendor@example.com',
                'password' => 'vendor123'
            ]
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 