<?php
// Vendor Sidebar
echo '
<!-- Dashboard -->
<li class="nav-item">
    <a href="../vendor/dashboard.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-tachometer-alt"></i>
        <p>Dashboard</p>
    </a>
</li>

<!-- Menu Management -->
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
                <p>All Menu Items</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/add_menu.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'add_menu.php' ? 'active' : '') . '">
                <p>Add New Item</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/categories.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '') . '">
                <p>Categories</p>
            </a>
        </li>
    </ul>
</li>

<!-- Order Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['orders.php', 'pending_orders.php', 'completed_orders.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['orders.php', 'pending_orders.php', 'completed_orders.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-shopping-cart"></i>
        <p>
            Order Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '') . '">
                <p>All Orders</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/pending_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'pending_orders.php' ? 'active' : '') . '">
                <p>Pending Orders</p>
                <span class="badge badge-warning right">5</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/completed_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'completed_orders.php' ? 'active' : '') . '">
                <p>Completed Orders</p>
            </a>
        </li>
    </ul>
</li>

<!-- Sales & Analytics -->
<li class="nav-item">
    <a href="../vendor/analytics.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-chart-line"></i>
        <p>Sales & Analytics</p>
    </a>
</li>

<!-- Store Profile -->
<li class="nav-item">
    <a href="../vendor/profile.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-store"></i>
        <p>Store Profile</p>
    </a>
</li>

<!-- Settings -->
<li class="nav-item">
    <a href="../vendor/settings.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '') . '">
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