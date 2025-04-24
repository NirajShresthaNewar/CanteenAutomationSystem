<?php
session_start();
require_once '../connection/db_connection.php';

// Set the content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access!']);
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    echo json_encode(['success' => false, 'message' => 'Vendor not found!']);
    exit();
}

$vendor_id = $vendor['id'];

// Validate input
if (!isset($_GET['alert_id']) || empty($_GET['alert_id'])) {
    echo json_encode(['success' => false, 'message' => 'Alert ID is required!']);
    exit();
}

$alert_id = $_GET['alert_id'];

try {
    // Get alert details with related information
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            i.name as ingredient_name,
            u1.name as created_by_name,
            u2.name as resolved_by_name,
            DATE_FORMAT(a.created_at, '%Y-%m-%d %H:%i:%s') as formatted_created_at,
            DATE_FORMAT(a.resolved_at, '%Y-%m-%d %H:%i:%s') as formatted_resolved_at
        FROM inventory_alerts a
        JOIN ingredients i ON a.ingredient_id = i.id
        LEFT JOIN users u1 ON a.created_by = u1.id
        LEFT JOIN users u2 ON a.resolved_by = u2.id
        WHERE a.id = ? AND a.vendor_id = ?
    ");
    $stmt->execute([$alert_id, $vendor_id]);
    $alert = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alert) {
        throw new Exception("Alert not found!");
    }

    // Format the response data
    $response = [
        'success' => true,
        'data' => [
            'ingredient_name' => $alert['ingredient_name'],
            'alert_type' => ucwords(str_replace('_', ' ', $alert['alert_type'])),
            'message' => $alert['message'],
            'created_at' => $alert['formatted_created_at'],
            'created_by' => $alert['created_by_name'],
            'is_resolved' => (bool)$alert['is_resolved'],
            'resolved_at' => $alert['formatted_resolved_at'],
            'resolved_by_name' => $alert['resolved_by_name']
        ]
    ];

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 