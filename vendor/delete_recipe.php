<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['recipe_id'])) {
    $_SESSION['error'] = "Invalid request!";
    header("Location: manage_recipes.php");
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

    // Start transaction
    $conn->beginTransaction();

    // Verify the recipe belongs to this vendor
    $stmt = $conn->prepare("
        SELECT r.id 
        FROM recipes r
        JOIN menu_items m ON m.recipe_id = r.id
        WHERE r.id = ? AND m.vendor_id = ?
    ");
    $stmt->execute([$_POST['recipe_id'], $vendor_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Recipe not found or unauthorized!");
    }

    // Delete recipe costs
    $stmt = $conn->prepare("DELETE FROM recipe_costs WHERE recipe_id = ?");
    $stmt->execute([$_POST['recipe_id']]);

    // Delete recipe ingredients
    $stmt = $conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
    $stmt->execute([$_POST['recipe_id']]);

    // Update menu_items to remove recipe_id reference
    $stmt = $conn->prepare("UPDATE menu_items SET recipe_id = NULL WHERE recipe_id = ?");
    $stmt->execute([$_POST['recipe_id']]);

    // Delete the recipe
    $stmt = $conn->prepare("DELETE FROM recipes WHERE id = ?");
    $stmt->execute([$_POST['recipe_id']]);

    $conn->commit();
    $_SESSION['success'] = "Recipe deleted successfully!";

} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['error'] = $e->getMessage();
}

header("Location: manage_recipes.php");
exit();
?> 