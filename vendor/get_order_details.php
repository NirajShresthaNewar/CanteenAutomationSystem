<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    exit('Unauthorized access');
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

// Check if order ID is provided
if (!isset($_POST['order_id']) || empty($_POST['order_id'])) {
    exit('Order ID is required');
}

$order_id = $_POST['order_id'];

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, u.username, u.email, u.contact_number 
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.vendor_id = ?
");
$stmt->execute([$order_id, $vendor_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    exit('Order not found or you do not have permission to view this order');
}

// Get order items
$stmt = $conn->prepare("
    SELECT * FROM order_items 
    WHERE order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order tracking history
$stmt = $conn->prepare("
    SELECT * FROM order_tracking 
    WHERE order_id = ?
    ORDER BY status_changed_at ASC
");
$stmt->execute([$order_id]);
$tracking = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status badge helper function
function getStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge badge-danger">Pending</span>';
        case 'accepted':
            return '<span class="badge badge-info">Accepted</span>';
        case 'in_progress':
            return '<span class="badge badge-primary">In Progress</span>';
        case 'ready':
            return '<span class="badge badge-warning">Ready</span>';
        case 'completed':
            return '<span class="badge badge-success">Completed</span>';
        case 'cancelled':
            return '<span class="badge badge-secondary">Cancelled</span>';
        default:
            return '<span class="badge badge-light">' . ucfirst($status) . '</span>';
    }
}
?>

<div class="order-details">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Order Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Order ID:</strong> #<?php echo $order['receipt_number']; ?></p>
                    <p><strong>Status:</strong> <?php echo getStatusBadge($order['status']); ?></p>
                    <p><strong>Order Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></p>
                    <p><strong>Payment Method:</strong> 
                        <?php if ($order['payment_method'] == 'cash'): ?>
                            <span class="badge badge-success">Cash</span>
                        <?php elseif ($order['payment_method'] == 'esewa'): ?>
                            <span class="badge badge-info">eSewa</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Total Amount:</strong> ₹<?php echo number_format($order['total_amount'], 2); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($order['contact_number']); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Order Items</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-center">Price</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['dish_name']); ?></td>
                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                    <td class="text-center">₹<?php echo number_format($item['price'], 2); ?></td>
                                    <td class="text-right">₹<?php echo number_format($item['quantity'] * $item['price'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right">Total:</th>
                                <th class="text-right">₹<?php echo number_format($order['total_amount'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">Order Tracking</h5>
                </div>
                <div class="card-body p-0">
                    <div class="timeline-order p-3">
                        <?php if (empty($tracking)): ?>
                            <p class="text-center text-muted">No tracking information available</p>
                        <?php else: ?>
                            <ul class="timeline">
                                <?php foreach ($tracking as $track): ?>
                                    <li>
                                        <div class="timeline-badge"><?php echo getStatusBadge($track['status']); ?></div>
                                        <div class="timeline-panel">
                                            <div class="timeline-heading">
                                                <p><small class="text-muted"><i class="fa fa-clock-o"></i> <?php echo date('M d, Y h:i A', strtotime($track['status_changed_at'])); ?></small></p>
                                            </div>
                                            <div class="timeline-body">
                                                <p>Order <?php echo ucfirst($track['status']); ?></p>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    list-style: none;
    padding: 0;
    position: relative;
}
.timeline:before {
    top: 0;
    bottom: 0;
    position: absolute;
    content: " ";
    width: 3px;
    background-color: #eeeeee;
    left: 12px;
    margin-left: -1.5px;
}
.timeline > li {
    margin-bottom: 15px;
    position: relative;
}
.timeline > li:before,
.timeline > li:after {
    content: " ";
    display: table;
}
.timeline > li:after {
    clear: both;
}
.timeline > li > .timeline-panel {
    width: calc(100% - 35px);
    float: right;
    border: 1px solid #d4d4d4;
    border-radius: 5px;
    padding: 10px;
    position: relative;
    box-shadow: 0 1px 6px rgba(0, 0, 0, 0.05);
    background: #fff;
}
.timeline > li > .timeline-panel:before {
    position: absolute;
    top: 10px;
    left: -15px;
    display: inline-block;
    border-top: 15px solid transparent;
    border-right: 15px solid #ccc;
    border-left: 0 solid #ccc;
    border-bottom: 15px solid transparent;
    content: " ";
}
.timeline > li > .timeline-panel:after {
    position: absolute;
    top: 11px;
    left: -14px;
    display: inline-block;
    border-top: 14px solid transparent;
    border-right: 14px solid #fff;
    border-left: 0 solid #fff;
    border-bottom: 14px solid transparent;
    content: " ";
}
.timeline > li > .timeline-badge {
    width: 25px;
    height: 25px;
    line-height: 25px;
    text-align: center;
    position: absolute;
    top: 8px;
    left: 0;
    margin-left: 0;
    z-index: 100;
}
.timeline-body > p,
.timeline-body > ul {
    margin-bottom: 0;
}
</style> 