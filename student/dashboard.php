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

// Get total credit balance across all vendors
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(current_balance), 0) as total_credit
    FROM credit_accounts
    WHERE user_id = ? AND status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$total_credit = $result['total_credit'];

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
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-credit-card"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Credit Balance</span>
                        <span class="info-box-number">₹<?php echo number_format($total_credit, 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-shopping-cart"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Orders This Month</span>
                        <span class="info-box-number"><?php echo $monthly_orders_count; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Active Orders</span>
                        <span class="info-box-number"><?php echo $active_orders_count; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders -->
        <div class="row">
            <div class="col-md-12">
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
                                  <!--  <th>Action</th> -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get recent orders
                                $stmt = $conn->prepare("
                                    SELECT 
                                        o.id,
                                        o.receipt_number,
                                        o.total_amount,
                                        u.username as vendor_name,
                                        ot.status as order_status
                                    FROM orders o
                                    JOIN vendors v ON o.vendor_id = v.id
                                    JOIN users u ON v.user_id = u.id
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
                                    ORDER BY o.order_date DESC
                                    LIMIT 5
                                ");
                                $stmt->execute([$_SESSION['user_id']]);
                                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (empty($orders)) {
                                    echo '<tr><td colspan="6" class="text-center">No orders found</td></tr>';
                                } else {
                                    foreach ($orders as $order) {
                                        // Get order items
                                        $stmt = $conn->prepare("
                                            SELECT 
                                                oi.quantity,
                                                mi.name as item_name
                                            FROM order_items oi
                                            JOIN menu_items mi ON oi.menu_item_id = mi.item_id
                                            WHERE oi.order_id = ?
                                        ");
                                        $stmt->execute([$order['id']]);
                                        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        $items_text = array_map(function($item) {
                                            return $item['quantity'] . 'x ' . $item['item_name'];
                                        }, $items);
                                        $items_text = implode(', ', $items_text);
                                        
                                        // Get status badge class
                                        $status_class = match($order['order_status']) {
                                            'pending' => 'warning',
                                            'accepted' => 'info',
                                            'in_progress' => 'primary',
                                            'ready' => 'success',
                                            'completed' => 'secondary',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                        
                                        echo '<tr>
                                            <td>#' . htmlspecialchars($order['receipt_number']) . '</td>
                                            <td>' . htmlspecialchars($order['vendor_name']) . '</td>
                                            <td>' . htmlspecialchars($items_text) . '</td>
                                            <td>₹' . number_format($order['total_amount'], 2) . '</td>
                                            <td><span class="badge badge-' . $status_class . '">' . ucfirst($order['order_status']) . '</span></td>
                                           <!-- <td>
                                                <a href="view_order.php?id=' . $order['id'] . '" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td> -->
                                        </tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
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