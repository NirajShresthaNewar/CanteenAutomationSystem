<?php
// Get database connection if not already included
require_once dirname(__FILE__) . '/../connection/db_connection.php';

// Get pending vendor count
$pending_vendor_count = 0;
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM vendors
        WHERE approval_status = 'pending'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_vendor_count = $result['count'];
} catch (PDOException $e) {
    // Error handling - keep count as 0
}

// Dashboard
echo '
<!-- Dashboard -->
<li class="nav-item">
    <a href="../admin/dashboard.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-tachometer-alt"></i>
        <p>Dashboard</p>
    </a>
</li>

<!-- User Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['staff.php', 'students.php', 'workers.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['staff.php', 'students.php', 'workers.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-users"></i>
        <p>
            User Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../admin/staff.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-user-tie"></i>
                <p>Staff</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/students.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-user-graduate"></i>
                <p>Students</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/workers.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'workers.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-hard-hat"></i>
                <p>Workers</p>
            </a>
        </li>
    </ul>
</li>

<!-- Vendor Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['vendors.php', 'approve_vendors.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['vendors.php', 'approve_vendors.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-store"></i>
        <p>
            Vendor Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../admin/vendors.php" class="nav-link submenu-link ' . (basename($_SERVER['PHP_SELF']) == 'vendors.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-store-alt"></i>
                <p>All Vendors</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/approve_vendors.php" class="nav-link submenu-link ' . (basename($_SERVER['PHP_SELF']) == 'approve_vendors.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-check-circle"></i>
                <p>Approve Vendors</p>
                ' . ($pending_vendor_count > 0 ? '<span class="badge badge-warning right">' . $pending_vendor_count . '</span>' : '') . '
            </a>
        </li>
    </ul>
</li>

<!-- Inventory Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['manage_ingredients.php', 'manage_categories.php', 'inventory_alerts.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['manage_ingredients.php', 'manage_categories.php', 'inventory_alerts.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-boxes"></i>
        <p>
            Inventory Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../admin/manage_ingredients.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'manage_ingredients.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-box"></i>
                <p>Manage Ingredients</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/manage_categories.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'manage_categories.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-tags"></i>
                <p>Manage Categories</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/inventory_alerts.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'inventory_alerts.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-exclamation-triangle"></i>
                <p>Inventory Alerts</p>
            </a>
        </li>
    </ul>
</li>

<!-- School Management -->
<li class="nav-item">
    <a href="../admin/manage_schools.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'manage_schools.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-school"></i>
        <p>Manage Schools</p>
    </a>
</li>

<!-- Financial Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['transactions.php', 'reports.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['transactions.php', 'reports.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-money-bill-wave"></i>
        <p>
            Financial Management
            <i class="right fas fa-angle-left"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../admin/transactions.php" class="nav-link">
                <i class="fas fa-exchange-alt nav-icon"></i>
                <p>Transactions</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/reports.php" class="nav-link">
                <i class="fas fa-chart-line nav-ico"></i>
                <p>Financial Reports</p>
            </a>
        </li>
    </ul>
</li>

<!-- Reports -->
<!--
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['user_reports.php', 'vendor_reports.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['user_reports.php', 'vendor_reports.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-chart-pie"></i>
        <p>
            Reports
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../admin/user_reports.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'user_reports.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-users-cog"></i>
                <p>User Reports</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/vendor_reports.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'vendor_reports.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-store-alt"></i>
                <p>Vendor Reports</p>
            </a>
        </li>
    </ul>
</li>
-->

<!-- Profile -->
<li class="nav-item">
    <a href="../admin/profile.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-user"></i>
        <p>My Profile</p>
    </a>
</li>

<!-- Settings -->
<li class="nav-item">
    <a href="../admin/settings.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-cog"></i>
        <p>Settings</p>
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