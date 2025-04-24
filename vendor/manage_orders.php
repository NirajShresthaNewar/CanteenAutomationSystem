<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$title = 'Manage Orders';
$pageTitle = 'Manage Orders';

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
                                    <th>Time Info</th>
                                    <th>Status</th>
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
                                                SELECT mi.name as dish_name, oi.quantity, oi.special_instructions 
                                                FROM order_items oi
                                                JOIN menu_items mi ON oi.menu_item_id = mi.item_id
                                                WHERE oi.order_id = ?
                                            ");
                                            $stmt->execute([$order['id']]);
                                            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($items as $item) {
                                                echo htmlspecialchars($item['quantity'] . 'x ' . $item['dish_name']);
                                                if (!empty($item['special_instructions'])) {
                                                    echo ' <small class="text-muted">(' . htmlspecialchars($item['special_instructions']) . ')</small>';
                                                }
                                                echo '<br>';
                                            }
                                            ?>
                                        </td>
                                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $badge_class = match($order['payment_method']) {
                                                'cash' => 'success',
                                                'esewa' => 'info',
                                                'credit' => 'warning',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge badge-<?php echo $badge_class; ?>">
                                                <?php echo ucfirst($order['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                Ordered: <?php echo date('M d, H:i', strtotime($order['order_date'])); ?><br>
                                                <?php if ($order['preparation_time']): ?>
                                                    Prep Time: <?php echo $order['preparation_time']; ?> mins<br>
                                                <?php endif; ?>
                                                <?php if ($order['pickup_time']): ?>
                                                    Pickup: <?php echo date('H:i', strtotime($order['pickup_time'])); ?><br>
                                                <?php endif; ?>
                                                <?php if ($order['completed_at']): ?>
                                                    Completed: <?php echo date('H:i', strtotime($order['completed_at'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_badge = match($order['status']) {
                                                'pending' => '<span class="badge badge-danger">Pending</span>',
                                                'accepted' => '<span class="badge badge-info">Accepted</span>',
                                                'in_progress' => '<span class="badge badge-primary">In Progress</span>',
                                                'ready' => '<span class="badge badge-warning">Ready</span>',
                                                'completed' => '<span class="badge badge-success">Completed</span>',
                                                'cancelled' => '<span class="badge badge-secondary">Cancelled</span>',
                                                default => '<span class="badge badge-secondary">Unknown</span>'
                                            };
                                            echo $status_badge;
                                            
                                            // Show cancelled reason if exists
                                            if ($order['status'] === 'cancelled' && !empty($order['cancelled_reason'])) {
                                                echo '<br><small class="text-danger">' . htmlspecialchars($order['cancelled_reason']) . '</small>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-info view-order" 
                                                        data-order-id="<?php echo $order['id']; ?>"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($order['status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-success accept-order" 
                                                            data-order-id="<?php echo $order['id']; ?>"
                                                            title="Accept Order">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger cancel-order" 
                                                            data-order-id="<?php echo $order['id']; ?>"
                                                            title="Cancel Order">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php elseif ($order['status'] === 'accepted'): ?>
                                                    <button type="button" class="btn btn-sm btn-primary start-preparation" 
                                                            data-order-id="<?php echo $order['id']; ?>"
                                                            title="Start Preparation">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                <?php elseif ($order['status'] === 'in_progress'): ?>
                                                    <button type="button" class="btn btn-sm btn-warning mark-ready" 
                                                            data-order-id="<?php echo $order['id']; ?>"
                                                            title="Mark as Ready">
                                                        <i class="fas fa-utensils"></i>
                                                    </button>
                                                <?php elseif ($order['status'] === 'ready'): ?>
                                                    <button type="button" class="btn btn-sm btn-success complete-order" 
                                                            data-order-id="<?php echo $order['id']; ?>"
                                                            title="Complete Order">
                                                        <i class="fas fa-check-double"></i>
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
    // Initialize DataTable
    $("#ordersTable").DataTable({
        "order": [[0, "desc"]],
        "pageLength": 25,
        "responsive": true
    });

    // View order details
    $(".view-order").click(function() {
        var orderId = $(this).data("order-id");
        $.ajax({
            url: "get_order_details.php",
            method: "POST",
            data: { order_id: orderId },
            success: function(data) {
                $("#orderDetails").html(data);
                $("#viewOrderModal").modal("show");
            }
        });
    });

    // Accept order with preparation time
    $(".accept-order").click(function() {
        var orderId = $(this).data("order-id");
        var modalHtml = \'<div class="form-group">\' +
            \'<label for="preparation-time">Preparation Time (minutes)</label>\' +
            \'<input type="number" id="preparation-time" class="form-control" min="1" max="180" value="30">\' +
            \'</div>\' +
            \'<div class="form-group">\' +
            \'<label for="pickup-time">Pickup Time</label>\' +
            \'<input type="time" id="pickup-time" class="form-control">\' +
            \'</div>\';

        Swal.fire({
            title: "Accept Order",
            html: modalHtml,
            showCancelButton: true,
            confirmButtonText: "Accept Order",
            cancelButtonText: "Cancel",
            preConfirm: function() {
                var prepTime = document.getElementById("preparation-time").value;
                var pickupTime = document.getElementById("pickup-time").value;
                if (!prepTime || prepTime < 1) {
                    Swal.showValidationMessage("Please enter a valid preparation time");
                    return false;
                }
                return { prepTime: prepTime, pickupTime: pickupTime };
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: "update_order_status.php",
                    method: "POST",
                    data: {
                        order_id: orderId,
                        status: "accepted",
                        preparation_time: result.value.prepTime,
                        pickup_time: result.value.pickupTime
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        if (data.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Order Accepted",
                                text: "The order has been accepted successfully.",
                                timer: 2000
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: data.message
                            });
                        }
                    }
                });
            }
        });
    });

    // Cancel order with reason
    $(".cancel-order").click(function() {
        var orderId = $(this).data("order-id");
        var modalHtml = \'<div class="form-group">\' +
            \'<label for="cancel-reason">Cancellation Reason</label>\' +
            \'<textarea id="cancel-reason" class="form-control" rows="3"></textarea>\' +
            \'</div>\';

        Swal.fire({
            title: "Cancel Order",
            html: modalHtml,
            showCancelButton: true,
            confirmButtonText: "Cancel Order",
            cancelButtonText: "Go Back",
            confirmButtonColor: "#dc3545",
            preConfirm: function() {
                var reason = document.getElementById("cancel-reason").value;
                if (!reason.trim()) {
                    Swal.showValidationMessage("Please provide a reason for cancellation");
                    return false;
                }
                return { reason: reason };
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: "update_order_status.php",
                    method: "POST",
                    data: {
                        order_id: orderId,
                        status: "cancelled",
                        cancelled_reason: result.value.reason
                    },
                    success: function(response) {
                        var data = JSON.parse(response);
                        if (data.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Order Cancelled",
                                text: "The order has been cancelled.",
                                timer: 2000
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: data.message
                            });
                        }
                    }
                });
            }
        });
    });

    // Other status updates
    $(".start-preparation, .mark-ready, .complete-order").click(function() {
        var orderId = $(this).data("order-id");
        var status = $(this).hasClass("start-preparation") ? "in_progress" :
                    $(this).hasClass("mark-ready") ? "ready" : "completed";
        var actionTitle = $(this).hasClass("start-preparation") ? "Start Preparation" :
                       $(this).hasClass("mark-ready") ? "Mark as Ready" : "Complete Order";
        
        Swal.fire({
            title: actionTitle,
            text: "Are you sure you want to " + actionTitle.toLowerCase() + "?",
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Yes",
            cancelButtonText: "No"
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: "update_order_status.php",
                    method: "POST",
                    data: { order_id: orderId, status: status },
                    success: function(response) {
                        var data = JSON.parse(response);
                        if (data.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Status Updated",
                                text: "The order status has been updated.",
                                timer: 2000
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: data.message
                            });
                        }
                    }
                });
            }
        });
    });
});
</script>
';
require_once "../includes/layout.php";
?> 