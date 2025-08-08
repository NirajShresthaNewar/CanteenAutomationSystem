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

try {
// Validate required fields
$required_fields = ['inventory_id', 'adjustment_type', 'quantity'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("All required fields must be filled!");
    }
}

$inventory_id = $_POST['inventory_id'];
$adjustment_type = $_POST['adjustment_type'];
$quantity = floatval($_POST['quantity']);
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $new_expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $cost_per_unit = isset($_POST['cost_per_unit']) ? floatval($_POST['cost_per_unit']) : null;
    $supplier = isset($_POST['supplier']) ? trim($_POST['supplier']) : null;

    // Log the values for debugging
    error_log("Adjusting inventory: ID=$inventory_id, Type=$adjustment_type, Qty=$quantity");

    // Test database connection
    if (!$conn) {
        throw new Exception("Database connection failed!");
    }

    // Start transaction
    $conn->beginTransaction();

    if ($adjustment_type === 'add') {
        // For adding inventory, we need to check if we should create a new batch
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

        // Check if we need a new expiry date
        if ($new_expiry_date && $new_expiry_date !== $inventory['expiry_date']) {
            // Create a new batch with different expiry date
            $ingredient_id = $inventory['ingredient_id'];
            
            // Generate new batch number
            $ingredient_prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $inventory['ingredient_name']), 0, 3));
            $date_prefix = date('Ym');
            
            // Get current sequence for this ingredient
            $stmt = $conn->prepare("
                SELECT current_sequence 
                FROM batch_sequences 
                WHERE vendor_id = ? AND ingredient_id = ? AND year = ? AND month = ?
                FOR UPDATE
            ");
            $stmt->execute([
                $vendor_id,
                $ingredient_id,
                date('Y'),
                date('n')
            ]);
            $sequence = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($sequence) {
                $new_sequence = $sequence['current_sequence'] + 1;
                $stmt = $conn->prepare("
                    UPDATE batch_sequences 
                    SET current_sequence = ? 
                    WHERE vendor_id = ? AND ingredient_id = ? AND year = ? AND month = ?
                ");
                $stmt->execute([
                    $new_sequence,
                    $vendor_id,
                    $ingredient_id,
                    date('Y'),
                    date('n')
                ]);
            } else {
                $new_sequence = 1;
                $stmt = $conn->prepare("
                    INSERT INTO batch_sequences (vendor_id, ingredient_id, year, month, current_sequence)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $vendor_id,
                    $ingredient_id,
                    date('Y'),
                    date('n'),
                    $new_sequence
                ]);
            }

            $new_batch_number = sprintf("%s%s-%03d", $ingredient_prefix, $date_prefix, $new_sequence);

            // Create new inventory record
            $stmt = $conn->prepare("
                INSERT INTO inventory (
                    vendor_id, ingredient_id, current_quantity,
                    batch_number, expiry_date, status
                ) VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $vendor_id,
                $ingredient_id,
                $quantity,
                $new_batch_number,
                $new_expiry_date
            ]);
            
            // Insert or update cost in vendor_ingredients if provided
            if ($cost_per_unit !== null) {
                $stmt = $conn->prepare("
                    INSERT INTO vendor_ingredients (vendor_id, ingredient_id, cost_per_unit, created_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE cost_per_unit = VALUES(cost_per_unit)
                ");
                $stmt->execute([$vendor_id, $ingredient_id, $cost_per_unit]);
            }
            $new_inventory_id = $conn->lastInsertId();

            // Record in inventory history
            $stmt = $conn->prepare("
                INSERT INTO inventory_history (
                    inventory_id, vendor_id, ingredient_id, previous_quantity, new_quantity,
                    change_type, batch_number, cost_per_unit, notes, changed_by
                ) VALUES (?, ?, ?, 0, ?, 'add', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $new_inventory_id,
                $vendor_id,
                $ingredient_id,
                $quantity,
                $new_batch_number,
                $cost_per_unit,
                $notes,
                $_SESSION['user_id']
            ]);

            $_SESSION['success'] = "New batch created successfully! Batch number: {$new_batch_number}";
        } else if ($new_expiry_date) {
            // Check if there's already a batch with the same expiry date
            // We'll create new batch if cost is different or if user specifies different supplier
            $stmt = $conn->prepare("
                SELECT id, current_quantity
                FROM inventory 
                WHERE vendor_id = ? AND ingredient_id = ? AND expiry_date = ? AND status = 'active'
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$vendor_id, $inventory['ingredient_id'], $new_expiry_date]);
            $existing_batch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if cost is significantly different (more than 5% difference)
            $cost_different = false;
            if ($existing_batch && $cost_per_unit) {
                // Get the cost from the most recent history entry for this batch
                $stmt = $conn->prepare("
                    SELECT cost_per_unit 
                    FROM inventory_history 
                    WHERE inventory_id = ? AND change_type = 'add'
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$existing_batch['id']]);
                $existing_cost = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_cost && $existing_cost['cost_per_unit']) {
                    $cost_diff_percent = abs(($cost_per_unit - $existing_cost['cost_per_unit']) / $existing_cost['cost_per_unit']) * 100;
                    if ($cost_diff_percent > 5) { // More than 5% difference
                        $cost_different = true;
                    }
                }
            }
            
            // If cost is significantly different, treat as new batch
            if ($cost_different) {
                $existing_batch = false; // Force new batch creation
            }
            
            // Check if supplier is different
            if ($existing_batch && $supplier) {
                // Get the supplier from the most recent history entry for this batch
                $stmt = $conn->prepare("
                    SELECT notes 
                    FROM inventory_history 
                    WHERE inventory_id = ? AND change_type = 'add'
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$existing_batch['id']]);
                $existing_notes = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_notes && $existing_notes['notes'] && 
                    stripos($existing_notes['notes'], $supplier) === false) {
                    // Different supplier, create new batch
                    $existing_batch = false;
                }
            }
            
            if ($existing_batch) {
                // Add to existing batch with same expiry date
                $current_quantity = $existing_batch['current_quantity'];
                $new_quantity = $current_quantity + $quantity;

                // Update inventory quantities
                $stmt = $conn->prepare("
                    UPDATE inventory 
                    SET current_quantity = ?
                    WHERE id = ?
                ");
                $stmt->execute([$new_quantity, $existing_batch['id']]);
                
                // Update or insert cost in vendor_ingredients if provided
                if ($cost_per_unit !== null) {
                    $stmt = $conn->prepare("
                        INSERT INTO vendor_ingredients (vendor_id, ingredient_id, cost_per_unit, created_at)
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE cost_per_unit = VALUES(cost_per_unit)
                    ");
                    $stmt->execute([$vendor_id, $existing_batch['ingredient_id'], $cost_per_unit]);
                }

                // Record in inventory history
                $stmt = $conn->prepare("
                    INSERT INTO inventory_history (
                        inventory_id, vendor_id, ingredient_id, previous_quantity, new_quantity,
                        change_type, batch_number, cost_per_unit, notes, changed_by
                    ) VALUES (?, ?, ?, ?, ?, 'add', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $existing_batch['id'],
                    $vendor_id,
                    $inventory['ingredient_id'],
                    $current_quantity,
                    $new_quantity,
                    $existing_batch['batch_number'],
                    $cost_per_unit,
                    $notes,
                    $_SESSION['user_id']
                ]);

                $_SESSION['success'] = "Inventory quantity added to existing batch successfully!";
                if ($supplier) {
                    $_SESSION['success'] .= " Supplier: " . $supplier;
        }
            } else {
                // Create new batch with same expiry date (different from current batch)
                $ingredient_id = $inventory['ingredient_id'];
                
                // Generate new batch number
                $ingredient_prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $inventory['ingredient_name']), 0, 3));
                $date_prefix = date('Ym');
                
                // Get current sequence for this ingredient
                $stmt = $conn->prepare("
                    SELECT current_sequence 
                    FROM batch_sequences 
                    WHERE vendor_id = ? AND ingredient_id = ? AND year = ? AND month = ?
                    FOR UPDATE
                ");
                $stmt->execute([
                    $vendor_id,
                    $ingredient_id,
                    date('Y'),
                    date('n')
                ]);
                $sequence = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($sequence) {
                    $new_sequence = $sequence['current_sequence'] + 1;
                    $stmt = $conn->prepare("
                        UPDATE batch_sequences 
                        SET current_sequence = ? 
                        WHERE vendor_id = ? AND ingredient_id = ? AND year = ? AND month = ?
                    ");
                    $stmt->execute([
                        $new_sequence,
                        $vendor_id,
                        $ingredient_id,
                        date('Y'),
                        date('n')
                    ]);
    } else {
                    $new_sequence = 1;
                    $stmt = $conn->prepare("
                        INSERT INTO batch_sequences (vendor_id, ingredient_id, year, month, current_sequence)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $vendor_id,
                        $ingredient_id,
                        date('Y'),
                        date('n'),
                        $new_sequence
                    ]);
                }

                $new_batch_number = sprintf("%s%s-%03d", $ingredient_prefix, $date_prefix, $new_sequence);

                // Create new inventory record
                $stmt = $conn->prepare("
                    INSERT INTO inventory (
                        vendor_id, ingredient_id, current_quantity,
                        batch_number, expiry_date, status
                    ) VALUES (?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $vendor_id,
                    $ingredient_id,
                    $quantity,
                    $new_batch_number,
                    $new_expiry_date
                ]);
                $new_inventory_id = $conn->lastInsertId();

                // Record in inventory history
                $stmt = $conn->prepare("
                    INSERT INTO inventory_history (
                        inventory_id, vendor_id, ingredient_id, previous_quantity, new_quantity,
                        change_type, batch_number, cost_per_unit, notes, changed_by
                    ) VALUES (?, ?, ?, 0, ?, 'add', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $new_inventory_id,
                    $vendor_id,
                    $ingredient_id,
                    $quantity,
                    $new_batch_number,
                    $cost_per_unit,
                    $notes,
                    $_SESSION['user_id']
                ]);

                $_SESSION['success'] = "New batch created successfully! Batch number: {$new_batch_number}";
                if ($supplier) {
                    $_SESSION['success'] .= " Supplier: " . $supplier;
    }
            }
        } else {
            // Add to existing batch (no new expiry date provided)
            $current_quantity = $inventory['current_quantity'];
            $new_quantity = $current_quantity + $quantity;

    // Update inventory quantities
    $stmt = $conn->prepare("
        UPDATE inventory 
        SET current_quantity = ?
        WHERE id = ? AND vendor_id = ?
    ");
    $stmt->execute([$new_quantity, $inventory_id, $vendor_id]);
    
    // Update or insert cost in vendor_ingredients if provided
    if ($cost_per_unit !== null) {
        $stmt = $conn->prepare("
            INSERT INTO vendor_ingredients (vendor_id, ingredient_id, cost_per_unit, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE cost_per_unit = VALUES(cost_per_unit)
        ");
        $stmt->execute([$vendor_id, $inventory['ingredient_id'], $cost_per_unit]);
    }

    // Record in inventory history
    $stmt = $conn->prepare("
        INSERT INTO inventory_history (
                    inventory_id, vendor_id, ingredient_id, previous_quantity, new_quantity,
                    change_type, batch_number, cost_per_unit, notes, changed_by
                ) VALUES (?, ?, ?, ?, ?, 'add', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $inventory_id,
                $vendor_id,
                $inventory['ingredient_id'],
        $current_quantity,
        $new_quantity,
                $inventory['batch_number'],
                $cost_per_unit,
                $notes,
                $_SESSION['user_id']
            ]);

            $_SESSION['success'] = "Inventory quantity added successfully!";
        }

    } else if ($adjustment_type === 'remove') {
        // For removing inventory, use FIFO method (remove from oldest batch first)
        $stmt = $conn->prepare("
            SELECT i.*, ing.name as ingredient_name, ing.unit
            FROM inventory i
            JOIN ingredients ing ON i.ingredient_id = ing.id
            WHERE i.ingredient_id = (SELECT ingredient_id FROM inventory WHERE id = ? AND vendor_id = ?)
            AND i.vendor_id = ? AND i.status = 'active' AND i.available_quantity > 0
            ORDER BY i.expiry_date ASC, i.id ASC
        ");
        $stmt->execute([$inventory_id, $vendor_id, $vendor_id]);
        $available_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($available_batches)) {
            throw new Exception("No available inventory to remove!");
        }

        $remaining_quantity = $quantity;
        $removed_from_batches = [];

        foreach ($available_batches as $batch) {
            if ($remaining_quantity <= 0) break;

            $batch_available = $batch['available_quantity'];
            $quantity_to_remove = min($remaining_quantity, $batch_available);
            
            $new_quantity = $batch['current_quantity'] - $quantity_to_remove;
            
            // Update this batch
            $stmt = $conn->prepare("
                UPDATE inventory 
                SET current_quantity = ?
                WHERE id = ?
            ");
            $stmt->execute([$new_quantity, $batch['id']]);

            // Record in inventory history
            $stmt = $conn->prepare("
                INSERT INTO inventory_history (
                    inventory_id, vendor_id, ingredient_id, previous_quantity, new_quantity,
                    change_type, batch_number, cost_per_unit, notes, changed_by
                ) VALUES (?, ?, ?, ?, ?, 'remove', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $batch['id'],
                $vendor_id,
                $batch['ingredient_id'],
                $batch['current_quantity'],
                $new_quantity,
                $batch['batch_number'],
                $batch['cost_per_unit'] ?? null,
                $notes,
                $_SESSION['user_id']
            ]);

            $removed_from_batches[] = $batch['batch_number'];
            $remaining_quantity -= $quantity_to_remove;
        }

        if ($remaining_quantity > 0) {
            throw new Exception("Not enough inventory available! Only " . ($quantity - $remaining_quantity) . " units could be removed.");
        }

        $_SESSION['success'] = "Inventory removed successfully from batches: " . implode(', ', $removed_from_batches);
    } else {
        throw new Exception("Invalid adjustment type!");
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
    $_SESSION['error'] = $e->getMessage();
}

header("Location: inventory.php");
exit();
?> 