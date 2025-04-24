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
if (!isset($_POST['alert_id']) || empty($_POST['alert_id'])) {
    echo json_encode(['success' => false, 'message' => 'Alert ID is required!']);
    exit();
}

$alert_id = $_POST['alert_id'];

try {
    // Start transaction
    $conn->beginTransaction();

    // Verify the alert belongs to the vendor and is not already resolved
    $stmt = $conn->prepare("
        SELECT * FROM inventory_alerts 
        WHERE id = ? AND vendor_id = ? AND is_resolved = 0
    ");
    $stmt->execute([$alert_id, $vendor_id]);
    $alert = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alert) {
        throw new Exception("Invalid alert or already resolved!");
    }

    // Update the alert
    $stmt = $conn->prepare("
        UPDATE inventory_alerts 
        SET is_resolved = 1,
            resolved_at = CURRENT_TIMESTAMP,
            resolved_by = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $alert_id]);

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 