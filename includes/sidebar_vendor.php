<ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
    <li class="nav-item">
        <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-speedometer"></i>
            <p>Dashboard</p>
        </a>
    </li>

    <li class="nav-item">
        <a href="#" class="nav-link">
            <i class="nav-icon bi bi-journal-text"></i>
            <p>
                Menu Management
                <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
        </a>
        <ul class="nav nav-treeview">
            <li class="nav-item">
                <a href="menu_items.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Menu Items</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="categories.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Categories</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="special_offers.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Special Offers</p>
                </a>
            </li>
        </ul>
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
                <a href="new_orders.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>
                        New Orders
                        <span class="badge text-bg-warning float-end">8</span>
                    </p>
                </a>
            </li>
            <li class="nav-item">
                <a href="processing_orders.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Processing</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="completed_orders.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Completed</p>
                </a>
            </li>
        </ul>
    </li>

    <li class="nav-item">
        <a href="#" class="nav-link">
            <i class="nav-icon bi bi-calendar-check"></i>
            <p>
                Meal Plans
                <i class="nav-arrow bi bi-chevron-right"></i>
            </p>
        </a>
        <ul class="nav nav-treeview">
            <li class="nav-item">
                <a href="manage_plans.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Manage Plans</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="plan_subscribers.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Subscribers</p>
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
        <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-graph-up"></i>
            <p>Reports</p>
        </a>
    </li>

    <li class="nav-item">
        <a href="wallet.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'wallet.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-wallet2"></i>
            <p>
                Earnings
                <span class="badge text-bg-success float-end">
                    ₹<?php echo isset($_SESSION['wallet_balance']) ? number_format($_SESSION['wallet_balance'], 2) : '0.00'; ?>
                </span>
            </p>
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
                <span class="badge text-bg-danger float-end">4</span>
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