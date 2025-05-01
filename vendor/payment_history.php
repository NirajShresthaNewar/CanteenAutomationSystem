<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../auth/login.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    $_SESSION['error'] = "Vendor not found";
    header('Location: ../auth/logout.php');
    exit();
}

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    $_SESSION['error'] = "Order ID is required";
    header('Location: manage_payments.php');
    exit();
}

$order_id = $_GET['order_id'];

try {
    // Get order details
    $stmt = $conn->prepare("
        SELECT o.*, u.username as customer_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.vendor_id = ?
    ");
    $stmt->execute([$order_id, $vendor['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found or access denied");
    }

    // Get payment history
    $stmt = $conn->prepare("
        SELECT 
            ph.*,
            u.username as updated_by_name
        FROM payment_history ph
        LEFT JOIN users u ON ph.created_by = u.id
        WHERE ph.order_id = ?
        ORDER BY ph.created_at DESC
    ");
    $stmt->execute([$order_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching payment history: " . $e->getMessage();
    header('Location: manage_payments.php');
    exit();
}

$page_title = 'Payment History';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Payment History</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="manage_payments.php">Manage Payments</a></li>
                    <li class="breadcrumb-item active">Payment History</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
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
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Order #<?php echo htmlspecialchars($order['receipt_number']); ?>
                        </h3>
                        <div class="card-tools">
                            <a href="manage_payments.php" class="btn btn-default btn-sm">
                                <i class="fas fa-arrow-left"></i> Back to Payments
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-sm-6">
                                <h6 class="mb-3">Order Information:</h6>
                                <div><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></div>
                                <div><strong>Order Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></div>
                                <div><strong>Total Amount:</strong> ₹<?php echo number_format($order['total_amount'], 2); ?></div>
                                <div>
                                    <strong>Current Status:</strong>
                                    <span class="badge <?php echo $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <h6 class="mb-3">Payment Information:</h6>
                                <?php if ($order['payment_status'] === 'paid'): ?>
                                    <div><strong>Cash Received:</strong> ₹<?php echo number_format($order['cash_received'], 2); ?></div>
                                    <div><strong>Change:</strong> ₹<?php echo number_format($order['cash_received'] - $order['total_amount'], 2); ?></div>
                                    <div><strong>Payment Time:</strong> <?php echo date('M d, Y h:i A', strtotime($order['payment_received_at'])); ?></div>
                                    <?php if ($order['payment_notes']): ?>
                                        <div><strong>Notes:</strong> <?php echo htmlspecialchars($order['payment_notes']); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-muted">Payment pending</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h5 class="mb-3">Payment History</h5>
                        <?php if (empty($history)): ?>
                            <p class="text-muted">No payment history available</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Status Change</th>
                                            <th>Amount Received</th>
                                            <th>Change</th>
                                            <th>Notes</th>
                                            <th>Updated By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history as $record): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y h:i A', strtotime($record['created_at'])); ?></td>
                                                <td>
                                                    <?php echo ucfirst($record['previous_status']); ?> →
                                                    <?php echo ucfirst($record['new_status']); ?>
                                                </td>
                                                <td>₹<?php echo number_format($record['amount_received'], 2); ?></td>
                                                <td>₹<?php echo number_format($record['amount_received'] - $order['total_amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($record['notes'] ?: '-'); ?></td>
                                                <td><?php echo htmlspecialchars($record['updated_by_name']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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