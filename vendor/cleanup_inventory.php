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

try {
    $conn->beginTransaction();

    // 1. Mark expired items as expired
    $stmt = $conn->prepare("
        UPDATE inventory 
        SET status = 'expired' 
        WHERE vendor_id = ? 
        AND status = 'active' 
        AND expiry_date < CURRENT_DATE
        AND current_quantity > 0
    ");
    $stmt->execute([$vendor_id]);
    $expired_count = $stmt->rowCount();

    // 2. Hide completely empty batches (optional - you can comment this out if you want to keep them for history)
    $stmt = $conn->prepare("
        UPDATE inventory 
        SET status = 'hidden' 
        WHERE vendor_id = ? 
        AND status = 'active' 
        AND current_quantity <= 0
        AND available_quantity <= 0
    ");
    $stmt->execute([$vendor_id]);
    $hidden_count = $stmt->rowCount();

    // 3. Create alerts for newly expired items
    if ($expired_count > 0) {
        $stmt = $conn->prepare("
            SELECT i.*, ing.name as ingredient_name, ing.unit
            FROM inventory i
            JOIN ingredients ing ON i.ingredient_id = ing.id
            WHERE i.vendor_id = ? 
            AND i.status = 'expired' 
            AND i.expiry_date < CURRENT_DATE
            AND i.current_quantity > 0
            AND NOT EXISTS (
                SELECT 1 FROM inventory_alerts 
                WHERE ingredient_id = i.ingredient_id 
                AND vendor_id = i.vendor_id 
                AND batch_number = i.batch_number
                AND alert_type = 'expired'
                AND is_resolved = 0
            )
        ");
        $stmt->execute([$vendor_id]);
        $expired_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expired_items as $item) {
            $message = sprintf(
                "%s (%s) has expired on %s. Quantity: %.2f %s",
                $item['ingredient_name'],
                $item['batch_number'],
                date('Y-m-d', strtotime($item['expiry_date'])),
                $item['current_quantity'],
                $item['unit']
            );

            $stmt = $conn->prepare("
                INSERT INTO inventory_alerts (
                    vendor_id, ingredient_id, alert_type,
                    alert_message, created_at
                ) VALUES (?, ?, 'expired', ?, NOW())
            ");
            $stmt->execute([
                $vendor_id,
                $item['ingredient_id'],
                $message
            ]);
        }
    }

    $conn->commit();

    $_SESSION['success'] = "Inventory cleanup completed! Marked {$expired_count} items as expired and hid {$hidden_count} empty batches.";
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Error during cleanup: " . $e->getMessage();
}

header("Location: inventory.php");
exit();
?> 