<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../connection/db_connection.php';

$page_title = 'Transaction Management';
ob_start();

// Get all vendors for filter
$vendors_query = $conn->query("SELECT v.id, u.username as vendor_name FROM vendors v JOIN users u ON v.user_id = u.id ORDER BY u.username");
$vendors = $vendors_query->fetchAll(PDO::FETCH_ASSOC);

// Initialize filters
$vendor_id = isset($_GET['vendor_id']) ? $_GET['vendor_id'] : '';
$payment_type = isset($_GET['payment_type']) ? $_GET['payment_type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Build query conditions
$conditions = ["DATE(o.order_date) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($vendor_id) {
    $conditions[] = "o.vendor_id = ?";
    $params[] = $vendor_id;
}

if ($payment_type) {
    $conditions[] = "o.payment_method = ?";
    $params[] = $payment_type;
}

$where_clause = implode(" AND ", $conditions);

// Get transactions
$transactions_query = "
    SELECT 
        o.id,
        o.receipt_number,
        o.order_date,
        o.total_amount,
        o.payment_method,
        o.payment_status,
        u.username as vendor_name,
        cu.username as customer_name
    FROM orders o
    JOIN vendors v ON o.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    JOIN users cu ON o.user_id = cu.id
    WHERE $where_clause
    ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($transactions_query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Transaction Management</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <!-- Filters Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Filters</h3>
            </div>
            <div class="card-body">
                <form method="GET" id="filterForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Vendor</label>
                                <select class="form-control" name="vendor_id">
                                    <option value="">All Vendors</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?php echo $vendor['id']; ?>" <?php echo $vendor_id == $vendor['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select class="form-control" name="payment_type">
                                    <option value="">All Methods</option>
                                    <option value="cash" <?php echo $payment_type === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="khalti" <?php echo $payment_type === 'khalti' ? 'selected' : ''; ?>>Khalti</option>
                                    <option value="credit" <?php echo $payment_type === 'credit' ? 'selected' : ''; ?>>Credit</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>From Date</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>To Date</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">Apply Filter</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transactions List</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Date</th>
                                <th>Vendor</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No transactions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transaction['receipt_number']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($transaction['order_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['vendor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['customer_name']); ?></td>
                                        <td>₹<?php echo number_format($transaction['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo match($transaction['payment_method']) {
                                                    'cash' => 'success',
                                                    'khalti' => 'info',
                                                    'credit' => 'warning',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($transaction['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo match($transaction['payment_status']) {
                                                    'paid' => 'success',
                                                    'pending' => 'warning',
                                                    'failed' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($transaction['payment_status']); ?>
                                            </span>
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
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 