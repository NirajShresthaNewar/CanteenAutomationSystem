<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a worker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header('Location: ../auth/login.php');
    exit();
}

// Get worker ID
$stmt = $conn->prepare("SELECT id FROM workers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$worker = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$worker) {
    $_SESSION['error'] = "Worker not found";
    header('Location: ../auth/logout.php');
    exit();
}

try {
    // Get assigned orders
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            cu.username as customer_name,
            v.id as vendor_id,
            vu.username as vendor_name,
            latest_assignment.status as assignment_status,
            latest_assignment.assigned_at,
            latest_assignment.picked_up_at,
            odd.order_type,
            odd.table_number,
            odd.delivery_location,
            odd.building_name,
            odd.floor_number,
            odd.room_number,
            odd.delivery_instructions,
            odd.contact_number,
            COALESCE(latest_tracking.status, 'pending') as order_status
        FROM orders o
        JOIN (
            SELECT oa1.*
            FROM order_assignments oa1
            INNER JOIN (
                SELECT order_id, MAX(assigned_at) as latest_assigned
                FROM order_assignments
                WHERE worker_id = ?
                GROUP BY order_id
            ) oa2 ON oa1.order_id = oa2.order_id AND oa1.assigned_at = oa2.latest_assigned
            WHERE oa1.worker_id = ?
        ) latest_assignment ON o.id = latest_assignment.order_id
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
        WHERE latest_assignment.status != 'delivered'
        AND COALESCE(latest_tracking.status, 'pending') NOT IN ('completed', 'cancelled')
        ORDER BY latest_assignment.assigned_at DESC, latest_assignment.id DESC
    ");
    $stmt->execute([$worker['id'], $worker['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching orders: " . $e->getMessage();
    $orders = [];
}

$page_title = 'Assigned Orders';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Assigned Orders</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Assigned Orders</li>
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
                    <i class="fas fa-tasks fa-4x text-muted mb-3"></i>
                    <h5>No Assigned Orders</h5>
                    <p>You don't have any active assignments at the moment.</p>
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
                                    <span class="badge bg-<?php echo getStatusClass($order['assignment_status']); ?> ml-2">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['assignment_status'])); ?>
                                    </span>
                                </h3>
                                <div class="card-tools">
                                    <span class="badge bg-<?php echo getStatusClass($order['assignment_status']); ?>">
                                        <?php echo ucfirst($order['assignment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p><strong>Vendor:</strong> <?php echo htmlspecialchars($order['vendor_name']); ?></p>
                                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                        <p><strong>Assigned:</strong> <?php echo date('M d, Y h:i A', strtotime($order['assigned_at'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p>
                                            <strong>Order Type:</strong>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?>
                                            </span>
                                        </p>
                                        <?php if ($order['order_type'] === 'delivery'): ?>
                                            <p><strong>Location:</strong> <?php echo htmlspecialchars($order['delivery_location']); ?></p>
                                            <?php if ($order['building_name']): ?>
                                                <p><strong>Building:</strong> <?php echo htmlspecialchars($order['building_name']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($order['floor_number']): ?>
                                                <p><strong>Floor:</strong> <?php echo htmlspecialchars($order['floor_number']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($order['room_number']): ?>
                                                <p><strong>Room:</strong> <?php echo htmlspecialchars($order['room_number']); ?></p>
                                            <?php endif; ?>
                                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($order['contact_number']); ?></p>
                                            <?php if ($order['delivery_instructions']): ?>
                                                <p><strong>Instructions:</strong> <?php echo htmlspecialchars($order['delivery_instructions']); ?></p>
                                            <?php endif; ?>
                                        <?php elseif ($order['order_type'] === 'dine_in'): ?>
                                            <p><strong>Table Number:</strong> <?php echo htmlspecialchars($order['table_number']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php
                                // Get order items
                                $stmt = $conn->prepare("
                                    SELECT oi.*, mi.name
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
                                                <th>Quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                    <td><?php echo $item['quantity']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    <?php if ($order['assignment_status'] === 'assigned' && $order['order_status'] === 'ready'): ?>
                                        <form action="update_order_status.php" method="POST" class="d-inline" onsubmit="return validateStatusTransition(this);">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="status" value="picked_up">
                                            <input type="hidden" name="current_status" value="<?php echo htmlspecialchars($order['assignment_status']); ?>">
                                            <input type="hidden" name="order_status" value="<?php echo htmlspecialchars($order['order_status']); ?>">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-box"></i> Mark as Picked Up
                                            </button>
                                        </form>
                                    <?php elseif ($order['assignment_status'] === 'picked_up'): ?>
                                        <form action="update_order_status.php" method="POST" class="d-inline" onsubmit="return validateStatusTransition(this);">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="status" value="delivered">
                                            <input type="hidden" name="current_status" value="<?php echo htmlspecialchars($order['assignment_status']); ?>">
                                            <input type="hidden" name="order_status" value="<?php echo htmlspecialchars($order['order_status']); ?>">
                                            <?php if ($order['order_type'] === 'delivery'): ?>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Mark as Delivered
                                                </button>
                                            <?php elseif ($order['order_type'] === 'dine_in'): ?>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Mark as Served
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Mark as Handed Over
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            No actions available for current status: <?php echo ucfirst(str_replace('_', ' ', $order['assignment_status'])); ?>
                                            <br>Order Status: <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                                        </div>
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
function validateStatusTransition(form) {
    const currentStatus = form.current_status.value;
    const newStatus = form.status.value;
    const orderStatus = form.order_status.value;
    
    // Define valid transitions
    const validTransitions = {
        'assigned': ['picked_up'],
        'picked_up': ['delivered']
    };
    
    // Check if transition is valid
    if (!validTransitions[currentStatus] || !validTransitions[currentStatus].includes(newStatus)) {
        alert('Invalid status transition from ' + currentStatus + ' to ' + newStatus);
        return false;
    }
    
    // Check order status for pick up
    if (newStatus === 'picked_up' && orderStatus !== 'ready') {
        alert('Cannot pick up order. Order is not ready yet.');
        return false;
    }
    
    // Add confirmation messages based on status
    let confirmMessage = '';
    if (newStatus === 'picked_up') {
        confirmMessage = 'Are you sure you want to mark this order as picked up?';
    } else if (newStatus === 'delivered') {
        const orderType = '<?php echo $order['order_type']; ?>';
        if (orderType === 'delivery') {
            confirmMessage = 'Confirm delivery to the specified location?';
        } else if (orderType === 'dine_in') {
            confirmMessage = 'Confirm delivery to Table <?php echo htmlspecialchars($order['table_number']); ?>?';
        } else {
            confirmMessage = 'Confirm pickup handover?';
        }
    }
    
    return confirm(confirmMessage);
}
</script>

<?php
function getStatusClass($status) {
    switch ($status) {
        case 'assigned':
            return 'warning';
        case 'picked_up':
            return 'info';
        case 'delivered':
            return 'success';
        default:
            return 'secondary';
    }
}

$content = ob_get_clean();
require_once '../includes/layout.php';
?> 