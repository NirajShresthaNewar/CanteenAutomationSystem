<?php
// Staff Sidebar
echo '

<!-- User Profile Section -->

 <div class="user-info">
                <div class="user-avatar">
                    <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="User Avatar">
                    <span class="status-indicator online"></span>
                </div>
                <div class="user-details">
                    <h5>' . htmlspecialchars($user_details['username'] ?? 'Staff') . '</h5>
                    <p>' . ucfirst($user_details['role'] ?? 'staff') . '</p>
                </div>
            </div>



<!-- Dashboard -->
<li class="nav-item">
    <a href="../staff/dashboard.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-tachometer-alt"></i>
        <p>Dashboard</p>
    </a>
</li>

<!-- Dining Options -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['vendors.php', 'menu_options.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['vendors.php', 'menu_options.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-utensils"></i>
        <p>
            Dining Options
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../staff/vendors.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'vendors.php' ? 'active' : '') . '">
                <p>Browse Vendors</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../staff/menu_options.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'menu_options.php' ? 'active' : '') . '">
                <p>Menu Options</p>
            </a>
        </li>
    </ul>
</li>

<!-- Orders -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['place_order.php', 'order_history.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['place_order.php', 'order_history.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-shopping-cart"></i>
        <p>
            Orders
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../staff/place_order.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'place_order.php' ? 'active' : '') . '">
                <p>Place Order</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../staff/order_history.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'order_history.php' ? 'active' : '') . '">
                <p>Order History</p>
            </a>
        </li>
    </ul>
</li>

<!-- Department Orders -->
<li class="nav-item">
    <a href="../staff/department_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'department_orders.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-building"></i>
        <p>Department Orders</p>
    </a>
</li>

<!-- Reports -->
<li class="nav-item">
    <a href="../staff/reports.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-file-alt"></i>
        <p>Reports</p>
    </a>
</li>

<!-- Profile -->
<li class="nav-item">
    <a href="../staff/profile.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '') . '">
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