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

// Get filter parameters
$status = isset($_GET['status']) && $_GET['status'] !== 'all' ? $_GET['status'] : null;
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle status update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = $_POST['order_id'] ?? null;
    $new_status = $_POST['status'] ?? null;
    $preparation_time = $_POST['preparation_time'] ?? null;
    $notes = $_POST['notes'] ?? null;

    if ($order_id && $new_status) {
        try {
            $conn->beginTransaction();

            // Verify order belongs to vendor
            $stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$order_id, $vendor_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Order not found or does not belong to this vendor");
            }

            // Insert into order_tracking
            $stmt = $conn->prepare("
                INSERT INTO order_tracking (order_id, status, notes, updated_by, status_changed_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$order_id, $new_status, $notes, $_SESSION['user_id']]);

            // Update preparation time if provided and status is 'accepted'
            if ($new_status === 'accepted' && $preparation_time) {
                $stmt = $conn->prepare("UPDATE orders SET preparation_time = ? WHERE id = ?");
                $stmt->execute([$preparation_time, $order_id]);
            }

            // Update completion time if status is 'completed'
            if ($new_status === 'completed') {
                $stmt = $conn->prepare("UPDATE orders SET completed_at = NOW() WHERE id = ?");
                $stmt->execute([$order_id]);
            }

            // Update cancellation reason if status is 'cancelled'
            if ($new_status === 'cancelled' && $notes) {
                $stmt = $conn->prepare("UPDATE orders SET cancelled_reason = ? WHERE id = ?");
                $stmt->execute([$notes, $order_id]);
            }

            $conn->commit();
            header('Location: manage_orders.php?success=1&message=' . urlencode('Order status updated successfully'));
            exit();

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            header('Location: manage_orders.php?error=1&message=' . urlencode($e->getMessage()));
            exit();
        }
    }
}

// Show success/error messages if they exist
if (isset($_GET['success'])) {
    $message_type = 'success';
    $message = $_GET['message'] ?? 'Operation completed successfully';
} elseif (isset($_GET['error'])) {
    $message_type = 'error';
    $message = $_GET['message'] ?? 'An error occurred';
}

// Function to get count of orders by status
function getOrderCountByStatus($conn, $vendor_id, $status) {
    $sql = "
        SELECT COUNT(*) 
        FROM orders o
        LEFT JOIN (
            SELECT ot1.*
            FROM order_tracking ot1
            INNER JOIN (
                SELECT order_id, MAX(status_changed_at) as max_date
                FROM order_tracking
                GROUP BY order_id
            ) ot2 ON ot1.order_id = ot2.order_id AND ot1.status_changed_at = ot2.max_date
        ) ot ON o.id = ot.order_id
        WHERE o.vendor_id = :vendor_id 
        AND COALESCE(ot.status, 'pending') = :status";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':vendor_id' => $vendor_id,
        ':status' => $status
    ]);
    return $stmt->fetchColumn();
}

// Get order counts
$counts = [
    'pending' => getOrderCountByStatus($conn, $vendor_id, 'pending'),
    'accepted' => getOrderCountByStatus($conn, $vendor_id, 'accepted'),
    'in_progress' => getOrderCountByStatus($conn, $vendor_id, 'in_progress'),
    'ready' => getOrderCountByStatus($conn, $vendor_id, 'ready'),
    'completed' => getOrderCountByStatus($conn, $vendor_id, 'completed'),
    'cancelled' => getOrderCountByStatus($conn, $vendor_id, 'cancelled')
];

// Initialize variables
$params = [$vendor_id];
$status_condition = "";
$date_condition = "";

// Add status filter if provided
if (isset($_GET['status']) && $_GET['status'] !== 'all') {
    $status_condition = "AND COALESCE(ot.status, 'pending') = ?";
    $params[] = $_GET['status'];
}

// Add date filter if provided
if (!empty($_GET['date'])) {
    $date_condition = "AND DATE(o.order_date) = ?";
    $params[] = $_GET['date'];
}

// Build the search condition
$search_condition = "";
if (!empty($_GET['search'])) {
    $search = $_GET['search'];
    // If search looks like an order ID (starts with #ORD-), remove the # for matching
    if (strpos($search, '#ORD-') === 0) {
        $search = substr($search, 1);
    }
    $search = "%$search%";
    $search_condition = "AND (
        o.id LIKE ? OR 
        o.receipt_number LIKE ? OR
        u.username LIKE ? OR 
        u.email LIKE ? OR 
        EXISTS (
            SELECT 1 FROM order_items oi2 
            JOIN menu_items mi2 ON oi2.menu_item_id = mi2.item_id 
            WHERE oi2.order_id = o.id 
            AND CONCAT(oi2.quantity, 'x ', mi2.name) LIKE ?
        )
    )";
    $params = array_merge($params, [$search, $search, $search, $search, $search]);
}

