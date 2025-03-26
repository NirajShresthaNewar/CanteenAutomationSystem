<?php
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
                <p>Staff</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/students.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : '') . '">
                <p>Students</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/workers.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'workers.php' ? 'active' : '') . '">
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
            <a href="../admin/vendors.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'vendors.php' ? 'active' : '') . '">
                <p>All Vendors</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/approve_vendors.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'approve_vendors.php' ? 'active' : '') . '">
                <p>Approve Vendors</p>
                <span class="badge badge-warning right">2</span>
            </a>
        </li>
    </ul>
</li>

<!-- Financial Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['transactions.php', 'financial_reports.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['transactions.php', 'financial_reports.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-money-bill"></i>
        <p>
            Financial Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../admin/transactions.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : '') . '">
                <p>Transactions</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/financial_reports.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'financial_reports.php' ? 'active' : '') . '">
                <p>Financial Reports</p>
            </a>
        </li>
    </ul>
</li>

<!-- Reports -->
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
                <p>User Reports</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../admin/vendor_reports.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'vendor_reports.php' ? 'active' : '') . '">
                <p>Vendor Reports</p>
            </a>
        </li>
    </ul>
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