<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
    exit();
}

$pageTitle = "Production History";

// Get vendor ID
try {
    $stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        throw new Exception("Vendor not found!");
    }
    $vendor_id = $vendor['id'];

    // Get production batches for this vendor
    $stmt = $conn->prepare("
        SELECT 
            pb.*, 
            r.name as recipe_name,
            u.username as produced_by_name
        FROM production_batches pb
        JOIN recipes r ON r.id = pb.recipe_id
        JOIN menu_items m ON m.recipe_id = r.id
        JOIN users u ON u.id = pb.produced_by
        WHERE m.vendor_id = ?
        ORDER BY pb.production_date DESC, pb.created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: dashboard.php");
    exit();
}

// Add necessary scripts
$additionalStyles = '
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
';

$additionalScripts = '
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $("#productionTable").DataTable({
        responsive: true,
        order: [[2, "desc"]],
        columnDefs: [
            { targets: -1, orderable: false }
        ]
    });

    // View batch details
    $(".view-batch").click(function() {
        var batchId = $(this).data("id");
        // Load batch details via AJAX
        $.get("get_batch_details.php?id=" + batchId, function(data) {
            $("#batchDetailsBody").html(data);
            $("#batchDetailsModal").modal("show");
        });
    });
});
</script>';

ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Production History</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Production History</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Production Batches</h3>
                <div class="card-tools">
                    <a href="record_production.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Record New Production
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table id="productionTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Batch Number</th>
                            <th>Recipe</th>
                            <th>Production Date</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Produced By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                            <td><?php echo htmlspecialchars($batch['recipe_name']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($batch['production_date'])); ?></td>
                            <td><?php echo number_format($batch['quantity_produced']); ?> servings</td>
                            <td>
                                <?php echo $batch['expiry_date'] ? date('Y-m-d', strtotime($batch['expiry_date'])) : 'N/A'; ?>
                            </td>
                            <td><?php echo htmlspecialchars($batch['produced_by_name']); ?></td>
                            <td>
                                <button type="button" class="btn btn-info btn-sm view-batch" 
                                        data-id="<?php echo $batch['id']; ?>">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Batch Details Modal -->
<div class="modal fade" id="batchDetailsModal" tabindex="-1" role="dialog" aria-labelledby="batchDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchDetailsModalLabel">Batch Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="batchDetailsBody">
                Loading...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?> 