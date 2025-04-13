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

// Get all menu items for this vendor
$stmt = $conn->prepare("
    SELECT m.*, c.name as category_name 
    FROM menu_items m 
    LEFT JOIN menu_categories c ON m.category_id = c.category_id 
    WHERE m.vendor_id = ?
    ORDER BY m.name
");
$stmt->execute([$vendor_id]);
$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for this vendor
$stmt = $conn->prepare("SELECT * FROM menu_categories WHERE vendor_id = ? ORDER BY name");
$stmt->execute([$vendor_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Menu Items';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Menu Items</h1>
            </div>
            <div class="col-sm-6">
                <a href="add_menu.php" class="btn btn-primary float-right">
                    <i class="fas fa-plus"></i> Add New Item
                </a>
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
                <h3 class="card-title">Menu Items List</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Dietary</th>
                            <th>Availability</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($menuItems)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No menu items found. Add your first item!</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($menuItems as $item): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($item['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars('../' . $item['image_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 width="50" height="50" class="rounded">
                                        <?php else: ?>
                                            <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>₹<?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <?php if ($item['is_vegetarian']): ?>
                                            <span class="badge badge-success">Vegetarian</span>
                                        <?php endif; ?>
                                        <?php if ($item['is_vegan']): ?>
                                            <span class="badge badge-info">Vegan</span>
                                        <?php endif; ?>
                                        <?php if ($item['is_gluten_free']): ?>
                                            <span class="badge badge-warning">Gluten Free</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['is_available']): ?>
                                            <span class="badge badge-success">Available</span>
                                            <?php if ($item['availability_start'] || $item['availability_end']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php 
                                                    if ($item['availability_start']) echo date('M d', strtotime($item['availability_start']));
                                                    if ($item['availability_start'] && $item['availability_end']) echo ' - ';
                                                    if ($item['availability_end']) echo date('M d', strtotime($item['availability_end']));
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Unavailable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-item" 
                                                data-id="<?php echo $item['item_id']; ?>"
                                                data-toggle="modal" data-target="#editItemModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-item"
                                                data-id="<?php echo $item['item_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                data-toggle="modal" data-target="#deleteItemModal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Add New Menu Item</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="process_menu_item.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
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
                                <select class="form-control" id="category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
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
                                <input type="file" class="form-control-file" id="image" name="image" accept="image/*">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Dietary Information</label>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="is_vegetarian" name="is_vegetarian" value="1">
                                    <label class="custom-control-label" for="is_vegetarian">Vegetarian</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="is_vegan" name="is_vegan" value="1">
                                    <label class="custom-control-label" for="is_vegan">Vegan</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="is_gluten_free" name="is_gluten_free" value="1">
                                    <label class="custom-control-label" for="is_gluten_free">Gluten Free</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Availability</label>
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="is_available" name="is_available" value="1" checked>
                                    <label class="custom-control-label" for="is_available">Item is Available</label>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="availability_start">Start Date</label>
                                        <input type="date" class="form-control" id="availability_start" name="availability_start">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="availability_end">End Date</label>
                                        <input type="date" class="form-control" id="availability_end" name="availability_end">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="add" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" role="dialog" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editItemModalLabel">Edit Menu Item</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="process_menu_item.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="item_id" id="edit_item_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_name">Item Name*</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_category_id">Category</label>
                                <select class="form-control" id="edit_category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_price">Price*</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₹</span>
                                    </div>
                                    <input type="number" class="form-control" id="edit_price" name="price" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_image">Item Image</label>
                                <input type="file" class="form-control-file" id="edit_image" name="image" accept="image/*">
                                <small class="form-text text-muted">Leave empty to keep the current image</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Dietary Information</label>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="edit_is_vegetarian" name="is_vegetarian" value="1">
                                    <label class="custom-control-label" for="edit_is_vegetarian">Vegetarian</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="edit_is_vegan" name="is_vegan" value="1">
                                    <label class="custom-control-label" for="edit_is_vegan">Vegan</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="edit_is_gluten_free" name="is_gluten_free" value="1">
                                    <label class="custom-control-label" for="edit_is_gluten_free">Gluten Free</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Availability</label>
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="edit_is_available" name="is_available" value="1">
                                    <label class="custom-control-label" for="edit_is_available">Item is Available</label>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="edit_availability_start">Start Date</label>
                                        <input type="date" class="form-control" id="edit_availability_start" name="availability_start">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="edit_availability_end">End Date</label>
                                        <input type="date" class="form-control" id="edit_availability_end" name="availability_end">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="update" class="btn btn-primary">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Item Modal -->
<div class="modal fade" id="deleteItemModal" tabindex="-1" role="dialog" aria-labelledby="deleteItemModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteItemModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the menu item: <strong id="delete_item_name"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form action="process_menu_item.php" method="post">
                    <input type="hidden" name="item_id" id="delete_item_id">
                    <button type="submit" name="action" value="delete" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>

<script>
$(document).ready(function() {
    // Edit Item
    $('.edit-item').click(function() {
        const itemId = $(this).data('id');
        // Fetch item details and populate the edit form
        $.get('get_menu_item.php', { item_id: itemId }, function(item) {
            $('#edit_item_id').val(item.item_id);
            $('#edit_name').val(item.name);
            $('#edit_category_id').val(item.category_id);
            $('#edit_description').val(item.description);
            $('#edit_price').val(item.price);
            $('#edit_is_vegetarian').prop('checked', item.is_vegetarian == 1);
            $('#edit_is_vegan').prop('checked', item.is_vegan == 1);
            $('#edit_is_gluten_free').prop('checked', item.is_gluten_free == 1);
            $('#edit_is_available').prop('checked', item.is_available == 1);
            $('#edit_availability_start').val(item.availability_start);
            $('#edit_availability_end').val(item.availability_end);
        });
    });

    // Delete Item
    $('.delete-item').click(function() {
        const itemId = $(this).data('id');
        const itemName = $(this).data('name');
        $('#delete_item_id').val(itemId);
        $('#delete_item_name').text(itemName);
    });
});
</script> 