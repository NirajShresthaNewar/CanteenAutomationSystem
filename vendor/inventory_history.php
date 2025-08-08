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

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$ingredient_filter = isset($_GET['ingredient']) ? intval($_GET['ingredient']) : '';
$batch_filter = isset($_GET['batch']) ? trim($_GET['batch']) : '';
$change_type_filter = isset($_GET['change_type']) ? trim($_GET['change_type']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build the query with filters
$where_conditions = ["ih.vendor_id = ?"];
$params = [$vendor_id];

if (!empty($search)) {
    $where_conditions[] = "(ing.name LIKE ? OR ih.batch_number LIKE ? OR ih.notes LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($ingredient_filter)) {
    $where_conditions[] = "ih.ingredient_id = ?";
    $params[] = $ingredient_filter;
}

if (!empty($batch_filter)) {
    $where_conditions[] = "ih.batch_number LIKE ?";
    $params[] = "%$batch_filter%";
}

if (!empty($change_type_filter)) {
    $where_conditions[] = "ih.change_type = ?";
    $params[] = $change_type_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(ih.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(ih.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($status_filter)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get inventory history with filters
$stmt = $conn->prepare("
    SELECT 
        ih.*,
        ing.name as ingredient_name,
        ing.unit,
        c.name as category_name,
        u.username as changed_by_name,
        COALESCE(i.status, 'unknown') as current_status,
        i.expiry_date,
        i.current_quantity as current_qty,
        i.available_quantity as available_qty,
        vi.cost_per_unit,
        DATE_FORMAT(ih.created_at, '%Y-%m-%d %H:%i:%s') as formatted_date
    FROM inventory_history ih
    JOIN ingredients ing ON ih.ingredient_id = ing.id
    LEFT JOIN categories c ON ing.category_id = c.id
    LEFT JOIN users u ON ih.changed_by = u.id
    LEFT JOIN inventory i ON ih.inventory_id = i.id
    LEFT JOIN vendor_ingredients vi ON ih.ingredient_id = vi.ingredient_id AND ih.vendor_id = vi.vendor_id
    WHERE $where_clause
    ORDER BY ih.created_at DESC
    LIMIT 1000
");
$stmt->execute($params);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ingredients for filter dropdown
$stmt = $conn->prepare("
    SELECT DISTINCT ing.id, ing.name, c.name as category_name
    FROM inventory_history ih
    JOIN ingredients ing ON ih.ingredient_id = ing.id
    LEFT JOIN categories c ON ing.category_id = c.id
    WHERE ih.vendor_id = ?
    ORDER BY ing.name
");
$stmt->execute([$vendor_id]);
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get batch numbers for filter dropdown
$stmt = $conn->prepare("
    SELECT DISTINCT batch_number
    FROM inventory_history
    WHERE vendor_id = ? AND batch_number IS NOT NULL
    ORDER BY batch_number
");
$stmt->execute([$vendor_id]);
$batch_numbers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page title and additional assets
$pageTitle = "Inventory History";
$additionalStyles = '
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.filter-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 15px;
    margin-bottom: 20px;
}
.change-type-badge {
    font-size: 0.8em;
    padding: 4px 8px;
}
.quantity-change {
    font-weight: bold;
}
.quantity-increase { color: #28a745; }
.quantity-decrease { color: #dc3545; }
.quantity-neutral { color: #6c757d; }
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
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
';

ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Inventory History</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                    <li class="breadcrumb-item active">History</li>
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

        <!-- Filters Card -->
        <div class="card filter-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter"></i> Advanced Filters
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="search">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Ingredient, batch, notes...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="ingredient">Ingredient</label>
                                <select class="form-control" id="ingredient" name="ingredient">
                                    <option value="">All Ingredients</option>
                                    <?php foreach ($ingredients as $ing): ?>
                                        <option value="<?= $ing['id'] ?>" 
                                                <?= $ingredient_filter == $ing['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ing['name']) ?> 
                                            (<?= htmlspecialchars($ing['category_name']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="batch">Batch Number</label>
                                <select class="form-control" id="batch" name="batch">
                                    <option value="">All Batches</option>
                                    <?php foreach ($batch_numbers as $batch): ?>
                                        <option value="<?= htmlspecialchars($batch['batch_number']) ?>" 
                                                <?= $batch_filter == $batch['batch_number'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($batch['batch_number']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="change_type">Change Type</label>
                                <select class="form-control" id="change_type" name="change_type">
                                    <option value="">All Types</option>
                                    <option value="add" <?= $change_type_filter == 'add' ? 'selected' : '' ?>>Add</option>
                                    <option value="remove" <?= $change_type_filter == 'remove' ? 'selected' : '' ?>>Remove</option>
                                    <option value="update" <?= $change_type_filter == 'update' ? 'selected' : '' ?>>Update</option>
                                    <option value="expired" <?= $change_type_filter == 'expired' ? 'selected' : '' ?>>Expired</option>
                                    <option value="damaged" <?= $change_type_filter == 'damaged' ? 'selected' : '' ?>>Damaged</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="date_from">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?= htmlspecialchars($date_from) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="date_to">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="status">Current Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="expired" <?= $status_filter == 'expired' ? 'selected' : '' ?>>Expired</option>
                                    <option value="hidden" <?= $status_filter == 'hidden' ? 'selected' : '' ?>>Hidden</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Apply Filters
                                    </button>
                                    <a href="inventory_history.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- History Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?= count($history) ?></h3>
                        <p>Total Records</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-list"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <?php
                        $add_count = array_filter($history, function($item) {
                            return $item['change_type'] === 'add';
                        });
                        ?>
                        <h3><?= count($add_count) ?></h3>
                        <p>Additions</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-plus"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <?php
                        $remove_count = array_filter($history, function($item) {
                            return $item['change_type'] === 'remove';
                        });
                        ?>
                        <h3><?= count($remove_count) ?></h3>
                        <p>Removals</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-minus"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <?php
                        $expired_count = array_filter($history, function($item) {
                            return $item['change_type'] === 'expired';
                        });
                        ?>
                        <h3><?= count($expired_count) ?></h3>
                        <p>Expired</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-ban"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Table Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Inventory History</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="historyTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Ingredient</th>
                                <th>Category</th>
                                <th>Batch Number</th>
                                <th>Change Type</th>
                                <th>Previous Qty</th>
                                <th>New Qty</th>
                                <th>Difference</th>
                                <th>Cost/Unit</th>
                                <th>Current Status</th>
                                <th>Expiry Date</th>
                                <th>Changed By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="13" class="text-center">No history records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $record): ?>
                                    <tr>
                                        <td data-order="<?= strtotime($record['formatted_date']) ?>">
                                            <?= $record['formatted_date'] ?>
                                        </td>
                                        <td><?= htmlspecialchars($record['ingredient_name']) ?></td>
                                        <td><?= htmlspecialchars($record['category_name']) ?></td>
                                        <td>
                                            <?php if ($record['batch_number']): ?>
                                                <span class="badge badge-info"><?= htmlspecialchars($record['batch_number']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $type_class = match($record['change_type']) {
                                                'add' => 'badge-success',
                                                'remove' => 'badge-danger',
                                                'update' => 'badge-warning',
                                                'expired' => 'badge-secondary',
                                                'damaged' => 'badge-dark',
                                                default => 'badge-info'
                                            };
                                            ?>
                                            <span class="change-type-badge badge <?= $type_class ?>">
                                                <?= ucfirst($record['change_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($record['previous_quantity'], 2) ?></td>
                                        <td><?= number_format($record['new_quantity'], 2) ?></td>
                                        <td>
                                            <?php
                                            $difference = $record['new_quantity'] - $record['previous_quantity'];
                                            $diff_class = $difference > 0 ? 'quantity-increase' : ($difference < 0 ? 'quantity-decrease' : 'quantity-neutral');
                                            $diff_sign = $difference > 0 ? '+' : '';
                                            ?>
                                            <span class="quantity-change <?= $diff_class ?>">
                                                <?= $diff_sign . number_format($difference, 2) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($record['cost_per_unit']): ?>
                                                ₹<?= number_format($record['cost_per_unit'], 2) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = match($record['current_status']) {
                                                'active' => 'badge-success',
                                                'expired' => 'badge-danger',
                                                'hidden' => 'badge-secondary',
                                                'unknown' => 'badge-warning',
                                                default => 'badge-info'
                                            };
                                            ?>
                                            <span class="badge <?= $status_class ?>">
                                                <?= ucfirst($record['current_status']) ?>
                                            </span>
                                        </td>
                                        <td data-order="<?= strtotime($record['expiry_date']) ?>">
                                            <?php if ($record['expiry_date']): ?>
                                                <?= date('Y-m-d', strtotime($record['expiry_date'])) ?>
                                                <?php
                                                $days_until_expiry = (strtotime($record['expiry_date']) - time()) / (60 * 60 * 24);
                                                if ($days_until_expiry <= 7 && $days_until_expiry > 0): ?>
                                                    <span class="badge badge-warning">Expiring Soon</span>
                                                <?php elseif ($days_until_expiry <= 0): ?>
                                                    <span class="badge badge-danger">Expired</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($record['changed_by_name']) ?></td>
                                        <td>
                                            <?php if ($record['notes']): ?>
                                                <span title="<?= htmlspecialchars($record['notes']) ?>">
                                                    <?= htmlspecialchars(substr($record['notes'], 0, 30)) ?>
                                                    <?= strlen($record['notes']) > 30 ? '...' : '' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
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

<script>
$(document).ready(function() {
    // Initialize DataTable with export buttons
    const table = $('#historyTable').DataTable({
        responsive: true,
        order: [[0, 'desc']], // Sort by date descending by default
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                text: '<i class="fas fa-copy"></i> Copy',
                className: 'btn btn-sm btn-secondary'
            },
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> Export CSV',
                className: 'btn btn-sm btn-secondary',
                title: 'Inventory History - <?= date('Y-m-d') ?>'
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Export Excel',
                className: 'btn btn-sm btn-secondary',
                title: 'Inventory History - <?= date('Y-m-d') ?>'
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-sm btn-secondary',
                title: 'Inventory History - <?= date('Y-m-d') ?>'
            }
        ],
        columnDefs: [
            { 
                orderable: false, 
                targets: [12] // Disable sorting on notes column
            }
        ]
    });

    // Initialize date pickers with validation
    flatpickr("#date_from", {
        dateFormat: "Y-m-d",
        allowInput: true,
        onChange: function(selectedDates, dateStr) {
            // Update date_to min date
            if (dateStr) {
                dateToPicker.set('minDate', dateStr);
            }
        }
    });
    
    const dateToPicker = flatpickr("#date_to", {
        dateFormat: "Y-m-d",
        allowInput: true,
        onChange: function(selectedDates, dateStr) {
            // Update date_from max date
            if (dateStr) {
                dateFromPicker.set('maxDate', dateStr);
            }
        }
    });
    
    const dateFromPicker = flatpickr("#date_from", {
        dateFormat: "Y-m-d",
        allowInput: true,
        onChange: function(selectedDates, dateStr) {
            // Update date_to min date
            if (dateStr) {
                dateToPicker.set('minDate', dateStr);
            }
        }
    });

    // Auto-submit form on filter change
    $('#ingredient, #batch, #change_type, #status').change(function() {
        $('#filterForm').submit();
    });

    // Form validation for date range
    $('#filterForm').submit(function(e) {
        const dateFrom = $('#date_from').val();
        const dateTo = $('#date_to').val();
        
        if (dateFrom && dateTo && dateFrom > dateTo) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Date Range',
                text: 'Date "From" cannot be greater than Date "To"'
            });
            return false;
        }
        return true;
    });

    // Real-time date validation
    $('#date_from, #date_to').on('change', function() {
        const dateFrom = $('#date_from').val();
        const dateTo = $('#date_to').val();
        
        if (dateFrom && dateTo && dateFrom > dateTo) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Date Range',
                text: 'Date "From" cannot be greater than Date "To". Please correct the dates.',
                showConfirmButton: false,
                timer: 3000
            });
        }
    });

    // Add tooltips for truncated notes
    $('[title]').tooltip();
});
</script>

<?php
$content = ob_get_clean();
include('../includes/layout.php');
?> 