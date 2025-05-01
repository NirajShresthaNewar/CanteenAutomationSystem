<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if vendor is logged in
if (!isset($_SESSION['vendor_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch orders for the vendor with latest status from order_tracking
$stmt = $conn->prepare("
    SELECT o.*, u.username, odd.order_type,
           COALESCE(ot.status, 'pending') as current_status
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_delivery_details odd ON o.id = odd.order_id
    LEFT JOIN (
        SELECT ot1.*
        FROM order_tracking ot1
        INNER JOIN (
            SELECT order_id, MAX(status_changed_at) as max_date
            FROM order_tracking
            GROUP BY order_id
        ) ot2 ON ot1.order_id = ot2.order_id AND ot1.status_changed_at = ot2.max_date
    ) ot ON o.id = ot.order_id
    WHERE o.vendor_id = ?
    ORDER BY o.order_date DESC
");
$stmt->bind_param("i", $_SESSION['vendor_id']);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Vendor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <h2>Orders</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Order Type</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                        <td>
                            <span class="badge badge-info">
                                <?php echo ucfirst(str_replace('_', ' ', $order['order_type'] ?? 'dine_in')); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($order['order_date'])); ?></td>
                        <td>
                            <span class="badge badge-<?php echo getStatusBadgeClass($order['current_status']); ?>">
                                <?php echo ucfirst($order['current_status']); ?>
                            </span>
                        </td>
                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <a href="get_order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-info btn-sm">View Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html> 