<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle approve/reject request
if (isset($_POST['update_status'])) {
    try {
        $student_id = $_POST['student_id'];
        $new_status = $_POST['status'];
        
        $conn->beginTransaction();
        
        // Update approval status in staff_students table
        $stmt = $conn->prepare("UPDATE staff_students SET approval_status = ? WHERE id = ? AND role = 'student'");
        $stmt->execute([$new_status, $student_id]);
        
        // Also update approval status in users table
        $stmt = $conn->prepare("
            UPDATE users u 
            JOIN staff_students ss ON u.id = ss.user_id 
            SET u.approval_status = ?
            WHERE ss.id = ?
        ");
        $stmt->execute([$new_status, $student_id]);
        
        $conn->commit();
        $_SESSION['success'] = "Student status has been updated to " . ucfirst($new_status);
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating status: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle delete request
if (isset($_POST['delete_student'])) {
    try {
        $student_id = $_POST['student_id'];
        
        $conn->beginTransaction();
        
        // Get user_id before deleting
        $stmt = $conn->prepare("SELECT user_id FROM staff_students WHERE id = ? AND role = 'student'");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            // Delete from staff_students table
            $stmt = $conn->prepare("DELETE FROM staff_students WHERE id = ?");
            $stmt->execute([$student_id]);
            
            // Delete from users table
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$student['user_id']]);
            
            $conn->commit();
            $_SESSION['success'] = "Student has been deleted successfully";
        } else {
            throw new Exception("Student not found");
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting student: " . $e->getMessage();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle edit request
if (isset($_POST['edit_student'])) {
    try {
        $student_id = $_POST['student_id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $school_id = $_POST['school_id'];
        
        $conn->beginTransaction();
        
        // Update users table and staff_students table
        $stmt = $conn->prepare("
            UPDATE users u 
            JOIN staff_students ss ON u.id = ss.user_id 
            SET u.username = ?, u.email = ?, ss.school_id = ?
            WHERE ss.id = ? AND ss.role = 'student'
        ");
        $stmt->execute([$username, $email, $school_id, $student_id]);
        
        $conn->commit();
        $_SESSION['success'] = "Student information updated successfully";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating student information: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$page_title = 'Student Management';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Student Management</h1>
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
                <h3 class="card-title">Student List</h3>
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
                            <th>Contact Number</th>
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
                            $where_conditions = ["ss.role = 'student'"];
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
                                    ss.id as student_id,
                                    u.username,
                                    u.email,
                                    u.contact_number,
                                    s.name as school_name,
                                    s.address as school_address,
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

                            while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $status_class = '';
                                switch($student['approval_status']) {
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
                                echo "<td>" . htmlspecialchars($student['username']) . "</td>";
                                echo "<td>" . htmlspecialchars($student['email']) . "</td>";
                                echo "<td>" . htmlspecialchars($student['contact_number'] ?? 'Not provided') . "</td>";
                                echo "<td>" . htmlspecialchars($student['school_name']) . "<br><small class='text-muted'>" . htmlspecialchars($student['school_address']) . "</small></td>";
                                echo "<td>" . date('Y-m-d H:i', strtotime($student['created_at'])) . "</td>";
                                echo "<td><span class='badge badge-" . $status_class . "'>" . ucfirst($student['approval_status']) . "</span></td>";
                                echo "<td>
                                        <div class='btn-group'>
                                            <button type='button' class='btn btn-primary btn-sm' data-toggle='modal' data-target='#editStudentModal" . $student['student_id'] . "'>
                                                <i class='fas fa-edit'></i> Edit
                                            </button>
                                            <button type='button' class='btn btn-info btn-sm dropdown-toggle dropdown-toggle-split' data-toggle='dropdown'>
                                                <i class='fas fa-cog'></i>
                                            </button>
                                            <div class='dropdown-menu'>
                                                <form method='POST'>
                                                    <input type='hidden' name='student_id' value='" . $student['student_id'] . "'>
                                                    <input type='hidden' name='status' value='approved'>
                                                    <button type='submit' name='update_status' class='dropdown-item text-success'>
                                                        <i class='fas fa-check'></i> Approve
                                                    </button>
                                                </form>
                                                <form method='POST'>
                                                    <input type='hidden' name='student_id' value='" . $student['student_id'] . "'>
                                                    <input type='hidden' name='status' value='rejected'>
                                                    <button type='submit' name='update_status' class='dropdown-item text-danger'>
                                                        <i class='fas fa-times'></i> Reject
                                                    </button>
                                                </form>
                                                <div class='dropdown-divider'></div>
                                                <form method='POST'>
                                                    <input type='hidden' name='student_id' value='" . $student['student_id'] . "'>
                                                    <button type='submit' name='delete_student' class='dropdown-item text-danger' onclick='return confirm(\"Are you sure you want to delete this student?\")'>
                                                        <i class='fas fa-trash'></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>";
                                echo "</tr>";

                                // Edit Modal for each student
                                echo "
                                <div class='modal fade' id='editStudentModal" . $student['student_id'] . "' tabindex='-1' role='dialog' aria-labelledby='editStudentModalLabel" . $student['student_id'] . "' aria-hidden='true'>
                                    <div class='modal-dialog' role='document'>
                                        <div class='modal-content'>
                                            <div class='modal-header'>
                                                <h5 class='modal-title' id='editStudentModalLabel" . $student['student_id'] . "'>Edit Student Information</h5>
                                                <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                                    <span aria-hidden='true'>&times;</span>
                                                </button>
                                            </div>
                                            <form method='POST'>
                                                <div class='modal-body'>
                                                    <input type='hidden' name='student_id' value='" . $student['student_id'] . "'>
                                                    <div class='form-group'>
                                                        <label for='username" . $student['student_id'] . "'>Name</label>
                                                        <input type='text' class='form-control' id='username" . $student['student_id'] . "' name='username' value='" . htmlspecialchars($student['username']) . "' required>
                                                    </div>
                                                    <div class='form-group'>
                                                        <label for='email" . $student['student_id'] . "'>Email</label>
                                                        <input type='email' class='form-control' id='email" . $student['student_id'] . "' name='email' value='" . htmlspecialchars($student['email']) . "' required>
                                                    </div>
                                                    <div class='form-group'>
                                                        <label for='school_id" . $student['student_id'] . "'>School</label>
                                                        <select class='form-control' id='school_id" . $student['student_id'] . "' name='school_id' required>";
                                                        
                                // Get all schools
                                $schools_stmt = $conn->query("SELECT id, name FROM schools ORDER BY name");
                                while ($school = $schools_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($school['id'] == $student['school_id']) ? 'selected' : '';
                                    echo "<option value='" . $school['id'] . "' " . $selected . ">" . htmlspecialchars($school['name']) . "</option>";
                                }
                                
                                echo "
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class='modal-footer'>
                                                    <button type='button' class='btn btn-secondary' data-dismiss='modal'>Close</button>
                                                    <button type='submit' name='edit_student' class='btn btn-primary'>Save changes</button>
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
                <h5 class="modal-title" id="filterModalLabel">Filter Students</h5>
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