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
    if (isset($_POST['worker_id']) && isset($_POST['action'])) {
        $worker_id = $_POST['worker_id'];
        $action = $_POST['action'];
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        
        try {
            $conn->beginTransaction();
            
            // Get user_id from worker record
            $stmt = $conn->prepare("SELECT user_id FROM workers WHERE id = ?");
            $stmt->execute([$worker_id]);
            $worker_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$worker_data) {
                throw new Exception("Worker not found");
            }
            
            $user_id = $worker_data['user_id'];
            
            // Update workers table
            $stmt = $conn->prepare("
                UPDATE workers 
                SET approval_status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $worker_id]);
            
            // Update users table
            $stmt = $conn->prepare("
                UPDATE users 
                SET approval_status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $user_id]);
            
            $conn->commit();
            $_SESSION['success'] = "Worker has been " . $new_status;
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
                <h3 class="card-title">Pending Worker Approvals</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Get vendor's ID
                            $stmt = $conn->prepare("
                                SELECT id 
                                FROM vendors 
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($vendor) {
                                $stmt = $conn->prepare("
                                    SELECT 
                                        w.id as worker_id,
                                        u.username,
                                        u.email,
                                        w.position,
                                        u.created_at
                                    FROM workers w
                                    JOIN users u ON w.user_id = u.id
                                    WHERE w.vendor_id = ? 
                                    AND w.approval_status = 'pending'
                                    ORDER BY u.created_at DESC
                                ");
                                $stmt->execute([$vendor['id']]);

                                while ($worker = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($worker['username']) . "</td>";
                                    echo "<td>" . htmlspecialchars($worker['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($worker['position']) . "</td>";
                                    echo "<td>" . date('Y-m-d H:i', strtotime($worker['created_at'])) . "</td>";
                                    echo "<td>
                                            <form method='POST' style='display:inline;'>
                                                <input type='hidden' name='worker_id' value='" . $worker['worker_id'] . "'>
                                                <input type='hidden' name='action' value='approve'>
                                                <button type='submit' class='btn btn-success btn-sm'>
                                                    <i class='fas fa-check'></i> Approve
                                                </button>
                                            </form>
                                            <form method='POST' style='display:inline;margin-left:5px;'>
                                                <input type='hidden' name='worker_id' value='" . $worker['worker_id'] . "'>
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
                            echo "<tr><td colspan='5' class='text-center text-danger'>Error: " . $e->getMessage() . "</td></tr>";
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

$pageTitle = "Approve Workers";

include '../includes/layout.php';
?> 