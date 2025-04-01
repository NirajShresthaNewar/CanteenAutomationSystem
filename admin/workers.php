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
        
        // Get user_id before deleting
        $stmt = $conn->prepare("SELECT user_id FROM workers WHERE id = ?");
        $stmt->execute([$worker_id]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($worker) {
            // Delete from workers table
            $stmt = $conn->prepare("DELETE FROM workers WHERE id = ?");
            $stmt->execute([$worker_id]);
            
            // Delete from users table
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$worker['user_id']]);
            
            $conn->commit();
            $_SESSION['success'] = "Worker has been deleted successfully";
        } else {
            throw new Exception("Worker not found");
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting worker: " . $e->getMessage();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle status update request
if (isset($_POST['update_status'])) {
    try {
        $worker_id = $_POST['worker_id'];
        $new_status = $_POST['status'];
        
        $conn->beginTransaction();
        
        // Get user_id
        $stmt = $conn->prepare("SELECT user_id FROM workers WHERE id = ?");
        $stmt->execute([$worker_id]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($worker) {
            // Update approval status in workers table
            $stmt = $conn->prepare("UPDATE workers SET approval_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $worker_id]);
            
            // Also update approval status in users table
            $stmt = $conn->prepare("UPDATE users SET approval_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $worker['user_id']]);
            
            $conn->commit();
            $_SESSION['success'] = "Worker status has been updated to " . ucfirst($new_status);
        } else {
            throw new Exception("Worker not found");
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating worker status: " . $e->getMessage();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
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
        
        // Validate data
        if (empty($username) || empty($email) || empty($position) || empty($vendor_id)) {
            throw new Exception("All fields are required");
        }
        
        $conn->beginTransaction();
        
        // Get user_id
        $stmt = $conn->prepare("SELECT user_id FROM workers WHERE id = ?");
        $stmt->execute([$worker_id]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($worker) {
            // Update users table
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->execute([$username, $email, $worker['user_id']]);
            
            // Update workers table
            $stmt = $conn->prepare("UPDATE workers SET position = ?, vendor_id = ? WHERE id = ?");
            $stmt->execute([$position, $vendor_id, $worker_id]);
            
            $conn->commit();
            $_SESSION['success'] = "Worker information updated successfully";
        } else {
            throw new Exception("Worker not found");
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating worker information: " . $e->getMessage();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$pageTitle = 'Worker Management';
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
                            <th>Contact Number</th>
                            <th>Position</th>
                            <th>Vendor</th>
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
                                    u.contact_number,
                                    w.position,
                                    w.vendor_id,
                                    v.user_id as vendor_user_id,
                                    vu.username as vendor_name,
                                    v.school_id,
                                    s.name as school_name,
                                    u.created_at,
                                    w.approval_status
                                FROM workers w
                                JOIN users u ON w.user_id = u.id
                                JOIN vendors v ON w.vendor_id = v.id
                                JOIN users vu ON v.user_id = vu.id
                                JOIN schools s ON v.school_id = s.id
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
                                echo "<td>" . htmlspecialchars($worker['contact_number'] ?? 'Not provided') . "</td>";
                                echo "<td>" . ucfirst(htmlspecialchars($worker['position'])) . "</td>";
                                echo "<td>" . htmlspecialchars($worker['vendor_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($worker['school_name']) . "</td>";
                                echo "<td>" . date('Y-m-d H:i', strtotime($worker['created_at'])) . "</td>";
                                echo "<td><span class='badge badge-" . $status_class . "'>" . ucfirst($worker['approval_status']) . "</span></td>";
                                echo "<td>
                                        <div class='btn-group'>
                                            <button type='button' class='btn btn-primary btn-sm' data-toggle='modal' data-target='#editWorkerModal" . $worker['worker_id'] . "'>
                                                <i class='fas fa-edit'></i> Edit
                                            </button>
                                            <button type='button' class='btn btn-secondary btn-sm dropdown-toggle dropdown-toggle-split' data-toggle='dropdown'>
                                                <i class='fas fa-cog'></i>
                                            </button>
                                            <div class='dropdown-menu'>
                                                <form method='POST' style='display:inline;'>
                                                    <input type='hidden' name='worker_id' value='" . $worker['worker_id'] . "'>
                                                    <input type='hidden' name='status' value='approved'>
                                                    <button type='submit' name='update_status' class='dropdown-item text-success'>
                                                        <i class='fas fa-check'></i> Approve
                                                    </button>
                                                </form>
                                                <form method='POST' style='display:inline;'>
                                                    <input type='hidden' name='worker_id' value='" . $worker['worker_id'] . "'>
                                                    <input type='hidden' name='status' value='rejected'>
                                                    <button type='submit' name='update_status' class='dropdown-item text-danger'>
                                                        <i class='fas fa-times'></i> Reject
                                                    </button>
                                                </form>
                                                <div class='dropdown-divider'></div>
                                                <form method='POST' style='display:inline;'>
                                                    <input type='hidden' name='worker_id' value='" . $worker['worker_id'] . "'>
                                                    <button type='submit' name='delete_worker' class='dropdown-item text-danger' onclick='return confirm(\"Are you sure you want to delete this worker?\")'>
                                                        <i class='fas fa-trash'></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>";
                                echo "</tr>";

                                // Edit Modal for each worker
                                echo "<div class='modal fade' id='editWorkerModal" . $worker['worker_id'] . "' tabindex='-1' role='dialog' aria-labelledby='editWorkerModalLabel" . $worker['worker_id'] . "' aria-hidden='true'>
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
                                                        <select class='form-control' id='position" . $worker['worker_id'] . "' name='position' required>
                                                            <option value='kitchen_staff' " . ($worker['position'] == 'kitchen_staff' ? 'selected' : '') . ">Kitchen Staff</option>
                                                            <option value='waiter' " . ($worker['position'] == 'waiter' ? 'selected' : '') . ">Waiter</option>
                                                        </select>
                                                    </div>
                                                    <div class='form-group'>
                                                        <label for='vendor_id" . $worker['worker_id'] . "'>Vendor ID</label>
                                                        <select class='form-control' id='vendor_id" . $worker['worker_id'] . "' name='vendor_id' required>";
                                
                                // Get all vendors
                                $vendors_stmt = $conn->query("SELECT id FROM vendors ORDER BY id");
                                while ($vendor = $vendors_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($vendor['id'] == $worker['vendor_id']) ? 'selected' : '';
                                    echo "<option value='" . $vendor['id'] . "' " . $selected . ">Vendor ID: " . htmlspecialchars($vendor['id']) . "</option>";
                                }
                                
                                echo "</select>
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
                        <label for="vendor_id">Vendor ID</label>
                        <select class="form-control" id="vendor_id" name="vendor_id">
                            <option value="">All Vendors</option>
                            <?php
                            $vendors_stmt = $conn->query("SELECT id FROM vendors ORDER BY id");
                            while ($vendor = $vendors_stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = (isset($_GET['vendor_id']) && $_GET['vendor_id'] == $vendor['id']) ? 'selected' : '';
                                echo "<option value='" . $vendor['id'] . "' " . $selected . ">Vendor ID: " . htmlspecialchars($vendor['id']) . "</option>";
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