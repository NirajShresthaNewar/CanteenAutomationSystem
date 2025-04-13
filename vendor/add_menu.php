<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../index.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

// Get all categories for this vendor
$stmt = $conn->prepare("SELECT * FROM menu_categories WHERE vendor_id = ? ORDER BY name");
$stmt->execute([$vendor_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Add Menu Item';
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
<div class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Menu Item Details</h3>
            </div>
            <div class="card-body">
                <form action="process_menu_item.php" method="post" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Item Name*</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <div class="input-group">
                                    <select class="form-control" id="category_id" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['category_id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#addCategoryModal">
                                            <i class="fas fa-plus"></i> New
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Describe your dish, ingredients, etc."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="price">Price*</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₹</span>
                                    </div>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="image">Item Image</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="image" name="image" accept="image/*">
                                    <label class="custom-file-label" for="image">Choose file</label>
                                </div>
                                <small class="form-text text-muted">Recommended size: 500x500px. Max file size: 5MB.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Dietary Information</h3>
                                </div>
                                <div class="card-body">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="is_vegetarian" name="is_vegetarian" value="1">
                                        <label class="custom-control-label" for="is_vegetarian">Vegetarian</label>
                                    </div>
                                    <div class="custom-control custom-switch mt-2">
                                        <input type="checkbox" class="custom-control-input" id="is_vegan" name="is_vegan" value="1">
                                        <label class="custom-control-label" for="is_vegan">Vegan</label>
                                    </div>
                                    <div class="custom-control custom-switch mt-2">
                                        <input type="checkbox" class="custom-control-input" id="is_gluten_free" name="is_gluten_free" value="1">
                                        <label class="custom-control-label" for="is_gluten_free">Gluten Free</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Availability</h3>
                                </div>
                                <div class="card-body">
                                    <div class="custom-control custom-switch mb-3">
                                        <input type="checkbox" class="custom-control-input" id="is_available" name="is_available" value="1" checked>
                                        <label class="custom-control-label" for="is_available">Make Available Immediately</label>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="availability_start">Start Date</label>
                                                <input type="date" class="form-control" id="availability_start" name="availability_start">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="availability_end">End Date</label>
                                                <input type="date" class="form-control" id="availability_end" name="availability_end">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-right">
                        <a href="menu_items.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="action" value="add" class="btn btn-primary">Save Menu Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
            <form id="categoryForm" action="process_category.php" method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="category_name">Category Name*</label>
                        <input type="text" class="form-control" id="category_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="category_description">Description</label>
                        <textarea class="form-control" id="category_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="add" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>

<script>
$(document).ready(function() {
    // Display filename in custom file input
    $(".custom-file-input").on("change", function() {
        var fileName = $(this).val().split("\\").pop();
        $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
    });

    // Submit category via AJAX to avoid page reload
    $("#categoryForm").on("submit", function(e) {
        e.preventDefault();
        $.ajax({
            type: "POST",
            url: "process_category.php",
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    // Add new category to dropdown
                    $("#category_id").append(new Option(response.name, response.id));
                    
                    // Select the new category
                    $("#category_id").val(response.id);
                    
                    // Reset the form and close the modal
                    $("#categoryForm")[0].reset();
                    $("#addCategoryModal").modal("hide");
                    
                    // Show success message
                    $(".container-fluid").prepend(
                        '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                        response.message +
                        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                        '<span aria-hidden="true">&times;</span></button></div>'
                    );
                } else {
                    // Show error message in the modal
                    $("#addCategoryModal .modal-body").prepend(
                        '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                        response.message +
                        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                        '<span aria-hidden="true">&times;</span></button></div>'
                    );
                }
            },
            error: function() {
                // Show error message in the modal
                $("#addCategoryModal .modal-body").prepend(
                    '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                    'An error occurred while processing your request. Please try again.' +
                    '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                    '<span aria-hidden="true">&times;</span></button></div>'
                );
            }
        });
    });
});
</script> 