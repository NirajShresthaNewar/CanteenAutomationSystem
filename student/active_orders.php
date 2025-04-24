<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

try {
    // Get active orders
    $stmt = $conn->prepare("
        SELECT o.*, v.id as vendor_id, u.username as vendor_name,
               CASE 
                    WHEN o.payment_method = 'credit' THEN 'Credit Account'
                    WHEN o.payment_method = 'esewa' THEN 'Online Payment (eSewa)'
                    ELSE 'Cash on Delivery'
               END as payment_method_name,
               CASE
                    WHEN o.status = 'pending' THEN 'bg-warning'
                    WHEN o.status = 'accepted' THEN 'bg-info'
                    WHEN o.status = 'in_progress' THEN 'bg-primary'
                    WHEN o.status = 'ready' THEN 'bg-success'
                    ELSE 'bg-secondary'
               END as status_class
        FROM orders o
        JOIN vendors v ON o.vendor_id = v.id
        JOIN users u ON v.user_id = u.id
        WHERE o.user_id = ? 
        AND o.status IN ('pending', 'accepted', 'in_progress', 'ready')
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $active_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching orders: " . $e->getMessage();
    $active_orders = [];
}

$page_title = 'Active Orders';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Active Orders</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Active Orders</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (empty($active_orders)): ?>
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                    <h5>No Active Orders</h5>
                    <p>You don't have any active orders at the moment.</p>
                    <a href="menu.php" class="btn btn-primary">
                        <i class="fas fa-utensils"></i> Browse Menu
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($active_orders as $order): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    Order #<?php echo $order['receipt_number']; ?>
                                </h3>
                                <div class="card-tools">
                                    <span class="badge <?php echo $order['status_class']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Vendor:</strong> <?php echo htmlspecialchars($order['vendor_name']); ?></p>
                                        <p><strong>Order Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Payment Method:</strong> <?php echo $order['payment_method_name']; ?></p>
                                        <p><strong>Total Amount:</strong> ₹<?php echo number_format($order['total_amount'], 2); ?></p>
                                    </div>
                                </div>

                                <?php
                                // Get order items
                                $stmt = $conn->prepare("
                                    SELECT oi.*, mi.name
                                    FROM order_items oi
                                    JOIN menu_items mi ON oi.menu_item_id = mi.item_id
                                    WHERE oi.order_id = ?
                                ");
                                $stmt->execute([$order['id']]);
                                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <div class="table-responsive mt-3">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                    <td><?php echo $item['quantity']; ?></td>
                                                    <td>₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                                    <td>₹<?php echo number_format($item['subtotal'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    <a href="order_details.php?receipt=<?php echo urlencode($order['receipt_number']); ?>" 
                                       class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if ($order['status'] === 'ready'): ?>
                                        <button type="button" class="btn btn-success btn-sm" 
                                                onclick="markAsReceived('<?php echo $order['id']; ?>')">
                                            <i class="fas fa-check"></i> Mark as Received
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function markAsReceived(orderId) {
    if (confirm('Are you sure you want to mark this order as received?')) {
        window.location.href = 'mark_order_received.php?order_id=' + orderId;
    }
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 