<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method!";
    header("Location: record_production.php");
    exit();
}

try {
    // Get vendor ID
    $stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        throw new Exception("Vendor not found!");
    }
    $vendor_id = $vendor['id'];

    // Validate input
    $recipe_id = filter_input(INPUT_POST, 'recipe', FILTER_VALIDATE_INT);
    $servings_per_batch = filter_input(INPUT_POST, 'servings_per_batch', FILTER_VALIDATE_INT);
    $number_of_batches = filter_input(INPUT_POST, 'number_of_batches', FILTER_VALIDATE_INT);
    $production_date = filter_input(INPUT_POST, 'production_date');
    $expiry_date = filter_input(INPUT_POST, 'expiry_date') ?: null;
    $notes = filter_input(INPUT_POST, 'production_notes', FILTER_SANITIZE_STRING);

    if (!$recipe_id || !$servings_per_batch || !$number_of_batches || !$production_date) {
        throw new Exception("Please fill in all required fields!");
    }

    if ($servings_per_batch <= 0 || $number_of_batches <= 0) {
        throw new Exception("Invalid quantity values!");
    }

    // Verify recipe belongs to vendor
    $stmt = $conn->prepare("
        SELECT r.id, r.name, r.serving_size 
        FROM recipes r
        JOIN menu_items m ON m.recipe_id = r.id
        WHERE r.id = ? AND m.vendor_id = ?
    ");
    $stmt->execute([$recipe_id, $vendor_id]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        throw new Exception("Invalid recipe selected!");
    }

    // Get recipe ingredients with their quantities
    $stmt = $conn->prepare("
        SELECT ri.ingredient_id, ri.quantity, ri.unit
        FROM recipe_ingredients ri
        WHERE ri.recipe_id = ?
    ");
    $stmt->execute([$recipe_id]);
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if there's enough inventory for all ingredients
    foreach ($ingredients as $ingredient) {
        $ingredientId = $ingredient['ingredient_id'];
        $requiredQuantity = $ingredient['quantity'];
        
        // Get available inventory for this ingredient (active and non-expired only)
        $inventoryQuery = "SELECT i.id, i.current_quantity, i.available_quantity, i.expiry_date 
                          FROM inventory i 
                          WHERE i.ingredient_id = ? 
                          AND i.status = 'active' 
                          AND i.expiry_date > CURRENT_DATE 
                          AND i.available_quantity > 0 
                          ORDER BY i.expiry_date ASC";
        
        $stmt = $conn->prepare($inventoryQuery);
        $stmt->execute([$ingredientId]);
        $inventoryResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalAvailable = 0;
        foreach ($inventoryResult as $row) {
            $totalAvailable += $row['available_quantity'];
        }
        
        if ($totalAvailable < $requiredQuantity) {
            throw new Exception("Insufficient inventory for ingredient ID $ingredientId. Required: $requiredQuantity, Available: $totalAvailable");
        }
    }

    // Start transaction
    $conn->beginTransaction();

    // Generate batch number (format: YYYYMMDD-VENDORID-SEQUENCE)
    $date_prefix = date('Ymd', strtotime($production_date));
    $stmt = $conn->prepare("
        SELECT COUNT(*) + 1 as sequence 
        FROM production_batches 
        WHERE DATE(production_date) = ?
    ");
    $stmt->execute([date('Y-m-d', strtotime($production_date))]);
    $sequence = $stmt->fetch(PDO::FETCH_ASSOC)['sequence'];
    $batch_number = sprintf("%s-%04d-%03d", $date_prefix, $vendor_id, $sequence);

    // Insert production batch
    $stmt = $conn->prepare("
        INSERT INTO production_batches (
            batch_number, recipe_id, production_date, quantity_produced,
            expiry_date, notes, produced_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $batch_number,
        $recipe_id,
        $production_date,
        $servings_per_batch * $number_of_batches,
        $expiry_date,
        $notes,
        $_SESSION['user_id']
    ]);
    $batch_id = $conn->lastInsertId();

    // Process each ingredient and deduct from inventory using FIFO
    foreach ($ingredients as $ingredient) {
        $ingredientId = $ingredient['ingredient_id'];
        $requiredQuantity = $ingredient['quantity'];
        $remainingToDeduct = $requiredQuantity;
        
        // Get available inventory batches for this ingredient
        $inventoryQuery = "SELECT id, available_quantity 
                          FROM inventory 
                          WHERE ingredient_id = ? 
                          AND status = 'active' 
                          AND expiry_date > CURRENT_DATE 
                          AND available_quantity > 0 
                          ORDER BY expiry_date ASC";
        
        $stmt = $conn->prepare($inventoryQuery);
        $stmt->execute([$ingredientId]);
        $inventoryBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($inventoryBatches as $inventoryBatch) {
            if ($remainingToDeduct <= 0) break;
            
            $deductionAmount = min($remainingToDeduct, $inventoryBatch['available_quantity']);
            $newAvailable = $inventoryBatch['available_quantity'] - $deductionAmount;
            
            // Update inventory
            $updateQuery = "UPDATE inventory 
                           SET available_quantity = ?, 
                               updated_at = CURRENT_TIMESTAMP 
                           WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->execute([$newAvailable, $inventoryBatch['id']]);
            
            // Record deduction
            $deductionQuery = "INSERT INTO production_inventory_deductions 
                              (production_batch_id, inventory_id, ingredient_id, quantity_used, deducted_by) 
                              VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($deductionQuery);
            $stmt->execute([
                $batch_id, 
                $inventoryBatch['id'], 
                $ingredientId, 
                $deductionAmount, 
                $_SESSION['user_id']
            ]);
            
            $remainingToDeduct -= $deductionAmount;
        }
        
        // Insert production batch ingredient
        $batchIngredientQuery = "INSERT INTO production_batch_ingredients 
                                (batch_id, ingredient_id, quantity_used, unit) 
                                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($batchIngredientQuery);
        $stmt->execute([
            $batch_id, 
            $ingredientId, 
            $ingredient['quantity'], 
            $ingredient['unit']
        ]);
    }

    // Generate alerts after inventory modifications
    require_once 'check_inventory_alerts.php';
    generateInventoryAlerts($conn, $vendor_id);

    // Commit transaction
    $conn->commit();

    $_SESSION['success'] = "Production batch {$batch_number} has been recorded successfully!";
    header("Location: production_history.php");
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = $e->getMessage();
    header("Location: record_production.php");
    exit();
}
?> 