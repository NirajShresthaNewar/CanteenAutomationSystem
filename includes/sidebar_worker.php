<?php
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
                <p>Assigned Orders</p>
                <span class="badge badge-info right">3</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../worker/order_history.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'order_history.php' ? 'active' : '') . '">
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