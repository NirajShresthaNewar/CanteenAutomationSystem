<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

try {
    // Get completed and cancelled orders
    $stmt = $conn->prepare("
        SELECT o.*, v.id as vendor_id, u.username as vendor_name,
               CASE 
                    WHEN o.payment_method = 'credit' THEN 'Credit Account'
                    WHEN o.payment_method = 'esewa' THEN 'Online Payment (eSewa)'
                    ELSE 'Cash on Delivery'
               END as payment_method_name,
               CASE
                    WHEN o.status = 'completed' THEN 'bg-success'
                    WHEN o.status = 'cancelled' THEN 'bg-danger'
                    ELSE 'bg-secondary'
               END as status_class
        FROM orders o
        JOIN vendors v ON o.vendor_id = v.id
        JOIN users u ON v.user_id = u.id
        WHERE o.user_id = ? 
        AND o.status IN ('completed', 'cancelled')
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching order history: " . $e->getMessage();
    $orders = [];
}

$page_title = 'Order History';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Order History</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Order History</li>
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

        <?php if (empty($orders)): ?>
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-history fa-4x text-muted mb-3"></i>
                    <h5>No Order History</h5>
                    <p>You haven't completed any orders yet.</p>
                    <a href="menu.php" class="btn btn-primary">
                        <i class="fas fa-utensils"></i> Browse Menu
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Filter buttons -->
            <div class="mb-3">
                <button type="button" class="btn btn-outline-success filter-btn active" data-status="all">
                    All Orders
                </button>
                <button type="button" class="btn btn-outline-success filter-btn" data-status="completed">
                    Completed Orders
                </button>
                <button type="button" class="btn btn-outline-danger filter-btn" data-status="cancelled">
                    Cancelled Orders
                </button>
            </div>

            <div class="row">
                <?php foreach ($orders as $order): ?>
                    <div class="col-md-6 order-item" data-status="<?php echo $order['status']; ?>">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    Order #<?php echo $order['receipt_number']; ?>
                                </h3>
                                <div class="card-tools">
                                    <span class="badge <?php echo $order['status_class']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
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
                                    <?php if ($order['status'] === 'completed'): ?>
                                        <a href="reorder.php?order_id=<?php echo $order['id']; ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="fas fa-redo"></i> Reorder
                                        </a>
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
// Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const orderItems = document.querySelectorAll('.order-item');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');

            const status = this.dataset.status;

            orderItems.forEach(item => {
                if (status === 'all' || item.dataset.status === status) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 