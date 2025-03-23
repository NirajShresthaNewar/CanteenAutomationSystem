<ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
    <li class="nav-item">
        <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-speedometer"></i>
            <p>Dashboard</p>
        </a>
    </li>

    <li class="nav-item">
        <a href="menu.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-journal-text"></i>
            <p>Menu</p>
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
                <a href="place_order.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Place Order</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="order_history.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Order History</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="bulk_orders.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Bulk Orders</p>
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
                <a href="meal_plans.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>View Plans</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="subscriptions.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>My Subscriptions</p>
                </a>
            </li>
            <li class="nav-item">
                <a href="department_plans.php" class="nav-link">
                    <i class="nav-icon bi bi-circle"></i>
                    <p>Department Plans</p>
                </a>
            </li>
        </ul>
    </li>

    <li class="nav-item">
        <a href="wallet.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'wallet.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-wallet2"></i>
            <p>
                My Wallet
                <span class="badge text-bg-info float-end">
                    ₹<?php echo isset($_SESSION['wallet_balance']) ? number_format($_SESSION['wallet_balance'], 2) : '0.00'; ?>
                </span>
            </p>
        </a>
    </li>

    <li class="nav-item">
        <a href="department.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'department.php' ? 'active' : ''; ?>">
            <i class="nav-icon bi bi-building"></i>
            <p>Department</p>
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
                <span class="badge text-bg-danger float-end">3</span>
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