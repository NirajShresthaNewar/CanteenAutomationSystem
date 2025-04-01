<?php
session_start();
require_once '../connection/db_connection.php';

// Verify role access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Get real data from database
try {
    // Total Users
    $userStmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $userStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active Vendors
    $vendorStmt = $conn->query("SELECT COUNT(*) as total FROM vendors WHERE approval_status = 'approved'");
    $activeVendors = $vendorStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Students
    $studentStmt = $conn->query("SELECT COUNT(*) as total FROM staff_students WHERE role = 'student'");
    $totalStudents = $studentStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Workers
    $workerStmt = $conn->query("SELECT COUNT(*) as total FROM workers");
    $totalWorkers = $workerStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending Approvals
    $pendingStmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE approval_status = 'pending'");
    $pendingApprovals = $pendingStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Recent Activity
    $recentActivityStmt = $conn->query("
        SELECT 
            u.username, 
            u.role, 
            u.created_at 
        FROM users u 
        ORDER BY u.created_at DESC 
        LIMIT 5
    ");
    $recentActivity = $recentActivityStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Handle any database errors
    $error = "Database error: " . $e->getMessage();
}

// Set page title
$pageTitle = "Admin Dashboard";

ob_start();
?>

<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-3 col-6">
        <!-- small box -->
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?php echo $totalUsers; ?></h3>
                <p>Total Users</p>
            </div>
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <!-- small box -->
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?php echo $activeVendors; ?></h3>
                <p>Active Vendors</p>
            </div>
            <div class="icon">
                <i class="fas fa-store"></i>
            </div>
            <a href="../admin/vendors.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <!-- small box -->
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?php echo $totalStudents; ?></h3>
                <p>Students</p>
            </div>
            <div class="icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <a href="../admin/students.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <!-- small box -->
        <div class="small-box bg-danger">
            <div class="inner">
                <h3><?php echo $pendingApprovals; ?></h3>
                <p>Pending Approvals</p>
            </div>
            <div class="icon">
                <i class="fas fa-user-clock"></i>
            </div>
            <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<!-- Main row -->
<div class="row">
    <!-- Left col -->
    <section class="col-lg-7 connectedSortable">
        <!-- User Statistics -->
        <div class="card">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-users mr-1"></i>
                    User Statistics
                </h3>
            </div>
            <div class="card-body">
                <canvas id="user-stats-chart" height="300"></canvas>
            </div>
        </div>
    </section>

    <!-- Right col -->
    <section class="col-lg-5 connectedSortable">
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-history mr-1"></i>
                    Recent Activity
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($recentActivity) && count($recentActivity) > 0): ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($activity['role'])); ?></td>
                                        <td><?php echo date('d M H:i', strtotime($activity['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No recent activity</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// User Statistics Chart
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById("user-stats-chart").getContext("2d");
    
    new Chart(ctx, {
        type: "bar",
        data: {
            labels: ["Vendors", "Students", "Workers", "Pending Approvals"],
            datasets: [{
                label: "Users",
                data: [<?php echo $activeVendors; ?>, <?php echo $totalStudents; ?>, <?php echo $totalWorkers; ?>, <?php echo $pendingApprovals; ?>],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',  // green for vendors
                    'rgba(255, 193, 7, 0.7)',  // yellow for students
                    'rgba(23, 162, 184, 0.7)', // cyan for workers
                    'rgba(220, 53, 69, 0.7)'   // red for pending
                ],
                borderColor: [
                    'rgb(40, 167, 69)',
                    'rgb(255, 193, 7)',
                    'rgb(23, 162, 184)',
                    'rgb(220, 53, 69)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();

// Include layout
include '../includes/layout.php';
?> 