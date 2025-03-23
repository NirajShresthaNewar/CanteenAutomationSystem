<ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
    <li class="nav-item">
        <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-speedometer"></i>
            <p>Dashboard</p>
        </a>
    </li>

    <li class="nav-item">
        <a href="#" class="nav-link">
            <i class="nav-icon bi bi-cart-fill"></i>
            <p>
                Orders
                <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
        </a>
        <ul class="nav nav-treeview">
            <li class="nav-item">
                <a href="pending_orders.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>
                        Pending Orders
                        <span class="badge text-bg-warning float-end">5</span>
                    </p>
                </a>
            </li>
            <li class="nav-item">
                <a href="completed_orders.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Completed Orders</p>
                </a>
            </li>
        </ul>
    </li>

    <li class="nav-item">
        <a href="inventory.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-box-seam"></i>
            <p>Inventory</p>
        </a>
    </li>

    <li class="nav-item">
        <a href="schedule.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-calendar-week"></i>
            <p>Work Schedule</p>
        </a>
    </li>

    <li class="nav-header">ACCOUNT</li>
    
    <li class="nav-item">
        <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-person-circle"></i>
            <p>My Profile</p>
        </a>
    </li>

    <li class="nav-item">
        <a href="notifications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-bell"></i>
            <p>
                Notifications
                <span class="badge text-bg-danger float-end">2</span>
            </p>
        </a>
    </li>

    <li class="nav-item">
        <a href="../auth/logout.php" class="nav-link">
            <i class="nav-icon bi bi-box-arrow-right"></i>
            <p>Logout</p>
        </a>
    </li>
</ul> 