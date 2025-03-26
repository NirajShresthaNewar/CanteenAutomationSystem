document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    if (typeof $.fn !== 'undefined' && $.fn.DataTable) {
        $('.table').each(function() {
            if (!$.fn.DataTable.isDataTable(this)) {
                $(this).DataTable({
                    "paging": true,
                    "lengthChange": false,
                    "searching": true,
                    "ordering": true,
                    "info": true,
                    "autoWidth": false,
                    "responsive": true
                });
            }
        });
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);

    // Confirm before rejecting vendor
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (this.querySelector('input[name="action"]').value === 'reject') {
                if (!confirm('Are you sure you want to reject this vendor?')) {
                    e.preventDefault();
                }
            }
        });
    });
}); 