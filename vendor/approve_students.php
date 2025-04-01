<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../index.php');
    exit();
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['student_id']) && isset($_POST['action'])) {
        $student_id = $_POST['student_id'];
        $action = $_POST['action'];
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        
        try {
            $conn->beginTransaction();
            
            // Get user_id from student record
            $stmt = $conn->prepare("SELECT user_id FROM staff_students WHERE id = ? AND role = 'student'");
            $stmt->execute([$student_id]);
            $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student_data) {
                throw new Exception("Student not found");
            }
            
            $user_id = $student_data['user_id'];
            
            // Update staff_students table
            $stmt = $conn->prepare("
                UPDATE staff_students 
                SET approval_status = ? 
                WHERE id = ? AND role = 'student'
            ");
            $stmt->execute([$new_status, $student_id]);
            
            // Update users table
            $stmt = $conn->prepare("
                UPDATE users 
                SET approval_status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $user_id]);
            
            $conn->commit();
            $_SESSION['success'] = "Student has been " . $new_status;
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Error processing request: " . $e->getMessage();
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = $e->getMessage();
        }
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pending Student Approvals</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Get vendor's school_id
                            $stmt = $conn->prepare("
                                SELECT school_id 
                                FROM vendors 
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($vendor) {
                                $stmt = $conn->prepare("
                                    SELECT 
                                        ss.id as student_id,
                                        u.username,
                                        u.email,
                                        u.created_at
                                    FROM staff_students ss
                                    JOIN users u ON ss.user_id = u.id
                                    WHERE ss.school_id = ? 
                                    AND ss.role = 'student'
                                    AND ss.approval_status = 'pending'
                                    ORDER BY u.created_at DESC
                                ");
                                $stmt->execute([$vendor['school_id']]);

                                while ($student = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($student['username']) . "</td>";
                                    echo "<td>" . htmlspecialchars($student['email']) . "</td>";
                                    echo "<td>" . date('Y-m-d H:i', strtotime($student['created_at'])) . "</td>";
                                    echo "<td>
                                            <form method='POST' style='display:inline;'>
                                                <input type='hidden' name='student_id' value='" . $student['student_id'] . "'>
                                                <input type='hidden' name='action' value='approve'>
                                                <button type='submit' class='btn btn-success btn-sm'>
                                                    <i class='fas fa-check'></i> Approve
                                                </button>
                                            </form>
                                            <form method='POST' style='display:inline;margin-left:5px;'>
                                                <input type='hidden' name='student_id' value='" . $student['student_id'] . "'>
                                                <input type='hidden' name='action' value='reject'>
                                                <button type='submit' class='btn btn-danger btn-sm'>
                                                    <i class='fas fa-times'></i> Reject
                                                </button>
                                            </form>
                                        </td>";
                                    echo "</tr>";
                                }
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='4' class='text-center text-danger'>Error: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

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

$pageTitle = "Approve Students";

include '../includes/layout.php';
?> 