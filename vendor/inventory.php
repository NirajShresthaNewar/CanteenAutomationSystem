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

// Generate alerts
generateInventoryAlerts($conn, $vendor['id']);

// Set page title for layout
$pageTitle = "Inventory Management";

// Additional styles for DataTables and Select2
$additionalStyles = '
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
';

// Additional scripts for DataTables and Select2
$additionalScripts = '
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
';

// Start output buffering
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Inventory Management</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Inventory</li>
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

        <!-- Inventory Summary Cards -->
                <div class="row">
            <div class="col-lg-3 col-6">
                <!-- Total Items -->
                <div class="small-box bg-info">
                    <div class="inner">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT COUNT(DISTINCT i.ingredient_id) as total
                            FROM inventory i
                            WHERE i.vendor_id = ? AND i.status = 'active'
                        ");
                        $stmt->execute([$vendor_id]);
                        $total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        ?>
                        <h3><?= $total_items ?></h3>
                        <p>Total Active Items</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <!-- Low Stock Items -->
                <div class="small-box bg-warning">
                    <div class="inner">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT COUNT(*) as total
                            FROM inventory_alerts
                            WHERE vendor_id = ? AND alert_type = 'low_stock' AND is_resolved = 0
                        ");
                        $stmt->execute([$vendor_id]);
                        $low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        ?>
                        <h3><?= $low_stock ?></h3>
                        <p>Low Stock Alerts</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <!-- Expiring Soon -->
                <div class="small-box bg-danger">
                    <div class="inner">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT COUNT(*) as total
                            FROM inventory
                            WHERE vendor_id = ? 
                            AND status = 'active'
                            AND expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
                        ");
                        $stmt->execute([$vendor_id]);
                        $expiring_soon = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        ?>
                        <h3><?= $expiring_soon ?></h3>
                        <p>Expiring Within 7 Days</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <!-- Total Value -->
                <div class="small-box bg-success">
                    <div class="inner">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT SUM(i.current_quantity * vi.cost_per_unit) as total_value
                            FROM inventory i
                            JOIN vendor_ingredients vi ON i.ingredient_id = vi.ingredient_id AND i.vendor_id = vi.vendor_id
                            WHERE i.vendor_id = ? AND i.status = 'active'
                        ");
                        $stmt->execute([$vendor_id]);
                        $total_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;
                        ?>
                        <h3>₹<?= number_format($total_value, 2) ?></h3>
                        <p>Total Inventory Value</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Table Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Current Inventory</h3>
                <div class="card-tools">
                    <a href="add_inventory.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Inventory
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table id="inventoryTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Category</th>
                            <th>Batch Number</th>
                                <th>Current Quantity</th>
                            <th>Available Quantity</th>
                                <th>Unit</th>
                            <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                                        <?php
                        $stmt = $conn->prepare("
                            SELECT i.*, ing.name as ingredient_name, ing.unit,
                                   c.name as category_name,
                                   DATEDIFF(i.expiry_date, CURRENT_DATE) as days_until_expiry
                            FROM inventory i
                            JOIN ingredients ing ON i.ingredient_id = ing.id
                            LEFT JOIN categories c ON ing.category_id = c.id
                            WHERE i.vendor_id = ?
                            ORDER BY i.status, i.expiry_date ASC
                        ");
                        $stmt->execute([$vendor_id]);
                        
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $status_class = 'badge ';
                            if ($row['status'] === 'active') {
                                if ($row['days_until_expiry'] <= 7 && $row['days_until_expiry'] > 0) {
                                    $status_class .= 'badge-warning';
                                } elseif ($row['days_until_expiry'] <= 0) {
                                    $status_class .= 'badge-danger';
                                        } else {
                                    $status_class .= 'badge-success';
                                }
                                        } else {
                                $status_class .= 'badge-secondary';
                            }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['ingredient_name']) ?></td>
                                <td><?= htmlspecialchars($row['category_name']) ?></td>
                                <td><?= htmlspecialchars($row['batch_number']) ?></td>
                                <td><?= number_format($row['current_quantity'], 2) ?></td>
                                <td><?= number_format($row['available_quantity'], 2) ?></td>
                                <td><?= htmlspecialchars($row['unit']) ?></td>
                                <td data-order="<?= $row['expiry_date'] ?>">
                                    <?= date('Y-m-d', strtotime($row['expiry_date'])) ?>
                                    <?php if ($row['days_until_expiry'] <= 7 && $row['days_until_expiry'] > 0): ?>
                                        <span class="badge badge-warning">Expiring Soon</span>
                                    <?php elseif ($row['days_until_expiry'] <= 0): ?>
                                        <span class="badge badge-danger">Expired</span>
                                    <?php endif; ?>
                                    </td>
                                <td><span class="<?= $status_class ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            onclick="viewHistory(<?= $row['id'] ?>)" 
                                            title="View History">
                                        <i class="fas fa-history"></i>
                                    </button>
                                    <?php if ($row['status'] === 'active'): ?>
                                        <button type="button" class="btn btn-sm btn-warning" 
                                                onclick="adjustQuantity(<?= $row['id'] ?>)"
                                                title="Adjust Quantity">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="markAsExpired(<?= $row['id'] ?>)"
                                                title="Mark as Expired">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                    </td>
                                </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title" id="historyModalLabel">
                    <i class="fas fa-history"></i> Inventory History
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Ingredient: <span id="historyIngredientName" class="font-weight-normal"></span></h6>
                        <h6>Current Quantity: <span id="historyCurrentQty" class="font-weight-normal"></span></h6>
                    </div>
                    <div class="col-md-6">
                        <h6>Unit: <span id="historyUnit" class="font-weight-normal"></span></h6>
                        <h6>Available Quantity: <span id="historyAvailableQty" class="font-weight-normal"></span></h6>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="historyTable">
                        <thead class="thead-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Change Type</th>
                                <th>Previous Qty</th>
                                <th>New Qty</th>
                                <th>Changed By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <tr>
                                <td colspan="6" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Adjust Quantity Modal -->
