<?php
session_start();
require_once '../connection/db_connection.php';

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

// Fetch all ingredients with their categories
$stmt = $conn->prepare("
    SELECT i.*, c.name as category_name, 
           vi.reorder_point, vi.min_reorder_quantity, vi.max_reorder_quantity, 
           vi.cost_per_unit, vi.preferred_supplier
    FROM ingredients i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN vendor_ingredients vi ON i.id = vi.ingredient_id AND vi.vendor_id = ?
    ORDER BY c.name, i.name
");
$stmt->execute([$vendor_id]);
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group ingredients by category
$categorized_ingredients = [];
foreach ($ingredients as $ingredient) {
    $category = $ingredient['category_name'] ?? 'Uncategorized';
    if (!isset($categorized_ingredients[$category])) {
        $categorized_ingredients[$category] = [];
    }
    $categorized_ingredients[$category][] = $ingredient;
}

// Set page title for layout
$pageTitle = "Add Inventory";

// Additional styles for Select2 and DateTimePicker
$additionalStyles = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
';

// Additional scripts for validation and dynamic updates
$additionalScripts = '
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
';

// Start output buffering
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Add Inventory</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                    <li class="breadcrumb-item active">Add Inventory</li>
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Add New Inventory</h3>
            </div>
            <div class="card-body">
                <form id="addInventoryForm" action="process_add_inventory.php" method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ingredient_id">Select Ingredient <span class="text-danger">*</span></label>
                                <select class="form-control select2" id="ingredient_id" name="ingredient_id" required>
                                    <option value="">Select an ingredient</option>
                                    <?php foreach ($categorized_ingredients as $category => $items): ?>
                                        <optgroup label="<?= htmlspecialchars($category) ?>">
                                            <?php foreach ($items as $ingredient): ?>
                                                <option value="<?= $ingredient['id'] ?>" 
                                                        data-unit="<?= htmlspecialchars($ingredient['unit']) ?>"
                                                        data-min-qty="<?= htmlspecialchars($ingredient['minimum_order_quantity']) ?>"
                                                        data-shelf-life="<?= htmlspecialchars($ingredient['shelf_life_days']) ?>"
                                                        data-reorder-point="<?= htmlspecialchars($ingredient['reorder_point']) ?>"
                                                        data-cost="<?= htmlspecialchars($ingredient['cost_per_unit']) ?>">
                                                    <?= htmlspecialchars($ingredient['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="quantity">Quantity <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           step="0.01" min="0" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="unit-display">Unit</span>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Minimum order quantity: <span id="min-qty-display">-</span></small>
                            </div>

                            <div class="form-group">
                                <label for="cost_per_unit">Cost per Unit <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₹</span>
                                    </div>
                                    <input type="number" class="form-control" id="cost_per_unit" name="cost_per_unit" 
                                           step="0.01" min="0" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="cost-unit-display">per unit</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="batch_number">Batch Number</label>
                                <input type="text" class="form-control" id="batch_number" name="batch_number" 
                                       placeholder="Auto-generated if left empty">
                                <small class="form-text text-muted">Leave empty for auto-generation</small>
                            </div>

                            <div class="form-group">
                                <label for="expiry_date">Expiry Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                                <small class="form-text text-muted">Shelf life: <span id="shelf-life-display">-</span> days</small>
                            </div>

                            <div class="form-group">
                                <label for="supplier">Supplier</label>
                                <input type="text" class="form-control" id="supplier" name="supplier" 
                                       placeholder="Enter supplier name">
                            </div>

                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                        placeholder="Enter any additional notes"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Add Inventory</button>
                            <a href="inventory.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4'
    });

    // Handle ingredient selection change
    $('#ingredient_id').change(function() {
        const selected = $(this).find(':selected');
        const unit = selected.data('unit') || '-';
        const minQty = selected.data('min-qty') || 0;
        const shelfLife = selected.data('shelf-life') || 0;
        const cost = selected.data('cost') || '';

        // Update displays
        $('#unit-display').text(unit);
        $('#cost-unit-display').text('per ' + unit);
        $('#min-qty-display').text(minQty + ' ' + unit);
        $('#shelf-life-display').text(shelfLife);
        
        // Set minimum quantity
        $('#quantity').attr('min', minQty);
        
        // Set cost if available
        if (cost) {
            $('#cost_per_unit').val(cost);
        }

        // Set expiry date based on shelf life
        if (shelfLife > 0) {
            const expiryDate = new Date();
            expiryDate.setDate(expiryDate.getDate() + parseInt(shelfLife));
            const formattedDate = expiryDate.toISOString().split('T')[0];
            $('#expiry_date').val(formattedDate);
        }
    });

    // Form validation
    $('#addInventoryForm').submit(function(e) {
        const quantity = parseFloat($('#quantity').val());
        const minQty = parseFloat($('#ingredient_id').find(':selected').data('min-qty') || 0);
        const expiryDate = new Date($('#expiry_date').val());
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (quantity < minQty) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Quantity',
                text: `Quantity must be at least ${minQty} ${$('#unit-display').text()}`
            });
            return false;
        }

        if (expiryDate < today) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Expiry Date',
                text: 'Expiry date cannot be in the past'
            });
            return false;
        }

        return true;
    });
});
</script>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout template
include('../includes/layout.php');
?> 