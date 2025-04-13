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
            u.id as user_id,
            u.username,
            u.email,
            u.contact_number,
            u.created_at,
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

$page_title = 'View Vendor';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">View Vendor</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-right">
                    <a href="edit_vendor.php?id=<?php echo $vendor_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Vendor
                    </a>
                    <a href="vendors.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Vendor Information</h3>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <th>Business Name</th>
                                <td><?php echo htmlspecialchars($vendor['username']); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars($vendor['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Contact Number</th>
                                <td><?php echo htmlspecialchars($vendor['contact_number']); ?></td>
                            </tr>
                            <tr>
                                <th>License Number</th>
                                <td><?php echo htmlspecialchars($vendor['license_number']); ?></td>
                            </tr>
                            <tr>
                                <th>Registration Date</th>
                                <td><?php echo date('Y-m-d H:i', strtotime($vendor['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch($vendor['approval_status']) {
                                        case 'approved':
                                            $statusClass = 'success';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'danger';
                                            break;
                                        default:
                                            $statusClass = 'warning';
                                    }
                                    echo "<span class='badge badge-" . $statusClass . "'>" . 
                                         ucfirst($vendor['approval_status']) . "</span>";
                                    ?>
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
                        <table class="table">
                            <tr>
                                <th>School Name</th>
                                <td><?php echo htmlspecialchars($vendor['school_name']); ?></td>
                            </tr>
                            <tr>
                                <th>School Address</th>
                                <td><?php echo htmlspecialchars($vendor['school_address']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 