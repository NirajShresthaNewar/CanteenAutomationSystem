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
                                        <td><strong class="cart-total">Rs. <?php echo number_format($vendor['total'], 2); ?></strong></td>
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
                                        <label for="order_type">Order Type</label>
                                        <select name="order_type" id="order_type" class="form-control" required>
                                            <option value="">Select Order Type</option>
                                            <option value="pickup">Pickup</option>
                                            <option value="delivery">Delivery</option>
                                            <option value="dine_in">Dine In</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Delivery Details (initially hidden) -->
                                <div class="col-12" id="deliveryDetails" style="display: none;">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="delivery_location">Delivery Location*</label>
                                                        <input type="text" class="form-control" id="delivery_location" name="delivery_location" placeholder="Enter delivery location">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="building_name">Building Name</label>
                                                        <input type="text" class="form-control" id="building_name" name="building_name" placeholder="Enter building name">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="floor_number">Floor Number</label>
                                                        <input type="text" class="form-control" id="floor_number" name="floor_number" placeholder="Enter floor number">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="room_number">Room Number</label>
                                                        <input type="text" class="form-control" id="room_number" name="room_number" placeholder="Enter room number">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="contact_number">Contact Number*</label>
                                                        <input type="tel" class="form-control" id="contact_number" name="contact_number" placeholder="Enter contact number">
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="delivery_instructions">Delivery Instructions</label>
                                                        <textarea class="form-control" id="delivery_instructions" name="delivery_instructions" rows="2" placeholder="Enter any special delivery instructions"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dine-in Details (initially hidden) -->
                                <div class="col-12" id="dineInDetails" style="display: none;">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label for="table_number">Table Number*</label>
                                                <input type="text" class="form-control" id="table_number" name="table_number" placeholder="Enter your table number">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select name="payment_method" id="payment_method" class="form-control" required>
                                            <option value="">Select Payment Method</option>
                                            <option value="cash">Cash</option>
                                            <option value="khalti">Khalti</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary float-right" id="checkoutBtn">
                                            <i class="fas fa-shopping-cart"></i> Proceed to Checkout
                                        </button>
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

