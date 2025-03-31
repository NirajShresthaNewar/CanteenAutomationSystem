<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle delete request
if (isset($_POST['delete_worker'])) {
    try {
        $worker_id = $_POST['worker_id'];
        
        $conn->beginTransaction();
        
        // Delete from workers table
        $stmt = $conn->prepare("DELETE FROM workers WHERE id = ?");
        $stmt->execute([$worker_id]);
        
        $conn->commit();
        $_SESSION['success'] = "Worker has been deleted successfully";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting worker: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle edit request
if (isset($_POST['edit_worker'])) {
    try {
        $worker_id = $_POST['worker_id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $position = $_POST['position'];
        $vendor_id = $_POST['vendor_id'];
        
        $conn->beginTransaction();
        
        // Update users table and workers table
        $stmt = $conn->prepare("
            UPDATE users u 
            JOIN workers w ON u.id = w.user_id 
            SET u.username = ?, u.email = ?, w.position = ?, w.vendor_id = ?
            WHERE w.id = ?
        ");
        $stmt->execute([$username, $email, $position, $vendor_id, $worker_id]);
        
        $conn->commit();
        $_SESSION['success'] = "Worker information updated successfully";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating worker information: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$page_title = 'Worker Management';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Worker Management</h1>
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
                <h3 class="card-title">Worker List</h3>
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
                            <th>Position</th>
                            <th>Store</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Build the query based on filters
                            $where_conditions = ["1=1"];
                            $params = [];
                            
                            if (isset($_GET['vendor_id']) && !empty($_GET['vendor_id'])) {
                                $where_conditions[] = "w.vendor_id = ?";
                                $params[] = $_GET['vendor_id'];
                            }
                            
                            if (isset($_GET['status']) && !empty($_GET['status'])) {
                                $where_conditions[] = "w.approval_status = ?";
                                $params[] = $_GET['status'];
                            }
                            
                            $where_clause = implode(" AND ", $where_conditions);
                            
                            $stmt = $conn->prepare("
                                SELECT 
                                    w.id as worker_id,
                                    u.username,
                                    u.email,
                                    w.position,
                                    v.store_name,
                                    w.vendor_id,
                                    u.created_at,
                                    w.approval_status
                                FROM workers w
                                JOIN users u ON w.user_id = u.id
                                JOIN vendors v ON w.vendor_id = v.id
                                WHERE $where_clause
                                ORDER BY u.created_at DESC
                            ");
                            $stmt->execute($params);

                            while ($worker = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $status_class = '';
                                switch($worker['approval_status']) {
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
                                echo "<td>" . htmlspecialchars($worker['username']) . "</td>";
                                echo "<td>" . htmlspecialchars($worker['email']) . "</td>";
                                echo "<td>" . htmlspecialchars($worker['position']) . "</td>";
                                echo "<td>" . htmlspecialchars($worker['store_name']) . "</td>";
                                echo "<td>" . date('Y-m-d H:i', strtotime($worker['created_at'])) . "</td>";
                                echo "<td><span class='badge badge-" . $status_class . "'>" . ucfirst($worker['approval_status']) . "</span></td>";
                                echo "<td>
                                        <button type='button' class='btn btn-primary btn-sm' data-toggle='modal' data-target='#editWorkerModal" . $worker['worker_id'] . "'>
                                            <i class='fas fa-edit'></i> Edit
                                        </button>
                                        <form method='POST' style='display:inline;margin-left:5px;'>
                                            <input type='hidden' name='worker_id' value='" . $worker['worker_id'] . "'>
                                            <button type='submit' name='delete_worker' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this worker?\")'>
                                                <i class='fas fa-trash'></i> Delete
                                            </button>
                                        </form>
                                    </td>";
                                echo "</tr>";

                                // Edit Modal for each worker
                                echo "
                                <div class='modal fade' id='editWorkerModal" . $worker['worker_id'] . "' tabindex='-1' role='dialog' aria-labelledby='editWorkerModalLabel" . $worker['worker_id'] . "' aria-hidden='true'>
                                    <div class='modal-dialog' role='document'>
                                        <div class='modal-content'>
                                            <div class='modal-header'>
                                                <h5 class='modal-title' id='editWorkerModalLabel" . $worker['worker_id'] . "'>Edit Worker Information</h5>
                                                <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                                    <span aria-hidden='true'>&times;</span>
                                                </button>
                                            </div>
                                            <form method='POST'>
                                                <div class='modal-body'>
                                                    <input type='hidden' name='worker_id' value='" . $worker['worker_id'] . "'>
                                                    <div class='form-group'>
                                                        <label for='username" . $worker['worker_id'] . "'>Name</label>
                                                        <input type='text' class='form-control' id='username" . $worker['worker_id'] . "' name='username' value='" . htmlspecialchars($worker['username']) . "' required>
                                                    </div>
                                                    <div class='form-group'>
                                                        <label for='email" . $worker['worker_id'] . "'>Email</label>
                                                        <input type='email' class='form-control' id='email" . $worker['worker_id'] . "' name='email' value='" . htmlspecialchars($worker['email']) . "' required>
                                                    </div>
                                                    <div class='form-group'>
                                                        <label for='position" . $worker['worker_id'] . "'>Position</label>
                                                        <input type='text' class='form-control' id='position" . $worker['worker_id'] . "' name='position' value='" . htmlspecialchars($worker['position']) . "' required>
                                                    </div>
                                                    <div class='form-group'>
                                                        <label for='vendor_id" . $worker['worker_id'] . "'>Store</label>
                                                        <select class='form-control' id='vendor_id" . $worker['worker_id'] . "' name='vendor_id' required>";
                                                        
                                // Get all vendors
                                $vendors_stmt = $conn->query("SELECT id, store_name FROM vendors ORDER BY store_name");
                                while ($vendor = $vendors_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($vendor['id'] == $worker['vendor_id']) ? 'selected' : '';
                                    echo "<option value='" . $vendor['id'] . "' " . $selected . ">" . htmlspecialchars($vendor['store_name']) . "</option>";
                                }
                                
                                echo "
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class='modal-footer'>
                                                    <button type='button' class='btn btn-secondary' data-dismiss='modal'>Close</button>
                                                    <button type='submit' name='edit_worker' class='btn btn-primary'>Save changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='7' class='text-center text-danger'>Error: " . $e->getMessage() . "</td></tr>";
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
                <h5 class="modal-title" id="filterModalLabel">Filter Workers</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="GET">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="vendor_id">Store</label>
                        <select class="form-control" id="vendor_id" name="vendor_id">
                            <option value="">All Stores</option>
                            <?php
                            $vendors_stmt = $conn->query("SELECT id, store_name FROM vendors ORDER BY store_name");
                            while ($vendor = $vendors_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = (isset($_GET['vendor_id']) && $_GET['vendor_id'] == $vendor['id']) ? 'selected' : '';
                                echo "<option value='" . $vendor['id'] . "' " . $selected . ">" . htmlspecialchars($vendor['store_name']) . "</option>";
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