// Build the complete query with proper sorting
$sql = "
    SELECT o.*, v.id as vendor_id, u.username as customer_name, u.email as customer_email,
        COALESCE(ot.status, 'pending') as current_status,
        oa.worker_id, w.user_id as worker_user_id, wu.username as worker_name,
        odd.order_type, odd.delivery_location, odd.building_name,
        odd.floor_number, odd.room_number, odd.contact_number,
        odd.table_number,
        CASE 
            WHEN o.payment_method = 'credit' OR ct.transaction_type = 'purchase' THEN 'credit'
            ELSE o.payment_method 
        END as payment_method,
        GROUP_CONCAT(DISTINCT CONCAT(oi.quantity, 'x ', mi.name) SEPARATOR ', ') as items
    FROM orders o
    JOIN vendors v ON o.vendor_id = v.id
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_delivery_details odd ON o.id = odd.order_id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN menu_items mi ON oi.menu_item_id = mi.item_id
    LEFT JOIN credit_transactions ct ON o.id = ct.order_id
    LEFT JOIN (
        SELECT order_id, status
        FROM order_tracking
        WHERE id IN (
            SELECT MAX(id)
            FROM order_tracking
            GROUP BY order_id
        )
    ) ot ON o.id = ot.order_id
    LEFT JOIN order_assignments oa ON o.id = oa.order_id
    LEFT JOIN workers w ON oa.worker_id = w.id
    LEFT JOIN users wu ON w.user_id = wu.id
    WHERE v.id = ? $status_condition $date_condition $search_condition
    GROUP BY o.id, v.id, u.username, u.email, ot.status, oa.worker_id, w.user_id, wu.username,
        odd.order_type, odd.delivery_location, odd.building_name, odd.floor_number, 
        odd.room_number, odd.contact_number, odd.table_number
    ORDER BY 
        CASE 
            WHEN COALESCE(ot.status, 'pending') IN ('completed', 'cancelled') THEN 2
            ELSE 1
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
        o.order_date DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Manage Orders';
ob_start();
?>

