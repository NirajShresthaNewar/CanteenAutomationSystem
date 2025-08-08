<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access!']);
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    http_response_code(403);
    echo json_encode(['error' => 'Vendor not found!']);
    exit();
}

$vendor_id = $vendor['id'];

// Validate inventory_id
if (!isset($_GET['inventory_id']) || empty($_GET['inventory_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Inventory ID is required!']);
    exit();
}

$inventory_id = $_GET['inventory_id'];

try {
    // Verify the inventory item belongs to the vendor
    $stmt = $conn->prepare("
        SELECT i.*, ing.name as ingredient_name, ing.unit
        FROM inventory i
        JOIN ingredients ing ON i.ingredient_id = ing.id
        WHERE i.id = ? AND i.vendor_id = ?
    ");
    $stmt->execute([$inventory_id, $vendor_id]);
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventory) {
        throw new Exception("Invalid inventory item or unauthorized access!");
    }

    // Get inventory history
    $stmt = $conn->prepare("
        SELECT 
            ih.*,
            u.username as changed_by_name,
            DATE_FORMAT(ih.created_at, '%Y-%m-%d %H:%i:%s') as formatted_date
        FROM inventory_history ih
        JOIN users u ON ih.changed_by = u.id
        WHERE ih.inventory_id = ?
        ORDER BY ih.created_at DESC
    ");
    $stmt->execute([$inventory_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare response data
    $response = [
        'inventory' => [
            'ingredient_name' => $inventory['ingredient_name'],
            'unit' => $inventory['unit'],
            'current_quantity' => $inventory['current_quantity'],
            'available_quantity' => $inventory['available_quantity']
        ],
        'history' => array_map(function($record) {
            return [
                'date' => $record['formatted_date'],
                'type' => ucfirst($record['change_type']),
                'previous' => number_format($record['previous_quantity'], 2),
                'new' => number_format($record['new_quantity'], 2),
                'changed_by' => $record['changed_by_name'],
                'notes' => $record['notes'] ?: 'No notes provided'
            ];
        }, $history)
    ];

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 