<?php
// Get ingredients for the dropdown
try {
    $stmt = $conn->prepare("SELECT id, name, unit FROM ingredients");
    $stmt->execute();
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ingredients = [];
}
?>

<!-- Debug: Start of ingredient fields -->
<div class="mb-3">
    <label class="form-label">Ingredients <span class="text-danger">*</span></label>
    <div id="ingredients-container">
        <!-- Initial ingredient row -->
        <div class="ingredient-row mb-3">
            <div class="row">
                <div class="col-md-3">
                    <label>Ingredient</label>
                    <select name="ingredients[]" class="form-control ingredient-select" required>
                        <option value="">Select Ingredient</option>
                        <?php foreach ($ingredients as $ingredient): ?>
                        <option value="<?php echo $ingredient['id']; ?>" 
                                data-unit="<?php echo htmlspecialchars($ingredient['unit']); ?>">
                            <?php echo htmlspecialchars($ingredient['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Quantity</label>
                    <input type="number" name="quantities[]" class="form-control" 
                           placeholder="Quantity" step="0.01" min="0" required>
                </div>
                <div class="col-md-3">
                    <label>Unit</label>
                    <input type="text" name="units[]" class="form-control" 
                           placeholder="Unit" readonly>
                </div>
                <div class="col-md-2">
                    <label>Notes</label>
                    <input type="text" name="notes[]" class="form-control" 
                           placeholder="Notes">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger remove-ingredient" style="margin-top: 25px;">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <button type="button" class="btn btn-success mt-3" id="add-ingredient">
        <i class="fas fa-plus"></i> Add Ingredient
    </button>
</div>

<!-- Debug: Check jQuery availability -->
<script>
console.log('Ingredient fields script loading...');
if (typeof jQuery === 'undefined') {
    console.error('jQuery is not loaded!');
} else {
    console.log('jQuery version:', jQuery.fn.jquery);
}
</script>

<!-- Ingredient Fields JavaScript -->
<script>
$(document).ready(function() {
    console.log("Ingredient fields initialized");
    
    // Debug: Check if elements exist
    console.log("Add button exists:", $("#add-ingredient").length > 0);
    console.log("Container exists:", $("#ingredients-container").length > 0);
    console.log("Initial rows:", $(".ingredient-row").length);
    
    // Initialize Select2 for ingredient selects
    try {
        $(".ingredient-select").select2({
            theme: "bootstrap4"
        });
        console.log("Select2 initialized");
    } catch (e) {
        console.error("Select2 initialization failed:", e);
    }

    // Add ingredient button click handler
    $("#add-ingredient").on("click", function(e) {
        console.log("Add ingredient clicked");
        e.preventDefault();
        e.stopPropagation();
        
        // Clone the first ingredient row
        var newRow = $(".ingredient-row:first").clone();
        console.log("Row cloned");
        
        // Clear the values
        newRow.find("select").val("");
        newRow.find("input").val("");
        console.log("Values cleared");
        
        // Add to container
        $("#ingredients-container").append(newRow);
        console.log("New row added to container");
        
        // Reinitialize Select2 for the new row
        try {
            newRow.find(".ingredient-select").select2({
                theme: "bootstrap4"
            });
            console.log("Select2 reinitialized for new row");
        } catch (e) {
            console.error("Select2 reinitialization failed:", e);
        }
    });

    // Remove ingredient button handler (using event delegation)
    $("#ingredients-container").on("click", ".remove-ingredient", function(e) {
        console.log("Remove clicked");
        e.preventDefault();
        e.stopPropagation();
        
        // Only remove if there is more than one ingredient row
        if ($(".ingredient-row").length > 1) {
            $(this).closest(".ingredient-row").remove();
            console.log("Row removed");
        } else {
            alert("You must have at least one ingredient!");
        }
    });

    // Handle ingredient selection
    $("#ingredients-container").on("change", ".ingredient-select", function(e) {
        console.log("Ingredient selected");
        e.preventDefault();
        e.stopPropagation();
        
        // Get the selected option unit
        var unit = $(this).find(":selected").data("unit") || "";
        console.log("Selected unit:", unit);
        
        // Set the unit field
        $(this).closest(".row").find("input[name=\"units[]\"]").val(unit);
        console.log("Unit field updated");
    });
});
</script> 