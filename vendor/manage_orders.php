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

// Function to get count of orders by status
function getOrderCountByStatus($conn, $vendor_id, $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE vendor_id = ? AND status = ?");
    $stmt->execute([$vendor_id, $status]);
    return $stmt->fetchColumn();
}

// Get order counts for each status
$pending_count = getOrderCountByStatus($conn, $vendor_id, 'pending');
$accepted_count = getOrderCountByStatus($conn, $vendor_id, 'accepted');
$in_progress_count = getOrderCountByStatus($conn, $vendor_id, 'in_progress');
$ready_count = getOrderCountByStatus($conn, $vendor_id, 'ready');
$completed_count = getOrderCountByStatus($conn, $vendor_id, 'completed');
$cancelled_count = getOrderCountByStatus($conn, $vendor_id, 'cancelled');

// Get current active tab from URL or default to 'pending'
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// Get orders based on active tab
$stmt = $conn->prepare("
    SELECT o.*, u.username, u.contact_number 
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.vendor_id = ? AND o.status = ?
    ORDER BY o.order_date DESC
");
$stmt->execute([$vendor_id, $active_tab]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Manage Orders';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Manage Orders</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Orders</li>
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

        <!-- Order Statistics -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-2">
                <div class="info-box bg-danger">
                    <span class="info-box-icon"><i class="fas fa-bell"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pending</span>
                        <span class="info-box-number"><?php echo $pending_count; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <div class="info-box bg-info">
                    <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Accepted</span>
                        <span class="info-box-number"><?php echo $accepted_count; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <div class="info-box bg-primary">
                    <span class="info-box-icon"><i class="fas fa-spinner"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">In Progress</span>
                        <span class="info-box-number"><?php echo $in_progress_count; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <div class="info-box bg-warning">
                    <span class="info-box-icon"><i class="fas fa-utensils"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Ready</span>
                        <span class="info-box-number"><?php echo $ready_count; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <div class="info-box bg-success">
                    <span class="info-box-icon"><i class="fas fa-check-double"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Completed</span>
                        <span class="info-box-number"><?php echo $completed_count; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <div class="info-box bg-secondary">
                    <span class="info-box-icon"><i class="fas fa-times-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Cancelled</span>
                        <span class="info-box-number"><?php echo $cancelled_count; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Tabs -->
        <div class="card card-primary card-outline card-tabs">
            <div class="card-header p-0 pt-1 border-bottom-0">
                <ul class="nav nav-tabs" id="order-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'pending' ? 'active' : ''; ?>" href="?tab=pending">
                            <i class="fas fa-bell mr-1"></i> Pending
                            <?php if ($pending_count > 0): ?>
                                <span class="badge badge-danger"><?php echo $pending_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'accepted' ? 'active' : ''; ?>" href="?tab=accepted">
                            <i class="fas fa-check-circle mr-1"></i> Accepted
                            <?php if ($accepted_count > 0): ?>
                                <span class="badge badge-info"><?php echo $accepted_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'in_progress' ? 'active' : ''; ?>" href="?tab=in_progress">
                            <i class="fas fa-spinner mr-1"></i> In Progress
                            <?php if ($in_progress_count > 0): ?>
                                <span class="badge badge-primary"><?php echo $in_progress_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'ready' ? 'active' : ''; ?>" href="?tab=ready">
                            <i class="fas fa-utensils mr-1"></i> Ready
                            <?php if ($ready_count > 0): ?>
                                <span class="badge badge-warning"><?php echo $ready_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'completed' ? 'active' : ''; ?>" href="?tab=completed">
                            <i class="fas fa-check-double mr-1"></i> Completed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>" href="?tab=cancelled">
                            <i class="fas fa-times-circle mr-1"></i> Cancelled
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <img src="../assets/images/no-orders.svg" alt="No Orders" class="img-fluid mb-3" style="max-width: 200px;">
                        <h4 class="text-muted">No <?php echo ucfirst($active_tab); ?> Orders</h4>
                        <p class="text-muted">There are currently no orders with <?php echo $active_tab; ?> status.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Payment Method</th>
                                    <th>Order Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['receipt_number']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($order['username']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['contact_number']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $stmt = $conn->prepare("
                                                SELECT dish_name, quantity 
                                                FROM order_items 
                                                WHERE order_id = ?
                                            ");
                                            $stmt->execute([$order['id']]);
                                            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($items as $item) {
                                                echo htmlspecialchars($item['quantity'] . 'x ' . $item['dish_name']) . '<br>';
                                            }
                                            ?>
                                        </td>
                                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <?php if ($order['payment_method'] == 'cash'): ?>
                                                <span class="badge badge-success">Cash</span>
                                            <?php elseif ($order['payment_method'] == 'esewa'): ?>
                                                <span class="badge badge-info">eSewa</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-info view-order" data-toggle="modal" data-target="#viewOrderModal" data-id="<?php echo $order['id']; ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($active_tab == 'pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-success update-status" data-id="<?php echo $order['id']; ?>" data-status="accepted">
                                                        <i class="fas fa-check"></i> Accept
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger update-status" data-id="<?php echo $order['id']; ?>" data-status="cancelled">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                <?php elseif ($active_tab == 'accepted'): ?>
                                                    <button type="button" class="btn btn-sm btn-primary update-status" data-id="<?php echo $order['id']; ?>" data-status="in_progress">
                                                        <i class="fas fa-spinner"></i> Start Preparing
                                                    </button>
                                                <?php elseif ($active_tab == 'in_progress'): ?>
                                                    <button type="button" class="btn btn-sm btn-warning update-status" data-id="<?php echo $order['id']; ?>" data-status="ready">
                                                        <i class="fas fa-utensils"></i> Mark as Ready
                                                    </button>
                                                <?php elseif ($active_tab == 'ready'): ?>
                                                    <button type="button" class="btn btn-sm btn-success update-status" data-id="<?php echo $order['id']; ?>" data-status="completed">
                                                        <i class="fas fa-check-double"></i> Complete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
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

    // Update order status
    $(".update-status").click(function() {
        if (confirm("Are you sure you want to update this order status?")) {
            var orderId = $(this).data("id");
            var status = $(this).data("status");
            $.ajax({
                url: "update_order_status.php",
                method: "POST",
                data: { order_id: orderId, status: status },
                success: function(response) {
                    var data = JSON.parse(response);
                    if (data.success) {
                        location.reload();
                    } else {
                        alert("Error: " + data.message);
                    }
                }
            });
        }
    });
});
</script>
';
require_once '../includes/layout.php';
?> 