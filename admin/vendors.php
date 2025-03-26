<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Initialize variables for layout
$pageTitle = 'All Vendors';
$additionalStyles = '
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
';
$additionalScripts = '
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="../assets/js/vendors.js"></script>
    <script src="../assets/js/sidebar.js"></script>
';

// Start output buffering
ob_start();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
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
                                        <a href='view_vendor.php?id=" . $vendor['vendor_id'] . "' class='btn btn-info btn-sm'>
                                            <i class='fas fa-eye'></i> View
                                        </a>
                                        <a href='edit_vendor.php?id=" . $vendor['vendor_id'] . "' class='btn btn-primary btn-sm ml-1'>
                                            <i class='fas fa-edit'></i> Edit
                                        </a>
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

// Include the layout
require_once '../includes/layout.php';
?> 