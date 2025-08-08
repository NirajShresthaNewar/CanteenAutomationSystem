<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../index.php');
    exit();
}

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    header('Location: orders.php');
    exit();
}

$order_id = $_GET['order_id'];

try {
    // Get order details with delivery information
    $stmt = $conn->prepare("
        SELECT o.*, 
               u.username as customer_name,
               odd.order_type,
               odd.table_number,
               odd.delivery_location,
               odd.building_name,
               odd.floor_number,
               odd.room_number,
               odd.delivery_instructions,
               odd.contact_number,
               ot.status as order_status,
               ot.status_changed_at
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN order_delivery_details odd ON o.order_id = odd.order_id
        LEFT JOIN order_tracking ot ON o.order_id = ot.order_id
        WHERE o.order_id = ? AND o.vendor_id = ?
        ORDER BY ot.status_changed_at DESC
        LIMIT 1
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found or access denied.");
    }

    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, mi.name
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.item_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: orders.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .order-details {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .section:last-child {
            border-bottom: none;
        }
        .section h3 {
            color: #333;
            margin-bottom: 10px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .items-table th, .items-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .items-table th {
            background-color: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-preparing { background: #cce5ff; color: #004085; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-completed { background: #c3e6cb; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include_once '../includes/header.php'; ?>

    <div class="container">
        <div class="order-details">
            <div class="section">
                <h2>Order #<?php echo $order_id; ?></h2>
                <p>
                    <strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?><br>
                    <strong>Order Date:</strong> <?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?><br>
                    <strong>Status:</strong> 
                    <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                        <?php echo ucfirst($order['order_status']); ?>
                    </span>
                </p>
            </div>

            <div class="section">
                <h3>Delivery Details</h3>
                <p>
                    <strong>Order Type:</strong> <?php echo ucfirst($order['order_type']); ?><br>
                    <?php if ($order['order_type'] == 'delivery'): ?>
                        <strong>Delivery Location:</strong> <?php echo htmlspecialchars($order['delivery_location']); ?><br>
                        <strong>Building:</strong> <?php echo htmlspecialchars($order['building_name']); ?><br>
                        <strong>Floor:</strong> <?php echo htmlspecialchars($order['floor_number']); ?><br>
                        <strong>Room:</strong> <?php echo htmlspecialchars($order['room_number']); ?><br>
                        <strong>Contact:</strong> <?php echo htmlspecialchars($order['contact_number']); ?><br>
                        <?php if ($order['delivery_instructions']): ?>
                            <strong>Instructions:</strong> <?php echo htmlspecialchars($order['delivery_instructions']); ?><br>
                        <?php endif; ?>
                    <?php elseif ($order['order_type'] == 'dine_in'): ?>
                        <strong>Table Number:</strong> <?php echo htmlspecialchars($order['table_number']); ?><br>
                    <?php endif; ?>
                </p>
            </div>

            <div class="section">
                <h3>Order Items</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>₹<?php echo number_format($item['price'], 2); ?></td>
                                <td>₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                            <td><strong>₹<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h3>Update Order Status</h3>
                <form action="update_order_status.php" method="POST">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <select name="status" required>
                        <option value="pending" <?php echo $order['order_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="preparing" <?php echo $order['order_status'] == 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                        <option value="ready" <?php echo $order['order_status'] == 'ready' ? 'selected' : ''; ?>>Ready</option>
                        <option value="completed" <?php echo $order['order_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $order['order_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>
            </div>

            <div class="section">
                <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html> 