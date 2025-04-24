<?php
require_once '../connection/db_connection.php';

/**
 * Generates inventory alerts for low stock, expiring soon, and expired stock
 * @param PDO $conn Database connection
 * @param int|null $specific_vendor_id Optional vendor ID to generate alerts for specific vendor only
 * @return bool Success status
 */
function generateInventoryAlerts($conn, $specific_vendor_id = null) {
    try {
        $conn->beginTransaction();

        // Build vendor filter
        $vendor_filter = "";
        $vendor_params = [];
        if ($specific_vendor_id !== null) {
            $vendor_filter = "AND vi.vendor_id = ?";
            $vendor_params[] = $specific_vendor_id;
        }

        // Check for low stock alerts
        $low_stock_query = $conn->prepare("
            SELECT 
                i.id as ingredient_id,
                i.name,
                i.unit,
                vi.vendor_id,
                vi.reorder_point,
                SUM(COALESCE(inv.available_quantity, 0)) as total_available_quantity,
                SUM(COALESCE(inv.reserved_quantity, 0)) as total_reserved_quantity,
                v.name as vendor_name
            FROM vendor_ingredients vi
            JOIN ingredients i ON vi.ingredient_id = i.id
            JOIN vendors v ON vi.vendor_id = v.id
            LEFT JOIN inventory inv ON i.id = inv.ingredient_id 
                AND inv.vendor_id = vi.vendor_id
                AND inv.status = 'active'
                AND inv.expiry_date > NOW()
            WHERE vi.is_preferred_supplier = 1 
            GROUP BY i.id, vi.vendor_id
            HAVING (total_available_quantity - total_reserved_quantity) <= vi.reorder_point
            AND NOT EXISTS (
                SELECT 1 FROM inventory_alerts 
                WHERE ingredient_id = i.id 
                AND vendor_id = vi.vendor_id 
                AND alert_type = 'low_stock'
                AND is_resolved = 0
            )
            $vendor_filter
        ");
        
        $low_stock_query->execute($vendor_params);
        $low_stock_items = $low_stock_query->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found " . count($low_stock_items) . " low stock items" . ($specific_vendor_id ? " for vendor $specific_vendor_id" : ""));

        foreach ($low_stock_items as $item) {
            $available = $item['total_available_quantity'] - $item['total_reserved_quantity'];
            $message = sprintf(
                "Low stock alert for %s. Available stock: %.2f %s (Reserved: %.2f %s, Reorder point: %.2f %s)",
                $item['name'],
                $available,
                $item['unit'],
                $item['total_reserved_quantity'],
                $item['unit'],
                $item['reorder_point'],
                $item['unit']
            );

            // Insert alert
            $insert_alert = $conn->prepare("
                INSERT INTO inventory_alerts (
                    ingredient_id, vendor_id, alert_type, alert_message, created_at
                ) VALUES (?, ?, 'low_stock', ?, NOW())
            ");
            
            $insert_alert->execute([
                $item['ingredient_id'],
                $item['vendor_id'],
                $message
            ]);

            // Create vendor notification
            createVendorNotification(
                $conn,
                $item['vendor_id'],
                'Low Stock Alert',
                $message
            );
        }

        // Build expiry filter
        $expiry_filter = $vendor_filter;

        // Check for expiring soon items (within 7 days)
        $expiring_soon_query = $conn->prepare("
            SELECT 
                i.id as ingredient_id,
                i.name,
                i.unit,
                inv.vendor_id,
                inv.batch_number,
                inv.available_quantity,
                inv.reserved_quantity,
                inv.expiry_date,
                inv.cost_per_unit,
                v.name as vendor_name
            FROM inventory inv
            JOIN ingredients i ON inv.ingredient_id = i.id
            JOIN vendors v ON inv.vendor_id = v.id
            WHERE inv.expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            AND inv.available_quantity > 0
            AND inv.status = 'active'
            AND NOT EXISTS (
                SELECT 1 FROM inventory_alerts 
                WHERE ingredient_id = i.id 
                AND vendor_id = inv.vendor_id 
                AND batch_number = inv.batch_number
                AND alert_type = 'expiring_soon'
                AND is_resolved = 0
            )
            $expiry_filter
        ");
        
        $expiring_soon_query->execute($vendor_params);
        $expiring_items = $expiring_soon_query->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found " . count($expiring_items) . " expiring items" . ($specific_vendor_id ? " for vendor $specific_vendor_id" : ""));

        foreach ($expiring_items as $item) {
            $days_until_expiry = round((strtotime($item['expiry_date']) - time()) / (60 * 60 * 24));
            $available = $item['available_quantity'] - $item['reserved_quantity'];
            $message = sprintf(
                "%s will expire in %d days. Available stock: %.2f %s (Reserved: %.2f %s)",
                $item['name'],
                $days_until_expiry,
                $available,
                $item['unit'],
                $item['reserved_quantity'],
                $item['unit']
            );

            // Insert alert
            $insert_alert = $conn->prepare("
                INSERT INTO inventory_alerts (
                    ingredient_id, vendor_id, alert_type, alert_message, created_at
                ) VALUES (?, ?, 'expiring_soon', ?, NOW())
            ");
            
            $insert_alert->execute([
                $item['ingredient_id'],
                $item['vendor_id'],
                $message
            ]);

            // Create vendor notification
            createVendorNotification(
                $conn,
                $item['vendor_id'],
                'Expiring Soon Alert',
                $message
            );
        }

        // Check for expired items
        $expired_query = $conn->prepare("
            SELECT 
                i.id as ingredient_id,
                i.name,
                i.unit,
                inv.vendor_id,
                inv.batch_number,
                inv.available_quantity,
                inv.reserved_quantity,
                inv.expiry_date,
                inv.cost_per_unit,
                v.name as vendor_name
            FROM inventory inv
            JOIN ingredients i ON inv.ingredient_id = i.id
            JOIN vendors v ON inv.vendor_id = v.id
            WHERE inv.expiry_date < NOW()
            AND inv.available_quantity > 0
            AND inv.status = 'active'
            AND NOT EXISTS (
                SELECT 1 FROM inventory_alerts 
                WHERE ingredient_id = i.id 
                AND vendor_id = inv.vendor_id 
                AND batch_number = inv.batch_number
                AND alert_type = 'expired'
                AND is_resolved = 0
            )
            $expiry_filter
        ");
        
        $expired_query->execute($vendor_params);
        $expired_items = $expired_query->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found " . count($expired_items) . " expired items" . ($specific_vendor_id ? " for vendor $specific_vendor_id" : ""));

        foreach ($expired_items as $item) {
            $available = $item['available_quantity'] - $item['reserved_quantity'];
            $message = sprintf(
                "%s has expired on %s. Available stock: %.2f %s (Reserved: %.2f %s)",
                $item['name'],
                date('Y-m-d', strtotime($item['expiry_date'])),
                $available,
                $item['unit'],
                $item['reserved_quantity'],
                $item['unit']
            );

            // Insert alert
            $insert_alert = $conn->prepare("
                INSERT INTO inventory_alerts (
                    ingredient_id, vendor_id, alert_type, alert_message, created_at
                ) VALUES (?, ?, 'expired', ?, NOW())
            ");
            
            $insert_alert->execute([
                $item['ingredient_id'],
                $item['vendor_id'],
                $message
            ]);

            // Create vendor notification
            createVendorNotification(
                $conn,
                $item['vendor_id'],
                'Expired Stock Alert',
                $message
            );
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error generating inventory alerts: " . $e->getMessage());
        return false;
    }
}

/**
 * Creates a notification for a vendor
 * @param PDO $conn Database connection
 * @param int $vendor_id Vendor ID
 * @param string $title Notification title
 * @param string $message Notification message
 * @return bool Success status
 */
function createVendorNotification($conn, $vendor_id, $title, $message) {
    try {
        $insert = $conn->prepare("
            INSERT INTO vendor_notifications (
                vendor_id, title, message, created_at, is_read
            ) VALUES (?, ?, ?, NOW(), 0)
        ");
        return $insert->execute([$vendor_id, $title, $message]);
    } catch (Exception $e) {
        error_log("Error creating vendor notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Marks an inventory alert as resolved
 * @param PDO $conn Database connection
 * @param int $alert_id Alert ID
 * @return bool Success status
 */
function resolveInventoryAlert($conn, $alert_id) {
    try {
        $update = $conn->prepare("
            UPDATE inventory_alerts 
            SET is_resolved = 1, resolved_at = NOW() 
            WHERE id = ?
        ");
        return $update->execute([$alert_id]);
    } catch (Exception $e) {
        error_log("Error resolving inventory alert: " . $e->getMessage());
        return false;
    }
} 