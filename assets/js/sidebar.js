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

            // Toggle the clicked menu
            if (submenu) {
                if (parent.classList.contains('menu-open')) {
                    // Close submenu
                    parent.classList.remove('menu-open');
                    $(submenu).slideUp(300, function() {
                        arrow?.classList.remove('rotate-90');
                    });
                } else {
                    // Open submenu
                    parent.classList.add('menu-open');
                    $(submenu).slideDown(300);
                    arrow?.classList.add('rotate-90');
                }
            }
        });
    });

    // Set active state based on current URL
    const currentPath = window.location.pathname;
    const currentPage = currentPath.split('/').pop();
    
    // First, remove all active classes
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });

    // Then set active class only for the current page
    const currentLink = document.querySelector(`.nav-link[href*="${currentPage}"]`);
    if (currentLink) {
        currentLink.classList.add('active');
        
        // Handle parent menu
        const parentMenu = currentLink.closest('.has-treeview');
        if (parentMenu) {
            parentMenu.classList.add('menu-open');
            const treeView = parentMenu.querySelector('.nav-treeview');
            if (treeView) {
                treeView.style.display = 'block';
            }
            const arrow = parentMenu.querySelector('.fa-angle-left');
            if (arrow) {
                arrow.classList.add('rotate-90');
            }
        }
    }

    // Add CSS for arrow rotation
    const style = document.createElement('style');
    style.textContent = `
        .rotate-90 {
            transform: rotate(-90deg);
        }
        .nav-item.has-treeview > a .fa-angle-left {
            transition: transform 0.3s ease;
        }
    `;
    document.head.appendChild(style);
}); 