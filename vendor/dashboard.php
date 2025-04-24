<?php
session_start();
require_once '../connection/db_connection.php';
require_once 'check_inventory_alerts.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../auth/login.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

// Get inventory alerts
$alerts = getInventoryAlerts($conn, $vendor_id);

// Get recent inventory changes
$stmt = $conn->prepare("
    SELECT 
        ih.*,
        i.name as ingredient_name,
        i.unit,
        u.username as changed_by_name
    FROM inventory_history ih
    JOIN ingredients i ON ih.ingredient_id = i.id
    JOIN users u ON ih.changed_by = u.id
    WHERE ih.vendor_id = ?
    ORDER BY ih.created_at DESC
    LIMIT 5
");
$stmt->execute([$vendor_id]);
$recent_changes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total inventory value
$stmt = $conn->prepare("
    SELECT SUM(i.available_quantity * COALESCE(vi.cost_per_unit, 0)) as total_value
    FROM inventory i
    LEFT JOIN vendor_ingredients vi ON i.ingredient_id = vi.ingredient_id AND i.vendor_id = vi.vendor_id
    WHERE i.vendor_id = ? AND i.status = 'active'
");
$stmt->execute([$vendor_id]);
$total_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

// Get total number of ingredients
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT ingredient_id) as total_ingredients
    FROM inventory
    WHERE vendor_id = ? AND status = 'active'
");
$stmt->execute([$vendor_id]);
$total_ingredients = $stmt->fetch(PDO::FETCH_ASSOC)['total_ingredients'] ?? 0;

// Count alerts by type
$low_stock_count = 0;
$expiring_soon_count = 0;
foreach ($alerts as $alert) {
    if ($alert['type'] === 'low_stock') {
        $low_stock_count++;
    } elseif ($alert['type'] === 'expiring_soon') {
        $expiring_soon_count++;
    }
}

$page_title = 'Vendor Dashboard';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Vendor Dashboard</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <!-- Info boxes -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <!-- Low stock alert box -->
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $low_stock_count; ?></h3>
                        <p>Low Stock Items</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <a href="inventory_alerts.php" class="small-box-footer">
                        View Details <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <!-- Expiring soon box -->
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $expiring_soon_count; ?></h3>
                        <p>Expiring Soon</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <a href="inventory_alerts.php" class="small-box-footer">
                        View Details <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <!-- Total inventory value box -->
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>₱<?php echo number_format($total_value, 2); ?></h3>
                        <p>Total Value</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <a href="inventory.php" class="small-box-footer">
                        View Inventory <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <!-- Total ingredients box -->
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $total_ingredients; ?></h3>
                        <p>Total Ingredients</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <a href="inventory.php" class="small-box-footer">
                        View Inventory <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Row -->
        <div class="row">
            <!-- Recent Changes -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Inventory Changes</h3>
                        <div class="card-tools">
                            <a href="inventory_history.php" class="btn btn-tool">
                                <i class="fas fa-history"></i> View All
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Ingredient</th>
                                        <th>Change</th>
                                        <th>Type</th>
                                        <th>By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_changes)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No recent changes</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_changes as $change): ?>
                                            <tr>
                                                <td><?php echo date('M d, H:i', strtotime($change['created_at'])); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($change['ingredient_name']); ?>
                                                    <small class="text-muted">(<?php echo $change['unit']; ?>)</small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $quantity_change = $change['new_quantity'] - $change['previous_quantity'];
                                                    $class = $quantity_change > 0 ? 'text-success' : ($quantity_change < 0 ? 'text-danger' : 'text-muted');
                                                    $prefix = $quantity_change > 0 ? '+' : '';
                                                    echo "<span class='$class'>$prefix" . number_format($quantity_change, 2) . "</span>";
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo match($change['change_type']) {
                                                            'add' => 'success',
                                                            'remove' => 'danger',
                                                            'update' => 'info',
                                                            'expired' => 'warning',
                                                            'damaged' => 'danger',
                                                            'prep_used' => 'primary',
                                                            default => 'secondary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($change['change_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($change['changed_by_name'] ?? 'System'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Inventory Alerts</h3>
                        <div class="card-tools">
                            <a href="inventory_alerts.php" class="btn btn-tool">
                                <i class="fas fa-exclamation-triangle"></i> View All
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (empty($alerts)): ?>
                                <div class="list-group-item">
                                    <p class="mb-0 text-success">
                                        <i class="fas fa-check-circle"></i> No alerts at this time
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($alerts as $alert): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <?php
                                            // Extract ingredient name from message
                                            preg_match('/(?:alert for|expired on) ([^\.]+)/', $alert['message'], $matches);
                                            $ingredient_name = $matches[1] ?? 'Unknown Ingredient';
                                            ?>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($ingredient_name); ?></h6>
                                            <small class="text-<?php 
                                                echo match($alert['type']) {
                                                    'low_stock' => 'warning',
                                                    'expiring_soon' => 'info',
                                                    'expired' => 'danger',
                                                    default => 'secondary'
                                                }; 
                                            ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $alert['type'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1">
                                            <?php echo htmlspecialchars($alert['message']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 