<?php
// Get database connection if not already included
require_once dirname(__FILE__) . '/../connection/db_connection.php';

// Get worker ID from session
$worker_id = 0;
$assigned_orders_count = 0;

if (isset($_SESSION['user_id'])) {
    // Get worker ID
    try {
        $stmt = $conn->prepare("SELECT id FROM workers WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($worker) {
            $worker_id = $worker['id'];
            
            // Get assigned orders count
            try {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count
                    FROM orders
                    WHERE assigned_worker_id = ?
                    AND status IN ('accepted', 'in_progress', 'ready')
                ");
                $stmt->execute([$worker_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $assigned_orders_count = $result['count'];
            } catch (PDOException $e) {
                // Column assigned_worker_id might not exist or other error
                $assigned_orders_count = 0;
            }
        }
    } catch (PDOException $e) {
        // Table doesn't exist or other error
        $worker_id = 0;
    }
}

// Worker Sidebar
echo '
<!-- Dashboard -->
<li class="nav-item">
    <a href="../worker/dashboard.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-tachometer-alt"></i>
        <p>Dashboard</p>
    </a>
</li>

<!-- Orders -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['assigned_orders.php', 'order_history.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['assigned_orders.php', 'order_history.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-clipboard-list"></i>
        <p>
            Orders
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../worker/assigned_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'assigned_orders.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-tasks"></i>
                <p>Assigned Orders</p>
                ' . ($assigned_orders_count > 0 ? '<span class="badge badge-info right">' . $assigned_orders_count . '</span>' : '') . '
            </a>
        </li>
        <li class="nav-item">
            <a href="../worker/order_history.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'order_history.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-history"></i>
                <p>Order History</p>
            </a>
        </li>
    </ul>
</li>

<!-- Schedule -->
<li class="nav-item">
    <a href="../worker/schedule.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-calendar-alt"></i>
        <p>My Schedule</p>
    </a>
</li>

<!-- Performance -->
<li class="nav-item">
    <a href="../worker/performance.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'performance.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-chart-bar"></i>
        <p>My Performance</p>
    </a>
</li>

<!-- Time Tracking -->
<li class="nav-item">
    <a href="../worker/timesheet.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'timesheet.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-clock"></i>
        <p>Time Tracking</p>
    </a>
</li>

<!-- Profile -->
<li class="nav-item">
    <a href="../worker/profile.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-user"></i>
        <p>My Profile</p>
    </a>
</li>

<!-- Logout -->
<li class="nav-item">
    <a href="../auth/logout.php" class="nav-link">
        <i class="nav-icon fas fa-sign-out-alt"></i>
        <p>Logout</p>
    </a>
</li>';
?> 