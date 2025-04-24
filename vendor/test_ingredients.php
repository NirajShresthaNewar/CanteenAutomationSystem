<?php
session_start();
require_once '../connection/db_connection.php';

// Get ingredients for testing
try {
    $stmt = $conn->prepare("SELECT id, name, unit FROM ingredients");
    $stmt->execute();
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ingredients = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Ingredients Form</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        .ingredient-row {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .remove-btn {
            margin-top: 25px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Test Ingredients Form</h2>
        
        <form id="testForm" method="post" action="process_test.php">
            <!-- Basic form fields -->
            <div class="mb-3">
                <label class="form-label">Recipe Name</label>
                <input type="text" class="form-control" name="recipe_name">
            </div>

            <!-- Ingredients Section -->
            <div class="mb-3">
                <label class="form-label">Ingredients</label>
                <div id="ingredientContainer">
                    <!-- Initial ingredient row -->
                    <div class="ingredient-row">
                        <div class="row">
                            <div class="col-md-4">
                                <label>Ingredient</label>
                                <select name="ingredients[]" class="form-control">
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
                                <input type="number" name="quantities[]" class="form-control" step="0.01">
                            </div>
                            <div class="col-md-3">
                                <label>Unit</label>
                                <input type="text" name="units[]" class="form-control" readonly>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-danger remove-btn">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-success mt-3" id="addIngredient">
                    Add Ingredient
                </button>
            </div>

            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function() {
        // Debug log
        console.log('Document ready');

        // Add ingredient button click handler
        $('#addIngredient').click(function() {
            console.log('Add ingredient clicked');
            
            // Clone the first ingredient row
            var newRow = $('.ingredient-row:first').clone();
            
            // Clear the values
            newRow.find('select').val('');
            newRow.find('input').val('');
            
            // Add to container
            $('#ingredientContainer').append(newRow);
            
            console.log('New row added');
        });

        // Remove ingredient button handler (using event delegation)
        $('#ingredientContainer').on('click', '.remove-btn', function() {
            console.log('Remove clicked');
            
            // Only remove if there's more than one ingredient row
            if ($('.ingredient-row').length > 1) {
                $(this).closest('.ingredient-row').remove();
                console.log('Row removed');
            } else {
                alert('You must have at least one ingredient!');
            }
        });

        // Handle ingredient selection
        $('#ingredientContainer').on('change', 'select', function() {
            console.log('Ingredient selected');
            
            // Get the selected option's unit
            var unit = $(this).find(':selected').data('unit') || '';
            
            // Set the unit field
            $(this).closest('.row').find('input[name="units[]"]').val(unit);
            
            console.log('Unit updated:', unit);
        });
    });
    </script>
</body>
</html> 