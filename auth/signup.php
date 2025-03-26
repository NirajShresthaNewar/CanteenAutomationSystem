<!-- Remove these fields -->
<!-- <div class="form-group">
    <label for="location">Location</label>
    <input type="text" class="form-control" name="location">
</div>
<div class="form-group">
    <label for="address">Address</label>
    <input type="text" class="form-control" name="address">
</div> -->

<!-- Add this school selection dropdown -->
<div class="form-group">
    <label for="school_id">Select School *</label>
    <select class="form-control" id="school_id" name="school_id" required>
        <option value="">Select a School</option>
        <?php
        try {
            $stmt = $conn->query("SELECT id, name, address FROM schools");
            while ($school = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<option value='" . $school['id'] . "'>" . $school['name'] . " (" . $school['address'] . ")</option>";
            }
        } catch(PDOException $e) {
            echo "<option value=''>Error loading schools</option>";
        }
        ?>
    </select>
</div>

<!-- Update vendor-specific fields -->
<div class="vendor-fields" style="display: none;">
    <div class="form-group">
        <label for="license_number">License Number</label>
        <input type="text" class="form-control" id="license_number" name="license_number">
    </div>
    <div class="form-group">
        <label for="opening_hours">Opening Hours</label>
        <input type="text" class="form-control" id="opening_hours" name="opening_hours" 
               placeholder="e.g., 9:00 AM - 5:00 PM">
    </div>
</div>

<script>
$(document).ready(function() {
    $('#role').change(function() {
        // Hide all role-specific fields first
        $('.vendor-fields, .worker-fields, .student-fields, .staff-fields').hide();
        
        // Show fields based on selected role
        var selectedRole = $(this).val();
        switch(selectedRole) {
            case 'vendor':
                $('.vendor-fields').show();
                break;
            case 'worker':
                $('.worker-fields').show();
                break;
            case 'student':
                $('.student-fields').show();
                break;
            case 'staff':
                $('.staff-fields').show();
                break;
        }
        
        // School selection is always visible now
        $('#school_id').prop('disabled', false);
    });
});
</script> 