<!-- Custom CSS -->
<style>
.order-card {
    transition: all 0.3s ease;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.order-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.status-badge {
    font-size: 0.85rem;
    padding: 0.4rem 0.8rem;
    border-radius: 50px;
}

.order-items {
    max-height: 100px;
    overflow-y: auto;
    font-size: 0.9rem;
}

.action-buttons .btn {
    margin: 0 2px;
    border-radius: 50px;
    padding: 0.4rem 1rem;
}

.stats-card {
    border-radius: 10px;
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.order-filter {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 1rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -30px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #007bff;
    border: 2px solid #fff;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.8);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.pulse {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.table th, .table td {
    vertical-align: middle;
}
.badge {
    font-size: 85%;
    padding: 0.4em 0.6em;
}
.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
}
.table td small {
    display: block;
    line-height: 1.4;
}
</style>

<!-- Loading Overlay -->
<div class="loading-overlay">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h3 class="m-0">Vendor</h3>
                <h4 class="mt-2">Manage Orders</h4>
            </div>
            <div class="col-sm-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb float-sm-right bg-white">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Orders</li>
                </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <?php if (isset($_GET['success']) && isset($_GET['message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Order Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="d-flex flex-column">
                    <div class="h4 mb-0" data-count="pending"><?php echo $counts['pending']; ?></div>
                    <div class="text-muted">Pending Orders</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="d-flex flex-column">
                    <div class="h4 mb-0" data-count="accepted"><?php echo $counts['accepted']; ?></div>
                    <div class="text-muted">Accepted Orders</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="d-flex flex-column">
                    <div class="h4 mb-0" data-count="in_progress"><?php echo $counts['in_progress']; ?></div>
                    <div class="text-muted">In Progress</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="d-flex flex-column">
                    <div class="h4 mb-0" data-count="ready"><?php echo $counts['ready']; ?></div>
                    <div class="text-muted">Ready Orders</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="d-flex flex-column">
                    <div class="h4 mb-0" data-count="completed"><?php echo $counts['completed']; ?></div>
                    <div class="text-muted">Completed</div>
                        </div>
                    </div>
            <div class="col-md-2">
                <div class="d-flex flex-column">
                    <div class="h4 mb-0" data-count="cancelled"><?php echo $counts['cancelled']; ?></div>
                    <div class="text-muted">Cancelled</div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="all">All Orders</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="accepted" <?php echo $status === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="ready" <?php echo $status === 'ready' ? 'selected' : ''; ?>>Ready</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search orders..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date ?? ''); ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="manage_orders.php" class="btn btn-secondary">Reset Filters</a>
                    </div>
                </form>
                </div>
        </div>

        <!-- Orders Table -->
                <div class="card">
                    <div class="card-body">
                            <div class="table-responsive">
                    <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Payment</th>
                                            <th>Date</th>
                                <th>Order Type</th>
                                <th>Delivery Details</th>
                                            <th>Status</th>
                                <th style="min-width: 120px;">Worker</th>
                                <th style="min-width: 150px;">Actions</th>
                                        </tr>
                                    </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <div class="text-muted">No orders found</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                        <td>
                                            <strong>#<?php echo htmlspecialchars($order['receipt_number']); ?></strong>
                                        </td>
                                                <td>
                                                    <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                                </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($order['items']); ?>">
                                                <?php echo htmlspecialchars($order['items']); ?>
                                            </div>
                                        </td>
                                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                            <span class="badge <?php echo getPaymentBadgeClass($order['payment_method']); ?>">
                                                <?php echo getPaymentMethodName($order['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($order['order_date'])); ?><br>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($order['order_date'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getOrderTypeBadgeClass($order['order_type']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($order['order_type'] === 'delivery'): ?>
                                                <small>
                                                    Location: <?php echo htmlspecialchars($order['delivery_location']); ?><br>
                                                    Building: <?php echo htmlspecialchars($order['building_name']); ?><br>
                                                    Floor: <?php echo htmlspecialchars($order['floor_number']); ?><br>
                                                    Room: <?php echo htmlspecialchars($order['room_number']); ?><br>
                                                    Contact: <?php echo htmlspecialchars($order['contact_number']); ?>
                                                </small>
                                            <?php elseif ($order['order_type'] === 'dine_in'): ?>
                                                <small>Table: <?php echo htmlspecialchars($order['table_number']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getStatusBadgeClass($order['current_status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['current_status'])); ?>
                                                    </span>
                                                </td>
                                        <td>
                                            <?php if ($order['worker_name']): ?>
                                                <span class="badge badge-info">
                                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($order['worker_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-info view-order" data-order-id="<?php echo $order['id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($order['current_status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-success" onclick="acceptOrder('<?php echo $order['id']; ?>')">
                                                        <i class="fas fa-check"></i> Accept
                                                    </button>
                                                    <button type="button" class="btn btn-danger" onclick="rejectOrder('<?php echo $order['id']; ?>')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php elseif ($order['current_status'] === 'accepted'): ?>
                                                    <button type="button" class="btn btn-info btn-sm" onclick="handleAssignWorker(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-user-plus"></i> Assign Worker
                                                    </button>

                                                    <form action="update_order_status.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="status" value="in_progress">
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-utensils"></i> Start Prep
                                                        </button>
                                                    </form>

                                                    <form action="update_order_status.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="showCancelDialog(this.form, 'cancel')">
                                                            <i class="fas fa-ban"></i> Cancel
                                                        </button>
                                                    </form>
                                                <?php elseif ($order['current_status'] === 'in_progress'): ?>
                                                    <button type="button" class="btn btn-info btn-sm" onclick="handleAssignWorker(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-user-plus"></i> Assign Worker
                                                    </button>

                                                    <form action="update_order_status.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to mark this order as ready?');">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="status" value="ready">
                                                        <button type="submit" class="btn btn-success btn-sm" name="submit_ready">
                                                            <i class="fas fa-check-circle"></i> Ready
                                                        </button>
                                                    </form>

                                                    <form action="update_order_status.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="showCancelDialog(this.form, 'cancel')">
                                                            <i class="fas fa-ban"></i> Cancel
                                                        </button>
                                                    </form>
                                                <?php elseif ($order['current_status'] === 'ready'): ?>
                                                    <button type="button" class="btn btn-info btn-sm" onclick="handleAssignWorker(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-user-plus"></i> Assign Worker
                                                    </button>

                                                    <form action="update_order_status.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-flag-checkered"></i> Complete
                                                        </button>
                                                    </form>

                                                    <form action="update_order_status.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="showCancelDialog(this.form, 'cancel')">
                                                            <i class="fas fa-ban"></i> Cancel
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
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
        </div>
    </div>
</div>

<!-- Prep Time Dialog -->
<div class="modal fade" id="prepTimeDialog" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enter Preparation Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="prep_time">Preparation Time (minutes):</label>
                    <input type="number" class="form-control" id="prep_time" min="1" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitPrepTime()">Accept Order</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel/Reject Dialog -->
<div class="modal fade" id="cancelDialog" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelDialogTitle">Enter Reason</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="cancel_reason">Reason:</label>
                    <textarea class="form-control" id="cancel_reason" rows="3" required></textarea>
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="submitCancelReason()">Submit</button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Worker Modal -->
<div class="modal fade" id="assignWorkerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Worker</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignWorkerForm" method="POST" action="assign_worker.php">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info"></i> Order Details</h6>
                        <p id="orderDetailsText"></p>
                    </div>
                    <input type="hidden" name="order_id" id="assignOrderId">
                    <div class="form-group">
                        <label for="worker_id">Select Worker</label>
                        <select class="form-control" id="worker_id" name="worker_id" required>
                            <option value="">-- Select Worker --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Worker</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Order Modal -->
<div class="modal fade" id="viewOrderModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Order Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Order ID:</th>
                                <td id="view-order-id"></td>
                            </tr>
                            <tr>
                                <th>Customer:</th>
                                <td id="view-customer"></td>
                            </tr>
                            <tr>
                                <th>Date:</th>
                                <td id="view-date"></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td id="view-status"></td>
                            </tr>
                            <tr>
                                <th>Payment Method:</th>
                                <td id="view-payment"></td>
                            </tr>
                            <tr>
                                <th>Total Amount:</th>
                                <td id="view-total"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Delivery Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Order Type:</th>
                                <td id="view-order-type"></td>
                            </tr>
                            <tr>
                                <th>Location:</th>
                                <td id="view-location"></td>
                            </tr>
                            <tr>
                                <th>Building:</th>
                                <td id="view-building"></td>
                            </tr>
                            <tr>
                                <th>Floor/Room:</th>
                                <td id="view-floor-room"></td>
                            </tr>
                            <tr>
                                <th>Contact:</th>
                                <td id="view-contact"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Order Items</h6>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="view-order-items">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Helper functions for badge classes
function getStatusBadgeClass(status) {
    switch (status) {
        case 'pending':
            return 'bg-warning text-dark';
        case 'accepted':
            return 'bg-info text-white';
        case 'in_progress':
            return 'bg-primary text-white';
        case 'ready':
            return 'bg-success text-white';
        case 'completed':
            return 'bg-secondary text-white';
        case 'cancelled':
            return 'bg-danger text-white';
        default:
            return 'bg-secondary text-white';
    }
}

function getPaymentBadgeClass($payment_method) {
    switch($payment_method) {
        case 'cash':
            return 'bg-success';
        case 'card':
            return 'bg-info';
        case 'khalti':
            return 'bg-purple';
        case 'esewa':
            return 'bg-warning';
        case 'online':
            return 'bg-primary';
        case 'credit':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

function getPaymentMethodName($payment_method) {
    switch($payment_method) {
        case 'cash':
            return 'Cash';
        case 'card':
            return 'Card';
        case 'khalti':
            return 'Khalti';
        case 'esewa':
            return 'eSewa';
        case 'online':
            return 'Online';
        case 'credit':
            return 'Credit';
        default:
            return 'Unknown';
    }
}

let currentForm = null;
let currentAction = '';
let prepTimeModal = null;
let cancelModal = null;
let assignWorkerModal = null;
let viewOrderModal = null;

// Initialize modals when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modals
    prepTimeModal = new bootstrap.Modal(document.getElementById('prepTimeDialog'));
    cancelModal = new bootstrap.Modal(document.getElementById('cancelDialog'));
    assignWorkerModal = new bootstrap.Modal(document.getElementById('assignWorkerModal'));
    viewOrderModal = new bootstrap.Modal(document.getElementById('viewOrderModal'));

    // Add event listeners for close buttons
    document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.closest('.modal').id;
            if (modalId === 'prepTimeDialog') {
                prepTimeModal.hide();
            } else if (modalId === 'cancelDialog') {
                cancelModal.hide();
            } else if (modalId === 'assignWorkerModal') {
                assignWorkerModal.hide();
            } else if (modalId === 'viewOrderModal') {
                viewOrderModal.hide();
            }
        });
    });

    // Add event listener for Cancel button in prep time modal
    document.querySelector('#prepTimeDialog .btn-secondary').addEventListener('click', function() {
        prepTimeModal.hide();
    });

    // Add event listener for Close button in cancel/reject modal
    document.querySelector('#cancelDialog .btn-secondary').addEventListener('click', function() {
        cancelModal.hide();
    });

    // Add view order button event listeners
    document.querySelectorAll('.view-order').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            viewOrder(orderId);
        });
    });
});

function showPrepTimeDialog(form) {
    currentForm = form;
    document.getElementById('prep_time').value = ''; // Clear previous value
    prepTimeModal.show();
}

function submitPrepTime() {
    const prepTime = document.getElementById('prep_time').value;
    if (prepTime && parseInt(prepTime) > 0) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'prep_time';
        input.value = prepTime;
        currentForm.appendChild(input);
        prepTimeModal.hide();
        currentForm.submit();
    }
}

function showCancelDialog(form, action) {
    currentForm = form;
    currentAction = action;
    document.getElementById('cancelDialogTitle').textContent = 
        action === 'reject' ? 'Enter Rejection Reason' : 'Enter Cancellation Reason';
    document.getElementById('cancel_reason').value = '';
    cancelModal.show();
}

function submitCancelReason() {
    const reason = document.getElementById('cancel_reason').value;
    if (reason.trim()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'notes';
        input.value = reason;
        currentForm.appendChild(input);
        cancelModal.hide();
        currentForm.submit();
    }
}

function handleAssignWorker(orderId) {
    // Clear previous data
    document.getElementById('assignOrderId').value = '';
    document.getElementById('orderDetailsText').textContent = '';
    document.getElementById('worker_id').innerHTML = '<option value="">-- Select Worker --</option>';

    // Fetch workers and order details
    fetch(`assign_worker.php?order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Set order details
                document.getElementById('assignOrderId').value = data.order.id;
                document.getElementById('orderDetailsText').textContent = 
                    `Order #${data.order.receipt_number} - Customer: ${data.order.customer_name}`;

                // Populate workers dropdown
                const select = document.getElementById('worker_id');
                data.workers.forEach(worker => {
                    const option = document.createElement('option');
                    option.value = worker.id;
                    option.textContent = `${worker.username} (${worker.contact_number})`;
                    select.appendChild(option);
                });

                // Show modal
                assignWorkerModal.show();
            } else {
                alert(data.message || 'Failed to load worker data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load worker data');
        });
}

function viewOrder(orderId) {
    // Show loading state
    document.querySelector('.loading-overlay').style.display = 'flex';

    // Fetch order details
    fetch(`get_order_details.php?order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update modal with order details
                document.getElementById('view-order-id').textContent = data.order.receipt_number;
                document.getElementById('view-customer').textContent = `${data.order.customer_name} (${data.order.customer_email})`;
                document.getElementById('view-date').textContent = data.order.order_date;
                document.getElementById('view-status').innerHTML = `<span class="badge ${getStatusBadgeClass(data.order.current_status)}">${data.order.current_status}</span>`;
                document.getElementById('view-payment').innerHTML = `<span class="badge ${getPaymentBadgeClass(data.order.payment_method)}">${getPaymentMethodName(data.order.payment_method)}</span>`;
                document.getElementById('view-total').textContent = `₹${parseFloat(data.order.total_amount).toFixed(2)}`;
                
                // Update delivery information
                document.getElementById('view-order-type').textContent = data.order.order_type || '-';
                document.getElementById('view-location').textContent = data.order.delivery_location || '-';
                document.getElementById('view-building').textContent = data.order.building_name || '-';
                document.getElementById('view-floor-room').textContent = 
                    `${data.order.floor_number || '-'}/${data.order.room_number || '-'}`;
                document.getElementById('view-contact').textContent = data.order.contact_number || '-';

                // Update order items
                const itemsContainer = document.getElementById('view-order-items');
                itemsContainer.innerHTML = '';
                data.items.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.name}</td>
                        <td>${item.quantity}</td>
                        <td class="text-end">₹${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td class="text-end">₹${(item.quantity * item.unit_price).toFixed(2)}</td>
                    `;
                    itemsContainer.appendChild(row);
                });

                // Show the modal
                viewOrderModal.show();
            } else {
                alert(data.message || 'Failed to load order details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load order details');
        })
        .finally(() => {
            document.querySelector('.loading-overlay').style.display = 'none';
        });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function confirmReady(orderId) {
    console.log('Submitting ready status for order:', orderId);
    return confirm('Are you sure this order is ready?');
}

