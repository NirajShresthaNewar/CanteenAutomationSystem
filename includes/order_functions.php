<?php

/**
 * Creates a notification for the vendor when a new order is placed
 */
function createOrderNotification($conn, $order_id, $vendor_id, $receipt_number, $payment_method = null) {
    try {
        // Create different message based on payment method
        $message = 'New order #' . $receipt_number . ' has been placed.';
        if ($payment_method === 'cash') {
            $message .= ' Payment method: Cash on Delivery';
        }

        $stmt = $conn->prepare("
            INSERT INTO notifications (
                user_id, 
                type,
                title,
                message,
                link,
                created_at
            ) VALUES (
                (SELECT user_id FROM vendors WHERE id = ?),
                'new_order',
                'New Order Received',
                ?,
                'vendor/view_order.php?order_id=' . ?,
                CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([$vendor_id, $message, $order_id]);
    } catch (Exception $e) {
        error_log("Error creating order notification: " . $e->getMessage());
    }
}

/**
 * Handles inventory deduction when an order is accepted
 */
function processInventoryDeduction($conn, $order_id) {
    try {
        // Start transaction
        $conn->beginTransaction();

        // Get order items and their recipe ingredients
        $stmt = $conn->prepare("
            SELECT oi.menu_item_id, oi.quantity, ri.ingredient_id, ri.quantity as recipe_quantity
            FROM order_items oi
            JOIN menu_items mi ON oi.menu_item_id = mi.item_id
            JOIN recipes r ON mi.recipe_id = r.id
            JOIN recipe_ingredients ri ON r.id = ri.recipe_id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get vendor ID from order
        $stmt = $conn->prepare("SELECT vendor_id FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $vendor_id = $stmt->fetch(PDO::FETCH_COLUMN);

        // Process each ingredient
        foreach ($ingredients as $ingredient) {
            // Calculate total quantity needed
            $total_quantity_needed = $ingredient['quantity'] * $ingredient['recipe_quantity'];

            // Check if enough inventory exists
            $stmt = $conn->prepare("
                SELECT available_quantity 
                FROM inventory 
                WHERE vendor_id = ? AND ingredient_id = ?
            ");
            $stmt->execute([$vendor_id, $ingredient['ingredient_id']]);
            $current_quantity = $stmt->fetch(PDO::FETCH_COLUMN);

            if ($current_quantity < $total_quantity_needed) {
                throw new Exception("Insufficient inventory for ingredient ID: " . $ingredient['ingredient_id']);
            }

            // Update inventory
            $stmt = $conn->prepare("
                UPDATE inventory 
                SET available_quantity = available_quantity - ?
                WHERE vendor_id = ? AND ingredient_id = ?
            ");
            $stmt->execute([$total_quantity_needed, $vendor_id, $ingredient['ingredient_id']]);

            // Record deduction in order_inventory_deductions
            $stmt = $conn->prepare("
                INSERT INTO order_inventory_deductions (
                    order_id, ingredient_id, quantity_deducted, deducted_at
                ) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$order_id, $ingredient['ingredient_id'], $total_quantity_needed]);

            // Record in inventory_history
            $stmt = $conn->prepare("
                INSERT INTO inventory_history (
                    vendor_id, ingredient_id, 
                    adjustment_type, quantity,
                    reference_type, reference_id,
                    notes, created_at
                ) VALUES (
                    ?, ?, 
                    'deduction', ?,
                    'order', ?,
                    'Order deduction', CURRENT_TIMESTAMP
                )
            ");
            $stmt->execute([
                $vendor_id,
                $ingredient['ingredient_id'],
                $total_quantity_needed,
                $order_id
            ]);
        }

        $conn->commit();
        return true;

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error processing inventory deduction: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates order status and handles related operations
 */
function updateOrderStatus($conn, $order_id, $new_status, $updated_by) {
    try {
        $conn->beginTransaction();

        // Update order status
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = ? 
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $order_id]);

        // Record in order tracking
        $stmt = $conn->prepare("
            INSERT INTO order_tracking (
                order_id, status, status_changed_at, updated_by
            ) VALUES (?, ?, CURRENT_TIMESTAMP, ?)
        ");
        $stmt->execute([$order_id, $new_status, $updated_by]);

        // If order is accepted, process inventory deduction
        if ($new_status === 'accepted') {
            if (!processInventoryDeduction($conn, $order_id)) {
                throw new Exception("Failed to process inventory deduction");
            }
        }

        $conn->commit();
        return true;

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error updating order status: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates the payment status of an order and creates a notification
 * 
 * @param PDO $conn Database connection
 * @param int $order_id Order ID to update
 * @param string $payment_status New payment status ('pending', 'paid', 'cancelled', 'refunded')
 * @param string|null $payment_notes Optional notes about the payment
 * @throws PDOException If database error occurs
 * @return void
 */
function updatePaymentStatus($conn, $order_id, $payment_status, $payment_notes = null) {
    try {
        $conn->beginTransaction();

        // Update order payment status
        $stmt = $conn->prepare("
            UPDATE orders 
            SET payment_status = :status,
                payment_notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :order_id
        ");
        
        $stmt->execute([
            ':status' => $payment_status,
            ':notes' => $payment_notes,
            ':order_id' => $order_id
        ]);

        // Get order details for notification
        $stmt = $conn->prepare("
            SELECT o.user_id, o.receipt_number, o.total_amount
            FROM orders o
            WHERE o.id = :order_id
        ");
        $stmt->execute([':order_id' => $order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new PDOException("Order not found");
        }

        // Create notification message
        $message = "Payment status for order #" . $order['receipt_number'] . " has been updated to " . strtoupper($payment_status);
        if ($payment_notes) {
            $message .= ". Note: " . $payment_notes;
        }

        // Insert notification
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, message, type, reference_id)
            VALUES (:user_id, :message, 'payment', :order_id)
        ");
        
        $stmt->execute([
            ':user_id' => $order['user_id'],
            ':message' => $message,
            ':order_id' => $order['order_id']
        ]);

        $conn->commit();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}
?> 