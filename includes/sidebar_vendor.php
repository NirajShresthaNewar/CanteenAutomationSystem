<?php
// Get database connection if not already included
require_once dirname(__FILE__) . '/../connection/db_connection.php';

// Get vendor ID from session
$vendor_id = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($vendor) {
        $vendor_id = $vendor['id'];
    }
}

// Get pending counts
$pending_staff_count = 0;
$pending_students_count = 0;
$pending_workers_count = 0;
$pending_orders_count = 0;

if ($vendor_id > 0) {
    // Get pending staff count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM staff_students
        WHERE school_id IN (SELECT school_id FROM vendors WHERE id = ?)
        AND approval_status = 'pending'
        AND role = 'staff'
    ");
    $stmt->execute([$vendor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_staff_count = $result['count'];
    
    // Get pending students count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM staff_students
        WHERE school_id IN (SELECT school_id FROM vendors WHERE id = ?)
        AND approval_status = 'pending'
        AND role = 'student'
    ");
    $stmt->execute([$vendor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_students_count = $result['count'];
    
    // Get pending workers count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM workers
        WHERE vendor_id = ?
        AND approval_status = 'pending'
    ");
    $stmt->execute([$vendor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_workers_count = $result['count'];
    
    // Get pending orders count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM orders
        WHERE vendor_id = ?
        AND status = 'pending'
    ");
    $stmt->execute([$vendor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_orders_count = $result['count'];
}

// Vendor Sidebar
echo '
<li class="nav-item">
    <a href="../vendor/dashboard.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-tachometer-alt"></i>
        <p>Dashboard</p>
    </a>
</li>

<!-- User Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['manage_staff.php', 'manage_students.php', 'manage_workers.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['manage_staff.php', 'manage_students.php', 'manage_workers.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-users"></i>
        <p>
            User Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/manage_staff.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) === 'manage_staff.php' ? 'active' : '') . '">
                <p>Manage Staff</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/manage_students.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) === 'manage_students.php' ? 'active' : '') . '">
                <p>Manage Students</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/manage_workers.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) === 'manage_workers.php' ? 'active' : '') . '">
                <p>Manage Workers</p>
            </a>
        </li>
    </ul>
</li>

<!-- User Approval -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['approve_staff.php', 'approve_students.php', 'approve_workers.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['approve_staff.php', 'approve_students.php', 'approve_workers.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-user-check"></i>
        <p>
            User Approval
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/approve_staff.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) === 'approve_staff.php' ? 'active' : '') . '">
                <p>Approve Staff</p>
                ' . ($pending_staff_count > 0 ? '<span class="badge badge-warning right">' . $pending_staff_count . '</span>' : '') . '
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/approve_students.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) === 'approve_students.php' ? 'active' : '') . '">
                <p>Approve Students</p>
                ' . ($pending_students_count > 0 ? '<span class="badge badge-warning right">' . $pending_students_count . '</span>' : '') . '
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/approve_workers.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) === 'approve_workers.php' ? 'active' : '') . '">
                <p>Approve Workers</p>
                ' . ($pending_workers_count > 0 ? '<span class="badge badge-warning right">' . $pending_workers_count . '</span>' : '') . '
            </a>
        </li>
    </ul>
</li>

<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['menu_items.php', 'add_menu.php', 'categories.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['menu_items.php', 'add_menu.php', 'categories.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-utensils"></i>
        <p>
            Menu Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/menu_items.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'menu_items.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-th"></i>
                <p>All Menu Items</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/add_menu.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'add_menu.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-plus-circle"></i>
                <p>Add New Item</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/categories.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-tags"></i>
                <p>Categories</p>
            </a>
        </li>
    </ul>
</li>

<!-- Menu QR Code -->
<li class="nav-item">
    <a href="../vendor/qr_code.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'qr_code.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-qrcode"></i>
        <p>Menu QR Code</p>
    </a>
</li>

<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['manage_orders.php', 'order_history.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['manage_orders.php', 'order_history.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-shopping-cart"></i>
        <p>
            Order Management
            <i class="fas fa-angle-left right"></i>
            ' . ($pending_orders_count > 0 ? '<span class="badge badge-danger right">' . $pending_orders_count . '</span>' : '') . '
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/manage_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'manage_orders.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-tasks"></i>
                <p>Manage Orders</p>
                ' . ($pending_orders_count > 0 ? '<span class="badge badge-danger right">' . $pending_orders_count . '</span>' : '') . '
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/order_history.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'order_history.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-history"></i>
                <p>Order History</p>
            </a>
        </li>
    </ul>
</li>

<!-- Credit Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['credit_accounts.php', 'credit_transactions.php', 'credit_settings.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['credit_accounts.php', 'credit_transactions.php', 'credit_settings.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-credit-card"></i>
        <p>
            Credit Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/credit_accounts.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'credit_accounts.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-users"></i>
                <p>Credit Accounts</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/credit_transactions.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'credit_transactions.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-exchange-alt"></i>
                <p>Transactions</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/credit_settings.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'credit_settings.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-cogs"></i>
                <p>Credit Settings</p>
            </a>
        </li>
    </ul>
</li>

<!-- Subscription Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['subscription_plans.php', 'subscription_transactions.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['subscription_plans.php', 'subscription_transactions.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-ticket-alt"></i>
        <p>
            Subscription Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/subscription_plans.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'subscription_plans.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-calendar-check"></i>
                <p>Subscription Plans</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/subscription_transactions.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'subscription_transactions.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-history"></i>
                <p>Transactions</p>
            </a>
        </li>
    </ul>
</li>

<li class="nav-item">
    <a href="../vendor/analytics.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-chart-line"></i>
        <p>Sales & Analytics</p>
    </a>
</li>

<li class="nav-item">
    <a href="../vendor/profile.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-store"></i>
        <p>Store Profile</p>
    </a>
</li>

<li class="nav-item">
    <a href="../vendor/settings.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-cog"></i>
        <p>Settings</p>
    </a>
</li>

<li class="nav-item">
    <a href="../auth/logout.php" class="nav-link">
        <i class="nav-icon fas fa-sign-out-alt"></i>
        <p>Logout</p>
    </a>
</li>';
?>