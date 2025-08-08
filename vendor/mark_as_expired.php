<?php
session_start();
require_once '../connection/db_connection.php';

// Set content type to JSON
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

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method!']);
    exit();
}

// Validate required fields
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Inventory ID is required!']);
    exit();
}

$inventory_id = $_POST['id'];

try {
    // Start transaction
    $conn->beginTransaction();

    // Get current inventory details
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

    // Check if already expired
    if ($inventory['status'] === 'expired') {
        throw new Exception("This item is already marked as expired!");
    }

    $current_quantity = $inventory['current_quantity'];

    // Update inventory status to expired
    $stmt = $conn->prepare("
        UPDATE inventory 
        SET status = 'expired'
        WHERE id = ? AND vendor_id = ?
    ");
    $stmt->execute([$inventory_id, $vendor_id]);

    // Record in inventory history
    $stmt = $conn->prepare("
        INSERT INTO inventory_history (
            inventory_id, vendor_id, ingredient_id, previous_quantity, new_quantity,
            change_type, batch_number, cost_per_unit, notes, changed_by
        ) VALUES (?, ?, ?, ?, ?, 'expired', ?, ?, 'Marked as expired by vendor', ?)
    ");
    $stmt->execute([
        $inventory_id,
        $vendor_id,
        $inventory['ingredient_id'],
        $current_quantity,
        0, // Set quantity to 0 when expired
        $inventory['batch_number'],
        $inventory['cost_per_unit'] ?? null,
        $_SESSION['user_id']
    ]);

    // Create expired alert if not already exists
    $stmt = $conn->prepare("
        SELECT id FROM inventory_alerts
        WHERE vendor_id = ? AND ingredient_id = ? 
        AND alert_type = 'expired' AND is_resolved = 0
    ");
    $stmt->execute([$vendor_id, $inventory['ingredient_id']]);
    
    if (!$stmt->fetch()) {
        // Create new expired alert
        $stmt = $conn->prepare("
            INSERT INTO inventory_alerts (
                vendor_id, ingredient_id, alert_type,
                alert_message
            ) VALUES (?, ?, 'expired', ?)
        ");
        $message = sprintf(
            "%s has been marked as expired. Previous quantity: %.2f %s",
            $inventory['ingredient_name'],
            $current_quantity,
            $inventory['unit']
        );
        $stmt->execute([$vendor_id, $inventory['ingredient_id'], $message]);
    }

    // Generate alerts after modification
    try {
        require_once 'check_inventory_alerts.php';
        generateInventoryAlerts($conn, $vendor_id, false); // Don't use transaction since we're already in one
    } catch (Exception $alertError) {
        // Log the alert error but don't fail the main transaction
        error_log("Alert generation error: " . $alertError->getMessage());
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Inventory item marked as expired successfully!']);
} catch (Exception $e) {
    // Rollback transaction on error if one is active
    try {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
    } catch (Exception $rollbackError) {
        // Log rollback error but don't throw it
        error_log("Rollback error: " . $rollbackError->getMessage());
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 