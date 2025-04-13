<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student or staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'staff'])) {
    header('Location: ../index.php');
    exit();
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    $_SESSION['error'] = "Your cart is empty.";
    header('Location: cart.php');
    exit();
}

// Get user's school_id
$stmt = $conn->prepare("
    SELECT ss.school_id 
    FROM staff_students ss 
    WHERE ss.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user_school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_school) {
    $_SESSION['error'] = "User school information not found.";
    header('Location: cart.php');
    exit();
}

// Get vendor information
$vendor_id = $_SESSION['cart'][0]['vendor_id'];
$stmt = $conn->prepare("
    SELECT v.*, u.username as vendor_name 
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    WHERE v.id = ? AND v.school_id = ?
");
$stmt->execute([$vendor_id, $user_school['school_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    $_SESSION['error'] = "Vendor not found or not affiliated with your school.";
    header('Location: cart.php');
    exit();
}

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Calculate total
        $total_amount = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }
        
        // Generate receipt number
        $receipt_number = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        
        // Get payment method
        $payment_method = $_POST['payment_method'];
        
        // Check credit account if payment method is credit
        $credit_account_id = null;
        if ($payment_method === 'credit') {
            $stmt = $conn->prepare("
                SELECT id, credit_limit, current_balance 
                FROM credit_accounts 
                WHERE user_id = ? AND vendor_id = ? AND status = 'active'
            ");
            $stmt->execute([$_SESSION['user_id'], $vendor_id]);
            $credit_account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$credit_account) {
                throw new Exception("You don't have an active credit account with this vendor.");
            }
            
            $available_credit = $credit_account['credit_limit'] - $credit_account['current_balance'];
            if ($available_credit < $total_amount) {
                throw new Exception("Insufficient credit available. Your available credit is Rs. " . 
                    number_format($available_credit, 2));
            }
            
            $credit_account_id = $credit_account['id'];
        }
        
        // Create order
        $stmt = $conn->prepare("
            INSERT INTO orders (
                user_id, vendor_id, total_amount, payment_method, 
                credit_account_id, receipt_number, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $vendor_id,
            $total_amount,
            $payment_method,
            $credit_account_id,
            $receipt_number
        ]);
        
        $order_id = $conn->lastInsertId();
        
        // Add order items
        $stmt = $conn->prepare("
            INSERT INTO order_items (
                order_id, item_id, quantity, price
            ) VALUES (?, ?, ?, ?)
        ");
        
        foreach ($_SESSION['cart'] as $item) {
            $stmt->execute([
                $order_id,
                $item['item_id'],
                $item['quantity'],
                $item['price']
            ]);
        }
        
        // If credit payment, update credit account and record transaction
        if ($payment_method === 'credit') {
            // Update credit account balance
            $stmt = $conn->prepare("
                UPDATE credit_accounts 
                SET current_balance = current_balance + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$total_amount, $credit_account_id]);
            
            // Record credit transaction
            $stmt = $conn->prepare("
                INSERT INTO credit_transactions (
                    user_id, vendor_id, order_id, amount, 
                    transaction_type, payment_method, created_at
                ) VALUES (?, ?, ?, ?, 'purchase', 'credit', NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $vendor_id,
                $order_id,
                $total_amount
            ]);
        }
        
        // Send notification to vendor
        $stmt = $conn->prepare("
            INSERT INTO notifications (
                user_id, message, status, created_at
            ) VALUES (?, ?, 'unread', NOW())
        ");
        $stmt->execute([
            $vendor['user_id'],
            "New order received: #" . $receipt_number . " - Rs. " . number_format($total_amount, 2)
        ]);
        
        $conn->commit();
        
        // Clear cart
        $_SESSION['cart'] = [];
        
        // Redirect to order confirmation
        header("Location: order_confirmation.php?receipt=" . $receipt_number);
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header('Location: cart.php');
        exit();
    }
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