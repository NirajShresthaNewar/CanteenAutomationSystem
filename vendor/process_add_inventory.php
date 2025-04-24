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
    // Validate input
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method!");
    }

    $ingredient_id = filter_input(INPUT_POST, 'ingredient_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
    $cost_per_unit = filter_input(INPUT_POST, 'cost_per_unit', FILTER_VALIDATE_FLOAT);
    $batch_number = filter_input(INPUT_POST, 'batch_number', FILTER_SANITIZE_STRING);
    $expiry_date = filter_input(INPUT_POST, 'expiry_date');
    $supplier = filter_input(INPUT_POST, 'supplier', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    if (!$ingredient_id || !$quantity || !$cost_per_unit || !$expiry_date) {
        throw new Exception("Please fill in all required fields!");
    }

    // Validate expiry date
    $expiry_timestamp = strtotime($expiry_date);
    if ($expiry_timestamp === false || $expiry_timestamp < strtotime('today')) {
        throw new Exception("Invalid expiry date!");
    }

    // Start transaction
    $conn->beginTransaction();

    // Get ingredient details
    $stmt = $conn->prepare("
        SELECT i.*, vi.id as vendor_ingredient_id 
        FROM ingredients i 
        LEFT JOIN vendor_ingredients vi ON i.id = vi.ingredient_id AND vi.vendor_id = ?
        WHERE i.id = ?
    ");
    $stmt->execute([$vendor_id, $ingredient_id]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ingredient) {
        throw new Exception("Invalid ingredient selected!");
    }

    // Validate minimum order quantity
    if ($quantity < $ingredient['minimum_order_quantity']) {
        throw new Exception("Quantity must be at least {$ingredient['minimum_order_quantity']} {$ingredient['unit']}!");
    }

    // Generate batch number if not provided
    if (empty($batch_number)) {
        // Format: ING[First 3 letters]-YYYYMM-[Sequence]
        $ingredient_prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $ingredient['name']), 0, 3));
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
            // Increment existing sequence
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
            // Create new sequence
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

        $batch_number = sprintf("%s%s-%03d", $ingredient_prefix, $date_prefix, $new_sequence);
    }

    // Insert or update vendor_ingredients
    if (!$ingredient['vendor_ingredient_id']) {
        $stmt = $conn->prepare("
            INSERT INTO vendor_ingredients (
                vendor_id, ingredient_id, cost_per_unit, 
                preferred_supplier, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $vendor_id,
            $ingredient_id,
            $cost_per_unit,
            $supplier
        ]);
    } else {
        $stmt = $conn->prepare("
            UPDATE vendor_ingredients 
            SET cost_per_unit = ?,
                preferred_supplier = COALESCE(?, preferred_supplier)
            WHERE id = ?
        ");
        $stmt->execute([
            $cost_per_unit,
            $supplier,
            $ingredient['vendor_ingredient_id']
        ]);
    }

    // Add to inventory
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
        $batch_number,
        $expiry_date
    ]);
    $inventory_id = $conn->lastInsertId();

    // Record in inventory history
    $stmt = $conn->prepare("
        INSERT INTO inventory_history (
            inventory_id, vendor_id, ingredient_id,
            previous_quantity, new_quantity, change_type,
            batch_number, cost_per_unit, notes, changed_by
        ) VALUES (?, ?, ?, 0, ?, 'add', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $inventory_id,
        $vendor_id,
        $ingredient_id,
        $quantity,
        $batch_number,
        $cost_per_unit,
        $notes,
        $_SESSION['user_id']
    ]);

    // Check if we need to create an alert for low stock
    $stmt = $conn->prepare("
        SELECT SUM(available_quantity) as total_available
        FROM inventory
        WHERE vendor_id = ? AND ingredient_id = ? AND status = 'active'
    ");
    $stmt->execute([$vendor_id, $ingredient_id]);
    $total_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total_available'];

    $reorder_point = $ingredient['reorder_point'];
    if ($reorder_point && $total_stock <= $reorder_point) {
        // Create low stock alert
        $stmt = $conn->prepare("
            INSERT INTO inventory_alerts (
                vendor_id, ingredient_id, alert_type,
                alert_message, created_at
            ) VALUES (?, ?, 'low_stock', ?, NOW())
        ");
        $stmt->execute([
            $vendor_id,
            $ingredient_id,
            "Low stock alert for {$ingredient['name']}. Current stock: {$total_stock} {$ingredient['unit']}"
        ]);
    }

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Inventory added successfully! Batch number: {$batch_number}";
    header("Location: inventory.php");
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = $e->getMessage();
    header("Location: add_inventory.php");
    exit();
}
?> 