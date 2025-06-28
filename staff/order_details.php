<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit();
}

// Check if receipt number is provided
if (!isset($_GET['receipt'])) {
    $_SESSION['error'] = "Receipt number is required";
    header('Location: order_history.php');
    exit();
}

$receipt_number = $_GET['receipt'];

try {
    // Get order details
    $stmt = $conn->prepare("
        SELECT o.*, v.id as vendor_id, u.username as vendor_name,
               COALESCE(ot.status, 'pending') as status,
               odd.order_type, odd.delivery_location, odd.building_name,
               odd.floor_number, odd.room_number, odd.contact_number,
               odd.table_number, odd.delivery_instructions,
               CASE 
                    WHEN o.payment_method = 'credit' THEN 'Credit Account'
                    WHEN o.payment_method = 'esewa' THEN 'Online Payment (eSewa)'
                    WHEN o.payment_method = 'khalti' THEN 'Khalti Payment'
                    ELSE 'Cash on Delivery'
               END as payment_method_name,
               CASE
                    WHEN COALESCE(ot.status, 'pending') = 'pending' THEN 'bg-warning'
                    WHEN COALESCE(ot.status, 'pending') = 'accepted' THEN 'bg-info'
                    WHEN COALESCE(ot.status, 'pending') = 'in_progress' THEN 'bg-primary'
                    WHEN COALESCE(ot.status, 'pending') = 'ready' THEN 'bg-success'
                    WHEN COALESCE(ot.status, 'pending') = 'completed' THEN 'bg-success'
                    WHEN COALESCE(ot.status, 'pending') = 'cancelled' THEN 'bg-danger'
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
        WHERE o.receipt_number = ? AND o.user_id = ?
    ");
    $stmt->execute([$receipt_number, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = "Order not found";
        header('Location: order_history.php');
        exit();
    }

    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, mi.name, mi.description
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.item_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get order tracking history
    $stmt = $conn->prepare("
        SELECT ot.*, u.username as updated_by
        FROM order_tracking ot
        LEFT JOIN users u ON ot.updated_by = u.id
        WHERE ot.order_id = ?
        ORDER BY ot.status_changed_at ASC
    ");
    $stmt->execute([$order['id']]);
    $tracking_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching order details: " . $e->getMessage();
    header('Location: order_history.php');
    exit();
}

$page_title = 'Order Details';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Order Details</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="order_history.php">Order History</a></li>
                    <li class="breadcrumb-item active">Order Details</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <!-- Order Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Order #<?php echo htmlspecialchars($order['receipt_number']); ?>
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
                                <p><strong>Payment Method:</strong> <?php echo $order['payment_method_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Order Type:</strong> 
                                    <span class="badge badge-info">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?>
                                    </span>
                                </p>
                                <?php if ($order['order_type'] === 'delivery'): ?>
                                    <p><strong>Delivery Location:</strong> <?php echo htmlspecialchars($order['delivery_location']); ?></p>
                                    <?php if ($order['building_name']): ?>
                                        <p><strong>Building:</strong> <?php echo htmlspecialchars($order['building_name']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($order['floor_number']): ?>
                                        <p><strong>Floor:</strong> <?php echo htmlspecialchars($order['floor_number']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($order['room_number']): ?>
                                        <p><strong>Room:</strong> <?php echo htmlspecialchars($order['room_number']); ?></p>
                                    <?php endif; ?>
                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($order['contact_number']); ?></p>
                                    <?php if ($order['delivery_instructions']): ?>
                                        <p><strong>Instructions:</strong> <?php echo htmlspecialchars($order['delivery_instructions']); ?></p>
                                    <?php endif; ?>
                                <?php elseif ($order['order_type'] === 'dine_in'): ?>
                                    <p><strong>Table Number:</strong> <?php echo htmlspecialchars($order['table_number']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="table-responsive mt-4">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>Rs. <?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td>Rs. <?php echo number_format($item['subtotal'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-right">Total:</th>
                                        <th>Rs. <?php echo number_format($order['total_amount'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Order Tracking Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Order Tracking</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="timeline timeline-inverse p-3">
                            <?php foreach ($tracking_history as $track): ?>
                                <div class="time-label">
                                    <span class="bg-primary">
                                        <?php echo date('M d, Y', strtotime($track['status_changed_at'])); ?>
                                    </span>
                                </div>
                                <div>
                                    <i class="fas fa-clock bg-primary"></i>
                                    <div class="timeline-item">
                                        <span class="time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('h:i A', strtotime($track['status_changed_at'])); ?>
                                        </span>
                                        <h3 class="timeline-header">
                                            Status changed to <strong><?php echo ucfirst(str_replace('_', ' ', $track['status'])); ?></strong>
                                        </h3>
                                        <?php if ($track['updated_by']): ?>
                                            <div class="timeline-body">
                                                Updated by: <?php echo htmlspecialchars($track['updated_by']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Actions Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Actions</h3>
                    </div>
                    <div class="card-body">
                        <a href="order_history.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-arrow-left"></i> Back to Order History
                        </a>
                        <?php if ($order['status'] === 'completed'): ?>
                            <button type="button" class="btn btn-success btn-block" onclick="reorder('<?php echo $order['id']; ?>')">
                                <i class="fas fa-redo"></i> Reorder
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function reorder(orderId) {
    if (confirm('Would you like to place the same order again?')) {
        window.location.href = 'reorder.php?order_id=' + orderId;
    }
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 