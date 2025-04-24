<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    http_response_code(403);
    echo "Unauthorized access!";
    exit();
}

// Validate batch ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "Invalid batch ID!";
    exit();
}

$batch_id = intval($_GET['id']);

try {
    // Get vendor ID
    $stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        throw new Exception("Vendor not found!");
    }
    $vendor_id = $vendor['id'];

    // Get batch details with recipe and menu item information
    $stmt = $conn->prepare("
        SELECT 
            pb.*,
            r.name as recipe_name,
            r.description as recipe_description,
            r.serving_size,
            r.preparation_time,
            r.instructions as recipe_instructions,
            u.username as produced_by_name
        FROM production_batches pb
        JOIN recipes r ON r.id = pb.recipe_id
        JOIN menu_items m ON m.recipe_id = r.id
        JOIN users u ON u.id = pb.produced_by
        WHERE pb.id = ? AND m.vendor_id = ?
    ");
    $stmt->execute([$batch_id, $vendor_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new Exception("Batch not found or access denied!");
    }

    // Get ingredients used in this batch
    $stmt = $conn->prepare("
        SELECT 
            i.name as ingredient_name,
            pbi.quantity_used,
            i.unit_of_measurement
        FROM production_batch_ingredients pbi
        JOIN ingredients i ON i.id = pbi.ingredient_id
        WHERE pbi.batch_id = ?
        ORDER BY i.name
    ");
    $stmt->execute([$batch_id]);
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total cost
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(pbi.quantity_used * i.cost_per_unit), 0) as total_cost
        FROM production_batch_ingredients pbi
        JOIN ingredients i ON i.id = pbi.ingredient_id
        WHERE pbi.batch_id = ?
    ");
    $stmt->execute([$batch_id]);
    $cost = $stmt->fetch(PDO::FETCH_ASSOC);

    ?>
    <div class="row">
        <div class="col-md-6">
            <h5>Batch Information</h5>
            <table class="table table-bordered">
                <tr>
                    <th>Batch Number:</th>
                    <td><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                </tr>
                <tr>
                    <th>Recipe:</th>
                    <td><?php echo htmlspecialchars($batch['recipe_name']); ?></td>
                </tr>
                <tr>
                    <th>Production Date:</th>
                    <td><?php echo date('Y-m-d', strtotime($batch['production_date'])); ?></td>
                </tr>
                <tr>
                    <th>Quantity Produced:</th>
                    <td><?php echo number_format($batch['quantity_produced']); ?> servings</td>
                </tr>
                <tr>
                    <th>Expiry Date:</th>
                    <td><?php echo $batch['expiry_date'] ? date('Y-m-d', strtotime($batch['expiry_date'])) : 'N/A'; ?></td>
                </tr>
                <tr>
                    <th>Produced By:</th>
                    <td><?php echo htmlspecialchars($batch['produced_by_name']); ?></td>
                </tr>
                <tr>
                    <th>Total Cost:</th>
                    <td>₱<?php echo number_format($cost['total_cost'], 2); ?></td>
                </tr>
                <tr>
                    <th>Cost Per Serving:</th>
                    <td>₱<?php echo number_format($cost['total_cost'] / $batch['quantity_produced'], 2); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h5>Recipe Details</h5>
            <table class="table table-bordered">
                <tr>
                    <th>Description:</th>
                    <td><?php echo nl2br(htmlspecialchars($batch['recipe_description'])); ?></td>
                </tr>
                <tr>
                    <th>Serving Size:</th>
                    <td><?php echo htmlspecialchars($batch['serving_size']); ?> person(s)</td>
                </tr>
                <tr>
                    <th>Preparation Time:</th>
                    <td><?php echo htmlspecialchars($batch['preparation_time']); ?> minutes</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <h5>Ingredients Used</h5>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Ingredient</th>
                        <th>Quantity Used</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ingredients as $ingredient): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></td>
                        <td><?php echo number_format($ingredient['quantity_used'], 2); ?></td>
                        <td><?php echo htmlspecialchars($ingredient['unit_of_measurement']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <h5>Cooking Instructions</h5>
            <div class="p-3 bg-light">
                <?php echo nl2br(htmlspecialchars($batch['recipe_instructions'])); ?>
            </div>
        </div>
    </div>
    <?php

} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?> 