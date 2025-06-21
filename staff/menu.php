<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit();
}

// Get vendors from the same school as the staff member
$stmt = $conn->prepare("
    SELECT v.id, u.username as vendor_name, v.opening_hours
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    JOIN staff_students ss ON v.school_id = ss.school_id
    WHERE ss.user_id = ? 
    AND ss.role = 'staff'
    AND v.approval_status = 'approved'
    ORDER BY u.username
");
$stmt->execute([$_SESSION['user_id']]);
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no vendors found in the school
if (empty($vendors)) {
    $_SESSION['error_message'] = "No approved vendors found in your school.";
    header('Location: dashboard.php');
    exit();
}

// Get selected vendor's menu items
$selected_vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : $vendors[0]['id'];

// Verify vendor is from same school
$has_access = false;
foreach ($vendors as $vendor) {
    if ($vendor['id'] == $selected_vendor_id) {
        $has_access = true;
        break;
    }
}

if (!$has_access) {
    $_SESSION['error_message'] = "You don't have access to this vendor's menu.";
    header('Location: menu.php');
    exit();
}

// Get menu items for the selected vendor
$stmt = $conn->prepare("
    SELECT 
        mi.*,
        mc.name as category_name,
        mc.description as category_description,
        mi.image_path
    FROM menu_items mi
    LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
    WHERE mi.vendor_id = ?
    AND mi.is_available = 1
    ORDER BY mc.name, mi.name
");
$stmt->execute([$selected_vendor_id]);
$menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <?php if (!empty($menu_items)): ?>
            <?php foreach ($menu_items as $item): ?>
                <div class="menu-item">
                    <?php if ($item['image_path']): ?>
                        <img src="<?php echo htmlspecialchars('../' . $item['image_path']); ?>" 
                             class="menu-item-image" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             onerror="this.src='../assets/images/placeholder-food.jpg'">
                    <?php else: ?>
                        <img src="../assets/images/placeholder-food.jpg" 
                             class="menu-item-image" 
                             alt="No image available">
                    <?php endif; ?>
                    
                    <div class="menu-item-content">
                        <h3 class="menu-item-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                        
                        <?php if ($item['description']): ?>
                            <p class="menu-item-description">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </p>
                        <?php endif; ?>

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
                        
                        <button onclick="addToCart(<?php echo $item['item_id']; ?>)" 
                                class="add-to-cart-btn"
                                data-item-id="<?php echo $item['item_id']; ?>">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info w-100">
                No menu items available for this vendor.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function addToCart(menuItemId) {
    const button = document.querySelector(`button[data-item-id="${menuItemId}"]`);
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    button.disabled = true;

    fetch('add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `menu_item_id=${menuItemId}&quantity=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Added to Cart!',
                text: data.message,
                showConfirmButton: false,
                timer: 1500
            });
            
            const cartBadge = document.querySelector('.cart-count-badge');
            if (cartBadge) {
                cartBadge.textContent = data.cart_count;
                cartBadge.style.display = data.cart_count > 0 ? 'inline' : 'none';
            }
        } else {
            throw new Error(data.message || 'Failed to add item to cart');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = originalText;
    });
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 