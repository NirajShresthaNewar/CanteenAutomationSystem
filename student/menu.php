<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student or staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'staff'])) {
    header('Location: ../index.php');
    exit();
}

// Get affiliated vendors
$vendors = getAffiliatedVendors($_SESSION['user_id']);

// Get selected vendor's menu
$selected_vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;

// If no vendor is selected and we have vendors, select the first one by default
if (!$selected_vendor_id && !empty($vendors)) {
    $selected_vendor_id = $vendors[0]['id'];
}

$menu_items = [];
$categories = [];

if ($selected_vendor_id) {
    // Get categories
    $stmt = $conn->prepare("
        SELECT * FROM menu_categories 
        WHERE vendor_id = ? 
        ORDER BY name
    ");
    $stmt->execute([$selected_vendor_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get menu items
    $stmt = $conn->prepare("
        SELECT mi.*, mc.name as category_name 
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
        WHERE mi.vendor_id = ? AND mi.is_available = 1
        ORDER BY mc.name, mi.name
    ");
    $stmt->execute([$selected_vendor_id]);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Menu';
ob_start();
?>

<style>
.menu-header {
    text-align: center;
    padding: 2rem 0;
    max-width: 800px;
    margin: 0 auto 2rem;
}

.menu-header h1 {
    color: #333;
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.menu-header p {
    color: #666;
    font-size: 1rem;
    line-height: 1.6;
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
    padding: 1rem;
}

.menu-item {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.menu-item:hover {
    transform: translateY(-5px);
}

.menu-item-image {
    width: 100%;
    height: 250px;
    object-fit: cover;
}

.menu-item-content {
    padding: 1.5rem;
}

.menu-item-title {
    font-size: 1.25rem;
    color: #333;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.menu-item-description {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 1rem;
    line-height: 1.4;
}

.menu-item-price {
    color: #e74c3c;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 1rem;
}

.add-to-cart-btn {
    width: 100%;
    padding: 0.8rem;
    background: #f1f1f1;
    border: none;
    border-radius: 4px;
    color: #333;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.add-to-cart-btn:hover {
    background: #e74c3c;
    color: white;
}

.vendor-selector {
    margin-bottom: 2rem;
    text-align: center;
}

.vendor-selector select {
    padding: 0.5rem 2rem;
    font-size: 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
}

.dietary-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    background: #f8f9fa;
    color: #666;
}
</style>

<div class="container">
    <!-- Menu Header -->
    <div class="menu-header">
        <h1>Menu</h1>
        <p>Explore our delicious offerings from various vendors. Each item is prepared with care and quality ingredients to ensure the best dining experience.</p>
    </div>

    <!-- Vendor Selector -->
    <div class="vendor-selector">
        <select class="form-control" onchange="window.location.href='?vendor_id=' + this.value">
            <option value="">Select a Vendor</option>
            <?php foreach ($vendors as $vendor): ?>
                <option value="<?php echo $vendor['id']; ?>" 
                        <?php echo $selected_vendor_id == $vendor['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($vendor['vendor_name']); ?> 
                    (<?php echo htmlspecialchars($vendor['opening_hours']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Menu Grid -->
    <div class="menu-grid">
        <?php if ($selected_vendor_id && !empty($menu_items)): ?>
            <?php foreach ($menu_items as $item): ?>
                <div class="menu-item">
                    <?php if ($item['image_path']): ?>
                        <img src="<?php echo htmlspecialchars('../' . $item['image_path']); ?>" 
                             class="menu-item-image" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <?php else: ?>
                        <img src="../assets/img/no-image.png" 
                             class="menu-item-image" 
                             alt="No image available">
                    <?php endif; ?>
                    
                    <div class="menu-item-content">
                        <h3 class="menu-item-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p class="menu-item-description"><?php echo htmlspecialchars($item['description']); ?></p>
                        
                        <div class="dietary-info">
                            <?php if ($item['is_vegetarian']): ?>
                                <span class="dietary-badge">Vegetarian</span>
                            <?php endif; ?>
                            <?php if ($item['is_vegan']): ?>
                                <span class="dietary-badge">Vegan</span>
                            <?php endif; ?>
                            <?php if ($item['is_gluten_free']): ?>
                                <span class="dietary-badge">Gluten Free</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="menu-item-price">
                            Rs. <?php echo number_format($item['price'], 2); ?>
                        </div>
                        
                        <button class="add-to-cart-btn" 
                                data-item-id="<?php echo $item['item_id']; ?>"
                                data-item-name="<?php echo htmlspecialchars($item['name']); ?>"
                                data-item-price="<?php echo $item['price']; ?>">
                            ADD TO CART
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info w-100">
                <?php if (!$selected_vendor_id): ?>
                    Please select a vendor to view their menu.
                <?php else: ?>
                    No menu items available for this vendor.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.add-to-cart-btn').on('click', function() {
        var button = $(this);
        var itemId = button.data('item-id');
        
        button.prop('disabled', true);
        button.text('Adding...');
        
        $.ajax({
            url: 'cart.php',
            method: 'POST',
            data: {
                action: 'add',
                item_id: itemId,
                quantity: 1
            },
            success: function(response) {
                if (response.success) {
                    button.text('Added!');
                    setTimeout(function() {
                        button.text('ADD TO CART');
                        button.prop('disabled', false);
                    }, 2000);
                } else {
                    button.text('Error');
                    alert(response.message || 'Error adding item to cart');
                    button.prop('disabled', false);
                }
            },
            error: function() {
                button.text('Error');
                button.prop('disabled', false);
                alert('Error adding item to cart');
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 