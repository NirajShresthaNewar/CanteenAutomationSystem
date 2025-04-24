<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate required fields
        if (empty($_POST['menu_item_id']) || empty($_POST['recipe_name']) || 
            empty($_POST['serving_size']) || empty($_POST['preparation_time']) || 
            empty($_POST['cooking_instructions'])) {
            throw new Exception("Please fill in all required fields!");
        }

        // Validate ingredients
        if (empty($_POST['ingredients']) || !is_array($_POST['ingredients']) || 
            empty($_POST['quantities']) || !is_array($_POST['quantities'])) {
            throw new Exception("Please add at least one ingredient!");
        }

        // Start transaction
        $conn->beginTransaction();

        try {
            // Verify menu item belongs to vendor and get its category_id
            $stmt = $conn->prepare("SELECT item_id, category_id FROM menu_items WHERE item_id = ? AND vendor_id = ?");
            $stmt->execute([$_POST['menu_item_id'], $vendor_id]);
            $menu_item = $stmt->fetch();
            if (!$menu_item) {
                throw new Exception("Invalid menu item selected!");
            }

            // Insert recipe with category_id from menu_item
            $stmt = $conn->prepare("
                INSERT INTO recipes (name, description, serving_size, 
                                  preparation_time, instructions, category_id, created_by, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $_POST['recipe_name'],
                $_POST['description'] ?? null,
                $_POST['serving_size'],
                $_POST['preparation_time'],
                $_POST['cooking_instructions'],
                $menu_item['category_id'],
                $_SESSION['user_id']
            ]);

            $recipe_id = $conn->lastInsertId();

            // Update menu item with recipe_id
            $stmt = $conn->prepare("UPDATE menu_items SET recipe_id = ? WHERE item_id = ?");
            $stmt->execute([$recipe_id, $_POST['menu_item_id']]);

            // Insert recipe ingredients
            $stmt = $conn->prepare("
                INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit, preparation_notes)
                VALUES (?, ?, ?, ?, ?)
            ");

            // Variables to track total cost
            $total_cost = 0;
            $cost_per_serving = 0;

            // Get ingredient costs and calculate total
            $stmt_cost = $conn->prepare("
                SELECT cost_per_unit 
                FROM vendor_ingredients 
                WHERE vendor_id = ? AND ingredient_id = ?
            ");

            foreach ($_POST['ingredients'] as $key => $ingredient_id) {
                if (empty($ingredient_id)) continue;
                
                $quantity = $_POST['quantities'][$key] ?? 0;
                $unit = $_POST['units'][$key] ?? '';
                $notes = $_POST['notes'][$key] ?? null;

                // Insert recipe ingredient
                $stmt->execute([
                    $recipe_id,
                    $ingredient_id,
                    $quantity,
                    $unit,
                    $notes
                ]);

                // Calculate ingredient cost
                $stmt_cost->execute([$vendor_id, $ingredient_id]);
                $cost_data = $stmt_cost->fetch(PDO::FETCH_ASSOC);
                if ($cost_data) {
                    $total_cost += ($cost_data['cost_per_unit'] * $quantity);
                }
            }

            // Calculate cost per serving
            $serving_size = intval($_POST['serving_size']);
            if ($serving_size > 0) {
                $cost_per_serving = $total_cost / $serving_size;
            }

            // Insert into recipe_costs table
            $stmt = $conn->prepare("
                INSERT INTO recipe_costs (recipe_id, calculation_date, total_cost, cost_per_serving)
                VALUES (?, CURRENT_TIMESTAMP, ?, ?)
            ");
            $stmt->execute([
                $recipe_id,
                $total_cost,
                $cost_per_serving
            ]);

            $conn->commit();
            $_SESSION['success'] = "Recipe added successfully with cost calculations!";

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header("Location: manage_recipes.php");
exit();
?> 