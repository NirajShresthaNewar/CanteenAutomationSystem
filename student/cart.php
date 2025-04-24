<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Get cart items grouped by vendor
$stmt = $conn->prepare("
    SELECT 
        ci.*, mi.name as item_name, mi.price,
        v.id as vendor_id, u.username as vendor_name
    FROM cart_items ci
    JOIN menu_items mi ON ci.menu_item_id = mi.item_id
    JOIN vendors v ON mi.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE ci.user_id = ?
    ORDER BY v.id, mi.name
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group items by vendor
$vendors = [];
foreach ($cart_items as $item) {
    if (!isset($vendors[$item['vendor_id']])) {
        $vendors[$item['vendor_id']] = [
            'name' => $item['vendor_name'],
            'items' => [],
            'total' => 0
        ];
    }
    $vendors[$item['vendor_id']]['items'][] = $item;
    $vendors[$item['vendor_id']]['total'] += $item['price'] * $item['quantity'];
}

// Handle AJAX requests for updating quantities
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        switch ($_POST['action']) {
            case 'update_quantity':
                if (!isset($_POST['cart_id'], $_POST['quantity']) || $_POST['quantity'] < 1) {
                    throw new Exception("Invalid parameters");
                }
                $stmt = $conn->prepare("
                    UPDATE cart_items 
                    SET quantity = ? 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $_POST['quantity'],
                    $_POST['cart_id'],
                    $_SESSION['user_id']
                ]);
                echo json_encode(['success' => true]);
                break;

            case 'remove_item':
                if (!isset($_POST['cart_id'])) {
                    throw new Exception("Invalid parameters");
                }
                $stmt = $conn->prepare("
                    DELETE FROM cart_items 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $_POST['cart_id'],
                    $_SESSION['user_id']
                ]);
                echo json_encode(['success' => true]);
                break;

            case 'clear_cart':
                $stmt = $conn->prepare("
                    DELETE FROM cart_items 
                    WHERE user_id = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                echo json_encode(['success' => true]);
                break;

            default:
                throw new Exception("Invalid action");
        }
        exit();
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

$pageTitle = 'Shopping Cart';

// Start output buffering
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
                        <div class="table-responsive">
                            <table class="table table-bordered">
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
                                                    <button class="btn btn-outline-secondary btn-sm quantity-btn" data-action="decrease" data-cart-id="<?php echo $item['id']; ?>">-</button>
                                                    <input type="number" class="form-control form-control-sm text-center quantity-input" value="<?php echo $item['quantity']; ?>" min="1" data-cart-id="<?php echo $item['id']; ?>">
                                                    <button class="btn btn-outline-secondary btn-sm quantity-btn" data-action="increase" data-cart-id="<?php echo $item['id']; ?>">+</button>
                                                </div>
                                            </td>
                                            <td>Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                            <td>
                                                <button class="btn btn-danger btn-sm remove-item" data-cart-id="<?php echo $item['id']; ?>">
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

                        <form action="checkout.php" method="POST" class="mt-3">
                            <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select name="payment_method" id="payment_method" class="form-control" required>
                                            <option value="">Select Payment Method</option>
                                            <option value="cash">Cash</option>
                                            <option value="esewa">eSewa</option>
                                            <option value="credit">Credit</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-shopping-cart"></i> Proceed to Checkout
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="text-right mb-4">
                <button id="clear-cart" class="btn btn-warning">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
// Get the buffered content
$content = ob_get_clean();

// Add page-specific scripts
$additionalScripts = '
<script>
    $(document).ready(function() {
        // Update quantity
        $(".quantity-btn").click(function() {
            const input = $(this).closest(".input-group").find(".quantity-input");
            const cartId = input.data("cart-id");
            let quantity = parseInt(input.val());
            
            if ($(this).data("action") === "decrease") {
                quantity = Math.max(1, quantity - 1);
            } else {
                quantity += 1;
            }
            
            updateQuantity(cartId, quantity, input);
        });

        $(".quantity-input").change(function() {
            const cartId = $(this).data("cart-id");
            const quantity = Math.max(1, parseInt($(this).val()) || 1);
            updateQuantity(cartId, quantity, $(this));
        });

        function updateQuantity(cartId, quantity, input) {
            $.post("cart.php", {
                action: "update_quantity",
                cart_id: cartId,
                quantity: quantity
            })
            .done(function() {
                input.val(quantity);
                location.reload();
            })
            .fail(function(response) {
                alert(response.responseJSON?.error || "Error updating quantity");
                location.reload();
            });
        }

        // Remove item
        $(".remove-item").click(function() {
            if (!confirm("Are you sure you want to remove this item?")) {
                return;
            }
            
            const cartId = $(this).data("cart-id");
            $.post("cart.php", {
                action: "remove_item",
                cart_id: cartId
            })
            .done(function() {
                location.reload();
            })
            .fail(function(response) {
                alert(response.responseJSON?.error || "Error removing item");
            });
        });

        // Clear cart
        $("#clear-cart").click(function() {
            if (!confirm("Are you sure you want to clear your entire cart?")) {
                return;
            }
            
            $.post("cart.php", {
                action: "clear_cart"
            })
            .done(function() {
                location.reload();
            })
            .fail(function(response) {
                alert(response.responseJSON?.error || "Error clearing cart");
            });
        });
    });
</script>';

// Include the layout template
require_once '../includes/layout.php';
?> 