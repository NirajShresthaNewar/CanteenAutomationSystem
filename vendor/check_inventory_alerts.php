<?php
require_once '../connection/db_connection.php';

/**
 * Get all active alerts for a vendor
 * @param PDO $conn Database connection
 * @param int $vendor_id Vendor ID
 * @return array Array of alerts
 */
function getInventoryAlerts($conn, $vendor_id) {
    $alerts = [];

    try {
        // Get low stock alerts
        $stmt = $conn->prepare("
            SELECT 
                i.ingredient_id,
                ing.name as ingredient_name,
                ing.unit,
                ing.minimum_order_quantity as default_reorder_point,
                vi.reorder_point as custom_reorder_point,
                SUM(i.available_quantity) as total_available
            FROM inventory i
            JOIN ingredients ing ON i.ingredient_id = ing.id
            LEFT JOIN vendor_ingredients vi ON i.ingredient_id = vi.ingredient_id AND vi.vendor_id = i.vendor_id
            WHERE i.vendor_id = ? AND i.status = 'active'
            GROUP BY i.ingredient_id, ing.name, ing.unit, ing.minimum_order_quantity, vi.reorder_point
            HAVING total_available <= COALESCE(custom_reorder_point, default_reorder_point)
        ");
        $stmt->execute([$vendor_id]);
        $low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($low_stock_items as $item) {
            $reorder_point = $item['custom_reorder_point'] ?? $item['default_reorder_point'];
            $alerts[] = [
                'type' => 'low_stock',
                'message' => "Low stock alert for {$item['ingredient_name']}. Current stock: {$item['total_available']} {$item['unit']}. Reorder point: {$reorder_point} {$item['unit']}",
                'ingredient_id' => $item['ingredient_id'],
                'vendor_id' => $vendor_id
            ];
        }

        // Get expiring soon alerts (items expiring in the next 7 days)
        $stmt = $conn->prepare("
            SELECT 
                i.ingredient_id,
                ing.name as ingredient_name,
                ing.unit,
                i.available_quantity,
                i.expiry_date,
                DATEDIFF(i.expiry_date, CURRENT_DATE) as days_until_expiry
            FROM inventory i
            JOIN ingredients ing ON i.ingredient_id = ing.id
            WHERE i.vendor_id = ? 
            AND i.status = 'active'
            AND i.expiry_date IS NOT NULL
            AND i.expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
            AND i.available_quantity > 0
        ");
        $stmt->execute([$vendor_id]);
        $expiring_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expiring_items as $item) {
            $alerts[] = [
                'type' => 'expiring_soon',
                'message' => "{$item['ingredient_name']} ({$item['available_quantity']} {$item['unit']}) will expire in {$item['days_until_expiry']} days",
                'ingredient_id' => $item['ingredient_id'],
                'vendor_id' => $vendor_id
            ];
        }

        // Get expired alerts
        $stmt = $conn->prepare("
            SELECT 
                i.ingredient_id,
                ing.name as ingredient_name,
                ing.unit,
                i.available_quantity,
                i.expiry_date
            FROM inventory i
            JOIN ingredients ing ON i.ingredient_id = ing.id
            WHERE i.vendor_id = ? 
            AND i.status = 'active'
            AND i.expiry_date < CURRENT_DATE
            AND i.available_quantity > 0
        ");
        $stmt->execute([$vendor_id]);
        $expired_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expired_items as $item) {
            $alerts[] = [
                'type' => 'expired',
                'message' => "{$item['ingredient_name']} ({$item['available_quantity']} {$item['unit']}) has expired on {$item['expiry_date']}",
                'ingredient_id' => $item['ingredient_id'],
                'vendor_id' => $vendor_id
            ];
        }

    } catch (PDOException $e) {
        // Log error and return empty array
        error_log("Error generating inventory alerts: " . $e->getMessage());
        return [];
    }

    return $alerts;
}

/**
 * Get the count of active alerts for a vendor
 * @param PDO $conn Database connection
 * @param int $vendor_id Vendor ID
 * @return int Number of active alerts
 */
function getAlertCount($conn, $vendor_id) {
    $alerts = getInventoryAlerts($conn, $vendor_id);
    return count($alerts);
}

function generateInventoryAlerts($conn, $vendor_id, $use_transaction = true) {
    try {
        // Get current alerts
        $alerts = getInventoryAlerts($conn, $vendor_id);
        
        if (empty($alerts)) {
            return;
        }

        // Start transaction only if not already in one
        if ($use_transaction && !$conn->inTransaction()) {
            $conn->beginTransaction();
        }

        // Delete existing unresolved alerts for this vendor
        $stmt = $conn->prepare("
            DELETE FROM inventory_alerts 
            WHERE vendor_id = ? AND is_resolved = 0
        ");
        $stmt->execute([$vendor_id]);

        // Insert new alerts
        $stmt = $conn->prepare("
            INSERT INTO inventory_alerts (
                vendor_id, ingredient_id, alert_type, alert_message, created_at
            ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        foreach ($alerts as $alert) {
            $stmt->execute([
                $alert['vendor_id'],
                $alert['ingredient_id'],
                $alert['type'],
                $alert['message']
            ]);
        }

        // Commit transaction only if we started it
        if ($use_transaction && $conn->inTransaction()) {
            $conn->commit();
        }

    } catch (Exception $e) {
        // Rollback transaction on error only if we started it
        if ($use_transaction && $conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error saving inventory alerts: " . $e->getMessage());
        throw $e; // Re-throw the exception
    }
}

// If this file is called directly, return alerts as JSON
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header('Content-Type: application/json');
    
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
        echo json_encode(['error' => 'Unauthorized access']);
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vendor) {
        echo json_encode(['alerts' => getInventoryAlerts($conn, $vendor['id'])]);
    } else {
        echo json_encode(['error' => 'Vendor not found']);
    }
} 