<div class="modal fade" id="adjustQuantityModal" tabindex="-1" role="dialog" aria-labelledby="adjustQuantityModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustQuantityModalLabel">Adjust Quantity</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="adjustQuantityForm" action="process_adjust_quantity.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="inventory_id" id="adjustInventoryId">
                    <div class="form-group">
                        <label for="adjustmentType">Adjustment Type</label>
                        <select class="form-control" id="adjustmentType" name="adjustment_type" required>
                            <option value="add">Add</option>
                            <option value="remove">Remove</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="adjustmentQuantity">Quantity</label>
                        <input type="number" class="form-control" id="adjustmentQuantity" 
                               name="quantity" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="adjustmentNotes">Notes</label>
                        <textarea class="form-control" id="adjustmentNotes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#inventoryTable').DataTable({
        responsive: true,
        lengthChange: true,
        autoWidth: false,
        order: [[6, 'asc']], // Sort by expiry date by default
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });

    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4'
    });
});

// View history function
function viewHistory(inventoryId) {
    // Show loading state
    $('#historyTableBody').html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');
    $('#historyModal').modal('show');

    // Fetch history data
    $.ajax({
        url: 'get_inventory_history.php',
        method: 'GET',
        data: { inventory_id: inventoryId },
        dataType: 'json',
        success: function(response) {
            // Update inventory details
            $('#historyIngredientName').text(response.inventory.ingredient_name);
            $('#historyUnit').text(response.inventory.unit);
            $('#historyCurrentQty').text(response.inventory.current_quantity + ' ' + response.inventory.unit);
            $('#historyAvailableQty').text(response.inventory.available_quantity + ' ' + response.inventory.unit);

            // Clear and populate history table
            let tableContent = '';
            if (response.history.length === 0) {
                tableContent = '<tr><td colspan="6" class="text-center">No history records found</td></tr>';
            } else {
                response.history.forEach(function(record) {
                    let typeClass = '';
                    switch(record.type.toLowerCase()) {
                        case 'add':
                            typeClass = 'badge badge-success';
                            break;
                        case 'remove':
                            typeClass = 'badge badge-danger';
                            break;
                        default:
                            typeClass = 'badge badge-info';
                    }

                    tableContent += `
                        <tr>
                            <td>${record.date}</td>
                            <td><span class="${typeClass}">${record.type}</span></td>
                            <td>${record.previous}</td>
                            <td>${record.new}</td>
                            <td>${record.changed_by}</td>
                            <td>${record.notes}</td>
                        </tr>
                    `;
                });
            }
            $('#historyTableBody').html(tableContent);
        },
        error: function(xhr, status, error) {
            let errorMessage = 'An error occurred while fetching history.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errorMessage = xhr.responseJSON.error;
            }
            $('#historyTableBody').html(`
                <tr>
                    <td colspan="6" class="text-center text-danger">
                        <i class="fas fa-exclamation-circle"></i> ${errorMessage}
                    </td>
                </tr>
            `);
        }
    });
}

// Adjust quantity function
function adjustQuantity(inventoryId) {
    $('#adjustInventoryId').val(inventoryId);
    $('#adjustQuantityModal').modal('show');
}

// Mark as expired function
function markAsExpired(inventoryId) {
    if (confirm('Are you sure you want to mark this item as expired?')) {
        $.post('mark_as_expired.php', { id: inventoryId }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'An error occurred');
            }
        });
    }
}
</script>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout template
include('../includes/layout.php');
?> 