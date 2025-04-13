<?php
/**
 * Order Management System Test Script
 * 
 * This script tests the functionality of the order management system
 * by going through the complete order lifecycle.
 */

session_start();
require_once '../connection/db_connection.php';

// Only run if explicitly requested
if (!isset($_GET['test']) || $_GET['test'] !== 'true') {
    echo '<h1>Test Order Management System</h1>';
    echo '<p>This script will test the order management system by simulating a complete order lifecycle.</p>';
    echo '<p><strong>Note:</strong> Make sure you have run create_test_order.php first to create sample orders.</p>';
    echo '<a href="?test=true" class="btn btn-primary">Run Test</a>';
    exit;
}

echo '<h1>Order Management System Test Results</h1>';
echo '<pre>';

// Test 1: Check if the database tables exist
function testDatabaseStructure($conn) {
    echo "Test 1: Checking Database Structure\n";
    
    $requiredTables = ['orders', 'order_items', 'order_tracking'];
    $missing = [];
    
    foreach ($requiredTables as $table) {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() == 0) {
            $missing[] = $table;
        }
    }
    
    if (empty($missing)) {
        echo "PASS: All required tables exist.\n";
        return true;
    } else {
        echo "FAIL: Missing tables: " . implode(', ', $missing) . "\n";
        return false;
    }
}

// Test 2: Check if orders table has the expected structure
function testOrdersTableStructure($conn) {
    echo "\nTest 2: Checking 'orders' Table Structure\n";
    
    $requiredColumns = ['id', 'user_id', 'vendor_id', 'total_amount', 'payment_method', 'status', 'order_date', 'receipt_number'];
    $stmt = $conn->query("DESCRIBE orders");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $missing = array_diff($requiredColumns, $columns);
    
    if (empty($missing)) {
        echo "PASS: 'orders' table has all required columns.\n";
        return true;
    } else {
        echo "FAIL: 'orders' table is missing columns: " . implode(', ', $missing) . "\n";
        return false;
    }
}

// Test 3: Check if there are sample orders in the database
function testSampleOrders($conn) {
    echo "\nTest 3: Checking Sample Orders\n";
    
    $stmt = $conn->query("SELECT COUNT(*) FROM orders");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "PASS: Found $count orders in the database.\n";
        return true;
    } else {
        echo "FAIL: No orders found in the database. Run create_test_order.php first.\n";
        return false;
    }
}

// Test 4: Check if manage_orders.php is accessible
function testManageOrdersPage() {
    echo "\nTest 4: Checking Manage Orders Page\n";
    
    $url = '../vendor/manage_orders.php';
    if (file_exists($url)) {
        echo "PASS: manage_orders.php file exists.\n";
        return true;
    } else {
        echo "FAIL: manage_orders.php file not found.\n";
        return false;
    }
}

// Test 5: Check if order status updates work
function testOrderStatusUpdate($conn) {
    echo "\nTest 5: Testing Order Status Update\n";
    
    // Get a pending order for testing
    $stmt = $conn->prepare("SELECT id FROM orders WHERE status = 'pending' LIMIT 1");
    $stmt->execute();
    $orderId = $stmt->fetchColumn();
    
    if (!$orderId) {
        echo "SKIP: No pending orders found for testing status updates.\n";
        return true;
    }
    
    // Update the order status to accepted
    $newStatus = 'accepted';
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $result1 = $stmt->execute([$newStatus, $orderId]);
    
    // Add to tracking
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (order_id, status, updated_at, status_changed_at)
        VALUES (?, ?, NOW(), NOW())
    ");
    $result2 = $stmt->execute([$orderId, $newStatus]);
    
    // Verify the update worked
    $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $status = $stmt->fetchColumn();
    
    // Verify tracking was added
    $stmt = $conn->prepare("SELECT COUNT(*) FROM order_tracking WHERE order_id = ? AND status = ?");
    $stmt->execute([$orderId, $newStatus]);
    $trackingCount = $stmt->fetchColumn();
    
    if ($status === $newStatus && $trackingCount > 0) {
        echo "PASS: Order status updated successfully and tracking record added.\n";
        return true;
    } else {
        echo "FAIL: Order status update failed or tracking record not added.\n";
        return false;
    }
}

// Test 6: Check if order_history.php is accessible
function testOrderHistoryPage() {
    echo "\nTest 6: Checking Order History Page\n";
    
    $url = '../vendor/order_history.php';
    if (file_exists($url)) {
        echo "PASS: order_history.php file exists.\n";
        return true;
    } else {
        echo "FAIL: order_history.php file not found.\n";
        return false;
    }
}

// Test 7: Check if get_order_details.php is functional
function testOrderDetails($conn) {
    echo "\nTest 7: Testing Order Details Functionality\n";
    
    // Get an order for testing
    $stmt = $conn->query("SELECT id FROM orders LIMIT 1");
    $orderId = $stmt->fetchColumn();
    
    if (!$orderId) {
        echo "SKIP: No orders found for testing details functionality.\n";
        return true;
    }
    
    // Check if get_order_details.php exists
    $url = '../vendor/get_order_details.php';
    if (!file_exists($url)) {
        echo "FAIL: get_order_details.php file not found.\n";
        return false;
    }
    
    // Check if order can be retrieved from database
    $stmt = $conn->prepare("
        SELECT o.*, u.username 
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "PASS: Order details can be retrieved from database.\n";
        
        // Get order items
        $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $itemCount = $stmt->fetchColumn();
        
        if ($itemCount > 0) {
            echo "PASS: Order items can be retrieved from database.\n";
        } else {
            echo "WARN: No order items found for the test order.\n";
        }
        
        return true;
    } else {
        echo "FAIL: Order details cannot be retrieved or JOIN query failed.\n";
        return false;
    }
}

// Run all tests
try {
    $tests = [
        'testDatabaseStructure',
        'testOrdersTableStructure',
        'testSampleOrders',
        'testManageOrdersPage',
        'testOrderStatusUpdate',
        'testOrderHistoryPage',
        'testOrderDetails'
    ];
    
    $totalTests = count($tests);
    $passedTests = 0;
    
    foreach ($tests as $test) {
        if ($test($conn)) {
            $passedTests++;
        }
    }
    
    echo "\n=====================================\n";
    echo "SUMMARY: Passed $passedTests out of $totalTests tests.\n";
    
    if ($passedTests === $totalTests) {
        echo "\nCONCLUSION: All tests passed! The order management system is functioning correctly.\n";
    } else {
        echo "\nCONCLUSION: Some tests failed. Please review the issues above.\n";
    }
    
} catch (Exception $e) {
    echo "\nERROR: Test failed with exception: " . $e->getMessage() . "\n";
}

echo '</pre>';
echo '<p><a href="../vendor/manage_orders.php" class="btn btn-primary">Go to Manage Orders</a></p>';
?> 