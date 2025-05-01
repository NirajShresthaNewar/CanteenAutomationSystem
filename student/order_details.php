<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Get receipt number from URL
$receipt_number = filter_input(INPUT_GET, 'receipt', FILTER_SANITIZE_STRING);

if (!$receipt_number) {
    $_SESSION['error'] = "Invalid order receipt number";
    header('Location: active_orders.php');
    exit();
}

try {
    // Get order details with latest status
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            v.id as vendor_id,
            u.username as vendor_name,
            u.contact_number as vendor_phone,
            u.email as vendor_email,
            COALESCE(ot.status, 'pending') as current_status,
            ot.notes as status_notes,
            ot.status_changed_at as status_updated_at,
            CASE 
                WHEN o.payment_method = 'credit' THEN 'Credit Account'
                WHEN o.payment_method = 'esewa' THEN 'Online Payment (eSewa)'
                ELSE 'Cash on Delivery'
            END as payment_method_name,
            CASE
                WHEN COALESCE(ot.status, 'pending') = 'pending' THEN 'bg-warning'
                WHEN COALESCE(ot.status, 'pending') = 'accepted' THEN 'bg-info'
                WHEN COALESCE(ot.status, 'pending') = 'in_progress' THEN 'bg-primary'
                WHEN COALESCE(ot.status, 'pending') = 'ready' THEN 'bg-success'
                WHEN COALESCE(ot.status, 'pending') = 'completed' THEN 'bg-success'
                WHEN COALESCE(ot.status, 'pending') = 'cancelled' THEN 'bg-danger'
                WHEN COALESCE(ot.status, 'pending') = 'rejected' THEN 'bg-danger'
                ELSE 'bg-secondary'
            END as status_class
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
        WHERE o.receipt_number = ? AND o.user_id = ?
    ");
    $stmt->execute([$receipt_number, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found");
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

    // Get status history
    $stmt = $conn->prepare("
        SELECT 
            ot.*,
            u.username as updated_by_name
        FROM order_tracking ot
        LEFT JOIN users u ON ot.updated_by = u.id
        WHERE ot.order_id = ?
        ORDER BY ot.status_changed_at DESC
    ");
    $stmt->execute([$order['id']]);
    $status_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching order details: " . $e->getMessage();
    header('Location: active_orders.php');
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
                    <li class="breadcrumb-item"><a href="active_orders.php">Active Orders</a></li>
                    <li class="breadcrumb-item active">Order Details</li>
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

        <div class="row">
            <!-- Order Summary -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Order #<?php echo htmlspecialchars($order['receipt_number']); ?>
                            <span class="badge <?php echo $order['status_class']; ?> ml-2">
                                <?php echo ucfirst(str_replace('_', ' ', $order['current_status'])); ?>
                            </span>
                        </h3>
                        <div class="card-tools">
                            <?php if ($order['current_status'] === 'completed'): ?>
                                <a href="reorder.php?order_id=<?php echo $order['id']; ?>" 
                                   class="btn btn-success btn-sm">
                                    <i class="fas fa-redo"></i> Reorder
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-sm-6">
                                <h6 class="mb-3">Order Information:</h6>
                                <div><strong>Order Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></div>
                                <div><strong>Payment Method:</strong> <?php echo $order['payment_method_name']; ?></div>
                                <div><strong>Total Amount:</strong> ₹<?php echo number_format($order['total_amount'], 2); ?></div>
                                <?php if ($order['preparation_time']): ?>
                                    <div><strong>Preparation Time:</strong> <?php echo $order['preparation_time']; ?> minutes</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-6">
                                <h6 class="mb-3">Vendor Information:</h6>
                                <div><strong>Name:</strong> <?php echo htmlspecialchars($order['vendor_name']); ?></div>
                                <div><strong>Phone:</strong> <?php echo htmlspecialchars($order['vendor_phone']); ?></div>
                                <div><strong>Email:</strong> <?php echo htmlspecialchars($order['vendor_email']); ?></div>
                            </div>
                        </div>

                        <h6 class="mb-3">Order Items:</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Description</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-right">Price</th>
                                        <th class="text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-right">₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td class="text-right">₹<?php echo number_format($item['subtotal'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-right"><strong>Total:</strong></td>
                                        <td class="text-right"><strong>₹<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Timeline -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Status Timeline</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="timeline timeline-inverse p-3">
                            <?php foreach ($status_history as $status): ?>
                                <div class="timeline-item">
                                    <span class="time">
                                        <i class="fas fa-clock"></i> 
                                        <?php echo date('M d, Y h:i A', strtotime($status['status_changed_at'])); ?>
                                    </span>
                                    <h3 class="timeline-header">
                                        Status changed to 
                                        <span class="badge <?php echo $order['status_class']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $status['status'])); ?>
                                        </span>
                                    </h3>
                                    <?php if (!empty($status['notes'])): ?>
                                        <div class="timeline-body">
                                            <?php echo htmlspecialchars($status['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="timeline-footer">
                                        <small class="text-muted">
                                            Updated by: <?php echo htmlspecialchars($status['updated_by_name']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    margin: 0;
    padding: 0;
    position: relative;
}

.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #ddd;
    left: 31px;
    margin: 0;
    border-radius: 2px;
}

.timeline-item {
    position: relative;
    margin-left: 60px;
    margin-right: 15px;
    margin-bottom: 15px;
    background: #f8f9fa;
    border-radius: 3px;
    padding: 10px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 0;
    width: 12px;
    height: 12px;
    background: #007bff;
    border-radius: 100%;
    border: 2px solid #fff;
}

.timeline-header {
    margin: 0;
    color: #555;
    border-bottom: 1px solid #f4f4f4;
    padding-bottom: 8px;
    font-size: 16px;
    line-height: 1.1;
    font-weight: 600;
}

.timeline-body {
    padding-top: 10px;
    color: #666;
}

.timeline-footer {
    margin-top: 10px;
}

.time {
    float: right;
    color: #999;
    font-size: 12px;
}
</style>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 