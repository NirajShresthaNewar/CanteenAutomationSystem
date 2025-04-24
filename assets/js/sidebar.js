$(document).ready(function() {
    // Prevent AdminLTE's default click handlers
    $('.nav-sidebar .nav-link').off('click');
    
    // Initialize active states based on current URL
    setInitialActiveStates();
    
    // Handle menu item clicks
    $('.nav-sidebar .nav-link').on('click', function(e) {
        const $link = $(this);
        const $item = $link.parent('.nav-item');
        
        // If item has submenu, handle toggle
        if ($item.has('.nav-treeview').length) {
            e.preventDefault();
            toggleSubmenu($item);
        }
    });
    
    // Handle sidebar collapse
    $('[data-widget="pushmenu"]').on('click', function() {
        // Close all submenus when sidebar collapses
        setTimeout(() => {
            if ($('body').hasClass('sidebar-collapse')) {
                $('.nav-sidebar .menu-open').removeClass('menu-open');
                $('.nav-treeview').slideUp(200);
            }
        }, 300);
    });
});

function toggleSubmenu($item) {
    const $submenu = $item.children('.nav-treeview');
    const isOpen = $item.hasClass('menu-open');
    
    // Close other open menus at the same level
    const $siblings = $item.siblings('.menu-open');
    $siblings.removeClass('menu-open');
    $siblings.find('.nav-treeview').slideUp(200);
    
    // Toggle current menu
    if (isOpen) {
        $item.removeClass('menu-open');
        $submenu.slideUp(200);
    } else {
        $item.addClass('menu-open');
        $submenu.slideDown(200);
    }
}

function setInitialActiveStates() {
    const currentPath = window.location.pathname;
    
    // Remove any existing active classes
    $('.nav-sidebar .nav-link').removeClass('active');
    
    // Find and activate the matching link
    $('.nav-sidebar .nav-link').each(function() {
        const href = $(this).attr('href');
        if (href && currentPath.includes(href)) {
            const $link = $(this);
            $link.addClass('active');
            
            // Open parent menus if this is a submenu item
            const $parents = $link.parents('.nav-item');
            $parents.addClass('menu-open');
            $parents.children('.nav-treeview').show();
        }
    });
} 