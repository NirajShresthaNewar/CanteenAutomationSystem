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

// Function to get popular items based on current month's orders for affiliated vendors only
function getPopularItems($conn, $affiliated_vendor_ids, $limit = 3) {
    if (empty($affiliated_vendor_ids)) {
        return [];
    }
    
    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($affiliated_vendor_ids) - 1) . '?';
    
    $query = "
        SELECT 
            mi.item_id,
            mi.name,
            mi.description,
            mi.price,
            mi.image_path,
            mi.is_vegetarian,
            mi.vendor_id,
            COUNT(oi.id) as order_count
        FROM menu_items mi
        JOIN order_items oi ON mi.item_id = oi.menu_item_id
        JOIN orders o ON oi.order_id = o.id
        WHERE mi.is_available = 1
        AND mi.vendor_id IN ($placeholders)
        AND MONTH(o.order_date) = MONTH(CURRENT_DATE())
        AND YEAR(o.order_date) = YEAR(CURRENT_DATE())
        GROUP BY mi.item_id, mi.name, mi.description, mi.price, mi.image_path, mi.is_vegetarian, mi.vendor_id
        ORDER BY order_count DESC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($query);
    
    // Bind vendor IDs
    $param_index = 1;
    foreach ($affiliated_vendor_ids as $vendor_id) {
        $stmt->bindValue($param_index++, $vendor_id, PDO::PARAM_INT);
    }
    
    // Bind limit
    $stmt->bindValue($param_index, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get affiliated vendor IDs for filtering popular items
$affiliated_vendor_ids = array_column($vendors, 'id');

// Get popular items from affiliated vendors only
$popular_items = getPopularItems($conn, $affiliated_vendor_ids);

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

.recommendations-section {
    background: #f8f9fa;
    padding: 20px 0;
    margin-bottom: 30px;
    border-radius: 8px;
}
.recommendation-title {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}
.recommendation-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
    margin-bottom: 20px;
    position: relative;
    overflow: hidden;
}
.recommendation-card:hover {
    transform: translateY(-5px);
}
.recommendation-img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-radius: 8px 8px 0 0;
}
.recommendation-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ff4757;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    z-index: 1;
}
.order-count {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
}
</style>

<div class="container">
    <!-- Menu Header -->
    <div class="menu-header">
        <h1>Menu</h1>
        <p>Explore our delicious offerings from various vendors. Each item is prepared with care and quality ingredients to ensure the best dining experience.</p>
                    </div>

    <!-- Popular Items Section -->
    <?php if (!empty($popular_items)): ?>
    <div class="recommendations-section">
        <h3 class="recommendation-title">Most Popular Items This Month</h3>
        <div class="row">
            <?php foreach ($popular_items as $item): ?>
                <div class="col-md-4">
                    <div class="recommendation-card">
                        <span class="recommendation-badge">Popular Choice!</span>
                        <img src="<?php echo '../' . htmlspecialchars($item['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                             class="recommendation-img">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($item['description']); ?></p>
                            <?php if ($item['is_vegetarian']): ?>
                                <span class="badge badge-success">Vegetarian</span>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="text-danger">Rs. <?php echo number_format($item['price'], 2); ?></span>
                                <button onclick="addToCart(<?php echo $item['item_id']; ?>, <?php echo $item['vendor_id']; ?>)" 
                                        class="btn btn-primary btn-sm">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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
                        
                        <button onclick="addToCart(<?php echo $item['item_id']; ?>, <?php echo $item['vendor_id']; ?>)" 
                                class="add-to-cart-btn"
                                data-item-id="<?php echo $item['item_id']; ?>"
                                data-vendor-id="<?php echo $item['vendor_id']; ?>">
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
function addToCart(itemId, vendorId) {
    const button = document.querySelector(`button[data-item-id="${itemId}"]`);
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    button.disabled = true;
    
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `menu_item_id=${itemId}&vendor_id=${vendorId}&quantity=1`
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