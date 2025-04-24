<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/order_functions.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    header('Location: active_orders.php');
    exit();
}

$order_id = $_GET['order_id'];

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, v.id as vendor_id, u.username as vendor_name
    FROM orders o
    JOIN vendors v ON o.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE o.id = ? AND o.user_id = ? AND o.payment_method = 'cash'
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "Order not found or unauthorized access.";
    header('Location: active_orders.php');
    exit();
}

$pageTitle = 'Cash Payment';
ob_start();
?>

<div class="content">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-money-bill-wave"></i> Cash Payment Details
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h4>Order #<?php echo htmlspecialchars($order['receipt_number']); ?></h4>
                            <p class="text-muted">Please show this to <?php echo htmlspecialchars($order['vendor_name']); ?></p>
                        </div>

                        <div class="info-box mb-3">
                            <span class="info-box-icon bg-success"><i class="fas fa-rupee-sign"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Amount to Pay</span>
                                <span class="info-box-number">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Please pay the exact amount to the vendor. 
                            They will mark your payment as completed once received.
                        </div>

                        <div id="paymentStatus">
                            <?php if ($order['payment_status'] === 'pending'): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-clock"></i> Payment Status: Pending
                                </div>
                            <?php elseif ($order['payment_status'] === 'paid'): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> Payment Status: Paid
                                    <?php if ($order['cash_received']): ?>
                                        <br>
                                        <small>
                                            Cash Received: ₹<?php echo number_format($order['cash_received'], 2); ?><br>
                                            Change: ₹<?php echo number_format($order['cash_received'] - $order['total_amount'], 2); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="text-center mt-4">
                            <a href="active_orders.php" class="btn btn-primary">
                                <i class="fas fa-shopping-bag"></i> View Active Orders
                            </a>
                            <a href="order_confirmation.php?receipt=<?php echo urlencode($order['receipt_number']); ?>" class="btn btn-success">
                                <i class="fas fa-receipt"></i> View Order Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Function to check payment status
function checkPaymentStatus() {
    fetch('../api/check_payment_status.php?order_id=<?php echo $order_id; ?>')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'paid') {
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
}

// Check payment status every 10 seconds if payment is pending
<?php if ($order['payment_status'] === 'pending'): ?>
    setInterval(checkPaymentStatus, 10000);
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 