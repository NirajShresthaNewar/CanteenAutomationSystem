<?php
/**
 * Test script to create sample orders for testing order management
 * 
 * This file creates sample orders with different statuses to test the
 * order management system functionality.
 */

session_start();
require_once '../connection/db_connection.php';

// Create sample data only if requested
if (!isset($_GET['create']) || $_GET['create'] !== 'true') {
    echo '<h1>Create Test Orders</h1>';
    echo '<p>This script will create sample orders for testing the order management system.</p>';
    echo '<a href="?create=true" class="btn btn-primary">Create Test Orders</a>';
    exit;
}

// Helper function to generate a random receipt number
function generateReceiptNumber() {
    return 'RCP' . date('Ymd') . rand(1000, 9999);
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Check if we have users in the database
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    
    if ($userCount == 0) {
        // Create a test user if none exists
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password, contact_number, role, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            'testuser', 
            'test@example.com', 
            password_hash('password123', PASSWORD_DEFAULT), 
            '1234567890', 
            'student'
        ]);
        echo "<p>Created test user</p>";
    }
    
    // Get a user ID for the orders
    $stmt = $conn->query("SELECT id FROM users WHERE role = 'student' LIMIT 1");
    $userId = $stmt->fetchColumn();
    
    if (!$userId) {
        echo "<p>Error: No student user found</p>";
        exit;
    }
    
    // Check if we have vendors in the database
    $stmt = $conn->query("SELECT COUNT(*) FROM vendors");
    $vendorCount = $stmt->fetchColumn();
    
    if ($vendorCount == 0) {
        // Create a test vendor if none exists
        // First create a vendor user
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password, contact_number, role, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            'testvendor', 
            'vendor@example.com', 
            password_hash('password123', PASSWORD_DEFAULT), 
            '0987654321', 
            'vendor'
        ]);
        $vendorUserId = $conn->lastInsertId();
        
        // Then create a school if needed
        $stmt = $conn->query("SELECT COUNT(*) FROM schools");
        $schoolCount = $stmt->fetchColumn();
        
        if ($schoolCount == 0) {
            $stmt = $conn->prepare("
                INSERT INTO schools (name, address, contact, email)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                'Test School', 
                '123 Test Street', 
                '1122334455', 
                'school@example.com'
            ]);
        }
        
        // Get a school ID
        $stmt = $conn->query("SELECT id FROM schools LIMIT 1");
        $schoolId = $stmt->fetchColumn();
        
        // Create the vendor
        $stmt = $conn->prepare("
            INSERT INTO vendors (user_id, school_id, approval_status, opening_hours, license_number)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $vendorUserId,
            $schoolId,
            'approved',
            '9:00 AM - 5:00 PM',
            'LIC123456'
        ]);
        
        echo "<p>Created test vendor</p>";
    }
    
    // Get a vendor ID for the orders
    $stmt = $conn->query("SELECT id FROM vendors LIMIT 1");
    $vendorId = $stmt->fetchColumn();
    
    if (!$vendorId) {
        echo "<p>Error: No vendor found</p>";
        exit;
    }
    
    // Create sample orders with different statuses
    $statuses = ['pending', 'accepted', 'in_progress', 'ready', 'completed', 'cancelled'];
    $payment_methods = ['cash', 'esewa'];
    
    foreach ($statuses as $status) {
        // Create 2 orders for each status
        for ($i = 0; $i < 2; $i++) {
            $payment_method = $payment_methods[array_rand($payment_methods)];
            $total_amount = rand(100, 1000);
            $receipt_number = generateReceiptNumber();
            
            // Create the order
            $stmt = $conn->prepare("
                INSERT INTO orders (user_id, vendor_id, total_amount, payment_method, status, receipt_number, order_date)
                VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? HOUR)
            ");
            $stmt->execute([
                $userId,
                $vendorId,
                $total_amount,
                $payment_method,
                $status,
                $receipt_number,
                rand(1, 48) // Random order date within past 48 hours
            ]);
            
            $orderId = $conn->lastInsertId();
            
            // Add order items (2-4 items per order)
            $itemCount = rand(2, 4);
            $dishNames = ['Burger', 'Pizza', 'Sandwich', 'Pasta', 'Salad', 'Wrap', 'Fries', 'Soda'];
            $prices = [120, 250, 180, 220, 150, 200, 100, 80];
            
            for ($j = 0; $j < $itemCount; $j++) {
                $itemIndex = array_rand($dishNames);
                $quantity = rand(1, 3);
                $price = $prices[$itemIndex];
                
                $stmt = $conn->prepare("
                    INSERT INTO order_items (order_id, dish_name, quantity, price)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orderId,
                    $dishNames[$itemIndex],
                    $quantity,
                    $price
                ]);
            }
            
            // Add order tracking history
            // For completed or cancelled orders, add all intermediate statuses
            // For others, add all statuses up to the current one
            $trackingStatuses = [];
            
            switch ($status) {
                case 'pending':
                    $trackingStatuses = ['pending'];
                    break;
                case 'accepted':
                    $trackingStatuses = ['pending', 'accepted'];
                    break;
                case 'in_progress':
                    $trackingStatuses = ['pending', 'accepted', 'in_progress'];
                    break;
                case 'ready':
                    $trackingStatuses = ['pending', 'accepted', 'in_progress', 'ready'];
                    break;
                case 'completed':
                    $trackingStatuses = ['pending', 'accepted', 'in_progress', 'ready', 'completed'];
                    break;
                case 'cancelled':
                    // For cancelled, it could happen at any stage
                    $cancelStage = rand(0, 3);
                    if ($cancelStage == 0) {
                        $trackingStatuses = ['pending', 'cancelled'];
                    } elseif ($cancelStage == 1) {
                        $trackingStatuses = ['pending', 'accepted', 'cancelled'];
                    } elseif ($cancelStage == 2) {
                        $trackingStatuses = ['pending', 'accepted', 'in_progress', 'cancelled'];
                    } else {
                        $trackingStatuses = ['pending', 'accepted', 'in_progress', 'ready', 'cancelled'];
                    }
                    break;
            }
            
            $timeOffset = 48; // Start from 48 hours ago
            foreach ($trackingStatuses as $trackStatus) {
                $timeOffset -= rand(1, 3); // Reduce by 1-3 hours for each status
                
                $stmt = $conn->prepare("
                    INSERT INTO order_tracking (order_id, status, updated_at, status_changed_at)
                    VALUES (?, ?, NOW() - INTERVAL ? HOUR, NOW() - INTERVAL ? HOUR)
                ");
                $stmt->execute([
                    $orderId,
                    $trackStatus,
                    $timeOffset,
                    $timeOffset
                ]);
            }
        }
    }
    
    // Commit the transaction
    $conn->commit();
    
    echo "<h1>Test Orders Created Successfully</h1>";
    echo "<p>Created 2 orders for each status: pending, accepted, in_progress, ready, completed, cancelled.</p>";
    echo "<p>Total: 12 test orders.</p>";
    echo "<a href='../vendor/manage_orders.php'>Go to Manage Orders</a>";
    
} catch (Exception $e) {
    // Rollback the transaction if something failed
    $conn->rollBack();
    echo "<h1>Error</h1>";
    echo "<p>Failed to create test orders: " . $e->getMessage() . "</p>";
}
?> 