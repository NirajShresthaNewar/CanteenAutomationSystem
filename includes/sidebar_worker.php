<?php
// Get database connection if not already included
require_once dirname(__FILE__) . '/../connection/db_connection.php';

// Get worker details
$worker = null;
$worker_position = null;
try {
    $stmt = $conn->prepare("
        SELECT w.*, u.username 
        FROM workers w
        JOIN users u ON w.user_id = u.id 
        WHERE w.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);
    $worker_position = strtolower($worker['position'] ?? '');
} catch (PDOException $e) {
    error_log("Error fetching worker details: " . $e->getMessage());
}

// Get assigned orders count for waiters
$assigned_orders_count = 0;
if ($worker && $worker_position === 'waiter') {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM order_assignments oa
            WHERE oa.worker_id = ?
            AND oa.status != 'delivered'
        ");
        $stmt->execute([$worker['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $assigned_orders_count = $result['count'];
    } catch (PDOException $e) {
        error_log("Error fetching assigned orders count: " . $e->getMessage());
        $assigned_orders_count = 0;
    }
}

// Get pending kitchen orders count if worker is kitchen staff
$pending_kitchen_orders = 0;
if ($worker && $worker_position === 'kitchen_staff') {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM orders o
            LEFT JOIN (
                SELECT ot1.*
                FROM order_tracking ot1
                INNER JOIN (
                    SELECT order_id, MAX(status_changed_at) as max_date
                    FROM order_tracking
                    GROUP BY order_id
                ) ot2 ON ot1.order_id = ot2.order_id AND ot1.status_changed_at = ot2.max_date
            ) latest_tracking ON o.id = latest_tracking.order_id
            WHERE COALESCE(latest_tracking.status, 'pending') IN ('pending', 'accepted', 'in_progress')
            AND o.vendor_id = (
                SELECT vendor_id 
                FROM workers 
                WHERE id = ?
            )
        ");
        $stmt->execute([$worker['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $pending_kitchen_orders = $result['count'];
    } catch (PDOException $e) {
        error_log("Error fetching pending kitchen orders: " . $e->getMessage());
        $pending_kitchen_orders = 0;
    }
}

// Start sidebar HTML
echo '
<!-- Dashboard -->
<li class="nav-item">
    <a href="../worker/dashboard.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-tachometer-alt"></i>
        <p>Dashboard</p>
    </a>
</li>';

// Show kitchen orders section only for kitchen staff
if ($worker_position === 'kitchen_staff') {
    echo '
    <!-- Kitchen Orders -->
    <li class="nav-item">
        <a href="../worker/kitchen_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'kitchen_orders.php' ? 'active' : '') . '">
            <i class="nav-icon fas fa-utensils"></i>
            <p>
                Kitchen Orders
                ' . ($pending_kitchen_orders > 0 ? '<span class="badge badge-warning right">' . $pending_kitchen_orders . '</span>' : '') . '
            </p>
        </a>
    </li>';
}

// Show assigned orders only for waiters
if ($worker_position === 'waiter') {
    echo '
    <!-- Assigned Orders -->
    <li class="nav-item">
        <a href="../worker/assigned_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'assigned_orders.php' ? 'active' : '') . '">
            <i class="nav-icon fas fa-tasks"></i>
            <p>
                Assigned Orders
                ' . ($assigned_orders_count > 0 ? '<span class="badge badge-info right">' . $assigned_orders_count . '</span>' : '') . '
            </p>
        </a>
    </li>';
}

echo '
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