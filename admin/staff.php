<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle delete request
if (isset($_POST['delete_staff'])) {
    try {
        $staff_id = $_POST['staff_id'];
        
        $conn->beginTransaction();
        
        // Delete from staff_students table
        $stmt = $conn->prepare("DELETE FROM staff_students WHERE id = ? AND role = 'staff'");
        $stmt->execute([$staff_id]);
        
        $conn->commit();
        $_SESSION['success'] = "Staff member has been deleted successfully";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting staff member: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle edit request
if (isset($_POST['edit_staff'])) {
    try {
        $staff_id = $_POST['staff_id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $school_id = $_POST['school_id'];
        
        $conn->beginTransaction();
        
        // Update users table and staff_students table
        $stmt = $conn->prepare("
            UPDATE users u 
            JOIN staff_students ss ON u.id = ss.user_id 
            SET u.username = ?, u.email = ?, ss.school_id = ?
            WHERE ss.id = ? AND ss.role = 'staff'
        ");
        $stmt->execute([$username, $email, $school_id, $staff_id]);
        
        $conn->commit();
        $_SESSION['success'] = "Staff information updated successfully";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating staff information: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$page_title = 'Staff Management';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Staff Management</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Staff List</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#filterModal">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>School</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Build the query based on filters
                            $where_conditions = ["ss.role = 'staff'"];
                            $params = [];
                            
                            if (isset($_GET['school_id']) && !empty($_GET['school_id'])) {
                                $where_conditions[] = "ss.school_id = ?";
                                $params[] = $_GET['school_id'];
                            }
                            
                            if (isset($_GET['status']) && !empty($_GET['status'])) {
                                $where_conditions[] = "ss.approval_status = ?";
                                $params[] = $_GET['status'];
                            }
                            
                            $where_clause = implode(" AND ", $where_conditions);
                            
                            $stmt = $conn->prepare("
                                SELECT 
                                    ss.id as staff_id,
                                    u.username,
                                    u.email,
                                    u.contact_number,
                                    s.name as school_name,
                                    ss.school_id,
                                    u.created_at,
                                    ss.approval_status
                                FROM staff_students ss
                                JOIN users u ON ss.user_id = u.id
                                JOIN schools s ON ss.school_id = s.id
                                WHERE $where_clause
                                ORDER BY u.created_at DESC
                            ");
                            $stmt->execute($params);

                            while ($staff = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $status_class = '';
                                switch($staff['approval_status']) {
                                    case 'approved':
                                        $status_class = 'success';
                                        break;
                                    case 'rejected':
                                        $status_class = 'danger';
                                        break;
                                    default:
                                        $status_class = 'warning';
                                }
                                
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($staff['username']) . "</td>";
                                echo "<td>" . htmlspecialchars($staff['email']) . "</td>";
                                echo "<td>" . ($staff['contact_number'] ? htmlspecialchars($staff['contact_number']) : 'N/A') . "</td>";
                                echo "<td>" . htmlspecialchars($staff['school_name']) . "</td>";
                                echo "<td>" . date('Y-m-d H:i', strtotime($staff['created_at'])) . "</td>";
                                echo "<td><span class='badge badge-" . $status_class . "'>" . ucfirst($staff['approval_status']) . "</span></td>";
                                echo "<td>
                                        <button type='button' class='btn btn-primary btn-sm' data-toggle='modal' data-target='#editStaffModal" . $staff['staff_id'] . "'>
                                            <i class='fas fa-edit'></i> Edit
                                        </button>
                                        <form method='POST' style='display:inline;margin-left:5px;'>
                                            <input type='hidden' name='staff_id' value='" . $staff['staff_id'] . "'>
                                            <button type='submit' name='delete_staff' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this staff member?\")'>
                                                <i class='fas fa-trash'></i> Delete
                                            </button>
                                        </form>
                                    </td>";
                                echo "</tr>";

                                // Edit Modal for each staff
                                echo "
                                <div class='modal fade' id='editStaffModal" . $staff['staff_id'] . "' tabindex='-1' role='dialog' aria-labelledby='editStaffModalLabel" . $staff['staff_id'] . "' aria-hidden='true'>
                                    <div class='modal-dialog' role='document'>
                                        <div class='modal-content'>
                                            <div class='modal-header'>
                                                <h5 class='modal-title' id='editStaffModalLabel" . $staff['staff_id'] . "'>Edit Staff Information</h5>
                                                <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                                    <span aria-hidden='true'>&times;</span>
                                                </button>
                                            </div>
                                            <form method='POST'>
                                                <div class='modal-body'>
                                                    <input type='hidden' name='staff_id' value='" . $staff['staff_id'] . "'>
                                                    <div class='form-group'>
                                                        <label for='username" . $staff['staff_id'] . "'>Name</label>
                                                        <input type='text' class='form-control' id='username" . $staff['staff_id'] . "' name='username' value='" . htmlspecialchars($staff['username']) . "' required>
                                                    </div>
                                                    <div class='form-group'>
                                                        <label for='email" . $staff['staff_id'] . "'>Email</label>
                                                        <input type='email' class='form-control' id='email" . $staff['staff_id'] . "' name='email' value='" . htmlspecialchars($staff['email']) . "' required>
                                                    </div>
                                                    <div class='form-group'>
                                                        <label for='school_id" . $staff['staff_id'] . "'>School</label>
                                                        <select class='form-control' id='school_id" . $staff['staff_id'] . "' name='school_id' required>";
                                                        
                                // Get all schools
                                $schools_stmt = $conn->query("SELECT id, name FROM schools ORDER BY name");
                                while ($school = $schools_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($school['id'] == $staff['school_id']) ? 'selected' : '';
                                    echo "<option value='" . $school['id'] . "' " . $selected . ">" . htmlspecialchars($school['name']) . "</option>";
                                }
                                
                                echo "
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class='modal-footer'>
                                                    <button type='button' class='btn btn-secondary' data-dismiss='modal'>Close</button>
                                                    <button type='submit' name='edit_staff' class='btn btn-primary'>Save changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='6' class='text-center text-danger'>Error: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" role="dialog" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filterModalLabel">Filter Staff</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="GET">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="school_id">School</label>
                        <select class="form-control" id="school_id" name="school_id">
                            <option value="">All Schools</option>
                            <?php
                            $schools_stmt = $conn->query("SELECT id, name FROM schools ORDER BY name");
                            while ($school = $schools_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = (isset($_GET['school_id']) && $_GET['school_id'] == $school['id']) ? 'selected' : '';
                                echo "<option value='" . $school['id'] . "' " . $selected . ">" . htmlspecialchars($school['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Clear Filters</a>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$additionalStyles = '
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
';

$additionalScripts = '
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $(".table").DataTable();
});
</script>
';

include '../includes/layout.php';
?> 