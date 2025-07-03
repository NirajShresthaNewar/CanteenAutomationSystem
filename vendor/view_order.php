<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../index.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header('Location: ../index.php');
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header('Location: manage_orders.php');
    exit();
}

try {
    // Get order details with delivery information
    $stmt = $conn->prepare("
        SELECT o.*, u.username as customer_name, u.email as customer_email,
            COALESCE(ot.status, 'pending') as current_status,
            odd.order_type, odd.delivery_location, odd.building_name,
            odd.floor_number, odd.room_number, odd.contact_number,
            odd.table_number
        FROM orders o
        JOIN users u ON o.user_id = u.id
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
        WHERE o.id = ? AND o.vendor_id = ?
    ");
    $stmt->execute([$_GET['id'], $vendor['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Location: manage_orders.php');
        exit();
    }

    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, mi.name as item_name
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.item_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get order timeline
    $stmt = $conn->prepare("
        SELECT ot.*, u.username as changed_by_name
        FROM order_tracking ot
        LEFT JOIN users u ON ot.updated_by = u.id
        WHERE ot.order_id = ?
        ORDER BY ot.status_changed_at ASC
    ");
    $stmt->execute([$order['id']]);
    $orderTimeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set page title
    $pageTitle = "Order Details #" . $order['receipt_number'];

    // Start output buffering
    ob_start();
?>
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <a href="manage_orders.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            </div>
        </div>
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Order #<?php echo $order['receipt_number']; ?>
                            <span class="badge badge-<?php echo getStatusBadgeClass($order['current_status']); ?> ml-2">
                                <?php echo ucfirst($order['current_status']); ?>
                            </span>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Customer Information</h5>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                <p><strong>Order Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Order Type</h5>
                                <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?></p>
                                <?php if ($order['order_type'] === 'delivery'): ?>
                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($order['delivery_location']); ?></p>
                                    <p><strong>Building:</strong> <?php echo htmlspecialchars($order['building_name']); ?></p>
                                    <p><strong>Floor/Room:</strong> <?php echo htmlspecialchars($order['floor_number']); ?>/<?php echo htmlspecialchars($order['room_number']); ?></p>
                                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($order['contact_number']); ?></p>
                                <?php elseif ($order['order_type'] === 'dine_in'): ?>
                                    <p><strong>Table Number:</strong> <?php echo htmlspecialchars($order['table_number']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h5 class="mt-4">Order Items</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td class="text-end">₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td class="text-end">₹<?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Total:</th>
                                        <th class="text-end">₹<?php echo number_format($order['total_amount'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Order Timeline</h3>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($orderTimeline as $event): ?>
                                <div class="time-label">
                                    <span class="bg-info">
                                        <?php echo date('M d, Y h:i A', strtotime($event['status_changed_at'])); ?>
                                    </span>
                                </div>
                                <div>
                                    <i class="fas fa-clock bg-blue"></i>
                                    <div class="timeline-item">
                                        <h3 class="timeline-header">
                                            Status changed to: <strong><?php echo ucfirst($event['status']); ?></strong>
                                        </h3>
                                        <?php if ($event['notes']): ?>
                                            <div class="timeline-body">
                                                <?php echo htmlspecialchars($event['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($event['changed_by_name']): ?>
                                            <div class="timeline-footer">
                                                By: <?php echo htmlspecialchars($event['changed_by_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
    // Get the buffered content
    $content = ob_get_clean();

    // Include the layout
    include '../includes/layout.php';
} catch (PDOException $e) {
    // Log error and redirect
    error_log("Error in view_order.php: " . $e->getMessage());
    header('Location: manage_orders.php');
    exit();
}
?> 