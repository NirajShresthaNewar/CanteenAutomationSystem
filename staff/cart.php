<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit();
}

// Get cart items grouped by vendor
$stmt = $conn->prepare("
    SELECT 
        ci.*, mi.name as item_name, mi.price,
        v.id as vendor_id, u.username as vendor_name,
        ss.id as staff_id,
        ca.credit_limit, ca.current_balance,
        ca.status as credit_status
    FROM cart_items ci
    JOIN menu_items mi ON ci.menu_item_id = mi.item_id
    JOIN vendors v ON mi.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    JOIN staff_students ss ON (
        ss.school_id = v.school_id AND 
        ss.user_id = ? AND 
        ss.role = 'staff'
    )
    LEFT JOIN credit_accounts ca ON (
        ca.user_id = ci.user_id AND 
        ca.vendor_id = v.id AND 
        ca.status = 'active'
    )
    WHERE ci.user_id = ?
    ORDER BY v.id, mi.name
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group items by vendor
$vendors = [];
foreach ($cart_items as $item) {
    if (!isset($vendors[$item['vendor_id']])) {
        $vendors[$item['vendor_id']] = [
            'name' => $item['vendor_name'],
            'items' => [],
            'total' => 0,
            'staff_id' => $item['staff_id'],
            'credit_limit' => $item['credit_limit'],
            'current_balance' => $item['current_balance'],
            'credit_status' => $item['credit_status']
        ];
    }
    $vendors[$item['vendor_id']]['items'][] = $item;
    $vendors[$item['vendor_id']]['total'] += $item['price'] * $item['quantity'];
}

// Store cart data in session for payment processing
if (!empty($vendors)) {
    foreach ($vendors as $vendor_id => $vendor) {
        $_SESSION['cart_data'][$vendor_id] = [
            'vendor_name' => $vendor['name'],
            'total_amount' => $vendor['total'],
            'staff_id' => $vendor['staff_id'],
            'items' => $vendor['items']
        ];
    }
}

$page_title = 'Shopping Cart';
ob_start();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Shopping Cart</h1>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (empty($vendors)): ?>
            <div class="alert alert-info">
                <h5><i class="icon fas fa-info"></i> Your cart is empty!</h5>
                <p>Browse our menu to add items to your cart.</p>
                <a href="menu.php" class="btn btn-primary">
                    <i class="fas fa-utensils"></i> View Menu
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($vendors as $vendor_id => $vendor): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-store"></i> <?php echo htmlspecialchars($vendor['name']); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <form action="checkout.php" method="POST" class="cart-form">
                            <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                            <input type="hidden" name="staff_id" value="<?php echo $vendor['staff_id']; ?>">
                            
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vendor['items'] as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                                                <td>
                                                    <div class="input-group" style="width: 120px;">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm quantity-btn" data-action="decrease" data-cart-id="<?php echo $item['id']; ?>">-</button>
                                                        <input type="number" class="form-control form-control-sm text-center quantity-input" value="<?php echo $item['quantity']; ?>" min="1" data-cart-id="<?php echo $item['id']; ?>">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm quantity-btn" data-action="increase" data-cart-id="<?php echo $item['id']; ?>">+</button>
                                                    </div>
                                                </td>
                                                <td>Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remove-item" data-cart-id="<?php echo $item['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                            <td><strong>Rs. <?php echo number_format($vendor['total'], 2); ?></strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="order_type">Order Type</label>
                                        <select name="order_type" class="form-control order-type-select" required>
                                            <option value="">Select Order Type</option>
                                            <option value="pickup">Pickup</option>
                                            <option value="delivery">Delivery</option>
                                            <option value="dine_in">Dine In</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select name="payment_method" class="form-control" required>
                                            <option value="">Select Payment Method</option>
                                            <?php if ($vendor['credit_status'] === 'active' && 
                                                     ($vendor['credit_limit'] - $vendor['current_balance']) >= $vendor['total']): ?>
                                                <option value="credit">Credit (Available: Rs. <?php 
                                                    echo number_format($vendor['credit_limit'] - $vendor['current_balance'], 2); 
                                                ?>)</option>
                                            <?php endif; ?>
                                            <option value="khalti">Khalti</option>
                                            <option value="cash">Cash</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="delivery-details" style="display: none;">
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Delivery Details</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="delivery_location">Delivery Location*</label>
                                                    <input type="text" name="delivery_location" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="contact_number">Contact Number*</label>
                                                    <input type="tel" name="contact_number" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="delivery_instructions">Delivery Instructions</label>
                                                    <textarea name="delivery_instructions" class="form-control" rows="2"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="dine-in-details" style="display: none;">
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <h5 class="card-title">Dine-in Details</h5>
                                        <div class="form-group">
                                            <label for="table_number">Table Number*</label>
                                            <input type="text" name="table_number" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-right mt-3">
                                <button type="submit" class="btn btn-primary" id="checkoutBtn">
                                    <i class="fas fa-shopping-cart"></i> Proceed to Checkout
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="text-right mt-3">
                <button type="button" class="btn btn-danger" id="clear-cart">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
$(document).ready(function() {
    // Show/hide delivery details based on order type
    $('.order-type-select').change(function() {
        const orderType = $(this).val();
        const form = $(this).closest('form');
        
        form.find('.delivery-details').hide();
        form.find('.dine-in-details').hide();
        
        if (orderType === 'delivery') {
            form.find('.delivery-details').show();
        } else if (orderType === 'dine_in') {
            form.find('.dine-in-details').show();
        }
    });

    // Handle quantity updates
    $('.quantity-btn').click(function() {
        const action = $(this).data('action');
        const cartId = $(this).data('cart-id');
        const input = $(this).closest('.input-group').find('.quantity-input');
        let quantity = parseInt(input.val());

        if (action === 'increase') {
            quantity++;
        } else if (action === 'decrease' && quantity > 1) {
            quantity--;
        }

        updateCartItemQuantity(cartId, quantity);
    });

    // Handle quantity input changes
    $('.quantity-input').change(function() {
        const cartId = $(this).data('cart-id');
        const quantity = parseInt($(this).val());
        if (quantity >= 1) {
            updateCartItemQuantity(cartId, quantity);
        }
    });

    // Function to update cart item quantity
    function updateCartItemQuantity(cartId, quantity) {
        $.post('update_cart.php', {
            action: 'update_quantity',
            cart_id: cartId,
            quantity: quantity
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            }
        })
        .fail(function(xhr) {
            alert('Error updating quantity');
        });
    }

    // Handle remove item
    $('.remove-item').click(function() {
        const cartId = $(this).data('cart-id');
        if (confirm('Are you sure you want to remove this item?')) {
            $.post('remove_from_cart.php', {
                cart_id: cartId
            })
            .done(function(response) {
                location.reload();
            })
            .fail(function(xhr) {
                alert('Error removing item');
            });
        }
    });

    // Handle clear cart
    $('#clear-cart').click(function() {
        if (confirm('Are you sure you want to clear your cart?')) {
            $.post('clear_cart.php')
            .done(function(response) {
                location.reload();
            })
            .fail(function(xhr) {
                alert('Error clearing cart');
            });
        }
    });

});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function() {
            checkoutBtn.disabled = true;
            checkoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
            // Optionally, submit the form if not already submitting
            checkoutBtn.form.submit();
        });
    }
});
</script>
<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 
?> 