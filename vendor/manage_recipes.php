<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Manage Recipes";

// Add necessary scripts - this will be added to the head section
$additionalStyles = '
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
';

// Scripts will be loaded in the head section
$additionalScripts = '
<!-- jQuery (load first) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Ingredient handling script -->
<script>
jQuery(document).ready(function($) {
    console.log("Document ready");
    
    // Initialize Select2
    try {
        $(".ingredient-select").select2({
            theme: "bootstrap4"
        });
        console.log("Select2 initialized");
    } catch (e) {
        console.error("Select2 initialization error:", e);
    }

    // Add ingredient button click handler
    $("#add-ingredient").on("click", function(e) {
        e.preventDefault();
        console.log("Add ingredient clicked");
        
        // Clone the first ingredient row
        var newRow = $(".ingredient-row:first").clone();
        
        // Clear the values
        newRow.find("select").val("");
        newRow.find("input").val("");
        
        // Destroy Select2 if it exists
        try {
            newRow.find(".ingredient-select").select2("destroy");
        } catch (e) {
            console.log("No Select2 to destroy");
        }
        
        // Add to container
        $("#ingredients-container").append(newRow);
        
        // Initialize Select2 on the new row
        try {
            newRow.find(".ingredient-select").select2({
                theme: "bootstrap4"
            });
        } catch (e) {
            console.error("Select2 initialization error on new row:", e);
        }
        
        console.log("New row added");
    });

    // Remove ingredient button handler
    $("#ingredients-container").on("click", ".remove-ingredient", function(e) {
        e.preventDefault();
        console.log("Remove clicked");
        
        if ($(".ingredient-row").length > 1) {
            $(this).closest(".ingredient-row").remove();
            console.log("Row removed");
        } else {
            alert("You must have at least one ingredient!");
        }
    });

    // Handle ingredient selection
    $("#ingredients-container").on("change", ".ingredient-select", function() {
        var unit = $(this).find(":selected").data("unit") || "";
        $(this).closest(".row").find("input[name=\"units[]\"]").val(unit);
        console.log("Unit updated:", unit);
    });

    // Form validation
    $("#addRecipeForm").on("submit", function(e) {
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

    // Get all recipes with menu item names and costs
    $stmt = $conn->prepare("
        SELECT r.*, m.name as menu_item_name, rc.total_cost, rc.cost_per_serving,
        GROUP_CONCAT(CONCAT(ri.quantity, ' ', ri.unit, ' ', i.name) SEPARATOR ', ') as ingredients_list
        FROM recipes r 
        LEFT JOIN menu_items m ON m.recipe_id = r.id 
        LEFT JOIN recipe_costs rc ON rc.recipe_id = r.id
        LEFT JOIN recipe_ingredients ri ON ri.recipe_id = r.id
        LEFT JOIN ingredients i ON i.id = ri.ingredient_id
        WHERE m.vendor_id = ?
        GROUP BY r.id
    ");
    $stmt->execute([$vendor_id]);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    $recipes = [];
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
                <a href="add_recipe.php" class="btn btn-primary float-right">
                    <i class="fas fa-plus"></i> Add New Recipe
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Recipes Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recipe List</h3>
            </div>
            <div class="card-body">
                <table id="recipesTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Menu Item</th>
                            <th>Recipe Name</th>
                            <th>Ingredients</th>
                            <th>Serving Size</th>
                            <th>Prep Time</th>
                            <th>Cost Details</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipes as $recipe): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($recipe['menu_item_name'] ?? 'Not Assigned'); ?></td>
                            <td><?php echo htmlspecialchars($recipe['name']); ?></td>
                            <td>
                                <small><?php echo htmlspecialchars($recipe['ingredients_list']); ?></small>
                            </td>
                            <td><?php echo $recipe['serving_size']; ?> servings</td>
                            <td><?php echo $recipe['preparation_time']; ?> mins</td>
                            <td>
                                <strong>Total:</strong> ₹<?php echo number_format($recipe['total_cost'] ?? 0, 2); ?><br>
                                <strong>Per Serving:</strong> ₹<?php echo number_format($recipe['cost_per_serving'] ?? 0, 2); ?>
                            </td>
                            <td>
                                <?php if ($recipe['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info viewRecipe" data-id="<?php echo $recipe['id']; ?>" title="View Recipe">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-primary editRecipe" data-id="<?php echo $recipe['id']; ?>" title="Edit Recipe">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger deleteRecipe" data-id="<?php echo $recipe['id']; ?>" title="Delete Recipe">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Recipe Modal -->
<div class="modal fade" id="viewRecipeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Recipe Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="recipeDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteRecipeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this recipe? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form action="delete_recipe.php" method="POST" style="display: inline;">
                    <input type="hidden" name="recipe_id" id="deleteRecipeId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#recipesTable').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "order": [[1, 'asc']] // Sort by recipe name by default
    });

    // View Recipe Handler
    $('.viewRecipe').on('click', function() {
        const recipeId = $(this).data('id');
        
        // Fetch recipe details via AJAX
        $.ajax({
            url: 'get_recipe_details.php',
            type: 'GET',
            data: { recipe_id: recipeId },
            success: function(response) {
                $('#recipeDetails').html(response);
                $('#viewRecipeModal').modal('show');
            },
            error: function() {
                alert('Error fetching recipe details');
            }
        });
    });

    // Edit Recipe Handler
    $('.editRecipe').on('click', function() {
        const recipeId = $(this).data('id');
        window.location.href = 'edit_recipe.php?id=' + recipeId;
    });

    // Delete Recipe Handler
    $('.deleteRecipe').on('click', function() {
        const recipeId = $(this).data('id');
        $('#deleteRecipeId').val(recipeId);
        $('#deleteRecipeModal').modal('show');
    });
});
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?> 