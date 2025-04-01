<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle delete request
if (isset($_POST['delete_vendor'])) {
    try {
        $vendor_id = $_POST['vendor_id'];
        
        $conn->beginTransaction();
        
        // Get user_id before deleting
        $stmt = $conn->prepare("SELECT user_id FROM vendors WHERE id = ?");
        $stmt->execute([$vendor_id]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vendor) {
            // Delete from vendors table
            $stmt = $conn->prepare("DELETE FROM vendors WHERE id = ?");
            $stmt->execute([$vendor_id]);
            
            // Delete from users table
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$vendor['user_id']]);
            
            $conn->commit();
            $_SESSION['success'] = "Vendor has been deleted successfully";
        } else {
            throw new Exception("Vendor not found");
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error deleting vendor: " . $e->getMessage();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle approval/rejection
if (isset($_POST['update_status'])) {
    try {
        $vendor_id = $_POST['vendor_id'];
        $new_status = $_POST['status'];
        
        $conn->beginTransaction();
        
        // Update approval status in vendors table
        $stmt = $conn->prepare("UPDATE vendors SET approval_status = ? WHERE id = ?");
        $stmt->execute([$new_status, $vendor_id]);
        
        // Also update approval status in users table
        $stmt = $conn->prepare("
            UPDATE users u 
            JOIN vendors v ON u.id = v.user_id 
            SET u.approval_status = ?
            WHERE v.id = ?
        ");
        $stmt->execute([$new_status, $vendor_id]);
        
        $conn->commit();
        $_SESSION['success'] = "Vendor status has been updated to " . ucfirst($new_status);
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating status: " . $e->getMessage();
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
                <h3 class="card-title">All Vendors</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Vendor Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>School</th>
                            <th>License Number</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->query("
                                SELECT 
                                    v.id as vendor_id,
                                    u.username,
                                    u.email,
                                    u.contact_number,
                                    s.name as school_name,
                                    v.license_number,
                                    u.created_at,
                                    v.approval_status
                                FROM vendors v
                                JOIN users u ON v.user_id = u.id
                                JOIN schools s ON v.school_id = s.id
                                ORDER BY u.created_at DESC
                            ");

                            while ($vendor = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $statusBadge = '';
                                switch($vendor['approval_status']) {
                                    case 'approved':
                                        $statusBadge = '<span class="badge badge-success">Approved</span>';
                                        break;
                                    case 'rejected':
                                        $statusBadge = '<span class="badge badge-danger">Rejected</span>';
                                        break;
                                    default:
                                        $statusBadge = '<span class="badge badge-warning">Pending</span>';
                                }
                                
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($vendor['username']) . "</td>";
                                echo "<td>" . htmlspecialchars($vendor['email']) . "</td>";
                                echo "<td>" . htmlspecialchars($vendor['contact_number']) . "</td>";
                                echo "<td>" . htmlspecialchars($vendor['school_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($vendor['license_number']) . "</td>";
                                echo "<td>" . date('Y-m-d H:i', strtotime($vendor['created_at'])) . "</td>";
                                echo "<td>" . $statusBadge . "</td>";
                                echo "<td>
                                        <div class='btn-group'>
                                            <a href='view_vendor.php?id=" . $vendor['vendor_id'] . "' class='btn btn-info btn-sm'>
                                                <i class='fas fa-eye'></i> View
                                            </a>
                                            <a href='edit_vendor.php?id=" . $vendor['vendor_id'] . "' class='btn btn-primary btn-sm'>
                                                <i class='fas fa-edit'></i> Edit
                                            </a>
                                            <button type='button' class='btn btn-secondary btn-sm dropdown-toggle dropdown-toggle-split' data-toggle='dropdown'>
                                                <i class='fas fa-cog'></i>
                                            </button>
                                            <div class='dropdown-menu'>
                                                <form method='POST'>
                                                    <input type='hidden' name='vendor_id' value='" . $vendor['vendor_id'] . "'>
                                                    <input type='hidden' name='status' value='approved'>
                                                    <button type='submit' name='update_status' class='dropdown-item text-success'>
                                                        <i class='fas fa-check'></i> Approve
                                                    </button>
                                                </form>
                                                <form method='POST'>
                                                    <input type='hidden' name='vendor_id' value='" . $vendor['vendor_id'] . "'>
                                                    <input type='hidden' name='status' value='rejected'>
                                                    <button type='submit' name='update_status' class='dropdown-item text-danger'>
                                                        <i class='fas fa-times'></i> Reject
                                                    </button>
                                                </form>
                                                <div class='dropdown-divider'></div>
                                                <form method='POST' onsubmit='return confirm(\"Are you sure you want to delete this vendor? This action cannot be undone.\")'>
                                                    <input type='hidden' name='vendor_id' value='" . $vendor['vendor_id'] . "'>
                                                    <button type='submit' name='delete_vendor' class='dropdown-item text-danger'>
                                                        <i class='fas fa-trash'></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='8'>Error loading vendors</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
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

// Set the page title
$pageTitle = "All Vendors";

// Include the layout
include '../includes/layout.php';
?> 