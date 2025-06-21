<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../config/khalti.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit();
}

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    $_SESSION['error'] = "Invalid order ID";
    header('Location: cart.php');
    exit();
}

$order_id = $_GET['order_id'];

try {
    // Get order details
    $stmt = $conn->prepare("
        SELECT o.*, v.id as vendor_id, u.username as vendor_name,
        ss.id as staff_id, su.username as staff_name,
        su.email as staff_email, su.contact_number as staff_phone
        FROM orders o
        JOIN vendors v ON o.vendor_id = v.id
        JOIN users u ON v.user_id = u.id
        JOIN staff_students ss ON o.customer_id = ss.id
        JOIN users su ON ss.user_id = su.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found");
    }

    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, mi.name
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.item_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare customer info for Khalti
    $customerInfo = [
        'name' => $order['staff_name'],
        'email' => $order['staff_email'],
        'phone' => $order['staff_phone']
    ];

    // Prepare order details
    $orderDetails = [
        'vendor_id' => $order['vendor_id'],
        'order_type' => $order['order_type'],
        'form_data' => []
    ];

    // Get delivery/dine-in details
    $stmt = $conn->prepare("
        SELECT * FROM order_delivery_details 
        WHERE order_id = ?
    ");
    $stmt->execute([$order_id]);
    $deliveryDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($deliveryDetails) {
        $orderDetails['form_data'] = $deliveryDetails;
    }

    $page_title = 'Khalti Payment';
    ob_start();
?>

<div class="content">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Khalti Payment</h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h4>Order #<?php echo htmlspecialchars($order['receipt_number']); ?></h4>
                            <p>Total Amount: Rs. <?php echo number_format($order['total_amount'], 2); ?></p>
                        </div>

                        <div class="order-details mb-4">
                            <h5>Order Items:</h5>
                            <ul class="list-group">
                                <?php foreach ($items as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                    <span>
                                        <?php echo $item['quantity']; ?> x Rs. <?php echo number_format($item['unit_price'], 2); ?>
                                        = Rs. <?php echo number_format($item['subtotal'], 2); ?>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="text-center">
                            <button id="payment-button" class="btn btn-primary">Pay with Khalti</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/2020.12.17.0.0.0/khalti-checkout.iffe.js"></script>
<script>
    // Prepare payment data
    const paymentData = {
        amount: <?php echo $order['total_amount'] * 100; ?>,
        purchase_order_id: "<?php echo $order['receipt_number']; ?>",
        purchase_order_name: "Order #<?php echo $order['receipt_number']; ?>",
        customer_info: <?php echo json_encode($customerInfo); ?>,
        order_details: <?php echo json_encode($orderDetails); ?>
    };

    // Initialize payment button
    document.getElementById('payment-button').addEventListener('click', function() {
        // Make AJAX request to initiate payment
        fetch('../payment/khalti_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(paymentData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.payment_url) {
                window.location.href = data.payment_url;
            } else {
                alert('Failed to initiate payment: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to initiate payment. Please try again.');
        });
    });
</script>

<?php
    $content = ob_get_clean();
    require_once '../includes/layout.php';
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: cart.php');
    exit();
}
?> 