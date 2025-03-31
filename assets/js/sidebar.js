document.addEventListener('DOMContentLoaded', function() {
    // Initialize AdminLTE components
    if (typeof $.fn.overlayScrollbars !== 'undefined') {
        $('.sidebar').overlayScrollbars({
            className: 'os-theme-light',
            sizeAutoCapable: true,
            scrollbars: {
                autoHide: 'leave',
                clickScrolling: true
            }
        });
    }

    // Handle submenu toggle
    const menuItems = document.querySelectorAll('.nav-item.has-treeview > a');
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const parent = this.parentElement;
            const submenu = parent.querySelector('.nav-treeview');
            const arrow = this.querySelector('.fa-angle-left');

            if (submenu) {
                if (parent.classList.contains('menu-open')) {
                    // Close submenu
                    parent.classList.remove('menu-open');
                    $(submenu).slideUp(300, function() {
                        if (arrow) arrow.style.transform = 'rotate(0deg)';
                    });
                } else {
                    // Open submenu
                    parent.classList.add('menu-open');
                    $(submenu).slideDown(300);
                    if (arrow) arrow.style.transform = 'rotate(-90deg)';
                }
            }
        });
    });

    // Set active state based on current URL
    const currentPath = window.location.pathname;
    const currentPage = currentPath.split('/').pop();
    
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.endsWith(currentPage)) {
            link.classList.add('active');
            
            const parentMenu = link.closest('.has-treeview');
            if (parentMenu) {
                parentMenu.classList.add('menu-open');
                const treeView = parentMenu.querySelector('.nav-treeview');
                if (treeView) {
                    treeView.style.display = 'block';
                }
                const arrow = parentMenu.querySelector('.fa-angle-left');
                if (arrow) {
                    arrow.style.transform = 'rotate(-90deg)';
                }
            }
        }
    });
}); 