// Function to apply filters
function applyFilters() {
    const status = document.querySelector('#status-filter').value;
    const search = document.querySelector('#search-input').value.trim();
    const date = document.querySelector('#date-filter').value;
    
    // Build query string
    let queryParams = new URLSearchParams(window.location.search);
    
    // Update or remove status parameter
    if (status && status !== 'all') {
        queryParams.set('status', status);
    } else {
        queryParams.delete('status');
    }
    
    // Update or remove search parameter
    if (search) {
        // If user didn't include #, but it looks like an order number, add it
        if (!search.startsWith('#') && /^ORD-\d{8}-[a-f0-9]+$/i.test(search)) {
            queryParams.set('search', '#' + search);
        } else {
            queryParams.set('search', search);
        }
    } else {
        queryParams.delete('search');
    }
    
    // Update or remove date parameter
    if (date) {
        queryParams.set('date', date);
    } else {
        queryParams.delete('date');
    }
    
    // Redirect with new query string
    window.location.href = window.location.pathname + '?' + queryParams.toString();
}

// Function to reset filters
function resetFilters() {
    document.querySelector('#status-filter').value = 'all';
    document.querySelector('#search-input').value = '';
    document.querySelector('#date-filter').value = '';
    window.location.href = window.location.pathname;
}

// Add event listeners when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Apply filters button
    document.querySelector('#apply-filters').addEventListener('click', applyFilters);
    
    // Reset filters button
    document.querySelector('#reset-filters').addEventListener('click', resetFilters);
    
    // Enter key in search input
    document.querySelector('#search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
    
    // Initialize date picker if using one
    const datePicker = document.querySelector('#date-filter');
    if (datePicker) {
        datePicker.addEventListener('change', applyFilters);
    }
    
    // Set initial values from URL params
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('status')) {
        document.querySelector('#status-filter').value = urlParams.get('status');
    }
    if (urlParams.has('search')) {
        document.querySelector('#search-input').value = urlParams.get('search');
    }
    if (urlParams.has('date')) {
        document.querySelector('#date-filter').value = urlParams.get('date');
    }
});

