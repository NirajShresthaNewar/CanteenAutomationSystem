<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Edit Recipe";

// Add necessary scripts
$additionalStyles = '
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@x.x.x/dist/select2-bootstrap4.min.css" rel="stylesheet" />
';

$additionalScripts = '
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize Select2
    $(".select2").select2({
        theme: "bootstrap4"
    });

    // Initialize ingredient select boxes
    $(".ingredient-select").select2({
        theme: "bootstrap4"
    });

    // Handle ingredient selection change
    $("#ingredients-container").on("change", ".ingredient-select", function() {
        var unit = $(this).find(":selected").data("unit") || "";
        $(this).closest(".row").find("input[name=\"units[]\"]").val(unit);
    });

    // Add ingredient button handler
    $("#add-ingredient").on("click", function(e) {
        e.preventDefault();
        var template = $(".ingredient-row:first").clone();
        template.find("select, input").val("");
        template.find(".ingredient-select").select2("destroy");
        $("#ingredients-container").append(template);
        template.find(".ingredient-select").select2({
            theme: "bootstrap4"
        });
    });

    // Remove ingredient button handler
    $("#ingredients-container").on("click", ".remove-ingredient", function(e) {
        e.preventDefault();
        if ($(".ingredient-row").length > 1) {
            $(this).closest(".ingredient-row").remove();
        } else {
            alert("You must have at least one ingredient!");
        }
    });

    // Form validation
    $("#editRecipeForm").on("submit", function(e) {
        let isValid = true;
        let message = "";

        // Check if at least one ingredient is added
        let hasIngredients = false;
        $(".ingredient-select").each(function() {
            if ($(this).val()) {
                hasIngredients = true;
                return false;
            }
        });

        if (!hasIngredients) {
            message += "Please add at least one ingredient.\n";
            isValid = false;
        }

        // Check quantities
        $(".ingredient-select").each(function() {
            if ($(this).val()) {
                const quantity = $(this).closest(".row").find("input[name=\"quantities[]\"]").val();
                if (!quantity) {
                    message += "Please enter quantity for all ingredients.\n";
                    isValid = false;
                    return false;
                }
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert(message);
        }
    });
});
</script>';

try {
    // Get vendor ID
    $stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        throw new Exception("Vendor not found!");
    }
    $vendor_id = $vendor['id'];

    // Check if recipe ID is provided
    if (!isset($_GET['id'])) {
        throw new Exception("Recipe ID not provided!");
    }

    // Get recipe details
    $stmt = $conn->prepare("
        SELECT r.*, m.item_id as menu_item_id, m.name as menu_item_name
        FROM recipes r
        JOIN menu_items m ON m.recipe_id = r.id
        WHERE r.id = ? AND m.vendor_id = ?
    ");
    $stmt->execute([$_GET['id'], $vendor_id]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        throw new Exception("Recipe not found or unauthorized!");
    }

    // Get menu items
    $stmt = $conn->prepare("SELECT item_id, name FROM menu_items WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get ingredients
    $stmt = $conn->prepare("
        SELECT i.id, i.name, i.unit 
        FROM ingredients i 
        LEFT JOIN vendor_ingredients vi ON i.id = vi.ingredient_id 
        WHERE vi.vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recipe ingredients
    $stmt = $conn->prepare("
        SELECT ri.ingredient_id, ri.quantity, ri.unit, ri.preparation_notes
        FROM recipe_ingredients ri
        WHERE ri.recipe_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $recipe_ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: manage_recipes.php");
    exit();
}

ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><?php echo $page_title; ?></h1>
            </div>
            <div class="col-sm-6">
                <a href="manage_recipes.php" class="btn btn-secondary float-right">
                    <i class="fas fa-arrow-left"></i> Back to Recipes
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form id="editRecipeForm" action="update_recipe.php" method="POST">
                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                    
                    <!-- Menu Item Selection -->
                    <div class="form-group">
                        <label for="menu_item_id">Menu Item <span class="text-danger">*</span></label>
                        <select class="form-control select2" id="menu_item_id" name="menu_item_id" required>
                            <option value="">Select Menu Item</option>
                            <?php foreach ($menu_items as $item): ?>
                            <option value="<?php echo $item['item_id']; ?>" 
                                    <?php echo ($item['item_id'] == $recipe['menu_item_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Recipe Name -->
                    <div class="form-group">
                        <label for="recipe_name">Recipe Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="recipe_name" name="recipe_name" 
                               value="<?php echo htmlspecialchars($recipe['name']); ?>" required>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($recipe['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- Serving Size and Preparation Time -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="serving_size">Serving Size <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="serving_size" name="serving_size" 
                                       value="<?php echo $recipe['serving_size']; ?>" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="preparation_time">Preparation Time (minutes) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="preparation_time" name="preparation_time" 
                                       value="<?php echo $recipe['preparation_time']; ?>" min="1" required>
                            </div>
                        </div>
                    </div>

                    <!-- Ingredients Section -->
                    <div class="form-group">
                        <label>Ingredients <span class="text-danger">*</span></label>
                        <div id="ingredients-container">
                            <?php foreach ($recipe_ingredients as $ri): ?>
                            <div class="ingredient-row mb-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <select name="ingredients[]" class="form-control ingredient-select" required>
                                            <option value="">Select Ingredient</option>
                                            <?php foreach ($ingredients as $ingredient): ?>
                                            <option value="<?php echo $ingredient['id']; ?>" 
                                                    data-unit="<?php echo htmlspecialchars($ingredient['unit']); ?>"
                                                    <?php echo ($ingredient['id'] == $ri['ingredient_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($ingredient['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" name="quantities[]" class="form-control" 
                                               value="<?php echo $ri['quantity']; ?>"
                                               placeholder="Quantity" step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="units[]" class="form-control" 
                                               value="<?php echo htmlspecialchars($ri['unit']); ?>"
                                               placeholder="Unit" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <?php if ($recipe_ingredients[0] !== $ri): ?>
                                        <button type="button" class="btn btn-danger remove-ingredient">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-success mt-2" id="add-ingredient">
                            <i class="fas fa-plus"></i> Add More Ingredients
                        </button>
                    </div>

                    <!-- Cooking Instructions -->
                    <div class="form-group">
                        <label for="cooking_instructions">Cooking Instructions <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="cooking_instructions" name="cooking_instructions" 
                                  rows="4" required><?php echo htmlspecialchars($recipe['instructions']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Update Recipe</button>
                        <a href="manage_recipes.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?> 