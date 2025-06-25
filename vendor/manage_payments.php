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

try {
    // Get all orders with payment information
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.receipt_number,
            o.total_amount,
            o.payment_status,
            o.payment_method,
            o.cash_received,
            o.payment_notes,
            o.payment_received_at,
            o.payment_updated_at,
            CASE 
                WHEN o.cash_received IS NOT NULL AND o.cash_received > 0
                THEN o.cash_received - o.total_amount 
                ELSE NULL 
            END as calculated_change,
            u.username as customer_name,
            GROUP_CONCAT(
                CONCAT(oi.quantity, 'x ', mi.name)
                SEPARATOR ', '
            ) as order_items,
            COALESCE(ot.status, 'pending') as order_status,
            ot.status_changed_at as status_updated_at
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN menu_items mi ON oi.menu_item_id = mi.item_id
        LEFT JOIN (
            SELECT ot1.*
            FROM order_tracking ot1
            INNER JOIN (
                SELECT order_id, MAX(status_changed_at) as max_date
                FROM order_tracking
                GROUP BY order_id
            ) ot2 ON ot1.order_id = ot2.order_id AND ot1.status_changed_at = ot2.max_date
        ) ot ON o.id = ot.order_id
        WHERE o.vendor_id = ?
        GROUP BY 
            o.id, o.receipt_number, o.total_amount, o.payment_status,
            o.payment_method, o.cash_received, o.payment_notes,
            o.payment_received_at, o.payment_updated_at,
            u.username, ot.status, ot.status_changed_at
        ORDER BY 
            CASE o.payment_status
                WHEN 'pending' THEN 1
                WHEN 'paid' THEN 2
                ELSE 3
            END,
            CASE COALESCE(ot.status, 'pending')
                WHEN 'pending' THEN 1
                WHEN 'accepted' THEN 2
                WHEN 'in_progress' THEN 3
                WHEN 'ready' THEN 4
                WHEN 'completed' THEN 5
                WHEN 'cancelled' THEN 6
                ELSE 7
            END,
            o.order_date DESC
    ");
    $stmt->execute([$vendor['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching orders: " . $e->getMessage();
    $orders = [];
}

$page_title = 'Manage Payments';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Manage Payments</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Payments</li>
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Payment Records</h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" id="payment-search" class="form-control float-right" placeholder="Search orders...">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-default">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>Order Info</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Payment Method</th>
                            <th>Amount Received</th>
                            <th>Change</th>
                            <th>Payment Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="10" class="text-center">No orders found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo htmlspecialchars($order['receipt_number']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Status: 
                                            <span class="badge <?php echo getStatusBadgeClass($order['order_status']); ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td>
                                        <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($order['order_items']); ?>">
                                            <?php echo htmlspecialchars($order['order_items']); ?>
                                        </span>
                                    </td>
                                    <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo getPaymentMethodBadgeClass($order['payment_method']); ?>">
                                            <?php echo getPaymentMethodLabel($order['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $order['cash_received'] ? '₹' . number_format($order['cash_received'], 2) : '-'; ?></td>
                                    <td><?php echo $order['calculated_change'] ? '₹' . number_format($order['calculated_change'], 2) : '-'; ?></td>
                                    <td>
                                        <span class="badge <?php echo getPaymentStatusBadgeClass($order['payment_status']); ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($order['payment_updated_at']): ?>
                                            <?php echo date('M d, Y h:i A', strtotime($order['payment_updated_at'])); ?>
                                        <?php else: ?>
                                            Not updated
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['payment_status'] !== 'paid'): ?>
                                            <form action="update_payment.php" method="POST" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="receipt_number" value="<?php echo $order['receipt_number']; ?>">
                                                <input type="hidden" name="total_amount" value="<?php echo $order['total_amount']; ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-money-bill-wave"></i> Update Payment
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="payment_history.php?order_id=<?php echo $order['id']; ?>" 
                                           class="btn btn-info btn-sm">
                                            <i class="fas fa-history"></i> History
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions for badges
function getStatusBadgeClass($status) {
    return match($status) {
        'pending' => 'bg-warning',
        'accepted' => 'bg-info',
        'in_progress' => 'bg-primary',
        'ready' => 'bg-success',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
        default => 'bg-secondary'
    };
}

function getPaymentMethodBadgeClass($method) {
    switch ($method) {
        case 'cash':
            return 'badge-success';
        case 'credit':
            return 'badge-info';
        case 'khalti':
            return 'badge-primary';
        case 'esewa':
            return 'badge-warning';
        default:
            return 'badge-secondary';
    }
}

function getPaymentMethodLabel($method) {
    switch ($method) {
        case 'cash':
            return 'Cash Payment';
        case 'credit':
            return 'Credit Account';
        case 'khalti':
            return 'Khalti Payment';
        case 'esewa':
            return 'eSewa Payment';
        default:
            return 'Unknown';
    }
}

function getPaymentStatusBadgeClass($status) {
    return match($status) {
        'paid' => 'bg-success',
        'failed' => 'bg-danger',
        'pending' => 'bg-warning',
        default => 'bg-secondary'
    };
}
?>

<script>
document.getElementById('payment-search').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('tbody tr');
    
    tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchText) ? '' : 'none';
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 