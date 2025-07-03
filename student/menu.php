<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student or staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'staff'])) {
    header('Location: ../index.php');
    exit();
}

// Function to get popular items based on current month's orders
function getPopularItems($conn, $limit = 3) {
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
        AND MONTH(o.order_date) = MONTH(CURRENT_DATE())
        AND YEAR(o.order_date) = YEAR(CURRENT_DATE())
        GROUP BY mi.item_id, mi.name, mi.description, mi.price, mi.image_path, mi.is_vegetarian, mi.vendor_id
        ORDER BY order_count DESC
        LIMIT :limit
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get affiliated vendors
$vendors = getAffiliatedVendors($_SESSION['user_id']);

// Get selected vendor's menu
$selected_vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;

// If no vendor is selected and we have vendors, select the first one by default
if (!$selected_vendor_id && !empty($vendors)) {
    $selected_vendor_id = $vendors[0]['id'];
}

// Get popular items
$popular_items = getPopularItems($conn);

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
    position: relative;
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

/* New styles for popular items */
.popular-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: #e74c3c;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    z-index: 1;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.popular-section {
    margin-bottom: 3rem;
}

.popular-section-title {
    text-align: center;
    color: #333;
    font-size: 1.8rem;
    margin-bottom: 2rem;
    position: relative;
    padding-bottom: 1rem;
}

.popular-section-title:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 3px;
    background: #e74c3c;
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
    <div class="popular-section">
        <h2 class="popular-section-title">Most Popular This Month</h2>
        <div class="menu-grid">
            <?php foreach ($popular_items as $item): ?>
                <div class="menu-item">
                    <span class="popular-badge">Popular Choice!</span>
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
                        <?php if ($item['is_vegetarian']): ?>
                            <span class="dietary-badge">Vegetarian</span>
                        <?php endif; ?>
                        <div class="menu-item-price">Rs. <?php echo number_format($item['price'], 2); ?></div>
                        <button class="add-to-cart-btn" onclick="addToCart(<?php echo $item['item_id']; ?>, <?php echo $item['vendor_id']; ?>, '<?php echo addslashes($item['name']); ?>')">
                            Add to Cart
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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
                            <?php if ($item['is_vegetarian']): ?>
                                <span class="dietary-badge">Vegetarian</span>
                            <?php endif; ?>
                        <div class="menu-item-price">Rs. <?php echo number_format($item['price'], 2); ?></div>
                        <button class="add-to-cart-btn" onclick="addToCart(<?php echo $item['item_id']; ?>, <?php echo $selected_vendor_id; ?>, '<?php echo addslashes($item['name']); ?>')">
                            Add to Cart
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
function addToCart(itemId, vendorId, itemName) {
    const button = document.querySelector(`button[onclick="addToCart(${itemId}, ${vendorId}, '${itemName}')"]`);
    const originalText = button.innerHTML;
    button.innerHTML = 'Adding...';
    button.disabled = true;

    fetch('add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `menu_item_id=${itemId}&vendor_id=${vendorId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count in sidebar if it exists
            const cartBadge = document.querySelector('.cart-count');
            if (cartBadge) {
                cartBadge.textContent = data.cart_count;
            }
            button.innerHTML = '<i class="fas fa-check"></i> Added';
            
            // Create and show the success message
            const alertDiv = document.createElement('div');
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '20px';
            alertDiv.style.right = '20px';
            alertDiv.style.backgroundColor = '#333';
            alertDiv.style.color = 'white';
            alertDiv.style.padding = '15px 25px';
            alertDiv.style.borderRadius = '5px';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.display = 'flex';
            alertDiv.style.flexDirection = 'column';
            alertDiv.style.alignItems = 'center';
            alertDiv.style.gap = '10px';
            alertDiv.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
            alertDiv.style.minWidth = '200px';

            const messageDiv = document.createElement('div');
            messageDiv.textContent = itemName + ' added to cart successfully!';
            messageDiv.style.marginBottom = '5px';

            const okButton = document.createElement('button');
            okButton.textContent = 'OK';
            okButton.style.backgroundColor = '#4C6FFF';
            okButton.style.color = 'white';
            okButton.style.border = 'none';
            okButton.style.padding = '5px 20px';
            okButton.style.borderRadius = '5px';
            okButton.style.cursor = 'pointer';
            okButton.style.width = '80px';
            okButton.style.fontSize = '14px';

            okButton.addEventListener('click', () => {
                document.body.removeChild(alertDiv);
            });

            alertDiv.appendChild(messageDiv);
            alertDiv.appendChild(okButton);
            document.body.appendChild(alertDiv);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (document.body.contains(alertDiv)) {
                    document.body.removeChild(alertDiv);
                }
            }, 5000);

            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 2000);
        } else {
            throw new Error(data.error || 'Error adding item to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Show error message in the same style
        const alertDiv = document.createElement('div');
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.backgroundColor = '#333';
        alertDiv.style.color = 'white';
        alertDiv.style.padding = '15px 25px';
        alertDiv.style.borderRadius = '5px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.display = 'flex';
        alertDiv.style.flexDirection = 'column';
        alertDiv.style.alignItems = 'center';
        alertDiv.style.gap = '10px';
        alertDiv.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
        alertDiv.style.minWidth = '200px';

        const messageDiv = document.createElement('div');
        messageDiv.textContent = 'Error adding ' + itemName + ' to cart';
        messageDiv.style.marginBottom = '5px';

        const okButton = document.createElement('button');
        okButton.textContent = 'OK';
        okButton.style.backgroundColor = '#4C6FFF';
        okButton.style.color = 'white';
        okButton.style.border = 'none';
        okButton.style.padding = '5px 20px';
        okButton.style.borderRadius = '5px';
        okButton.style.cursor = 'pointer';
        okButton.style.width = '80px';
        okButton.style.fontSize = '14px';

        okButton.addEventListener('click', () => {
            document.body.removeChild(alertDiv);
        });

        alertDiv.appendChild(messageDiv);
        alertDiv.appendChild(okButton);
        document.body.appendChild(alertDiv);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (document.body.contains(alertDiv)) {
                document.body.removeChild(alertDiv);
            }
        }, 5000);

        button.innerHTML = originalText;
        button.disabled = false;
    });
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 