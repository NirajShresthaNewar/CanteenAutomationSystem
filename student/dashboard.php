<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

require_once '../connection/db_connection.php';

// Get active orders count (orders that are not completed, cancelled, or rejected)
$stmt = $conn->prepare("
    SELECT COUNT(*) as active_count
    FROM orders o
    LEFT JOIN (
        SELECT ot1.*
        FROM order_tracking ot1
        INNER JOIN (
            SELECT order_id, MAX(status_changed_at) as max_date
            FROM order_tracking
            GROUP BY order_id
        ) ot2 ON ot1.order_id = ot2.order_id AND ot1.status_changed_at = ot2.max_date
    ) ot ON o.id = ot.order_id
    WHERE o.user_id = ? 
    AND COALESCE(ot.status, 'pending') NOT IN ('completed', 'cancelled', 'rejected')
");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$active_orders_count = $result['active_count'];

// Get orders count for current month
$stmt = $conn->prepare("
    SELECT COUNT(*) as month_count
    FROM orders
    WHERE user_id = ? 
    AND MONTH(order_date) = MONTH(CURRENT_DATE())
    AND YEAR(order_date) = YEAR(CURRENT_DATE())
");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$monthly_orders_count = $result['month_count'];

// Get favorite menu items count - using menu_items table instead of favorites
$stmt = $conn->prepare("
    SELECT COUNT(*) as fav_count
    FROM menu_items mi
    JOIN order_items oi ON mi.item_id = oi.menu_item_id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.user_id = ?
    GROUP BY mi.item_id
    HAVING COUNT(*) > 1
");
$stmt->execute([$_SESSION['user_id']]);
$favorite_items_count = $stmt->rowCount();

$page_title = 'Student Dashboard';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Student Dashboard</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <!-- Info boxes -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-wallet"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Wallet Balance</span>
                        <span class="info-box-number">₹0</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-shopping-cart"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Orders This Month</span>
                        <span class="info-box-number"><?php echo $monthly_orders_count; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Orders</span>
                        <span class="info-box-number"><?php echo $active_orders_count; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-danger"><i class="fas fa-heart"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Favorite Items</span>
                        <span class="info-box-number"><?php echo $favorite_items_count; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders and Favorites -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Orders</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Vendor</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center">No orders found</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Favorite Vendors</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">No favorite vendors found</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 