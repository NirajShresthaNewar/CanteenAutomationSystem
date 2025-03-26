<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle vendor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $vendor_id = $_POST['vendor_id'];
        $action = $_POST['action'];
        $status = ($action === 'approve') ? 'approved' : 'rejected';

        // Start transaction
        $conn->beginTransaction();

        // Update users table
        $stmt = $conn->prepare("UPDATE users SET approval_status = ? WHERE id = (SELECT user_id FROM vendors WHERE id = ?)");
        $stmt->execute([$status, $vendor_id]);

        // Update vendors table
        $stmt = $conn->prepare("UPDATE vendors SET approval_status = ? WHERE id = ?");
        $stmt->execute([$status, $vendor_id]);

        $conn->commit();
        $_SESSION['success'] = "Vendor successfully " . $status;
    } catch (Exception $e) {
        $conn->rollBack();
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pending Vendor Approvals</h3>
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
                                    u.created_at
                                FROM vendors v
                                JOIN users u ON v.user_id = u.id
                                JOIN schools s ON v.school_id = s.id
                                WHERE v.approval_status = 'pending'
                                ORDER BY u.created_at DESC
                            ");

                            while ($vendor = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($vendor['username']) . "</td>";
                                echo "<td>" . htmlspecialchars($vendor['email']) . "</td>";
                                echo "<td>" . htmlspecialchars($vendor['contact_number']) . "</td>";
                                echo "<td>" . htmlspecialchars($vendor['school_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($vendor['license_number']) . "</td>";
                                echo "<td>" . date('Y-m-d H:i', strtotime($vendor['created_at'])) . "</td>";
                                echo "<td>
                                        <form method='POST' style='display:inline;'>
                                            <input type='hidden' name='vendor_id' value='" . $vendor['vendor_id'] . "'>
                                            <input type='hidden' name='action' value='approve'>
                                            <button type='submit' class='btn btn-success btn-sm'>
                                                <i class='fas fa-check'></i> Approve
                                            </button>
                                        </form>
                                        <form method='POST' style='display:inline;margin-left:5px;'>
                                            <input type='hidden' name='vendor_id' value='" . $vendor['vendor_id'] . "'>
                                            <input type='hidden' name='action' value='reject'>
                                            <button type='submit' class='btn btn-danger btn-sm'>
                                                <i class='fas fa-times'></i> Reject
                                            </button>
                                        </form>
                                    </td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='7'>Error loading vendors</td></tr>";
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
<script type="text/javascript" src="../assets/js/vendors.js"></script>
';

$pageTitle = "Approve Vendors";

include '../includes/layout.php';
?> 