<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit();
}

try {
    // Get completed orders
    $stmt = $conn->prepare("
        SELECT o.*, v.id as vendor_id, u.username as vendor_name,
               COALESCE(ot.status, 'completed') as status,
               odd.order_type, odd.delivery_location,
               odd.contact_number, odd.table_number, 
               odd.delivery_instructions,
               CASE 
                    WHEN o.payment_method = 'khalti' THEN 'Khalti Payment'
                    WHEN o.payment_method = 'cash' THEN 'Cash Payment'
                    ELSE o.payment_method
               END as payment_method_name,
               CASE
                    WHEN COALESCE(ot.status, 'completed') = 'completed' THEN 'bg-success'
                    WHEN COALESCE(ot.status, 'completed') = 'cancelled' THEN 'bg-danger'
                    ELSE 'bg-secondary'
               END as status_class
        FROM orders o
        JOIN vendors v ON o.vendor_id = v.id
        JOIN users u ON v.user_id = u.id
        LEFT JOIN order_delivery_details odd ON o.id = odd.order_id
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
        AND COALESCE(ot.status, 'completed') IN ('completed', 'cancelled')
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $completed_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching orders: " . $e->getMessage();
    $completed_orders = [];
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

        <?php if (empty($completed_orders)): ?>
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
            <div class="row">
                <?php foreach ($completed_orders as $order): ?>
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
                                        <p><strong>Total Amount:</strong> Rs. <?php echo number_format($order['total_amount'], 2); ?></p>
                                    </div>
                                </div>

                                <!-- Add Delivery Details Section -->
                                <div class="delivery-details mt-3">
                                    <h6 class="border-bottom pb-2">Order Details</h6>
                                    <div class="row">
                                        <div class="col-12">
                                            <p>
                                                <strong>Order Type:</strong> 
                                                <span class="badge badge-info">
                                                    <?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?>
                                                </span>
                                            </p>
                                            <?php if ($order['order_type'] === 'delivery'): ?>
                                                <p><strong>Delivery Location:</strong> <?php echo htmlspecialchars($order['delivery_location']); ?></p>
                                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($order['contact_number']); ?></p>
                                                <?php if ($order['delivery_instructions']): ?>
                                                    <p><strong>Instructions:</strong> <?php echo htmlspecialchars($order['delivery_instructions']); ?></p>
                                                <?php endif; ?>
                                            <?php elseif ($order['order_type'] === 'dine_in'): ?>
                                                <p><strong>Table Number:</strong> <?php echo htmlspecialchars($order['table_number']); ?></p>
                                            <?php endif; ?>
                                        </div>
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
                                                    <td>Rs. <?php echo number_format($item['unit_price'], 2); ?></td>
                                                    <td>Rs. <?php echo number_format($item['subtotal'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="3" class="text-right">Total:</th>
                                                <th>Rs. <?php echo number_format($order['total_amount'], 2); ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    <a href="order_details.php?receipt=<?php echo urlencode($order['receipt_number']); ?>" 
                                       class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 