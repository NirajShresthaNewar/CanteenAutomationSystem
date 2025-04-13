<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student or staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'staff'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                if (isset($_POST['item_id'], $_POST['quantity'])) {
                    $item_id = (int)$_POST['item_id'];
                    $quantity = (int)$_POST['quantity'];
                    
                    // Get item details
                    $stmt = $conn->prepare("
                        SELECT mi.*, v.id as vendor_id 
                        FROM menu_items mi
                        JOIN vendors v ON mi.vendor_id = v.id
                        WHERE mi.item_id = ? AND mi.is_available = 1
                    ");
                    $stmt->execute([$item_id]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($item) {
                        // Check if adding item from same vendor
                        if (!empty($_SESSION['cart']) && $_SESSION['cart'][0]['vendor_id'] != $item['vendor_id']) {
                            $response['message'] = 'You can only order from one vendor at a time.';
                        } else {
                            // Add to cart
                            $_SESSION['cart'][] = [
                                'item_id' => $item_id,
                                'name' => $item['name'],
                                'price' => $item['price'],
                                'quantity' => $quantity,
                                'vendor_id' => $item['vendor_id']
                            ];
                            $response['success'] = true;
                            $response['message'] = 'Item added to cart';
                        }
                    } else {
                        $response['message'] = 'Item not found or not available';
                    }
                }
                break;
                
            case 'remove':
                if (isset($_POST['index'])) {
                    $index = (int)$_POST['index'];
                    if (isset($_SESSION['cart'][$index])) {
                        array_splice($_SESSION['cart'], $index, 1);
                        $response['success'] = true;
                        $response['message'] = 'Item removed from cart';
                    }
                }
                break;
                
            case 'update':
                if (isset($_POST['index'], $_POST['quantity'])) {
                    $index = (int)$_POST['index'];
                    $quantity = (int)$_POST['quantity'];
                    if (isset($_SESSION['cart'][$index]) && $quantity > 0) {
                        $_SESSION['cart'][$index]['quantity'] = $quantity;
                        $response['success'] = true;
                        $response['message'] = 'Cart updated';
                    }
                }
                break;
                
            case 'clear':
                $_SESSION['cart'] = [];
                $response['success'] = true;
                $response['message'] = 'Cart cleared';
                break;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get available credit accounts for payment options
$stmt = $conn->prepare("
    SELECT 
        ca.id, 
        ca.vendor_id, 
        ca.credit_limit, 
        ca.current_balance,
        u.username as vendor_name
    FROM credit_accounts ca
    JOIN vendors v ON ca.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE ca.user_id = ? AND ca.status = 'active'
    ORDER BY u.username
");
$stmt->execute([$user_id]);
$credit_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group credit accounts by vendor
$vendor_credit_accounts = [];
foreach ($credit_accounts as $account) {
    $vendor_credit_accounts[$account['vendor_id']] = $account;
}

// Calculate cart total
$cart_total = 0;
$cart_vendor_id = 0;
$cart_vendor_name = '';

if (!empty($_SESSION['cart'])) {
    // Get vendor details for the first item (assuming all from same vendor)
    $first_item = reset($_SESSION['cart']);
    $cart_vendor_id = $first_item['vendor_id'];
    
    $stmt = $conn->prepare("
        SELECT u.username as vendor_name 
        FROM vendors v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.id = ?
    ");
    $stmt->execute([$cart_vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    $cart_vendor_name = $vendor ? $vendor['vendor_name'] : 'Unknown Vendor';
    
    // Calculate total
    foreach ($_SESSION['cart'] as $item) {
        $cart_total += $item['price'] * $item['quantity'];
    }
}

// Check if user has credit account with the vendor in the cart
$has_credit_account = false;
$available_credit = 0;

if ($cart_vendor_id && isset($vendor_credit_accounts[$cart_vendor_id])) {
    $has_credit_account = true;
    $account = $vendor_credit_accounts[$cart_vendor_id];
    $available_credit = $account['credit_limit'] - $account['current_balance'];
}

$page_title = 'Shopping Cart';
ob_start();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Shopping Cart</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($_SESSION['cart'])): ?>
                        <div class="alert alert-info">
                            Your cart is empty. <a href="menu.php">Browse menu</a> to add items.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total = 0;
                                    foreach ($_SESSION['cart'] as $index => $item): 
                                        $item_total = $item['price'] * $item['quantity'];
                                        $total += $item_total;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                                            <td>
                                                <input type="number" class="form-control quantity-input" 
                                                       value="<?php echo $item['quantity']; ?>" 
                                                       min="1" 
                                                       data-index="<?php echo $index; ?>"
                                                       style="width: 80px;">
                                            </td>
                                            <td>Rs. <?php echo number_format($item_total, 2); ?></td>
                                            <td>
                                                <button class="btn btn-danger btn-sm remove-item" 
                                                        data-index="<?php echo $index; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                        <td colspan="2"><strong>Rs. <?php echo number_format($total, 2); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-danger clear-cart">Clear Cart</button>
                            <a href="menu.php" class="btn btn-secondary">Continue Shopping</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <?php if (!empty($_SESSION['cart'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Checkout</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Vendor:</strong> <?php echo htmlspecialchars($cart_vendor_name); ?></p>
                        <p><strong>Total Amount:</strong> Rs. <?php echo number_format($cart_total, 2); ?></p>
                        
                        <form action="checkout.php" method="POST">
                            <div class="form-group">
                                <label for="payment_method">Payment Method</label>
                                <select class="form-control" id="payment_method" name="payment_method" required>
                                    <option value="cash">Cash</option>
                                    <option value="esewa">eSewa</option>
                                    <?php if ($has_credit_account && $available_credit >= $cart_total): ?>
                                        <option value="credit">Credit (Available: Rs. <?php echo number_format($available_credit, 2); ?>)</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">Proceed to Checkout</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Update quantity
    $('.quantity-input').on('change', function() {
        var index = $(this).data('index');
        var quantity = $(this).val();
        
        $.ajax({
            url: 'cart.php',
            method: 'POST',
            data: {
                action: 'update',
                index: index,
                quantity: quantity
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Error updating cart');
                }
            }
        });
    });
    
    // Remove item
    $('.remove-item').on('click', function() {
        var index = $(this).data('index');
        
        $.ajax({
            url: 'cart.php',
            method: 'POST',
            data: {
                action: 'remove',
                index: index
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Error removing item');
                }
            }
        });
    });
    
    // Clear cart
    $('.clear-cart').on('click', function() {
        if (confirm('Are you sure you want to clear your cart?')) {
            $.ajax({
                url: 'cart.php',
                method: 'POST',
                data: {
                    action: 'clear'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || 'Error clearing cart');
                    }
                }
            });
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 