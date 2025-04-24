<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_ingredient'])) {
        try {
            // Validate required fields
            $name = trim($_POST['name']);
            if (empty($name)) {
                throw new Exception("Ingredient name is required.");
            }

            $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
            $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
            $unit = trim($_POST['unit']);
            $base_unit = !empty($_POST['base_unit']) ? trim($_POST['base_unit']) : null;
            $min_order = !empty($_POST['minimum_order_quantity']) ? $_POST['minimum_order_quantity'] : null;
            $shelf_life = !empty($_POST['shelf_life_days']) ? $_POST['shelf_life_days'] : null;
            $storage = !empty($_POST['storage_instructions']) ? trim($_POST['storage_instructions']) : null;

            // Check if ingredient name already exists
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM ingredients WHERE name = ?");
            $check_stmt->execute([$name]);
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception("An ingredient with this name already exists.");
            }

            // Insert new ingredient
            $stmt = $conn->prepare("
                INSERT INTO ingredients (
                    name, description, category_id, unit, base_unit,
                    minimum_order_quantity, shelf_life_days, storage_instructions,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");

            $stmt->execute([
                $name, $description, $category_id, $unit, $base_unit,
                $min_order, $shelf_life, $storage, $_SESSION['user_id']
            ]);

            $_SESSION['success'] = "Ingredient added successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: manage_ingredients.php');
        exit();
    }

    if (isset($_POST['edit_ingredient'])) {
        try {
            // Validate ingredient ID
            if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
                throw new Exception("Invalid ingredient ID.");
            }

            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            
            if (empty($name)) {
                throw new Exception("Ingredient name is required.");
            }

            // Check if name exists for other ingredients
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM ingredients WHERE name = ? AND id != ?");
            $check_stmt->execute([$name, $id]);
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception("An ingredient with this name already exists.");
            }

            // Update ingredient
            $stmt = $conn->prepare("
                UPDATE ingredients SET 
                    name = ?,
                    description = ?,
                    category_id = ?,
                    unit = ?,
                    base_unit = ?,
                    minimum_order_quantity = ?,
                    shelf_life_days = ?,
                    storage_instructions = ?,
                    updated_by = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $result = $stmt->execute([
                $name,
                !empty($_POST['description']) ? trim($_POST['description']) : null,
                !empty($_POST['category_id']) ? $_POST['category_id'] : null,
                trim($_POST['unit']),
                !empty($_POST['base_unit']) ? trim($_POST['base_unit']) : null,
                !empty($_POST['minimum_order_quantity']) ? $_POST['minimum_order_quantity'] : null,
                !empty($_POST['shelf_life_days']) ? $_POST['shelf_life_days'] : null,
                !empty($_POST['storage_instructions']) ? trim($_POST['storage_instructions']) : null,
                $_SESSION['user_id'],
                $id
            ]);

            if ($result) {
                $_SESSION['success'] = "Ingredient updated successfully!";
            } else {
                throw new Exception("Failed to update ingredient.");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: manage_ingredients.php');
        exit();
    }

    if (isset($_POST['delete_ingredient'])) {
        try {
            $id = (int)$_POST['id'];

            // Check if ingredient is being used by any vendors
            $check_stmt = $conn->prepare("
                SELECT COUNT(*) FROM vendor_ingredients WHERE ingredient_id = ?
            ");
            $check_stmt->execute([$id]);
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception("Cannot delete this ingredient as it is being used by vendors.");
            }

            // Delete the ingredient
            $stmt = $conn->prepare("DELETE FROM ingredients WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['success'] = "Ingredient deleted successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: manage_ingredients.php');
        exit();
    }
}

// Fetch all ingredients with their details
$query = "
    SELECT 
        i.id,
        i.name,
        i.description,
        i.unit,
        i.base_unit,
        i.minimum_order_quantity,
        i.shelf_life_days,
        i.storage_instructions,
        i.created_at,
        i.updated_at,
        i.category_id,
        c.name as category_name,
        creator.username as created_by_name,
        updater.username as updated_by_name,
        COUNT(DISTINCT vi.vendor_id) as vendor_count
    FROM 
        ingredients i
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN vendor_ingredients vi ON i.id = vi.ingredient_id
        LEFT JOIN users creator ON i.created_by = creator.id
        LEFT JOIN users updater ON i.updated_by = updater.id
    GROUP BY 
        i.id
    ORDER BY 
        c.name, i.name
";

try {
    $ingredients = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error loading ingredients: " . $e->getMessage();
    $ingredients = [];
}

// Get categories for dropdown
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Manage System Ingredients";
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
                <div class="float-right">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addModal">
                        <i class="fas fa-plus"></i> Add New Ingredient
                    </button>
                </div>
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
                <button type="button" class="close" data-dismiss="alert">
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
                <button type="button" class="close" data-dismiss="alert">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Ingredients Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">System Ingredients</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="ingredientsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Unit</th>
                                <th>Base Unit</th>
                                <th>Min. Order Qty</th>
                                <th>Shelf Life</th>
                                <th>Storage Instructions</th>
                                <th>Vendors Using</th>
                                <th>Created By</th>
                                <th>Updated By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ingredients as $ingredient): ?>
                                <tr>
                                    <td><?php echo $ingredient['id']; ?></td>
                                    <td><?php echo htmlspecialchars($ingredient['name']); ?></td>
                                    <td><?php echo htmlspecialchars($ingredient['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td><?php echo htmlspecialchars($ingredient['description'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($ingredient['unit']); ?></td>
                                    <td><?php echo htmlspecialchars($ingredient['base_unit'] ?? '-'); ?></td>
                                    <td><?php echo $ingredient['minimum_order_quantity'] ? number_format($ingredient['minimum_order_quantity'], 2) : '-'; ?></td>
                                    <td><?php echo $ingredient['shelf_life_days'] ? $ingredient['shelf_life_days'] . ' days' : '-'; ?></td>
                                    <td>
                                        <?php if ($ingredient['storage_instructions']): ?>
                                            <span data-toggle="tooltip" title="<?php echo htmlspecialchars($ingredient['storage_instructions']); ?>">
                                                <i class="fas fa-info-circle"></i> View
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ingredient['vendor_count'] > 0): ?>
                                            <span class="badge badge-success">
                                                <?php echo $ingredient['vendor_count']; ?> vendor<?php echo $ingredient['vendor_count'] > 1 ? 's' : ''; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Not in use</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ingredient['created_by_name']): ?>
                                            <?php echo htmlspecialchars($ingredient['created_by_name']); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ingredient['updated_by_name']): ?>
                                            <?php echo htmlspecialchars($ingredient['updated_by_name']); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-btn"
                                                data-toggle="modal" 
                                                data-target="#editModal"
                                                data-id="<?php echo $ingredient['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($ingredient['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($ingredient['description'] ?? ''); ?>"
                                                data-category="<?php echo $ingredient['category_id']; ?>"
                                                data-unit="<?php echo htmlspecialchars($ingredient['unit']); ?>"
                                                data-base-unit="<?php echo htmlspecialchars($ingredient['base_unit'] ?? ''); ?>"
                                                data-min-order="<?php echo $ingredient['minimum_order_quantity']; ?>"
                                                data-shelf-life="<?php echo $ingredient['shelf_life_days']; ?>"
                                                data-storage="<?php echo htmlspecialchars($ingredient['storage_instructions'] ?? ''); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($ingredient['vendor_count'] == 0): ?>
                                            <button type="button" class="btn btn-sm btn-danger delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteModal"
                                                    data-id="<?php echo $ingredient['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($ingredient['name']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Ingredient</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="addIngredientForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category_id">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Unit <span class="text-danger">*</span></label>
                                <select class="form-control" name="unit" required>
                                    <option value="kg">Kilogram (kg)</option>
                                    <option value="g">Gram (g)</option>
                                    <option value="L">Liter (L)</option>
                                    <option value="ml">Milliliter (ml)</option>
                                    <option value="pcs">Pieces (pcs)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Base Unit</label>
                                <select class="form-control" name="base_unit">
                                    <option value="">Select Base Unit</option>
                                    <option value="kg">Kilogram (kg)</option>
                                    <option value="g">Gram (g)</option>
                                    <option value="L">Liter (L)</option>
                                    <option value="ml">Milliliter (ml)</option>
                                    <option value="pcs">Pieces (pcs)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Minimum Order Quantity</label>
                                <input type="number" class="form-control" name="minimum_order_quantity" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Shelf Life (days)</label>
                                <input type="number" class="form-control" name="shelf_life_days" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Storage Instructions</label>
                        <textarea class="form-control" name="storage_instructions" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_ingredient" class="btn btn-primary">Add Ingredient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Ingredient</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="editIngredientForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Ingredient ID</label>
                        <input type="text" class="form-control" id="editIdDisplay" readonly>
                        <input type="hidden" name="id" id="editId">
                    </div>
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editName" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category_id" id="editCategory">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Unit <span class="text-danger">*</span></label>
                                <select class="form-control" name="unit" id="editUnit" required>
                                    <option value="kg">Kilogram (kg)</option>
                                    <option value="g">Gram (g)</option>
                                    <option value="L">Liter (L)</option>
                                    <option value="ml">Milliliter (ml)</option>
                                    <option value="pcs">Pieces (pcs)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Base Unit</label>
                                <select class="form-control" name="base_unit" id="editBaseUnit">
                                    <option value="">Select Base Unit</option>
                                    <option value="kg">Kilogram (kg)</option>
                                    <option value="g">Gram (g)</option>
                                    <option value="L">Liter (L)</option>
                                    <option value="ml">Milliliter (ml)</option>
                                    <option value="pcs">Pieces (pcs)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Minimum Order Quantity</label>
                                <input type="number" class="form-control" name="minimum_order_quantity" id="editMinOrder" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Shelf Life (days)</label>
                                <input type="number" class="form-control" name="shelf_life_days" id="editShelfLife" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Storage Instructions</label>
                        <textarea class="form-control" name="storage_instructions" id="editStorage" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_ingredient" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Ingredient</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="deleteId">
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteName"></strong>?</p>
                    <p class="text-danger">
                        This action cannot be undone. The ingredient will only be deleted if it is not being used by any vendors.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_ingredient" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?>

<!-- Make sure jQuery is loaded first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#ingredientsTable').DataTable({
            "pageLength": 25,
            "order": [[0, "asc"]], // Sort by ID by default
            "responsive": true,
            "columnDefs": [
                { "orderable": false, "targets": -1 } // Disable sorting on action column
            ]
        });
    }

    // Initialize tooltips if Bootstrap is loaded
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }

    // Edit button click handler
    $(document).on('click', '.edit-btn', function() {
        var id = $(this).data('id');
        console.log('Editing ingredient ID:', id);

        // Set form values
        $('#editId').val(id);
        $('#editIdDisplay').val(id); // Display ID in the readonly field
        $('#editName').val($(this).data('name'));
        $('#editDescription').val($(this).data('description'));
        $('#editCategory').val($(this).data('category'));
        $('#editUnit').val($(this).data('unit'));
        $('#editBaseUnit').val($(this).data('base-unit'));
        $('#editMinOrder').val($(this).data('min-order'));
        $('#editShelfLife').val($(this).data('shelf-life'));
        $('#editStorage').val($(this).data('storage'));

        // Update modal title to include ID
        $('.modal-title').text('Edit Ingredient (ID: ' + id + ')');
    });

    // Delete button click handler
    $(document).on('click', '.delete-btn', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        
        $('#deleteId').val(id);
        $('#deleteName').text(name);
    });

    // Form validation
    $('#addIngredientForm, #editIngredientForm').on('submit', function(e) {
        var name = $(this).find('input[name="name"]').val().trim();
        var unit = $(this).find('select[name="unit"]').val();
        
        if (!name) {
            e.preventDefault();
            alert('Please enter an ingredient name.');
            return false;
        }
        
        if (!unit) {
            e.preventDefault();
            alert('Please select a unit.');
            return false;
        }
    });
});
</script>