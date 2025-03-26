document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable only if not already initialized
    if (typeof $.fn !== 'undefined' && $.fn.DataTable) {
        var table = $('.table');
        if (!$.fn.DataTable.isDataTable(table)) {
            table.DataTable({
                "paging": true,
                "lengthChange": false,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true,
                "destroy": true // Allow table to be destroyed and recreated
            });
        }
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);

    // Character counter for textarea
    var addressTextarea = document.getElementById('address');
    if (addressTextarea) {
        addressTextarea.addEventListener('input', function() {
            var maxLength = this.getAttribute('maxlength');
            var currentLength = this.value.length;
            var remaining = maxLength - currentLength;
            
            // Update or create character counter
            var counter = this.nextElementSibling;
            counter.textContent = remaining + ' characters remaining';
            
            // Change color when approaching limit
            if (remaining < 50) {
                counter.classList.remove('text-muted');
                counter.classList.add('text-warning');
            } else {
                counter.classList.remove('text-warning');
                counter.classList.add('text-muted');
            }
        });

        // Auto-expand height based on content
        addressTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            var newHeight = Math.min(this.scrollHeight, 200); // Max height of 200px
            this.style.height = newHeight + 'px';
        });
    }
}); 