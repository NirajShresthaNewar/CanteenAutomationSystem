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
    // Check if cart_items table exists first
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

// Output the profile section first
echo '
<!-- User Profile Section -->

            <div class="user-info">
                <div class="user-avatar">
                    <img src="' . (isset($user_details['profile_pic']) && $user_details['profile_pic'] ? '../uploads/profile/' . $user_details['profile_pic'] : '../assets/img/default-profile.png') . '" class="sidebar-profile-img" alt="User Image">
                    <span class="status-indicator online"></span>
                </div>
                <div class="user-details">
                    <h5>' . htmlspecialchars($user_details['username'] ?? 'Student') . '</h5>
                    <p>' . ucfirst($user_details['role'] ?? 'student') . '</p>
                </div>
            </div>

<!-- Navigation Items -->
';

// Student Sidebar
echo '
<!-- Dashboard -->
<li class="nav-item">
    <a href="../student/dashboard.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-tachometer-alt"></i>
        <p>Dashboard</p>
    </a>
</li>

<!-- Meal Subscription -->
<li class="nav-item">
    <a href="../student/meal_subscription.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'meal_subscription.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-utensils"></i>
        <p>Meal Subscription</p>
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
                <i class="nav-icon fas fa-store-alt"></i>
                <p>Browse Vendors</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../student/menu.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-clipboard-list"></i>
                <p>Menu</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../student/scan_qr.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'scan_qr.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-qrcode"></i>
                <p>Scan QR Menu</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../student/cart.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : '') . '">
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
            <a href="../student/active_orders.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'active_orders.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-spinner"></i>
                <p>Active Orders</p>
                ' . ($active_orders_count > 0 ? '<span class="badge badge-info right">' . $active_orders_count . '</span>' : '') . '
            </a>
        </li>
        <li class="nav-item">
            <a href="../student/order_history.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'order_history.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-history"></i>
                <p>Order History</p>
            </a>
        </li>
    </ul>
</li>

<!-- Credit Accounts -->
<li class="nav-item has-treeview ' . (in_array(basename($_SERVER['PHP_SELF']), ['credit_accounts.php', 'credit_transactions.php']) ? 'menu-open' : '') . '">
    <a href="#" class="nav-link ' . (in_array(basename($_SERVER['PHP_SELF']), ['credit_accounts.php', 'credit_transactions.php']) ? 'active' : '') . '">
        <i class="nav-icon fas fa-credit-card"></i>
        <p>
            Credit Accounts
            <i class="fas fa-angle-left right"></i>
        </p>
    </a>
    <ul class="nav nav-treeview">
        <li class="nav-item">
            <a href="../student/credit_accounts.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'credit_accounts.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-wallet"></i>
                <p>My Credit Accounts</p>
            </a>
        </li>
        <li class="nav-item">
            <a href="../student/credit_transactions.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'credit_transactions.php' ? 'active' : '') . '">
                <i class="nav-icon fas fa-exchange-alt"></i>
                <p>Transactions</p>
            </a>
        </li>
    </ul>
</li>


<!-- Subscription Portal -->
<li class="nav-item">
<a href="../student/subscription_portal.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'subscription_portal.php' ? 'active' : '') . '">
<i class="nav-icon fas fa-ticket-alt"></i>
<p>Subscription Portal</p>
</a>
</li>
<!-- Favorites -->
<!--
<li class="nav-item">
    <a href="../student/favorites.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'favorites.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-heart"></i>
        <p>Favorites</p>
    </a>
</li>
-->

<!-- Payments -->
<!--
<li class="nav-item">
    <a href="../student/payments.php" class="nav-link ' . (basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : '') . '">
        <i class="nav-icon fas fa-money-bill-wave"></i>
        <p>Payment Methods</p>
    </a>
</li>
-->
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