<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    die("Unauthorized access!");
}

if (!isset($_GET['recipe_id'])) {
    die("Recipe ID not provided!");
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

    // Get recipe details with menu item, costs, and ingredients
    $stmt = $conn->prepare("
        SELECT r.*, m.name as menu_item_name, rc.total_cost, rc.cost_per_serving,
               rc.calculation_date
        FROM recipes r 
        LEFT JOIN menu_items m ON m.recipe_id = r.id 
        LEFT JOIN recipe_costs rc ON rc.recipe_id = r.id
        WHERE r.id = ? AND m.vendor_id = ?
    ");
    $stmt->execute([$_GET['recipe_id'], $vendor_id]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        throw new Exception("Recipe not found!");
    }

    // Get recipe ingredients
    $stmt = $conn->prepare("
        SELECT ri.quantity, ri.unit, ri.preparation_notes, i.name as ingredient_name
        FROM recipe_ingredients ri
        JOIN ingredients i ON i.id = ri.ingredient_id
        WHERE ri.recipe_id = ?
    ");
    $stmt->execute([$_GET['recipe_id']]);
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output recipe details in a formatted way
    ?>
    <div class="row">
        <div class="col-md-6">
            <h5>Basic Information</h5>
            <table class="table table-bordered">
                <tr>
                    <th>Menu Item:</th>
                    <td><?php echo htmlspecialchars($recipe['menu_item_name']); ?></td>
                </tr>
                <tr>
                    <th>Recipe Name:</th>
                    <td><?php echo htmlspecialchars($recipe['name']); ?></td>
                </tr>
                <tr>
                    <th>Description:</th>
                    <td><?php echo htmlspecialchars($recipe['description'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Serving Size:</th>
                    <td><?php echo $recipe['serving_size']; ?> servings</td>
                </tr>
                <tr>
                    <th>Preparation Time:</th>
                    <td><?php echo $recipe['preparation_time']; ?> minutes</td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h5>Cost Information</h5>
            <table class="table table-bordered">
                <tr>
                    <th>Total Cost:</th>
                    <td>₹<?php echo number_format($recipe['total_cost'] ?? 0, 2); ?></td>
                </tr>
                <tr>
                    <th>Cost per Serving:</th>
                    <td>₹<?php echo number_format($recipe['cost_per_serving'] ?? 0, 2); ?></td>
                </tr>
                <tr>
                    <th>Last Calculated:</th>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($recipe['calculation_date'])); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <h5>Ingredients</h5>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Preparation Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ingredients as $ingredient): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></td>
                        <td><?php echo $ingredient['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($ingredient['unit']); ?></td>
                        <td><?php echo htmlspecialchars($ingredient['preparation_notes'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <h5>Cooking Instructions</h5>
            <div class="card">
                <div class="card-body">
                    <?php echo nl2br(htmlspecialchars($recipe['instructions'])); ?>
                </div>
            </div>
        </div>
    </div>
    <?php

} catch (Exception $e) {
    echo '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
}
?> 