<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a kitchen staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header('Location: ../auth/login.php');
    exit();
}

// Get worker details and verify they are kitchen staff
$stmt = $conn->prepare("
    SELECT w.* 
    FROM workers w 
    WHERE w.user_id = ? AND LOWER(w.position) = 'kitchen_staff'
");
$stmt->execute([$_SESSION['user_id']]);
$worker = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$worker) {
    $_SESSION['error'] = "Access denied. Only kitchen staff can view this page.";
    header('Location: dashboard.php');
    exit();
}

try {
    // Get pending and in-progress orders
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            cu.username as customer_name,
            v.id as vendor_id,
            vu.username as vendor_name,
            odd.order_type,
            odd.table_number,
            odd.delivery_location,
            COALESCE(latest_tracking.status, 'pending') as order_status,
            latest_tracking.status_changed_at
        FROM orders o
        LEFT JOIN (
            SELECT ot1.*
            FROM order_tracking ot1
            INNER JOIN (
                SELECT order_id, MAX(status_changed_at) as max_date
                FROM order_tracking
                GROUP BY order_id
            ) ot2 ON ot1.order_id = ot2.order_id AND ot1.status_changed_at = ot2.max_date
        ) latest_tracking ON o.id = latest_tracking.order_id
        JOIN users cu ON o.user_id = cu.id
        JOIN vendors v ON o.vendor_id = v.id
        JOIN users vu ON v.user_id = vu.id
        LEFT JOIN order_delivery_details odd ON o.id = odd.order_id
        WHERE COALESCE(latest_tracking.status, 'pending') IN ('pending', 'accepted', 'in_progress')
        AND o.vendor_id = (
            SELECT vendor_id 
            FROM workers 
            WHERE id = ?
        )
        ORDER BY 
            FIELD(COALESCE(latest_tracking.status, 'pending'), 'pending', 'accepted', 'in_progress'),
            o.order_date ASC
    ");
    $stmt->execute([$worker['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching kitchen orders: " . $e->getMessage());
    $_SESSION['error'] = "Error loading orders. Please try refreshing the page.";
    $orders = [];
}

// Start output buffering
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Kitchen Orders</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Kitchen Orders</li>
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
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Pending & Active Orders</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-utensils fa-4x text-muted mb-3"></i>
                                <h5>No Pending Orders</h5>
                                <p>There are no orders waiting to be prepared at the moment.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Type</th>
                                            <th>Items</th>
                                            <th>Status</th>
                                            <th>Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($order['receipt_number']); ?></td>
                                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?>
                                                    </span>
                                                    <?php if ($order['order_type'] === 'dine_in'): ?>
                                                        <br>
                                                        <small>Table: <?php echo htmlspecialchars($order['table_number']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $stmt = $conn->prepare("
                                                        SELECT oi.*, mi.name
                                                        FROM order_items oi
                                                        JOIN menu_items mi ON oi.menu_item_id = mi.item_id
                                                        WHERE oi.order_id = ?
                                                    ");
                                                    $stmt->execute([$order['id']]);
                                                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    foreach ($items as $item) {
                                                        echo htmlspecialchars($item['quantity'] . 'x ' . $item['name']) . '<br>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo getStatusBadgeClass($order['order_status']); ?>">
                                                        <?php echo ucfirst($order['order_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $orderTime = new DateTime($order['order_date']);
                                                    $now = new DateTime();
                                                    $diff = $now->diff($orderTime);
                                                    if ($diff->h > 0) {
                                                        echo $diff->h . 'h ' . $diff->i . 'm ago';
                                                    } else {
                                                        echo $diff->i . ' minutes ago';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($order['order_status'] === 'pending'): ?>
                                                        <button class="btn btn-success btn-sm update-status" 
                                                                data-order-id="<?php echo $order['id']; ?>" 
                                                                data-status="accepted">
                                                            <i class="fas fa-check"></i> Accept
                                                        </button>
                                                    <?php elseif ($order['order_status'] === 'accepted'): ?>
                                                        <button class="btn btn-primary btn-sm update-status" 
                                                                data-order-id="<?php echo $order['id']; ?>" 
                                                                data-status="in_progress">
                                                            <i class="fas fa-utensils"></i> Start Preparing
                                                        </button>
                                                    <?php elseif ($order['order_status'] === 'in_progress'): ?>
                                                        <button class="btn btn-info btn-sm update-status" 
                                                                data-order-id="<?php echo $order['id']; ?>" 
                                                                data-status="ready">
                                                            <i class="fas fa-bell"></i> Mark Ready
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-secondary btn-sm view-details" 
                                                            data-order-id="<?php echo $order['id']; ?>">
                                                        <i class="fas fa-eye"></i> Details
                                                    </button>
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
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle status update buttons
    $('.update-status').click(function() {
        const orderId = $(this).data('order-id');
        const newStatus = $(this).data('status');
        const button = $(this);

        // Disable the button and show loading state
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: 'update_kitchen_order.php',
            method: 'POST',
            data: {
                order_id: orderId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // Reload the page to show updated status
                        location.reload();
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                    // Re-enable the button
                    button.prop('disabled', false).html(button.data('original-text'));
                }
            },
            error: function() {
                // Show error message
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to update order status. Please try again.'
                });
                // Re-enable the button
                button.prop('disabled', false).html(button.data('original-text'));
            }
        });
    });

    // Store original button text
    $('.update-status').each(function() {
        $(this).data('original-text', $(this).html());
    });

    // Handle view details buttons
    $('.view-details').click(function() {
        const orderId = $(this).data('order-id');
        $('#orderDetailsModal').modal('show');
        // Load order details via AJAX
        $.get('get_order_details.php', { id: orderId }, function(data) {
            $('#orderDetailsModal .modal-body').html(data);
        });
    });
});
</script>

<?php
// Get the buffered content
$content = ob_get_clean();

// Set the page title
$pageTitle = "Kitchen Orders";

// Include the layout template
require_once '../includes/layout.php';
?> 