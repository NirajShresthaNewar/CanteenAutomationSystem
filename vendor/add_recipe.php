<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Add New Recipe";

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
    if (typeof jQuery !== "undefined") {
        // Initialize Select2 for all ingredient selects
        $(".ingredient-select").select2({
            theme: "bootstrap4",
            width: "100%"
        });

        // Update unit when ingredient is selected
        $(document).on("change", ".ingredient-select", function() {
            var unit = $(this).find("option:selected").data("unit") || "";
            $(this).closest(".row").find(".unit-input").val(unit);
        });

        // Form validation
        $("#addRecipeForm").on("submit", function(e) {
            var isValid = true;
            var message = "";
            
            // Check if at least one ingredient is selected
            var hasIngredients = false;
            $(".ingredient-select").each(function() {
                if ($(this).val()) {
                    hasIngredients = true;
                    return false;
                }
            });
            
            if (!hasIngredients) {
                message += "Please select at least one ingredient.\n";
                isValid = false;
            }
            
            // Check quantities for selected ingredients
            $(".ingredient-select").each(function() {
                if ($(this).val()) {
                    var quantity = $(this).closest(".row").find(".quantity-input").val();
                    if (!quantity) {
                        message += "Please enter quantity for all selected ingredients.\n";
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
    }
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

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    $menu_items = [];
    $ingredients = [];
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
                <form id="addRecipeForm" action="process_recipe.php" method="POST">
                    <!-- Menu Item Selection -->
                    <div class="form-group">
                        <label for="menu_item_id">Menu Item <span class="text-danger">*</span></label>
                        <select class="form-control select2" id="menu_item_id" name="menu_item_id" required>
                            <option value="">Select Menu Item</option>
                            <?php foreach ($menu_items as $item): ?>
                            <option value="<?php echo $item['item_id']; ?>">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Recipe Name -->
                    <div class="form-group">
                        <label for="recipe_name">Recipe Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="recipe_name" name="recipe_name" required>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <!-- Serving Size and Preparation Time -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="serving_size">Serving Size <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="serving_size" name="serving_size" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="preparation_time">Preparation Time (minutes) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="preparation_time" name="preparation_time" min="1" required>
                            </div>
                        </div>
                    </div>

                    <!-- Ingredients Section -->
                    <div class="form-group">
                        <label>Ingredients <span class="text-danger">*</span></label>
                        <div id="ingredients-container">
                            <?php for($i = 0; $i < 5; $i++): ?>
                            <div class="ingredient-row mb-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <select name="ingredients[]" class="form-control ingredient-select" <?php echo $i === 0 ? 'required' : ''; ?>>
                                            <option value="">Select Ingredient</option>
                                            <?php foreach ($ingredients as $ingredient): ?>
                                            <option value="<?php echo $ingredient['id']; ?>" 
                                                    data-unit="<?php echo htmlspecialchars($ingredient['unit']); ?>">
                                                <?php echo htmlspecialchars($ingredient['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" name="quantities[]" class="form-control quantity-input" 
                                               placeholder="Quantity" step="0.01" min="0" <?php echo $i === 0 ? 'required' : ''; ?>>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="units[]" class="form-control unit-input" 
                                               placeholder="Unit" readonly>
                                    </div>
                                    <div class="col-md-2">
                                        <?php if ($i > 0): ?>
                                        <button type="button" class="btn btn-danger remove-ingredient">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <button type="button" class="btn btn-success mt-2" id="add-ingredient">
                            <i class="fas fa-plus"></i> Add More Ingredients
                        </button>
                    </div>

                    <!-- Cooking Instructions -->
                    <div class="form-group">
                        <label for="cooking_instructions">Cooking Instructions <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="cooking_instructions" name="cooking_instructions" rows="4" required></textarea>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Save Recipe</button>
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