<?php
// Student Sidebar
echo '
<!-- Dashboard -->
<li class="nav-item">
    <a href="../student/dashboard.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-tachometer-alt"></i>
        <p>Dashboard</p>
    </a>
</li>

<!-- Order Food -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['vendors.php', 'menu.php', 'cart.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['vendors.php', 'menu.php', 'cart.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-utensils"></i>
        <p>
            Order Food
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../student/vendors.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'vendors.php' ? 'active' : '') . '">
                <p>Browse Vendors</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../student/menu.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : '') . '">
                <p>Menu</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../student/cart.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : '') . '">
                <p>Cart</p>
                <span class="badge badge-success right">4</span>
            </a>
        </li>
    </ul>
</li>

<!-- My Orders -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['active_orders.php', 'order_history.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['active_orders.php', 'order_history.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-shopping-bag"></i>
        <p>
            My Orders
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../student/active_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'active_orders.php' ? 'active' : '') . '">
                <p>Active Orders</p>
                <span class="badge badge-info right">2</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../student/order_history.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'order_history.php' ? 'active' : '') . '">
                <p>Order History</p>
            </a>
        </li>
    </ul>
</li>

<!-- Favorites -->
<li class="nav-item">
    <a href="../student/favorites.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'favorites.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-heart"></i>
        <p>Favorites</p>
    </a>
</li>

<!-- Payments -->
<li class="nav-item">
    <a href="../student/payments.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-credit-card"></i>
        <p>Payment Methods</p>
    </a>
</li>

<!-- Profile -->
<li class="nav-item">
    <a href="../student/profile.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '') . '">
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