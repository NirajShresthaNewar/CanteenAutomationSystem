<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit();
}

// Database connection
require_once '../connection/db_connection.php';

// Derive scope: staff's school and affiliated vendors only
$school_id = null;
$active_students_count = 0;
$student_issues_count = 0; // Placeholder – no issues table available yet
$reports_count = 0;        // Placeholder – adjust when reports are implemented
$vendors_status = [];

try {
    // Find staff's school
    $stmt = $conn->prepare("SELECT school_id FROM staff_students WHERE user_id = ? AND role = 'staff' LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $school_id = (int)$row['school_id'];
    }

    if ($school_id) {
        // Active students in the same school (approved only)
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM staff_students WHERE school_id = ? AND role = 'student' AND approval_status = 'approved'");
        $stmt->execute([$school_id]);
        $active_students_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Vendor status for affiliated vendors only + active orders count per vendor
        // Active statuses align with usage elsewhere in codebase
        $stmt = $conn->prepare("\r
            SELECT 
                v.id AS vendor_id,
                u.username AS vendor_name,
                COALESCE(SUM(CASE WHEN COALESCE(ot.status, 'pending') IN ('pending','accepted','in_progress','ready') THEN 1 ELSE 0 END), 0) AS active_orders
            FROM vendors v
            JOIN users u ON v.user_id = u.id
            LEFT JOIN orders o ON o.vendor_id = v.id
            LEFT JOIN (
                SELECT order_id, status
                FROM order_tracking
                WHERE id IN (SELECT MAX(id) FROM order_tracking GROUP BY order_id)
            ) ot ON ot.order_id = o.id
            WHERE v.school_id = ? AND v.approval_status = 'approved'
            GROUP BY v.id, u.username
            ORDER BY u.username
        ");
        $stmt->execute([$school_id]);
        $vendors_status = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (PDOException $e) {
    // Fail silently for dashboard; leave defaults (zeros/empty)
}

$page_title = 'Staff Dashboard';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Staff Dashboard</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <!-- Info boxes (restricted to staff's school) -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Students</span>
                        <span class="info-box-number"><?php echo (int)$active_students_count; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Student Issues</span>
                        <span class="info-box-number"><?php echo (int)$student_issues_count; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-danger"><i class="fas fa-file-alt"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Reports</span>
                        <span class="info-box-number"><?php echo (int)$reports_count; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <div class="col-md-8">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Orders</h3>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Staff</th>
                        <th>Vendor</th>
                        <th>Status</th>
                        <th>Order Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch 10 most recent orders for this school
                    $recent_orders = [];
                     if ($school_id) {
                        $stmt = $conn->prepare("
                            SELECT 
                                o.id AS order_id,
                                su.username AS staff_name,
                                vu.username AS vendor_name,
                                COALESCE(ot.status, 'pending') AS status,
                                o.order_date
                            FROM orders o
                            JOIN staff_students ss ON o.user_id = ss.user_id AND ss.school_id = ? AND ss.role = 'staff'
                            JOIN users su ON o.user_id = su.id
                            JOIN vendors v ON o.vendor_id = v.id
                            JOIN users vu ON v.user_id = vu.id
                            LEFT JOIN (
                                SELECT order_id, status
                                FROM order_tracking
                                WHERE id IN (SELECT MAX(id) FROM order_tracking GROUP BY order_id)
                            ) ot ON ot.order_id = o.id
                            ORDER BY o.order_date DESC
                            LIMIT 10
                        ");
                        $stmt->execute([$school_id]);
                        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    if (empty($recent_orders)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No recent orders found</td>
                        </tr>
                    <?php else:
                        foreach ($recent_orders as $order): ?>
                            <tr>
                                <td><?php echo (int)$order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['staff_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['vendor_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($order['status'])); ?></td>
                                <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                            </tr>
                        <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Vendor Status</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (empty($vendors_status)): ?>
                                <li class="list-group-item">No vendors found</li>
                            <?php else: ?>
                                <?php foreach ($vendors_status as $vs): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?php echo htmlspecialchars($vs['vendor_name']); ?></span>
                                        <span class="badge badge-primary badge-pill">
                                            <?php echo (int)$vs['active_orders']; ?> active orders
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
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