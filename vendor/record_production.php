<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: ../auth/login.php");
    exit();
}

$pageTitle = "Record Production";

// Get vendor ID
try {
    $stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        throw new Exception("Vendor not found!");
    }
    $vendor_id = $vendor['id'];

    // Get recipes for this vendor
    $stmt = $conn->prepare("
        SELECT DISTINCT r.id, r.name, r.serving_size
        FROM recipes r
        JOIN menu_items m ON m.recipe_id = r.id
        WHERE m.vendor_id = ?
        ORDER BY r.name
    ");
    $stmt->execute([$vendor_id]);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: dashboard.php");
    exit();
}

// Add necessary scripts
$additionalStyles = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
';

$additionalScripts = '
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $("#recipe").select2({
        theme: "bootstrap-5",
        placeholder: "Select Recipe"
    });

    // Calculate total servings when either input changes
    $("#servings_per_batch, #number_of_batches").on("input", function() {
        calculateTotalServings();
    });

    function calculateTotalServings() {
        var servingsPerBatch = parseInt($("#servings_per_batch").val()) || 0;
        var numberOfBatches = parseInt($("#number_of_batches").val()) || 0;
        var totalServings = servingsPerBatch * numberOfBatches;
        $("#total_servings").text(totalServings.toLocaleString() + " servings");
    }

    // Form validation
    $("#productionForm").on("submit", function(e) {
        var servingsPerBatch = parseInt($("#servings_per_batch").val());
        var numberOfBatches = parseInt($("#number_of_batches").val());
        
        if (!servingsPerBatch || servingsPerBatch <= 0) {
            e.preventDefault();
            alert("Please enter a valid number of servings per batch");
            return false;
        }
        
        if (!numberOfBatches || numberOfBatches <= 0) {
            e.preventDefault();
            alert("Please enter a valid number of batches");
            return false;
        }
        
        return true;
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
                <h1 class="m-0">Record Production</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="production_history.php">Production History</a></li>
                    <li class="breadcrumb-item active">Record Production</li>
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
                <h3 class="card-title">Record New Production</h3>
            </div>
            <div class="card-body">
                <form id="productionForm" action="process_production.php" method="POST">
                    <div class="form-group">
                        <label for="recipe">Recipe <span class="text-danger">*</span></label>
                        <select class="form-control" id="recipe" name="recipe" required>
                            <option value="">Select Recipe</option>
                            <?php foreach ($recipes as $recipe): ?>
                            <option value="<?php echo $recipe['id']; ?>">
                                <?php echo htmlspecialchars($recipe['name']); ?>
                                (Serving Size: <?php echo $recipe['serving_size']; ?> person(s))
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="servings_per_batch">Servings per Batch <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="servings_per_batch" name="servings_per_batch" 
                                       min="1" required placeholder="Number of servings produced in each batch">
                                <small class="form-text text-muted">Number of servings produced in each batch</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="number_of_batches">Number of Batches <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="number_of_batches" name="number_of_batches" 
                                       min="1" value="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Total Servings to be Produced:</label>
                        <div class="h4 text-primary" id="total_servings">0 servings</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="production_date">Production Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="production_date" name="production_date" 
                                       required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                                <small class="form-text text-muted">Optional - Leave blank if not applicable</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="production_notes">Production Notes</label>
                        <textarea class="form-control" id="production_notes" name="production_notes" 
                                  rows="3" placeholder="Any special notes about this production batch"></textarea>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Record Production</button>
                        <a href="production_history.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?> 