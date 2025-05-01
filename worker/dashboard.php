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

// Initialize variables
$pending_orders = 0;
$active_deliveries = 0;
$completed_today = 0;
$active_tables = 0;
$recent_deliveries = [];

try {
    // Get pending orders count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM orders o
        WHERE o.assigned_worker_id = ?
        AND o.status = 'ready'
    ");
    $stmt->execute([$worker['id']]);
    $pending_orders = $stmt->fetchColumn();

    // Get orders in delivery
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM orders o
        WHERE o.assigned_worker_id = ?
        AND o.status = 'in_delivery'
    ");
    $stmt->execute([$worker['id']]);
    $active_deliveries = $stmt->fetchColumn();

    // Get completed orders today
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM orders o
        WHERE o.assigned_worker_id = ?
        AND o.status = 'completed'
        AND DATE(o.updated_at) = CURRENT_DATE
    ");
    $stmt->execute([$worker['id']]);
    $completed_today = $stmt->fetchColumn();

    // Get active tables (for dine-in orders)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT odd.table_number) as count
        FROM orders o
        JOIN order_delivery_details odd ON o.id = odd.order_id
        WHERE o.assigned_worker_id = ?
        AND o.status IN ('ready', 'in_delivery')
        AND odd.order_type = 'dine_in'
    ");
    $stmt->execute([$worker['id']]);
    $active_tables = $stmt->fetchColumn();

    // Get recent deliveries
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.receipt_number,
            o.order_date,
            o.updated_at as delivered_at,
            v.name as vendor_name,
            odd.order_type,
            odd.delivery_location,
            odd.table_number
        FROM orders o
        JOIN vendors v ON o.vendor_id = v.id
        LEFT JOIN order_delivery_details odd ON o.id = odd.order_id
        WHERE o.assigned_worker_id = ?
        AND o.status = 'completed'
        ORDER BY o.updated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$worker['id']]);
    $recent_deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error loading worker dashboard: " . $e->getMessage());
    $_SESSION['error'] = "Error loading dashboard statistics. Please try refreshing the page.";
}

$page_title = 'Worker Dashboard';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Dashboard</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item active">Dashboard</li>
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

        <!-- Info boxes -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $pending_orders; ?></h3>
                        <p>Pending Orders</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <a href="assigned_orders.php" class="small-box-footer">
                        View Orders <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $active_deliveries; ?></h3>
                        <p>Active Deliveries</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <a href="assigned_orders.php" class="small-box-footer">
                        View Deliveries <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $completed_today; ?></h3>
                        <p>Completed Today</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <a href="order_history.php" class="small-box-footer">
                        View History <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $active_tables; ?></h3>
                        <p>Active Tables</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <a href="assigned_orders.php" class="small-box-footer">
                        View Tables <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Deliveries -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Deliveries</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>Delivered At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_deliveries)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No recent deliveries</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_deliveries as $delivery): ?>
                                            <tr>
                                                <td><?php echo $delivery['receipt_number']; ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $delivery['order_type'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($delivery['order_type'] === 'delivery'): ?>
                                                        <?php echo htmlspecialchars($delivery['delivery_location']); ?>
                                                    <?php else: ?>
                                                        Table #<?php echo htmlspecialchars($delivery['table_number']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('h:i A', strtotime($delivery['delivered_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Stats -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Today's Performance</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-0">Completed Orders</h4>
                                <small class="text-muted">Total orders delivered today</small>
                            </div>
                            <div>
                                <h3 class="text-success mb-0"><?php echo $completed_today; ?></h3>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-0">Pending Orders</h4>
                                <small class="text-muted">Orders waiting for pickup</small>
                            </div>
                            <div>
                                <h3 class="text-warning mb-0"><?php echo $pending_orders; ?></h3>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">Active Tables</h4>
                                <small class="text-muted">Tables currently being served</small>
                            </div>
                            <div>
                                <h3 class="text-danger mb-0"><?php echo $active_tables; ?></h3>
                            </div>
                        </div>
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