<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Verify POST request with required data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['vendor_id'], $_POST['payment_method'], $_POST['order_type'])) {
    $_SESSION['error'] = "Invalid checkout request. Please fill in all required fields.";
    header('Location: cart.php');
    exit();
}

$vendor_id = $_POST['vendor_id'];
$payment_method = $_POST['payment_method'];
$order_type = $_POST['order_type'];

// Validate delivery details based on order type
if ($order_type === 'delivery') {
    if (empty($_POST['delivery_location']) || empty($_POST['contact_number'])) {
        $_SESSION['error'] = "Please provide delivery location and contact number.";
        header('Location: cart.php');
        exit();
    }
}

if ($order_type === 'dine_in' && empty($_POST['table_number'])) {
    $_SESSION['error'] = "Please provide table number for dine-in orders.";
    header('Location: cart.php');
    exit();
}

try {
    // Validate that user has access to this vendor (same school)
    $stmt = $conn->prepare("
        SELECT ss.school_id 
        FROM staff_students ss
        JOIN vendors v ON ss.school_id = v.school_id
        WHERE ss.user_id = ? AND ss.role = 'student' AND v.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);
    $has_access = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$has_access) {
        throw new Exception("You do not have access to this vendor.");
    }

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

    // Calculate total and check subscription
    $total_amount = 0;
    $discount_amount = 0;

    // Check for active subscription
    $stmt = $conn->prepare("
        SELECT us.*, sp.discount_percentage
        FROM user_subscriptions us
        JOIN subscription_plans sp ON us.plan_id = sp.id
        WHERE us.user_id = ? 
        AND sp.vendor_id = ?
        AND us.status = 'active'
        AND us.start_date <= NOW()
        AND us.end_date >= NOW()
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get menu categories for discount eligibility
    $stmt = $conn->prepare("
        SELECT category_id
        FROM menu_items
        WHERE item_id = ?
    ");

    foreach ($cart_items as $item) {
        $item_total = $item['price'] * $item['quantity'];
        $total_amount += $item_total;
        
        // Apply discount if subscription exists and item is eligible
        if ($subscription) {
            $stmt->execute([$item['menu_item_id']]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Only apply discount to main meals (categories 1 and 2)
            if (in_array($category['category_id'], [1, 2])) {
                $item_discount = $item_total * ($subscription['discount_percentage'] / 100);
                $discount_amount += $item_discount;
    }
        }
    }

    // Apply discount to total
    $final_total = $total_amount - $discount_amount;

    // Get student ID
    $stmt = $conn->prepare("SELECT id FROM staff_students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student record not found.");
    }

    // Get vendor details
    $stmt = $conn->prepare("
        SELECT v.*, u.username as vendor_name 
        FROM vendors v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    // Start transaction
    $conn->beginTransaction();

    // Generate receipt number
    $receipt_number = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

    // Create order
    $stmt = $conn->prepare("
        INSERT INTO orders (
            receipt_number, user_id, customer_id, vendor_id,
            total_amount, payment_method, payment_status,
            order_type
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?,
            ?
        )
    ");
    $stmt->execute([
        $receipt_number,
        $_SESSION['user_id'],
        $student['id'],
        $vendor_id,
        $final_total,
        $payment_method,
        'pending',
        $order_type
    ]);

    $order_id = $conn->lastInsertId();

    // Insert order delivery details
    $stmt = $conn->prepare("
        INSERT INTO order_delivery_details (
            order_id,
            order_type,
            table_number,
            delivery_location,
            building_name,
            floor_number,
            room_number,
            delivery_instructions,
            contact_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $order_id,
        $order_type,
        $_POST['table_number'] ?? null,
        $_POST['delivery_location'] ?? null,
        $_POST['building_name'] ?? null,
        $_POST['floor_number'] ?? null,
        $_POST['room_number'] ?? null,
        $_POST['delivery_instructions'] ?? null,
        $_POST['contact_number'] ?? null
    ]);

    // Create order items
    $stmt = $conn->prepare("
        INSERT INTO order_items (
            order_id, menu_item_id, quantity,
            unit_price, subtotal
        ) VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($cart_items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $stmt->execute([
            $order_id,
            $item['menu_item_id'],
            $item['quantity'],
            $item['price'],
            $subtotal
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

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Checkout</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="cart.php">Cart</a></li>
                    <li class="breadcrumb-item active">Checkout</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
<div class="container-fluid">
        <div class="container mt-4">
    <div class="row">
                <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                            <h4>Checkout</h4>
                </div>
                <div class="card-body">
                            <form id="checkoutForm" method="POST" action="process_order.php">
                                <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                                
                                <!-- Order Type Selection -->
                                <div class="form-group mb-4">
                                    <label for="order_type"><strong>Order Type</strong></label>
                                    <select class="form-control" id="order_type" name="order_type" required>
                                        <option value="">Select Order Type</option>
                                        <option value="delivery">Delivery</option>
                                        <option value="dine_in">Dine In</option>
                                    </select>
                                </div>

                                <!-- Delivery Details Section -->
                                <div id="deliveryDetails" style="display: none;">
                                    <h5 class="mb-3">Delivery Details</h5>
                                    
                                    <div class="form-group mb-3">
                                        <label for="delivery_location">Delivery Location*</label>
                                        <input type="text" class="form-control" id="delivery_location" name="delivery_location">
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="building_name">Building Name</label>
                                        <input type="text" class="form-control" id="building_name" name="building_name">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="floor_number">Floor Number</label>
                                                <input type="text" class="form-control" id="floor_number" name="floor_number">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="room_number">Room Number</label>
                                                <input type="text" class="form-control" id="room_number" name="room_number">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="contact_number">Contact Number*</label>
                                        <input type="tel" class="form-control" id="contact_number" name="contact_number">
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="delivery_instructions">Delivery Instructions</label>
                                        <textarea class="form-control" id="delivery_instructions" name="delivery_instructions" rows="2"></textarea>
                                    </div>
                                </div>

                                <!-- Dine-in Details Section -->
                                <div id="dineInDetails" style="display: none;">
                                    <h5 class="mb-3">Dine-in Details</h5>
                                    
                                    <div class="form-group mb-3">
                                        <label for="table_number">Table Number*</label>
                                        <input type="text" class="form-control" id="table_number" name="table_number">
                                    </div>
                                </div>

                                <!-- Payment Method Selection -->
                                <div class="form-group mb-4">
                                    <label for="payment_method"><strong>Payment Method</strong></label>
                                    <select class="form-control" id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <option value="credit">Credit Account</option>
                                        <option value="esewa">Online Payment (eSewa)</option>
                                        <option value="cash">Cash on Delivery</option>
                                    </select>
                                </div>

                                <!-- Order Summary -->
                                <div class="order-summary mb-4">
                                    <h5>Order Summary</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                                    <th class="text-right">Price</th>
                                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                                <?php foreach ($cart_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                                        <td class="text-right">₹<?php echo number_format($item['price'], 2); ?></td>
                                                        <td class="text-right">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                                    <th colspan="3" class="text-right">Total Amount:</th>
                                                    <th class="text-right">₹<?php echo number_format($total_amount, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
            </div>
        </div>
        
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary">Place Order</button>
                                    <a href="cart.php" class="btn btn-secondary">Back to Cart</a>
                </div>
                            </form>
                            </div>
                        </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const orderType = document.getElementById('order_type');
    const deliveryDetails = document.getElementById('deliveryDetails');
    const dineInDetails = document.getElementById('dineInDetails');
    
    // Required fields for delivery
    const deliveryFields = ['delivery_location', 'contact_number'];
    // Required fields for dine-in
    const dineInFields = ['table_number'];
    
    function toggleRequiredFields(fields, required) {
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.required = required;
            }
        });
    }

    orderType.addEventListener('change', function() {
        if (this.value === 'delivery') {
            deliveryDetails.style.display = 'block';
            dineInDetails.style.display = 'none';
            toggleRequiredFields(deliveryFields, true);
            toggleRequiredFields(dineInFields, false);
        } else if (this.value === 'dine_in') {
            deliveryDetails.style.display = 'none';
            dineInDetails.style.display = 'block';
            toggleRequiredFields(deliveryFields, false);
            toggleRequiredFields(dineInFields, true);
        } else {
            deliveryDetails.style.display = 'none';
            dineInDetails.style.display = 'none';
            toggleRequiredFields(deliveryFields, false);
            toggleRequiredFields(dineInFields, false);
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 