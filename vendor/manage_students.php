<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../index.php');
    exit();
}

// Get vendor's school_id
$stmt = $conn->prepare("SELECT school_id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$school_id = $vendor['school_id'];

// Handle delete request
if (isset($_POST['delete_student'])) {
    try {
        $student_id = $_POST['student_id'];
        
        $conn->beginTransaction();
        
        // Check if student belongs to vendor's school
        $stmt = $conn->prepare("SELECT user_id FROM staff_students WHERE id = ? AND school_id = ? AND role = 'student'");
        $stmt->execute([$student_id, $school_id]);
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
            throw new Exception("Unauthorized action");
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting student: " . $e->getMessage();
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
        
        $conn->beginTransaction();
        
        // Check if student belongs to vendor's school
        $stmt = $conn->prepare("SELECT user_id FROM staff_students WHERE id = ? AND school_id = ? AND role = 'student'");
        $stmt->execute([$student_id, $school_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            // Update users table
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->execute([$username, $email, $student['user_id']]);
            
            $conn->commit();
            $_SESSION['success'] = "Student information updated successfully";
        } else {
            throw new Exception("Unauthorized action");
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating student information: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle status update request
if (isset($_POST['update_status'])) {
    try {
        $student_id = $_POST['student_id'];
        $new_status = $_POST['status'];
        
        $conn->beginTransaction();
        
        // Check if student belongs to vendor's school
        $stmt = $conn->prepare("SELECT user_id FROM staff_students WHERE id = ? AND school_id = ? AND role = 'student'");
        $stmt->execute([$student_id, $school_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            // Update approval status in staff_students table
            $stmt = $conn->prepare("UPDATE staff_students SET approval_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $student_id]);
            
            // Also update approval status in users table
            $stmt = $conn->prepare("UPDATE users SET approval_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $student['user_id']]);
            
            $conn->commit();
            $_SESSION['success'] = "Student status has been updated to " . ucfirst($new_status);
        } else {
            throw new Exception("Unauthorized action");
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating status: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$page_title = 'Manage Students';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Manage Students</h1>
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
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->prepare("
                                SELECT 
                                    ss.id as student_id,
                                    u.username,
                                    u.email,
                                    u.created_at,
                                    ss.approval_status
                                FROM staff_students ss
                                JOIN users u ON ss.user_id = u.id
                                WHERE ss.school_id = ? AND ss.role = 'student'
                                ORDER BY u.created_at DESC
                            ");
                            $stmt->execute([$school_id]);

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
                                echo "<td>" . date('Y-m-d H:i', strtotime($student['created_at'])) . "</td>";
                                echo "<td><span class='badge badge-" . $status_class . "'>" . ucfirst($student['approval_status']) . "</span></td>";
                                echo "<td>
                                        <div class='btn-group'>
                                            <button type='button' class='btn btn-primary btn-sm' data-toggle='modal' data-target='#editStudentModal" . $student['student_id'] . "'>
                                                <i class='fas fa-edit'></i> Edit
                                            </button>
                                            <button type='button' class='btn btn-secondary btn-sm dropdown-toggle dropdown-toggle-split' data-toggle='dropdown'>
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
                            echo "<tr><td colspan='5' class='text-center text-danger'>Error: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
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