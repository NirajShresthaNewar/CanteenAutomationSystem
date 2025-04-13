<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->connect();

    // Check schools
    $schools_query = "SELECT COUNT(*) as count FROM schools";
    $schools_result = $db->query($schools_query)->fetch(PDO::FETCH_ASSOC);
    echo "Schools count: " . $schools_result['count'] . "\n";

    // Check approved vendors
    $vendors_query = "SELECT COUNT(*) as count FROM vendors WHERE approval_status = 'approved'";
    $vendors_result = $db->query($vendors_query)->fetch(PDO::FETCH_ASSOC);
    echo "Approved vendors count: " . $vendors_result['count'] . "\n";

    // Check menu items
    $items_query = "SELECT COUNT(*) as count FROM menu_items WHERE is_available = 1";
    $items_result = $db->query($items_query)->fetch(PDO::FETCH_ASSOC);
    echo "Available menu items count: " . $items_result['count'] . "\n";

    // Get detailed menu items info
    $details_query = "SELECT 
        v.id as vendor_id,
        u.username as vendor_name,
        v.opening_hours,
        mi.name as item_name,
        mi.price,
        mi.is_available
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    JOIN menu_items mi ON mi.vendor_id = v.id
    WHERE v.approval_status = 'approved'";
    
    $details_result = $db->query($details_query);
    echo "\nMenu Items Details:\n";
    while ($row = $details_result->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 