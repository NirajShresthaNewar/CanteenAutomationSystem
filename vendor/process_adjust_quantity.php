<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    $_SESSION['error'] = "Vendor not found!";
    header("Location: ../auth/logout.php");
    exit();
}

$vendor_id = $vendor['id'];

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method!";
    header("Location: inventory.php");
    exit();
}

// Validate required fields
$required_fields = ['inventory_id', 'adjustment_type', 'quantity'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $_SESSION['error'] = "All required fields must be filled!";
        header("Location: inventory.php");
        exit();
    }
}

$inventory_id = $_POST['inventory_id'];
$adjustment_type = $_POST['adjustment_type'];
$quantity = floatval($_POST['quantity']);
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

try {
    // Start transaction
    $conn->beginTransaction();

    // Get current inventory details
    $stmt = $conn->prepare("
        SELECT i.*, ing.name as ingredient_name, ing.unit
        FROM inventory i
        JOIN ingredients ing ON i.ingredient_id = ing.id
        WHERE i.id = ? AND i.vendor_id = ? AND i.status = 'active'
    ");
    $stmt->execute([$inventory_id, $vendor_id]);
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventory) {
        throw new Exception("Invalid inventory item or unauthorized access!");
    }

    $current_quantity = $inventory['current_quantity'];
    $available_quantity = $inventory['available_quantity'];
    $new_quantity = $current_quantity;
    $new_available = $available_quantity;

    // Calculate new quantities based on adjustment type
    if ($adjustment_type === 'add') {
        $new_quantity += $quantity;
        $new_available += $quantity;
    } else if ($adjustment_type === 'remove') {
        if ($quantity > $available_quantity) {
            throw new Exception("Cannot remove more than available quantity!");
        }
        $new_quantity -= $quantity;
        $new_available -= $quantity;
    } else {
        throw new Exception("Invalid adjustment type!");
    }

    // Update inventory quantities
    $stmt = $conn->prepare("
        UPDATE inventory 
        SET current_quantity = ?, available_quantity = ?, 
            updated_at = CURRENT_TIMESTAMP, updated_by = ?
        WHERE id = ? AND vendor_id = ?
    ");
    $stmt->execute([$new_quantity, $new_available, $_SESSION['user_id'], $inventory_id, $vendor_id]);

    // Record in inventory history
    $stmt = $conn->prepare("
        INSERT INTO inventory_history (
            inventory_id, change_type, previous_quantity, new_quantity,
            changed_by, notes, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([
        $inventory_id,
        $adjustment_type,
        $current_quantity,
        $new_quantity,
        $_SESSION['user_id'],
        $notes
    ]);

    // Check if we need to create a low stock alert
    $stmt = $conn->prepare("
        SELECT vi.reorder_point 
        FROM vendor_ingredients vi 
        WHERE vi.vendor_id = ? AND vi.ingredient_id = ?
    ");
    $stmt->execute([$vendor_id, $inventory['ingredient_id']]);
    $vendor_ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vendor_ingredient && $new_available <= $vendor_ingredient['reorder_point']) {
        // Check if there's already an unresolved alert
        $stmt = $conn->prepare("
            SELECT id FROM inventory_alerts
            WHERE vendor_id = ? AND ingredient_id = ? 
            AND alert_type = 'low_stock' AND is_resolved = 0
        ");
        $stmt->execute([$vendor_id, $inventory['ingredient_id']]);
        
        if (!$stmt->fetch()) {
            // Create new low stock alert
            $stmt = $conn->prepare("
                INSERT INTO inventory_alerts (
                    vendor_id, ingredient_id, alert_type,
                    message, created_at, created_by
                ) VALUES (?, ?, 'low_stock', ?, CURRENT_TIMESTAMP, ?)
            ");
            $message = sprintf(
                "Low stock alert for %s (%s). Current available quantity: %.2f %s",
                $inventory['ingredient_name'],
                $inventory['unit'],
                $new_available,
                $inventory['unit']
            );
            $stmt->execute([$vendor_id, $inventory['ingredient_id'], $message, $_SESSION['user_id']]);
        }
    }

    // Generate alerts after modification
    require_once 'check_inventory_alerts.php';
    generateInventoryAlerts($conn, $vendor_id);

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Inventory quantity adjusted successfully!";
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    $_SESSION['error'] = $e->getMessage();
}

header("Location: inventory.php");
exit();
?> 