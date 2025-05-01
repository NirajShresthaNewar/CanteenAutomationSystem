document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    // Initialize DataTables
    if (typeof $.fn !== 'undefined' && $.fn.DataTable) {
        console.log('DataTables is available');
        $('.table').each(function() {
            if (!$.fn.DataTable.isDataTable(this)) {
                console.log('Initializing DataTable');
                $(this).DataTable({
                    "paging": true,
                    "lengthChange": false,
                    "searching": true,
                    "ordering": true,
                    "info": true,
                    "autoWidth": false,
                    "responsive": true,
                    "drawCallback": function(settings) {
                        // Check button visibility after table draw
                        console.log('Table drawn, checking buttons');
                        $('.action-button').each(function() {
                            console.log('Button display style:', $(this).css('display'));
                        });
                    }
                });
            }
        });
    } else {
        console.log('DataTables not available');
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