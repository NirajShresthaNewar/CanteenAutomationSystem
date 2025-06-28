<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a kitchen staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header('Location: ../auth/login.php');
    exit();
}

// Get worker details and verify they are kitchen staff
$stmt = $conn->prepare("
    SELECT w.* 
    FROM workers w 
    WHERE w.user_id = ? AND w.position = 'Kitchen_staff'
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
        AND v.id IN (
            SELECT vendor_id 
            FROM worker_vendor_assignments 
            WHERE worker_id = ?
        )
        ORDER BY o.order_date ASC
    ");
    $stmt->execute([$worker['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching kitchen orders: " . $e->getMessage());
    $_SESSION['error'] = "Error loading orders. Please try refreshing the page.";
    $orders = [];
}

function getStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'accepted':
            return 'info';
        case 'in_progress':
            return 'primary';
        case 'ready':
            return 'success';
        default:
            return 'secondary';
    }
}

$page_title = 'Kitchen Orders';
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

        <?php if (empty($orders)): ?>
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-utensils fa-4x text-muted mb-3"></i>
                    <h5>No Pending Orders</h5>
                    <p>There are no orders waiting to be prepared at the moment.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($orders as $order): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    Order #<?php echo htmlspecialchars($order['receipt_number']); ?>
                                </h3>
                                <div class="card-tools">
                                    <span class="badge bg-<?php echo getStatusClass($order['order_status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                        <p><strong>Order Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p>
                                            <strong>Order Type:</strong>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?>
                                            </span>
                                        </p>
                                        <?php if ($order['order_type'] === 'dine_in'): ?>
                                            <p><strong>Table Number:</strong> <?php echo htmlspecialchars($order['table_number']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php
                                // Get order items
                                $stmt = $conn->prepare("
                                    SELECT oi.*, mi.name, mi.description
                                    FROM order_items oi
                                    JOIN menu_items mi ON oi.menu_item_id = mi.item_id
                                    WHERE oi.order_id = ?
                                ");
                                $stmt->execute([$order['id']]);
                                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Description</th>
                                                <th>Quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                    <td><?php echo $item['quantity']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    <?php if ($order['order_status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-primary" onclick="updateOrderStatus('<?php echo $order['id']; ?>', 'accepted')">
                                            <i class="fas fa-check"></i> Accept Order
                                        </button>
                                    <?php elseif ($order['order_status'] === 'accepted'): ?>
                                        <button type="button" class="btn btn-info" onclick="updateOrderStatus('<?php echo $order['id']; ?>', 'in_progress')">
                                            <i class="fas fa-clock"></i> Start Preparing
                                        </button>
                                    <?php elseif ($order['order_status'] === 'in_progress'): ?>
                                        <button type="button" class="btn btn-success" onclick="updateOrderStatus('<?php echo $order['id']; ?>', 'ready')">
                                            <i class="fas fa-check-circle"></i> Mark as Ready
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateOrderStatus(orderId, status) {
    if (confirm('Are you sure you want to update this order\'s status?')) {
        fetch('update_kitchen_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'order_id=' + orderId + '&status=' + status
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error updating order status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating order status');
        });
    }
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 