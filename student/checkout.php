<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Verify POST request with required data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['vendor_id'], $_POST['payment_method'])) {
    $_SESSION['error'] = "Invalid checkout request.";
    header('Location: cart.php');
    exit();
}

$vendor_id = $_POST['vendor_id'];
$payment_method = $_POST['payment_method'];

try {
    // Get cart items for this vendor
    $stmt = $conn->prepare("
        SELECT ci.*, mi.name, mi.price
        FROM cart_items ci
        JOIN menu_items mi ON ci.menu_item_id = mi.item_id
        WHERE ci.user_id = ? AND mi.vendor_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        throw new Exception("Your cart is empty.");
    }

    // Calculate total
    $total_amount = 0;
    foreach ($cart_items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }

    // Get student ID
    $stmt = $conn->prepare("SELECT id FROM staff_students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student record not found.");
    }

    // Start transaction
    $conn->beginTransaction();

    // Generate receipt number
    $receipt_number = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

    // Create order
    $stmt = $conn->prepare("
        INSERT INTO orders (
            receipt_number, user_id, customer_id, vendor_id,
            total_amount, payment_method, payment_status,
            status, order_date
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?,
            'pending', CURRENT_TIMESTAMP
        )
    ");
    $stmt->execute([
        $receipt_number,
        $_SESSION['user_id'],
        $student['id'],
        $vendor_id,
        $total_amount,
        $payment_method,
        'pending'
    ]);

    $order_id = $conn->lastInsertId();

    // Create order items
    $stmt = $conn->prepare("
        INSERT INTO order_items (
            order_id, menu_item_id, quantity,
            unit_price, subtotal, special_instructions
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($cart_items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $stmt->execute([
            $order_id,
            $item['menu_item_id'],
            $item['quantity'],
            $item['price'],
            $subtotal,
            $item['special_instructions'] ?? null
        ]);
    }

    // Clear cart items for this vendor
    $stmt = $conn->prepare("
        DELETE FROM cart_items 
        WHERE user_id = ? 
        AND menu_item_id IN (
            SELECT item_id 
            FROM menu_items 
            WHERE vendor_id = ?
        )
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);

    // Create order tracking entry
    $stmt = $conn->prepare("
        INSERT INTO order_tracking (
            order_id, status, status_changed_at, updated_by
        ) VALUES (?, 'pending', CURRENT_TIMESTAMP, ?)
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);

    $conn->commit();

    // Redirect based on payment method
    switch ($payment_method) {
        case 'cash':
            header("Location: cash_payment.php?order_id=" . $order_id);
            break;
        case 'esewa':
            header("Location: esewa_payment.php?order_id=" . $order_id);
            break;
        case 'credit':
            header("Location: credit_payment.php?order_id=" . $order_id);
            break;
        default:
            throw new Exception("Invalid payment method.");
    }
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Error processing checkout: " . $e->getMessage();
    header('Location: cart.php');
    exit();
}

$page_title = 'Checkout';
ob_start();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Order Summary</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                foreach ($_SESSION['cart'] as $item): 
                                    $item_total = $item['price'] * $item['quantity'];
                                    $total += $item_total;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>Rs. <?php echo number_format($item_total, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-right">Total:</th>
                                    <th>Rs. <?php echo number_format($total, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Payment Details</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Vendor</label>
                            <p class="form-control-static"><?php echo htmlspecialchars($vendor['vendor_name']); ?></p>
                        </div>
                        
                        <div class="form-group">
                            <label>Payment Method</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       value="cash" checked>
                                <label class="form-check-label">Cash</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       value="esewa">
                                <label class="form-check-label">eSewa</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" 
                                       value="credit">
                                <label class="form-check-label">Credit Account</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            Confirm Order
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 