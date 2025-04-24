<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if receipt number is provided
if (!isset($_GET['receipt']) || empty($_GET['receipt'])) {
    header('Location: my_orders.php');
    exit();
}

$receipt_number = $_GET['receipt'];

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, v.user_id as vendor_user_id, u.username as vendor_name,
           CASE 
                WHEN o.payment_method = 'credit' THEN 'Credit Account'
                WHEN o.payment_method = 'esewa' THEN 'Online Payment (eSewa)'
                ELSE 'Cash on Delivery'
           END as payment_method_name
    FROM orders o
    JOIN vendors v ON o.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    JOIN staff_students ss ON o.customer_id = ss.id
    WHERE o.receipt_number = ? AND ss.user_id = ?
");
$stmt->execute([$receipt_number, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "Order not found or does not belong to you.";
    header('Location: my_orders.php');
    exit();
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, mi.name
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.item_id
    WHERE oi.order_id = ?
");
$stmt->execute([$order['id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Order Confirmation';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Order Confirmation</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Order Confirmation</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success">
                        <h3 class="card-title">
                            <i class="fas fa-check-circle"></i> Your Order Has Been Placed!
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="fas fa-shopping-cart fa-4x text-success mb-3"></i>
                            <h4>Thank you for your order!</h4>
                            <p class="lead">Your order has been received and is being processed.</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span class="info-box-icon bg-info"><i class="fas fa-receipt"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Order Number</span>
                                        <span class="info-box-number">#<?php echo $order['receipt_number']; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span class="info-box-icon bg-warning"><i class="fas fa-store"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Vendor</span>
                                        <span class="info-box-number"><?php echo htmlspecialchars($order['vendor_name']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Payment Method</span>
                                        <span class="info-box-number"><?php echo $order['payment_method_name']; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span class="info-box-icon bg-danger"><i class="fas fa-rupee-sign"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Amount</span>
                                        <span class="info-box-number">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h5><i class="fas fa-clipboard-list"></i> Order Summary</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td>₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>₹<?php echo number_format($item['subtotal'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-right">Total:</th>
                                        <th>₹<?php echo number_format($order['total_amount'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <?php if ($order['payment_method'] === 'credit'): ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> This order was paid with your credit account. 
                                The amount has been added to your account balance.
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4 text-center">
                            <a href="active_orders.php" class="btn btn-primary">
                                <i class="fas fa-spinner"></i> View Active Orders
                            </a>
                            <a href="menu.php" class="btn btn-success ml-2">
                                <i class="fas fa-utensils"></i> Continue Shopping
                            </a>
                        </div>
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