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
    if (isset($_POST['add_category'])) {
        $name = $_POST['name'];
        $description = !empty($_POST['description']) ? $_POST['description'] : null;

        try {
            $stmt = $conn->prepare("
                INSERT INTO categories (name, description, created_by)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $description, $_SESSION['user_id']]);
            $_SESSION['success'] = "Category added successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry error
                $_SESSION['error'] = "A category with this name already exists.";
            } else {
                $_SESSION['error'] = "Error adding category: " . $e->getMessage();
            }
        }
        header('Location: manage_categories.php');
        exit();
    }

    if (isset($_POST['edit_category'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $description = !empty($_POST['description']) ? $_POST['description'] : null;

        try {
            $stmt = $conn->prepare("
                UPDATE categories 
                SET name = ?, 
                    description = ?,
                    updated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $_SESSION['user_id'], $id]);
            $_SESSION['success'] = "Category updated successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry error
                $_SESSION['error'] = "A category with this name already exists.";
            } else {
                $_SESSION['error'] = "Error updating category: " . $e->getMessage();
            }
        }
        header('Location: manage_categories.php');
        exit();
    }

    if (isset($_POST['delete_category'])) {
        $id = $_POST['id'];
        try {
            // Check if category is in use
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM ingredients 
                WHERE category_id = ?
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                $_SESSION['error'] = "Cannot delete category as it is being used by ingredients.";
            } else {
                $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "Category deleted successfully!";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting category: " . $e->getMessage();
        }
        header('Location: manage_categories.php');
        exit();
    }
}

// Get all categories with their usage counts
$query = "
    SELECT 
        c.*,
        COUNT(DISTINCT i.id) as ingredients_count,
        COUNT(DISTINCT vi.vendor_id) as vendors_count
    FROM categories c
    LEFT JOIN ingredients i ON c.id = i.category_id
    LEFT JOIN vendor_ingredients vi ON i.id = vi.ingredient_id
    GROUP BY c.id
    ORDER BY c.name
";

$stmt = $conn->query($query);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Manage Categories";
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
                        <i class="fas fa-plus"></i> Add New Category
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

        <!-- Categories Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="categoriesTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Ingredients</th>
                                <th>Vendors Using</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($category['ingredients_count'] > 0): ?>
                                            <span class="badge badge-info">
                                                <?php echo $category['ingredients_count']; ?> ingredient<?php echo $category['ingredients_count'] > 1 ? 's' : ''; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">No ingredients</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($category['vendors_count'] > 0): ?>
                                            <span class="badge badge-success">
                                                <?php echo $category['vendors_count']; ?> vendor<?php echo $category['vendors_count'] > 1 ? 's' : ''; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Not in use</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-btn"
                                                data-toggle="modal" 
                                                data-target="#editModal"
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($category['description'] ?? ''); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($category['ingredients_count'] == 0): ?>
                                            <button type="button" class="btn btn-sm btn-danger delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteModal"
                                                    data-id="<?php echo $category['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($category['name']); ?>">
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
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
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
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" name="name" id="editName" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">Save Changes</button>
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
                <h5 class="modal-title">Delete Category</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="id" id="deleteId">
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_category" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#categoriesTable').DataTable({
        "pageLength": 25,
        "order": [[0, "asc"]], // Sort by name
        "responsive": true
    });

    // Edit modal
    $('.edit-btn').click(function() {
        $('#editId').val($(this).data('id'));
        $('#editName').val($(this).data('name'));
        $('#editDescription').val($(this).data('description'));
    });

    // Delete modal
    $('.delete-btn').click(function() {
        $('#deleteId').val($(this).data('id'));
        $('#deleteName').text($(this).data('name'));
    });
});
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?> 