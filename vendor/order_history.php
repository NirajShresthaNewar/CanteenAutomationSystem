<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../index.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';

// Build query conditions
$sql = "SELECT o.id, o.receipt_number, o.total_amount, o.payment_method, o.order_date,
       u.username as customer_name, u.email as customer_email,
       COALESCE(ot.status, 'pending') as current_status,
       o.payment_status, o.cash_received, o.amount_tendered, o.change_amount
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
LEFT JOIN (
    SELECT order_id, status
    FROM order_tracking
    WHERE (order_id, status_changed_at) IN (
        SELECT order_id, MAX(status_changed_at)
        FROM order_tracking
        GROUP BY order_id
    )
) ot ON o.id = ot.order_id
WHERE o.vendor_id = :vendor_id";

$params = [':vendor_id' => $vendor_id];

if (!empty($start_date)) {
    $sql .= " AND DATE(o.order_date) >= :start_date";
    $params[':start_date'] = $start_date;
}

if (!empty($end_date)) {
    $sql .= " AND DATE(o.order_date) <= :end_date";
    $params[':end_date'] = $end_date;
}

if (!empty($status)) {
    $sql .= " AND COALESCE(ot.status, 'pending') = :status";
    $params[':status'] = $status;
}

if (!empty($payment_method)) {
    $sql .= " AND o.payment_method = :payment_method";
    $params[':payment_method'] = $payment_method;
}

$sql .= " ORDER BY o.order_date DESC";

// Get total count of orders
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$total_orders = $stmt->rowCount();
$total_pages = ceil($total_orders / $limit);

// Get orders for current page
$sql .= " LIMIT :offset, :limit";
$stmt = $conn->prepare($sql);

// Bind all parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total revenue
$stmt = $conn->prepare("
    SELECT SUM(o.total_amount) 
    FROM orders o
    LEFT JOIN (
        SELECT order_id, status
        FROM order_tracking
        WHERE (order_id, status_changed_at) IN (
            SELECT order_id, MAX(status_changed_at)
            FROM order_tracking
            GROUP BY order_id
        )
    ) ot ON o.id = ot.order_id
    WHERE o.vendor_id = :vendor_id 
    AND COALESCE(ot.status, 'pending') = 'completed'
    AND o.payment_status = 'paid'
");
$stmt->bindValue(':vendor_id', $vendor_id);
$stmt->execute();
$total_revenue = $stmt->fetchColumn();

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
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Order Analytics</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon"><i class="fas fa-money-bill"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Revenue</span>
                                        <span class="info-box-number">₹<?php echo number_format($total_revenue, 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-shopping-cart"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Orders</span>
                                        <span class="info-box-number"><?php echo $total_orders; ?></span>
                                    </div>
                                </div>
                            </div>
                            <!-- Additional analytics can be added here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Filter Orders</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Start Date</label>
                                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>End Date</label>
                                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select class="form-control" name="status">
                                            <option value="">All Statuses</option>
                                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="accepted" <?php echo $status == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                            <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="ready" <?php echo $status == 'ready' ? 'selected' : ''; ?>>Ready</option>
                                            <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Payment Method</label>
                                        <select class="form-control" name="payment_method">
                                            <option value="">All Methods</option>
                                            <option value="cash" <?php echo $payment_method == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                            <option value="esewa" <?php echo $payment_method == 'esewa' ? 'selected' : ''; ?>>eSewa</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 text-right">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="order_history.php" class="btn btn-default">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Order History</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <img src="../assets/images/no-orders.svg" alt="No Orders" class="img-fluid mb-3" style="max-width: 200px;">
                                <h4 class="text-muted">No Orders Found</h4>
                                <p class="text-muted">There are no orders matching your filter criteria.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Total</th>
                                            <th>Payment Method</th>
                                            <th>Status</th>
                                            <th>Order Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['receipt_number']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                                </td>
                                                <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <?php if ($order['payment_method'] == 'cash'): ?>
                                                        <span class="badge badge-success">Cash</span>
                                                        <?php if ($order['payment_status'] == 'paid'): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                Tendered: ₹<?php echo number_format($order['amount_tendered'], 2); ?><br>
                                                                Change: ₹<?php echo number_format($order['change_amount'], 2); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php elseif ($order['payment_method'] == 'esewa'): ?>
                                                        <span class="badge badge-info">eSewa</span>
                                                    <?php elseif ($order['payment_method'] == 'credit'): ?>
                                                        <span class="badge badge-warning">Credit</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status = $order['current_status'] ?? 'pending';
                                                    switch ($status) {
                                                        case 'pending':
                                                            echo '<span class="badge badge-danger">Pending</span>';
                                                            break;
                                                        case 'accepted':
                                                            echo '<span class="badge badge-info">Accepted</span>';
                                                            break;
                                                        case 'in_progress':
                                                            echo '<span class="badge badge-primary">In Progress</span>';
                                                            break;
                                                        case 'ready':
                                                            echo '<span class="badge badge-warning">Ready</span>';
                                                            break;
                                                        case 'completed':
                                                            echo '<span class="badge badge-success">Completed</span>';
                                                            break;
                                                        case 'cancelled':
                                                            echo '<span class="badge badge-secondary">Cancelled</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info view-order" data-toggle="modal" data-target="#viewOrderModal" data-id="<?php echo $order['id']; ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="d-flex justify-content-center mt-4">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&status=<?php echo $status; ?>&payment_method=<?php echo $payment_method; ?>">Previous</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&status=<?php echo $status; ?>&payment_method=<?php echo $payment_method; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&status=<?php echo $status; ?>&payment_method=<?php echo $payment_method; ?>">Next</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Order Modal -->
<div class="modal fade" id="viewOrderModal" tabindex="-1" role="dialog" aria-labelledby="viewOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewOrderModalLabel">Order Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="orderDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$additionalScripts = '
<script>
$(document).ready(function() {
    // View order details
    $(".view-order").click(function() {
        var orderId = $(this).data("id");
        $.ajax({
            url: "get_order_details.php",
            method: "POST",
            data: { order_id: orderId },
            success: function(data) {
                $("#orderDetails").html(data);
            }
        });
    });
});
</script>
';
require_once '../includes/layout.php';
?> 