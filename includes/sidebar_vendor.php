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
        FROM orders o
        LEFT JOIN (
            SELECT order_id, status
            FROM order_tracking
            WHERE id IN (
                SELECT MAX(id)
                FROM order_tracking
                GROUP BY order_id
            )
        ) ot ON o.id = ot.order_id
        WHERE o.vendor_id = ?
        AND COALESCE(ot.status, 'pending') = 'pending'
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
                <i class="nav-icon fas fa-user-tie"></i>
                <p>Manage Staff</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/manage_students.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) === 'manage_students.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-user-graduate"></i>
                <p>Manage Students</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/manage_workers.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) === 'manage_workers.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-hard-hat"></i>
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
                <i class="nav-icon fas fa-user-shield"></i>
                <p>Approve Staff</p>
                ' . ($pending_staff_count > 0 ? '<span class="badge badge-warning right">' . $pending_staff_count . '</span>' : '') . '
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/approve_students.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) === 'approve_students.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-user-plus"></i>
                <p>Approve Students</p>
                ' . ($pending_students_count > 0 ? '<span class="badge badge-warning right">' . $pending_students_count . '</span>' : '') . '
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/approve_workers.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) === 'approve_workers.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-user-cog"></i>
                <p>Approve Workers</p>
                ' . ($pending_workers_count > 0 ? '<span class="badge badge-warning right">' . $pending_workers_count . '</span>' : '') . '
            </a>
        </li>
    </ul>
</li>

<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['menu_items.php', 'add_menu.php', 'categories.php', 'manage_recipes.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['menu_items.php', 'add_menu.php', 'categories.php', 'manage_recipes.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-utensils"></i>
        <p>
            Menu Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/menu_items.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'menu_items.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-list"></i>
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
        <li class="nav-item">
            <a href="../vendor/manage_recipes.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'manage_recipes.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-book"></i>
                <p>Manage Recipes</p>
            </a>
        </li>
    </ul>
</li>

<!-- Production Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['record_production.php', 'production_history.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['record_production.php', 'production_history.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-industry"></i>
        <p>
            Production
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/record_production.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'record_production.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-plus-circle"></i>
                <p>Record Production</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/production_history.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'production_history.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-history"></i>
                <p>Production History</p>
            </a>
        </li>
    </ul>
</li>

<!-- Inventory Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'add_inventory.php', 'inventory_history.php', 'ingredient_settings.php', 'inventory_alerts.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'add_inventory.php', 'inventory_history.php', 'ingredient_settings.php', 'inventory_alerts.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-boxes"></i>
        <p>
            Inventory Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/inventory.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-box"></i>
                <p>Current Inventory</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/add_inventory.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'add_inventory.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-plus-circle"></i>
                <p>Add Inventory</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/inventory_history.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'inventory_history.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-history"></i>
                <p>Inventory History</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/inventory_alerts.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'inventory_alerts.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-exclamation-triangle"></i>
                <p>Inventory Alerts';

// Get alert count using the new function
require_once dirname(__FILE__) . '/../vendor/check_inventory_alerts.php';
$alert_count = getAlertCount($conn, $vendor_id);
if ($alert_count > 0) {
    echo '<span class="badge badge-danger right">' . $alert_count . '</span>';
}

echo '</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/ingredient_settings.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'ingredient_settings.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-cog"></i>
                <p>Ingredient Settings</p>
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

<!-- Order Management -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['manage_orders.php', 'order_history.php', 'manage_payments.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['manage_orders.php', 'order_history.php', 'manage_payments.php']) ? 'active' : '') . '">
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
            <a href="../vendor/manage_payments.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'manage_payments.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-money-bill-wave"></i>
                <p>Manage Payments</p>
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
<li class="nav-item">
    <a href="../vendor/manage_credit_requests.php" class="nav-link">
        <i class="nav-icon fas fa-credit-card"></i>
        <p>Credit Requests</p>
    </a>
</li>

<!-- Subscription Management -->
<li class="nav-item">
    <a href="#" class="nav-link">
        <i class="nav-icon fas fa-utensils"></i>
        <p>
            Subscription Plans
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../vendor/subscription_plans.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'subscription_plans.php' ? 'active' : '') . '">
                <i class="far fa-circle nav-icon"></i>
                <p>Manage Plans</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/meal_slots.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'meal_slots.php' ? 'active' : '') . '">
                <i class="far fa-circle nav-icon"></i>
                <p>Meal Time Slots</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/meal_combos.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'meal_combos.php' ? 'active' : '') . '">
                <i class="far fa-circle nav-icon"></i>
                <p>Meal Combos</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../vendor/subscription_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'subscription_orders.php' ? 'active' : '') . '">
                <i class="far fa-circle nav-icon"></i>
                <p>Subscription Orders</p>
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
<!--
<li class="nav-item">
    <a href="../vendor/settings.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-cog"></i>
        <p>Settings</p>
    </a>
    -->
</li>

<li class="nav-item">
    <a href="../auth/logout.php" class="nav-link">
        <i class="nav-icon fas fa-sign-out-alt"></i>
        <p>Logout</p>
    </a>
</li>';
?>