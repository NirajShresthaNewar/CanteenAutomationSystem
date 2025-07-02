<?php
require_once '../connection/db_connection.php';

if (isset($_GET['vendor_id'])) {
    $vendor_id = $_GET['vendor_id'];
    
    // Get menu items for the selected vendor
    $stmt = $conn->prepare("
        SELECT 
            mi.*,
            mc.name as category_name,
            COALESCE(order_counts.total_orders, 0) as order_count
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
        LEFT JOIN (
            SELECT menu_item_id, COUNT(*) as total_orders
            FROM order_items
            GROUP BY menu_item_id
        ) order_counts ON mi.item_id = order_counts.menu_item_id
        WHERE mi.vendor_id = ? AND mi.is_available = 1
        ORDER BY order_counts.total_orders DESC, mc.name, mi.name
    ");
    
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);

    $current_category = '';
    
    foreach ($items as $item) {
        if ($current_category != $item['category_name']) {
            if ($current_category != '') {
                echo '</div>'; // Close previous category row
            }
            $current_category = $item['category_name'];
            echo '<h3 class="col-12 mt-4">' . htmlspecialchars($current_category) . '</h3>';
            echo '<div class="row">';
        }
        ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <?php if ($item['order_count'] > 5): ?>
                    <div class="recommendation-badge">Popular Choice!</div>
                <?php endif; ?>
                
                <img src="<?php echo '../' . $item['image_path']; ?>" 
                     class="card-img-top" 
                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                     style="height: 200px; object-fit: cover;">
                
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                    <p class="card-text"><?php echo htmlspecialchars($item['description']); ?></p>
                    
                    <?php if ($item['is_vegetarian']): ?>
                        <span class="badge badge-success">Vegetarian</span>
                    <?php endif; ?>
                    
                    <div class="mt-2">
                        <span class="text-danger">Rs. <?php echo number_format($item['price'], 2); ?></span>
                    </div>
                </div>
                
                <div class="card-footer bg-transparent">
                    <button onclick="addToCart(<?php echo $item['item_id']; ?>, <?php echo $vendor_id; ?>)" 
                            class="btn btn-primary btn-block">
                        Add to Cart
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    if ($current_category != '') {
        echo '</div>'; // Close last category row
    }
}
?> 