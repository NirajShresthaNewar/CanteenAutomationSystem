<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
    exit();
}

$pageTitle = "Add New Menu Item";

// Get vendor ID
try {
    $stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        throw new Exception("Vendor not found!");
    }
    $vendor_id = $vendor['id'];

    // Fetch categories
    $stmt = $conn->prepare("SELECT * FROM menu_categories WHERE vendor_id = ? OR vendor_id IS NULL ORDER BY name");
    $stmt->execute([$vendor_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: menu_items.php");
    exit();
}

ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Add New Menu Item</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="menu_items.php">Menu Items</a></li>
                    <li class="breadcrumb-item active">Add New Item</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <form action="process_menu_item.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <!-- Menu Item Details -->
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Basic Details -->
                            <div class="form-group">
                                <label for="item_name">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="item_name" name="name" required>
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Describe your dish, ingredients, etc."></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="price">Price <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₹</span>
                                            </div>
                                            <input type="number" class="form-control" id="price" name="price" 
                                                   min="0" step="0.01" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category">Category</label>
                                        <div class="input-group">
                                            <select class="form-control" id="category" name="category_id">
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['category_id']; ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary" 
                                                        data-toggle="modal" data-target="#addCategoryModal">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="item_image">Item Image</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="item_image" name="image" 
                                           accept="image/*">
                                    <label class="custom-file-label" for="item_image">Choose file</label>
                                </div>
                                <small class="form-text text-muted">Recommended size: 500x500px. Max file size: 5MB.</small>
                            </div>

                            <!-- Dietary Information -->
                            <div class="form-group">
                                <label>Dietary Information</label>
                                <div class="custom-control custom-switch mb-2">
                                    <input type="checkbox" class="custom-control-input" id="vegetarian" name="is_vegetarian" value="1">
                                    <label class="custom-control-label" for="vegetarian">Vegetarian</label>
                                </div>
                                <div class="custom-control custom-switch mb-2">
                                    <input type="checkbox" class="custom-control-input" id="vegan" name="is_vegan" value="1">
                                    <label class="custom-control-label" for="vegan">Vegan</label>
                                </div>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="gluten_free" name="is_gluten_free" value="1">
                                    <label class="custom-control-label" for="gluten_free">Gluten Free</label>
                                </div>
                            </div>

                            <!-- Availability -->
                            <div class="form-group mt-4">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="available" name="is_available" value="1">
                                    <label class="custom-control-label" for="available">Make Available Immediately</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary">Save Menu Item</button>
                        <a href="menu_items.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addCategoryForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="category_name">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <div class="form-group">
                        <label for="category_description">Description</label>
                        <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additionalScripts = '
<script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>
<script>
$(document).ready(function() {
    bsCustomFileInput.init();

    // Handle category form submission
    $("#addCategoryForm").on("submit", function(e) {
        e.preventDefault();
        $.ajax({
            url: "add_category.php",
            method: "POST",
            data: $(this).serialize(),
            success: function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    // Add new category to select
                    $("#category").append(new Option(data.name, data.id));
                    // Select the new category
                    $("#category").val(data.id);
                    // Close modal
                    $("#addCategoryModal").modal("hide");
                    // Reset form
                    $("#addCategoryForm")[0].reset();
                } else {
                    alert(data.message);
                }
            },
            error: function() {
                alert("Error adding category");
            }
        });
    });
});
</script>';

$content = ob_get_clean();
include '../includes/layout.php';
?> 