<!-- Add Khalti JavaScript SDK -->
<script src="https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/2020.12.17.0.0.0/khalti-checkout.iffe.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const orderTypeSelect = document.getElementById('order_type');
    const deliveryDetails = document.getElementById('deliveryDetails');
    const dineInDetails = document.getElementById('dineInDetails');
    
    // Required fields for delivery
    const requiredDeliveryFields = ['delivery_location', 'contact_number'];
    
    function toggleDeliveryDetails() {
        const selectedType = orderTypeSelect.value;
        
        // Hide both sections initially
        deliveryDetails.style.display = 'none';
        dineInDetails.style.display = 'none';
        
        // Reset required fields
        document.querySelectorAll('#deliveryDetails input, #deliveryDetails textarea').forEach(field => {
            field.required = false;
        });
        document.getElementById('table_number').required = false;
        
        // Show relevant section based on selection
        if (selectedType === 'delivery') {
            deliveryDetails.style.display = 'block';
            // Set required fields for delivery
            requiredDeliveryFields.forEach(fieldId => {
                document.getElementById(fieldId).required = true;
            });
        } else if (selectedType === 'dine_in') {
            dineInDetails.style.display = 'block';
            document.getElementById('table_number').required = true;
        }
    }
    
    // Add event listener for order type changes
    orderTypeSelect.addEventListener('change', toggleDeliveryDetails);
    
    // Initial toggle on page load
    toggleDeliveryDetails();

    // Handle form submission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted');
            
            const paymentMethod = this.querySelector('[name="payment_method"]').value;
            console.log('Payment method:', paymentMethod);
            
            const formData = new FormData(this);
            const orderType = formData.get('order_type');
            const vendorId = formData.get('vendor_id');
            
            console.log('Order details:', {
                orderType: orderType,
                vendorId: vendorId
            });
            
            // Validate required fields based on order type
            if (orderType === 'delivery') {
                if (!formData.get('delivery_location') || !formData.get('contact_number')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Please fill in all required delivery details'
                    });
                    return;
                }
            } else if (orderType === 'dine_in' && !formData.get('table_number')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please enter your table number'
                });
                return;
            }
            
            if (paymentMethod === 'khalti') {
                // Get the total amount from the vendor's card
                const card = this.closest('.card');
                if (!card) {
                    console.error('Could not find parent card element');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Could not determine order amount'
                    });
                    return;
                }

                // Find the total element using the class selector
                const totalElement = card.querySelector('.cart-total');
                if (!totalElement) {
                    console.error('Could not find total element');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Could not determine order amount'
                    });
                    return;
                }

                const totalText = totalElement.textContent.trim();
                console.log('Total text:', totalText);
                
                // First remove "Rs. " prefix
                let cleanAmount = totalText.replace('Rs. ', '');
                // Then remove any commas
                cleanAmount = cleanAmount.replace(/,/g, '');
                console.log('Cleaned amount:', cleanAmount);
                
                // Parse as float
                const amountInRupees = parseFloat(cleanAmount);
                console.log('Amount in Rupees:', amountInRupees);
                
                if (isNaN(amountInRupees) || amountInRupees <= 0) {
                    console.error('Invalid amount:', {
                        totalText,
                        cleanAmount,
                        amountInRupees
                    });
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Invalid order amount'
                    });
                    return;
                }

                // Convert Rupees to Paisa (1 Rupee = 100 Paisa)
                const amountInPaisa = Math.round(amountInRupees * 100);
                console.log('Amount in Paisa:', amountInPaisa);

                // Ensure minimum amount requirement (100 paisa = 1 rupee)
                if (amountInRupees < 1) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Minimum order amount for Khalti payment is Rs. 1'
                    });
                    return;
                }
                
                // Convert FormData to object
                const formDataObj = {};
                formData.forEach((value, key) => {
                    formDataObj[key] = value;
                });
                
                // Initiate Khalti payment with amount in paisa
                initiateKhaltiPayment(amountInPaisa, formDataObj.vendor_id, formDataObj.order_type, formDataObj);
            } else {
                // Handle cash payment
                this.submit();
            }
        });
    });
});

function initiateKhaltiPayment(amount, vendorId, orderType, formData) {
    // First verify session is active
    fetch('../auth/check_session.php')
    .then(response => response.json())
    .then(data => {
        // If session valid, proceed with payment
        const orderId = 'ORDER-' + vendorId + '-' + Date.now();
        
        // Show loading indicator
        Swal.fire({
            title: 'Initiating Payment',
            text: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Set payment flow flag in session first
        fetch('../payment/set_payment_flow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ start_flow: true })
        })
        .then(response => response.json())
        .then(flowData => {
            if (!flowData.success) {
                throw new Error('Failed to initialize payment flow');
            }
            
            // Now make request to khalti_handler.php
            return fetch('../payment/khalti_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    amount: amount,
                    purchase_order_id: orderId,
                    purchase_order_name: 'Food Order',
                    customer_info: {
                        name: '<?php echo $_SESSION["username"]; ?>',
                        email: '<?php echo $_SESSION["email"] ?? ""; ?>',
                        phone: formData.contact_number || '9800000001'
                    },
                    order_details: {
                        vendor_id: vendorId,
                        order_type: orderType,
                        form_data: formData
                    }
                })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Payment URL received:', data.payment_url);
                // Store payment info in localStorage for recovery
                localStorage.setItem('khalti_payment_pending', JSON.stringify({
                    amount: amount,
                    vendor_id: vendorId,
                    order_type: orderType,
                    timestamp: new Date().getTime()
                }));

                // Redirect to Khalti payment page
                window.location.href = data.payment_url;
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Payment Failed',
                    text: data.message || 'Failed to initiate payment'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to initiate payment. Please try again.'
            });
        });
    })
    .catch(error => {
        console.error('Session check failed:', error);
        Swal.fire({
            icon: 'error',
            title: 'Session Error',
            text: 'Your session may have expired. Please refresh and try again.'
        });
    });
}
</script>

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