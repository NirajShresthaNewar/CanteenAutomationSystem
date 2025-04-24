<?php
session_start();
require_once '../connection/db_connection.php';
require_once 'check_inventory_alerts.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    $_SESSION['error'] = "Vendor not found!";
    header("Location: ../auth/logout.php");
    exit();
}

$vendor_id = $vendor['id'];

// Handle alert resolution
if (isset($_POST['resolve_alert'])) {
    $alert_id = $_POST['alert_id'];
    try {
        $stmt = $conn->prepare("
            UPDATE inventory_alerts 
            SET is_resolved = 1, 
                resolved_at = CURRENT_TIMESTAMP, 
                resolved_by = ? 
            WHERE id = ? AND vendor_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $alert_id, $vendor_id]);
        $_SESSION['success'] = "Alert marked as resolved.";
        header("Location: inventory_alerts.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error resolving alert: " . $e->getMessage();
    }
}

// Get alerts from the database with additional information
$stmt = $conn->prepare("
    SELECT 
        ia.*, 
        i.name as ingredient_name,
        i.unit,
        COALESCE(vi.reorder_point, i.minimum_order_quantity) as reorder_point,
        (
            SELECT SUM(inv.available_quantity)
            FROM inventory inv
            WHERE inv.ingredient_id = ia.ingredient_id
            AND inv.vendor_id = ia.vendor_id
            AND inv.status = 'active'
        ) as current_stock
    FROM inventory_alerts ia
    JOIN ingredients i ON ia.ingredient_id = i.id
    LEFT JOIN vendor_ingredients vi ON vi.ingredient_id = i.id AND vi.vendor_id = ia.vendor_id
    WHERE ia.vendor_id = ? AND ia.is_resolved = 0
    ORDER BY 
        CASE ia.alert_type
            WHEN 'expired' THEN 1
            WHEN 'low_stock' THEN 2
            WHEN 'expiring_soon' THEN 3
        END,
        ia.created_at DESC
");
$stmt->execute([$vendor_id]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count alerts by type
$low_stock_count = 0;
$expiring_soon_count = 0;
$expired_count = 0;

foreach ($alerts as $alert) {
    switch ($alert['alert_type']) {
        case 'low_stock':
            $low_stock_count++;
            break;
        case 'expiring_soon':
            $expiring_soon_count++;
            break;
        case 'expired':
            $expired_count++;
            break;
    }
}

// Generate new alerts
generateInventoryAlerts($conn, $vendor_id);

// Set page title and additional assets
$pageTitle = "Inventory Alerts";
$additionalStyles = '
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">
<style>
.alert-badge {
    font-size: 0.9em;
    padding: 8px 12px;
    border-radius: 4px;
    white-space: nowrap;
}
.stock-status {
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 3px;
}
.stock-critical { color: #dc3545; }
.stock-warning { color: #ffc107; }
.stock-normal { color: #28a745; }
</style>
';

$additionalScripts = '
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
';

ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    Inventory Alerts
                    <?php if ($low_stock_count + $expiring_soon_count + $expired_count > 0): ?>
                        <span class="badge badge-danger"><?= $low_stock_count + $expiring_soon_count + $expired_count ?></span>
                    <?php endif; ?>
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                    <li class="breadcrumb-item active">Alerts</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Alert Summary Cards -->
        <div class="row">
            <div class="col-lg-4 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?= $expired_count ?></h3>
                        <p>Expired Items</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-ban"></i>
                    </div>
                    <?php if ($expired_count > 0): ?>
                        <a href="#" class="small-box-footer" onclick="filterAlerts('expired')">
                            View Details <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?= $low_stock_count ?></h3>
                        <p>Low Stock Alerts</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <?php if ($low_stock_count > 0): ?>
                        <a href="#" class="small-box-footer" onclick="filterAlerts('low_stock')">
                            View Details <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?= $expiring_soon_count ?></h3>
                        <p>Expiring Soon</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <?php if ($expiring_soon_count > 0): ?>
                        <a href="#" class="small-box-footer" onclick="filterAlerts('expiring_soon')">
                            View Details <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Alerts Table Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Current Alerts</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="alertsTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Priority</th>
                                <th>Alert Type</th>
                                <th>Ingredient</th>
                                <th>Current Stock</th>
                                <th>Details</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alerts)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No alerts at this time</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alerts as $alert): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $priority = match($alert['alert_type']) {
                                                'expired' => 1,
                                                'low_stock' => 2,
                                                'expiring_soon' => 3,
                                                default => 4
                                            };
                                            echo $priority;
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $alert_class = match($alert['alert_type']) {
                                                'low_stock' => 'warning',
                                                'expiring_soon' => 'info',
                                                'expired' => 'danger',
                                                default => 'secondary'
                                            };
                                            $alert_icon = match($alert['alert_type']) {
                                                'low_stock' => 'exclamation-triangle',
                                                'expiring_soon' => 'clock',
                                                'expired' => 'ban',
                                                default => 'info-circle'
                                            };
                                            ?>
                                            <span class="alert-badge badge badge-<?= $alert_class ?>">
                                                <i class="fas fa-<?= $alert_icon ?>"></i>
                                                <?= ucwords(str_replace('_', ' ', $alert['alert_type'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($alert['ingredient_name']) ?></td>
                                        <td>
                                            <?php
                                            $stock_status = '';
                                            $stock_class = '';
                                            if ($alert['current_stock'] <= 0) {
                                                $stock_status = 'Out of Stock';
                                                $stock_class = 'stock-critical';
                                            } elseif ($alert['current_stock'] <= $alert['reorder_point']) {
                                                $stock_status = 'Low Stock';
                                                $stock_class = 'stock-warning';
                                            } else {
                                                $stock_status = 'In Stock';
                                                $stock_class = 'stock-normal';
                                            }
                                            ?>
                                            <span class="stock-status <?= $stock_class ?>">
                                                <?= number_format($alert['current_stock'], 2) ?> <?= $alert['unit'] ?>
                                                (<?= $stock_status ?>)
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($alert['alert_message']) ?></td>
                                        <td data-order="<?= strtotime($alert['created_at']) ?>">
                                            <?= date('Y-m-d H:i', strtotime($alert['created_at'])) ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <?php if ($alert['alert_type'] === 'low_stock'): ?>
                                                    <a href="add_inventory.php?ingredient_id=<?= $alert['ingredient_id'] ?>" 
                                                       class="btn btn-sm btn-success" 
                                                       title="Add Inventory">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="inventory.php?ingredient_id=<?= $alert['ingredient_id'] ?>" 
                                                   class="btn btn-sm btn-info" 
                                                   title="View Inventory">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <button type="button" 
                                                        class="btn btn-sm btn-success resolve-alert" 
                                                        data-alert-id="<?= $alert['id'] ?>"
                                                        title="Mark as Resolved">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Hidden form for alert resolution -->
<form id="resolveAlertForm" method="POST" style="display: none;">
    <input type="hidden" name="resolve_alert" value="1">
    <input type="hidden" name="alert_id" id="alertIdInput">
</form>

<script>
$(document).ready(function() {
    // Initialize DataTable with export buttons
    const table = $('#alertsTable').DataTable({
        responsive: true,
        order: [[0, 'asc']], // Sort by priority by default
        columnDefs: [
            { 
                targets: 0,
                visible: false // Hide priority column but use it for sorting
            },
            { 
                orderable: false, 
                targets: 6 // Disable sorting on actions column
            }
        ],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> Export CSV',
                className: 'btn btn-sm btn-secondary',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5]
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-sm btn-secondary',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5]
                }
            }
        ]
    });

    // Handle alert resolution
    $('.resolve-alert').click(function() {
        const alertId = $(this).data('alert-id');
        Swal.fire({
            title: 'Resolve Alert?',
            text: 'Are you sure you want to mark this alert as resolved?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, resolve it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#alertIdInput').val(alertId);
                $('#resolveAlertForm').submit();
            }
        });
    });

    // Auto-refresh alerts every 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
});

// Function to filter alerts by type
function filterAlerts(type) {
    const table = $('#alertsTable').DataTable();
    table.column(1).search(type, true, false).draw();
}
</script>

<?php
$content = ob_get_clean();
include('../includes/layout.php');
?> 