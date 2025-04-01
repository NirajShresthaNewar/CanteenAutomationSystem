<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle delete request
if (isset($_POST['delete_school'])) {
    try {
        $school_id = $_POST['school_id'];
        
        // First check if school has any associated vendors
        $stmt = $conn->prepare("SELECT COUNT(*) FROM vendors WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $vendorCount = $stmt->fetchColumn();
        
        if ($vendorCount > 0) {
            throw new Exception("Cannot delete school: There are " . $vendorCount . " vendors associated with this school");
        }
        
        // Also check for staff/students
        $stmt = $conn->prepare("SELECT COUNT(*) FROM staff_students WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $studentStaffCount = $stmt->fetchColumn();
        
        if ($studentStaffCount > 0) {
            throw new Exception("Cannot delete school: There are " . $studentStaffCount . " staff/students associated with this school");
        }
        
        // Now safe to delete
        $stmt = $conn->prepare("DELETE FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        
        $_SESSION['success'] = "School has been deleted successfully";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle edit request
if (isset($_POST['edit_school'])) {
    try {
        $school_id = $_POST['school_id'];
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);
        
        // Validate inputs
        if (empty($name) || empty($address)) {
            throw new Exception("School name and address are required");
        }
        
        $stmt = $conn->prepare("UPDATE schools SET name = ?, address = ? WHERE id = ?");
        $stmt->execute([$name, $address, $school_id]);
        
        $_SESSION['success'] = "School updated successfully";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle add school request
if (isset($_POST['add_school'])) {
    try {
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);

        if (empty($name) || empty($address)) {
            throw new Exception("School name and address are required");
        }

        $stmt = $conn->prepare("INSERT INTO schools (name, address) VALUES (?, ?)");
        $stmt->execute([$name, $address]);

        $_SESSION['success'] = "School added successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Start output buffering
ob_start();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <!-- Alert Messages -->
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

        <div class="row">
            <!-- Form Card -->
            <div class="col-md-4">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Add New School</h3>
                    </div>
                    <form method="POST">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="name">School Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       required placeholder="Enter school name">
                            </div>
                            <div class="form-group">
                                <label for="address">School Address *</label>
                                <textarea class="form-control" 
                                          id="address" 
                                          name="address" 
                                          required 
                                          rows="3" 
                                          placeholder="Enter school address"
                                          maxlength="255"></textarea>
                                <small class="text-muted">Maximum 255 characters</small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="add_school" class="btn btn-primary">Add School</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Schools List Card -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Manage Schools</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped" id="schoolsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Address</th>
                                    <th>Added On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT * FROM schools ORDER BY created_at DESC");
                                    while ($school = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<tr>";
                                        echo "<td>" . $school['id'] . "</td>";
                                        echo "<td>" . htmlspecialchars($school['name']) . "</td>";
                                        echo "<td>" . htmlspecialchars($school['address']) . "</td>";
                                        echo "<td>" . date('Y-m-d', strtotime($school['created_at'])) . "</td>";
                                        echo "<td>
                                                <button type='button' class='btn btn-primary btn-sm' data-toggle='modal' data-target='#editSchoolModal" . $school['id'] . "'>
                                                    <i class='fas fa-edit'></i> Edit
                                                </button>
                                                <form method='POST' style='display:inline;'>
                                                    <input type='hidden' name='school_id' value='" . $school['id'] . "'>
                                                    <button type='submit' name='delete_school' class='btn btn-danger btn-sm ml-1' onclick=\"return confirm('Are you sure you want to delete this school? This action cannot be undone.');\">
                                                        <i class='fas fa-trash'></i> Delete
                                                    </button>
                                                </form>
                                            </td>";
                                        echo "</tr>";
                                        
                                        // Edit Modal for each school
                                        echo "<div class='modal fade' id='editSchoolModal" . $school['id'] . "' tabindex='-1' role='dialog' aria-labelledby='editSchoolModalLabel" . $school['id'] . "' aria-hidden='true'>
                                                <div class='modal-dialog' role='document'>
                                                    <div class='modal-content'>
                                                        <div class='modal-header'>
                                                            <h5 class='modal-title' id='editSchoolModalLabel" . $school['id'] . "'>Edit School</h5>
                                                            <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                                                <span aria-hidden='true'>&times;</span>
                                                            </button>
                                                        </div>
                                                        <form method='POST'>
                                                            <div class='modal-body'>
                                                                <input type='hidden' name='school_id' value='" . $school['id'] . "'>
                                                                <div class='form-group'>
                                                                    <label for='name" . $school['id'] . "'>School Name</label>
                                                                    <input type='text' class='form-control' id='name" . $school['id'] . "' name='name' value='" . htmlspecialchars($school['name']) . "' required>
                                                                </div>
                                                                <div class='form-group'>
                                                                    <label for='address" . $school['id'] . "'>School Address</label>
                                                                    <textarea class='form-control' id='address" . $school['id'] . "' name='address' rows='3' required>" . htmlspecialchars($school['address']) . "</textarea>
                                                                </div>
                                                            </div>
                                                            <div class='modal-footer'>
                                                                <button type='button' class='btn btn-secondary' data-dismiss='modal'>Close</button>
                                                                <button type='submit' name='edit_school' class='btn btn-primary'>Save changes</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='5' class='text-center text-danger'>Error: " . $e->getMessage() . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Get the buffered content
$content = ob_get_clean();

// Add DataTables CSS and JS
$additionalStyles = '
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<style>
    textarea {
        resize: none;
        height: 100px;
        overflow-y: auto;
        padding: 10px;
        line-height: 1.5;
        font-size: 14px;
    }
    
    textarea:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    
    textarea::placeholder {
        color: #999;
        font-style: italic;
    }
</style>
';

$additionalScripts = '
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $("#schoolsTable").DataTable({
        "pageLength": 10,
        "order": [[0, "desc"]]
    });
});
</script>
';

// Set the page title
$pageTitle = "Manage Schools";

// Include the layout
include '../includes/layout.php';
?> 