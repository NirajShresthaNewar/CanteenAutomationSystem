<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Check if vendor ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Vendor ID is required";
    header('Location: vendors.php');
    exit();
}

$vendor_id = $_GET['id'];

// Fetch vendor data
try {
    $stmt = $conn->prepare("
        SELECT 
            v.id as vendor_id,
            v.license_number,
            v.approval_status,
            v.created_at as vendor_created_at,
            u.id as user_id,
            u.username,
            u.email,
            u.contact_number,
            u.created_at as user_created_at,
            s.id as school_id,
            s.name as school_name,
            s.address as school_address
        FROM vendors v
        JOIN users u ON v.user_id = u.id
        JOIN schools s ON v.school_id = s.id
        WHERE v.id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        $_SESSION['error'] = "Vendor not found";
        header('Location: vendors.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching vendor: " . $e->getMessage();
    header('Location: vendors.php');
    exit();
}

// Start output buffering
ob_start();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <a href="vendors.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Vendors
                </a>
                <a href="edit_vendor.php?id=<?= $vendor['vendor_id'] ?>" class="btn btn-primary ml-2">
                    <i class="fas fa-edit"></i> Edit Vendor
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Vendor Information</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 40%">Vendor Name</th>
                                <td><?= htmlspecialchars($vendor['username']) ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?= htmlspecialchars($vendor['email']) ?></td>
                            </tr>
                            <tr>
                                <th>Contact Number</th>
                                <td><?= htmlspecialchars($vendor['contact_number']) ?></td>
                            </tr>
                            <tr>
                                <th>License Number</th>
                                <td><?= htmlspecialchars($vendor['license_number']) ?></td>
                            </tr>
                            <tr>
                                <th>Registration Date</th>
                                <td><?= date('Y-m-d H:i', strtotime($vendor['user_created_at'])) ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?php
                                    $statusClass = 'badge-warning';
                                    if ($vendor['approval_status'] === 'approved') {
                                        $statusClass = 'badge-success';
                                    } elseif ($vendor['approval_status'] === 'rejected') {
                                        $statusClass = 'badge-danger';
                                    }
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= ucfirst($vendor['approval_status']) ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">School Information</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 40%">School Name</th>
                                <td><?= htmlspecialchars($vendor['school_name']) ?></td>
                            </tr>
                            <tr>
                                <th>School Address</th>
                                <td><?= htmlspecialchars($vendor['school_address']) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Worker Statistics -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Vendor Statistics</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            // Count workers
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM workers WHERE vendor_id = ?");
                            $stmt->execute([$vendor_id]);
                            $workerCount = $stmt->fetchColumn();
                            
                            // Count staff and students
                            $stmt = $conn->prepare("
                                SELECT 
                                    COUNT(CASE WHEN ss.role = 'staff' THEN 1 END) as staff_count,
                                    COUNT(CASE WHEN ss.role = 'student' THEN 1 END) as student_count
                                FROM staff_students ss
                                WHERE ss.school_id = ?
                            ");
                            $stmt->execute([$vendor['school_id']]);
                            $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            
                            <div class="col-md-4">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-user-tie"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Workers</span>
                                        <span class="info-box-number"><?= $workerCount ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon"><i class="fas fa-chalkboard-teacher"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Staff</span>
                                        <span class="info-box-number"><?= $counts['staff_count'] ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon"><i class="fas fa-user-graduate"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Students</span>
                                        <span class="info-box-number"><?= $counts['student_count'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Workers -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Workers</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Position</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $conn->prepare("
                                        SELECT 
                                            u.username,
                                            u.email,
                                            w.position,
                                            u.created_at,
                                            u.approval_status
                                        FROM workers w
                                        JOIN users u ON w.user_id = u.id
                                        WHERE w.vendor_id = ?
                                        ORDER BY u.created_at DESC
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$vendor_id]);
                                    
                                    $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($workers) > 0) {
                                        foreach ($workers as $worker) {
                                            $statusBadge = '';
                                            switch($worker['approval_status']) {
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
                                            echo "<td>" . htmlspecialchars($worker['username']) . "</td>";
                                            echo "<td>" . htmlspecialchars($worker['email']) . "</td>";
                                            echo "<td>" . htmlspecialchars($worker['position']) . "</td>";
                                            echo "<td>" . date('Y-m-d H:i', strtotime($worker['created_at'])) . "</td>";
                                            echo "<td>" . $statusBadge . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' class='text-center'>No workers found</td></tr>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='5' class='text-center'>Error loading workers</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
    $(".table-striped").DataTable({
        "pageLength": 5,
        "ordering": true,
        "info": false,
        "searching": false,
        "lengthChange": false
    });
});
</script>
';

// Set the page title
$pageTitle = "View Vendor: " . $vendor['username'];

// Include the layout
include '../includes/layout.php';
?> 