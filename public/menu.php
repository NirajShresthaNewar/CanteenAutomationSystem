<?php
require_once '../connection/db_connection.php';

// Get vendor ID from URL
$vendor_id = isset($_GET['v']) ? (int)$_GET['v'] : null;

if (!$vendor_id) {
    die('Invalid QR code');
}

// Verify if vendor exists and is approved
$stmt = $conn->prepare("
    SELECT v.*, u.username as vendor_name 
    FROM vendors v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.id = ? AND v.approval_status = 'approved'
");
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    die('Vendor not found or not approved');
}

// Get menu items
$stmt = $conn->prepare("
    SELECT mi.*, mc.name as category_name 
    FROM menu_items mi
    LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
    WHERE mi.vendor_id = ? AND mi.is_available = 1
    ORDER BY mc.name, mi.name
");
$stmt->execute([$vendor_id]);
$menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vendor['vendor_name']); ?> - Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .menu-header {
            text-align: center;
            padding: 2rem 0;
            max-width: 800px;
            margin: 0 auto 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        .menu-header h1 {
            color: #333;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .menu-header .opening-hours {
            color: #666;
            font-size: 1.1rem;
            margin-top: 1rem;
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

        body {
            background-color: #f5f5f5;
            padding: 20px;
        }

        .category-title {
            color: #2c3e50;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Menu Header -->
        <div class="menu-header">
            <h1><?php echo htmlspecialchars($vendor['vendor_name']); ?></h1>
            <?php if ($vendor['opening_hours']): ?>
                <div class="opening-hours">
                    <i class="bi bi-clock"></i> Opening Hours: <?php echo htmlspecialchars($vendor['opening_hours']); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($menu_items)): ?>
            <div class="alert alert-info">
                No menu items available at the moment.
            </div>
        <?php else: ?>
            <?php
            $current_category = '';
            foreach ($menu_items as $item):
                if ($item['category_name'] !== $current_category):
                    if ($current_category !== '') echo '</div>'; // Close previous grid
                    $current_category = $item['category_name'];
            ?>
                    <h2 class="category-title"><?php echo htmlspecialchars($current_category ?: 'Other Items'); ?></h2>
                    <div class="menu-grid">
            <?php endif; ?>
                
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
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 