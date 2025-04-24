// Wait for jQuery to be loaded
function initializeIngredientFields() {
    if (typeof jQuery === 'undefined') {
        console.log('Waiting for jQuery...');
        setTimeout(initializeIngredientFields, 100);
        return;
    }

    console.log('jQuery loaded, version:', jQuery.fn.jquery);

    // Wait for document ready
    jQuery(function($) {
        console.log("Ingredient fields initializing...");
        
        // Debug: Check if elements exist
        console.log("Add button exists:", $("#add-ingredient").length > 0);
        console.log("Container exists:", $("#ingredients-container").length > 0);
        console.log("Initial rows:", $(".ingredient-row").length);
        
        // Initialize Select2 if available
        if (typeof $.fn.select2 !== 'undefined') {
            try {
                $(".ingredient-select").select2({
                    theme: "bootstrap4"
                });
                console.log("Select2 initialized");
            } catch (e) {
                console.error("Select2 initialization failed:", e);
            }
        } else {
            console.log("Select2 not available");
        }

        // Add ingredient button click handler
        $("#add-ingredient").on("click", function(e) {
            console.log("Add ingredient clicked");
            e.preventDefault();
            
            // Clone the first ingredient row
            var newRow = $(".ingredient-row:first").clone();
            console.log("Row cloned");
            
            // Clear the values
            newRow.find("select").val("");
            newRow.find("input").val("");
            console.log("Values cleared");
            
            // Destroy existing Select2 if it exists
            if (typeof $.fn.select2 !== 'undefined') {
                newRow.find('.ingredient-select').select2('destroy');
            }
            
            // Add to container
            $("#ingredients-container").append(newRow);
            console.log("New row added to container");
            
            // Reinitialize Select2 for the new row if available
            if (typeof $.fn.select2 !== 'undefined') {
                try {
                    newRow.find(".ingredient-select").select2({
                        theme: "bootstrap4"
                    });
                    console.log("Select2 reinitialized for new row");
                } catch (e) {
                    console.error("Select2 reinitialization failed:", e);
                }
            }
        });

        // Remove ingredient button handler
        $("#ingredients-container").on("click", ".remove-ingredient", function(e) {
            console.log("Remove clicked");
            e.preventDefault();
            
            // Only remove if there is more than one ingredient row
            if ($(".ingredient-row").length > 1) {
                // Destroy Select2 if it exists
                if (typeof $.fn.select2 !== 'undefined') {
                    $(this).closest('.ingredient-row').find('.ingredient-select').select2('destroy');
                }
                $(this).closest(".ingredient-row").remove();
                console.log("Row removed");
            } else {
                alert("You must have at least one ingredient!");
            }
        });

        // Handle ingredient selection
        $("#ingredients-container").on("change", ".ingredient-select", function(e) {
            console.log("Ingredient selected");
            
            // Get the selected option unit
            var unit = $(this).find(":selected").data("unit") || "";
            console.log("Selected unit:", unit);
            
            // Set the unit field
            $(this).closest(".row").find("input[name=\"units[]\"]").val(unit);
            console.log("Unit field updated");
        });

        console.log("Ingredient fields initialization complete");
    });
}

// Start initialization
initializeIngredientFields(); 