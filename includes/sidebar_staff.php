<?php
// Get database connection if not already included
require_once dirname(__FILE__) . '/../connection/db_connection.php';

// Get user details
$user_details = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("
            SELECT u.username, u.profile_pic, u.role
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error silently
    }
}

// Get cart and active order counts
$cart_count = 0;
$active_orders_count = 0;

if (isset($_SESSION['user_id'])) {
    // Check cart items count
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM cart_items
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $cart_count = $result['count'];
    } catch (PDOException $e) {
        // Table doesn't exist or other error
        $cart_count = 0;
    }
    
    // Check active orders count
    try {
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
            WHERE o.customer_id = (
                SELECT id FROM staff_students WHERE user_id = ?
            )
            AND COALESCE(ot.status, 'pending') IN ('pending', 'accepted', 'in_progress', 'ready')
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $active_orders_count = $result['count'];
    } catch (PDOException $e) {
        // Query failed or other error
        $active_orders_count = 0;
    }
}

// Get profile image or default placeholder
$profileImage = !empty($user_details['profile_pic']) ? 
    '../uploads/profile/' . $user_details['profile_pic'] : 
    'https://via.placeholder.com/150?text=' . substr($user_details['username'] ?? 'S', 0, 1);

// Staff Sidebar
echo '

<!-- User Profile Section -->

 <div class="user-info">
                <div class="user-avatar">
                    <img src="' . $profileImage . '" class="sidebar-profile-img" alt="User Image">
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
      <!--  <li class="nav-item">
            <a href="../staff/vendors.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'vendors.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-store-alt"></i>
                <p>Browse Vendors</p>
            </a>
        </li> -->
        <li class="nav-item">
            <a href="../staff/menu.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-clipboard-list"></i>
                <p>Menu</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../staff/cart.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-shopping-basket"></i>
                <p>Cart</p>
                ' . ($cart_count > 0 ? '<span class="badge badge-success right">' . $cart_count . '</span>' : '') . '
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
            <a href="../staff/active_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'active_orders.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-spinner"></i>
                <p>Active Orders</p>
                ' . ($active_orders_count > 0 ? '<span class="badge badge-info right">' . $active_orders_count . '</span>' : '') . '
            </a>
        </li>
        <li class="nav-item">
            <a href="../staff/order_history.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'order_history.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-history"></i>
                <p>Order History</p>
            </a>
        </li>
    </ul>
</li>

<!-- Department Orders -->
<!--
<li class="nav-item">
    <a href="../staff/department_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'department_orders.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-building"></i>
        <p>Department Orders</p>
    </a>
</li>
-->
<!-- Reports -->
<!--
<li class="nav-item">
    <a href="../staff/reports.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-file-alt"></i>
        <p>Reports</p>
    </a>
</li>
-->
<!-- Profile -->
<li class="nav-item">
    <a href="../staff/profile.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-user"></i>
        <p>My Profile</p>
    </a>
</li>

<!-- Credit Management -->
<li class="nav-item">
    <a href="#" class="nav-link">
        <i class="nav-icon fas fa-credit-card"></i>
        <p>
            Credit Management
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../staff/request_credit.php" class="nav-link">
                <i class="far fa-circle nav-icon"></i>
                <p>Request Credit</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../staff/view_credit_requests.php" class="nav-link">
                <i class="far fa-circle nav-icon"></i>
                <p>My Credit Requests</p>
            </a>
        </li>
    </ul>
</li>

<!-- Logout -->
<li class="nav-item">
    <a href="../auth/logout.php" class="nav-link">
        <i class="nav-icon fas fa-sign-out-alt"></i>
        <p>Logout</p>
    </a>
</li>';
?> 