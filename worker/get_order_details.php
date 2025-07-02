<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a kitchen staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    echo 'Unauthorized access';
    exit();
}

// Validate input
if (!isset($_GET['id'])) {
    echo 'Missing order ID';
    exit();
}

$order_id = $_GET['id'];

try {
    // Get worker details and verify they are kitchen staff
    $stmt = $conn->prepare("
        SELECT w.* 
        FROM workers w 
        WHERE w.user_id = ? AND LOWER(w.position) = 'kitchen_staff'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$worker) {
        echo 'Access denied. Only kitchen staff can view order details';
        exit();
    }

    // Get order details
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            cu.username as customer_name,
            cu.contact_number as customer_contact,
            v.id as vendor_id,
            vu.username as vendor_name,
            odd.order_type,
            odd.table_number,
            odd.delivery_location,
            odd.building_name,
            odd.floor_number,
            odd.room_number,
            odd.delivery_instructions,
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
        WHERE o.id = ?
        AND o.vendor_id = (
            SELECT vendor_id 
            FROM workers 
            WHERE id = ?
        )
    ");
    $stmt->execute([$order_id, $worker['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo 'Order not found or access denied';
        exit();
    }

    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, mi.name, mi.description
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.item_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get order tracking history
    $stmt = $conn->prepare("
        SELECT 
            ot.*,
            u.username as updated_by_name
        FROM order_tracking ot
        LEFT JOIN users u ON ot.updated_by = u.id
        WHERE ot.order_id = ?
        ORDER BY ot.status_changed_at DESC
    ");
    $stmt->execute([$order_id]);
    $tracking_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ?>
    <div class="row">
        <div class="col-md-6">
            <h5>Order Information</h5>
            <table class="table table-sm">
                <tr>
                    <th>Order Number:</th>
                    <td><?php echo htmlspecialchars($order['receipt_number']); ?></td>
                </tr>
                <tr>
                    <th>Customer:</th>
                    <td>
                        <?php echo htmlspecialchars($order['customer_name']); ?>
                        <br>
                        <small class="text-muted"><?php echo htmlspecialchars($order['customer_contact']); ?></small>
                    </td>
                </tr>
                <tr>
                    <th>Order Date:</th>
                    <td><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td>
                        <span class="badge badge-<?php echo getStatusBadgeClass($order['order_status']); ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Order Type:</th>
                    <td>
                        <span class="badge badge-info">
                            <?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?>
                        </span>
                    </td>
                </tr>
                <?php if ($order['order_type'] === 'dine_in'): ?>
                    <tr>
                        <th>Table Number:</th>
                        <td><?php echo htmlspecialchars($order['table_number']); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($order['order_type'] === 'delivery'): ?>
                    <tr>
                        <th>Delivery Location:</th>
                        <td>
                            <?php echo htmlspecialchars($order['delivery_location']); ?><br>
                            <?php if ($order['building_name']): ?>
                                Building: <?php echo htmlspecialchars($order['building_name']); ?><br>
                            <?php endif; ?>
                            <?php if ($order['floor_number']): ?>
                                Floor: <?php echo htmlspecialchars($order['floor_number']); ?><br>
                            <?php endif; ?>
                            <?php if ($order['room_number']): ?>
                                Room: <?php echo htmlspecialchars($order['room_number']); ?><br>
                            <?php endif; ?>
                            <?php if ($order['delivery_instructions']): ?>
                                <small class="text-muted">
                                    Instructions: <?php echo htmlspecialchars($order['delivery_instructions']); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="col-md-6">
            <h5>Order Items</h5>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Special Instructions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($item['name']); ?>
                                <?php if ($item['description']): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($item['description']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>
                                <?php if ($item['special_instructions']): ?>
                                    <small><?php echo htmlspecialchars($item['special_instructions']); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <h5>Order Status History</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Updated By</th>
                            <th>Time</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tracking_history as $history): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?php echo getStatusBadgeClass($history['status']); ?>">
                                        <?php echo ucfirst($history['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($history['updated_by_name']); ?></td>
                                <td><?php echo date('M d, Y h:i:s A', strtotime($history['status_changed_at'])); ?></td>
                                <td><?php echo htmlspecialchars($history['notes'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
} catch (Exception $e) {
    error_log("Error getting order details: " . $e->getMessage());
    echo 'Error loading order details. Please try again.';
}
?> 