// View order details
function viewOrder(orderId) {
    window.location.href = 'view_order.php?id=' + orderId;
}

// Accept order
function acceptOrder(orderId) {
    if (confirm('Are you sure you want to accept this order?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'update_order_status.php';
        
        const orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'order_id';
        orderIdInput.value = orderId;
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = 'accepted';
        
        form.appendChild(orderIdInput);
        form.appendChild(statusInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Reject order
function rejectOrder(orderId) {
    if (confirm('Are you sure you want to reject this order? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'update_order_status.php';
        
        const orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'order_id';
        orderIdInput.value = orderId;
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = 'rejected';
        
        form.appendChild(orderIdInput);
        form.appendChild(statusInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Update the action buttons HTML
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers to all view buttons
    document.querySelectorAll('.view-order').forEach(button => {
        button.onclick = function() {
            viewOrder(this.dataset.orderId);
        }
    });
});
</script>

<?php
// Helper function for status badge classes
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

// Helper function for order type badge classes
function getOrderTypeBadgeClass($type) {
    return match($type) {
        'dine_in' => 'bg-info',
        'takeaway' => 'bg-warning',
        'delivery' => 'bg-primary',
        default => 'bg-secondary'
    };
}

// Helper function for payment badge classes
function getPaymentBadgeClass($payment_method) {
    switch($payment_method) {
        case 'cash':
            return 'bg-success';
        case 'card':
            return 'bg-info';
        case 'khalti':
            return 'bg-purple';
        case 'esewa':
            return 'bg-warning';
        case 'online':
            return 'bg-primary';
        case 'credit':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Helper function to get payment method name
function getPaymentMethodName($payment_method) {
    switch($payment_method) {
        case 'cash':
            return 'Cash';
        case 'card':
            return 'Card';
        case 'khalti':
            return 'Khalti';
        case 'esewa':
            return 'eSewa';
        case 'online':
            return 'Online';
        case 'credit':
            return 'Credit';
        default:
            return 'Unknown';
    }
}

$content = ob_get_clean();
require_once '../includes/layout.php';
?>