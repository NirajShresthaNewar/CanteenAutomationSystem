<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Campus Dining | Dashboard</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Overlay Scrollbars -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@1.13.1/css/OverlayScrollbars.min.css">

    <!-- Add this right before the closing </head> tag -->
    <style>
        /* Larger font size for sidebar items */
        .nav-sidebar .nav-link p {
            font-size: 16px !important; 
        }
        
        .nav-sidebar .nav-icon {
            font-size: 18px !important;
        }
        
        /* Improve submenu items */
        .nav-treeview .nav-link p {
            font-size: 15px !important;
        }
        
        /* Better spacing */
        .nav-sidebar .nav-item {
            margin-bottom: 2px;
        }
        
        .nav-sidebar .nav-link {
            padding: 10px 15px;
        }
        
        /* Improved hover effects */
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        /* Better active state */
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
            background-color: #007bff;
            color: #fff;
        }
        
        /* Smoother transitions */
        .nav-sidebar .nav-link, 
        .nav-treeview, 
        .nav-sidebar .nav-treeview .nav-link {
            transition: all 0.3s ease;
        }
        
        /* Better badge positioning */
        .badge-warning.right {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        /* Improved submenu arrow animation */
        .nav-sidebar .nav-link > .right, 
        .nav-sidebar .nav-link > p > .right {
            transition: transform 0.3s ease;
        }
        
        .nav-sidebar .nav-item.menu-open > .nav-link > .right,
        .nav-sidebar .nav-item.menu-open > .nav-link > p > .right {
            transform: rotate(-90deg);
        }
        
        /* Remove text decoration from all sidebar links */
        .nav-sidebar .nav-link,
        .nav-sidebar .nav-treeview .nav-link,
        .sidebar a {
            text-decoration: none !important;
        }
        
        /* Fix the sidebar font to be larger and more readable */
        .nav-sidebar .nav-link p {
            font-size: 16px !important;
            font-weight: 400;
        }
        
        /* Make sidebar main item text larger for better readability */
        .nav-sidebar > .nav-item > .nav-link p {
            font-size: 16px !important;
            font-weight: 500;
        }
        
        /* Fix brand link */
        .brand-link {
            text-decoration: none !important;
        }
        
        /* Fix user name in sidebar */
        .user-panel .info a {
            text-decoration: none !important;
            font-size: 15px;
        }
        
        /* Make sure all sidebar links have proper hover states without underlines */
        .sidebar a:hover {
            text-decoration: none !important;
        }
        
        /* Fix badge styling to not overflow */
        .badge.right,
        .badge-warning.right {
            right: 5px;
            position: absolute;
            font-size: 12px;
            padding: 3px 6px;
        }
        
        /* Style for collapsed sidebar */
        .sidebar-mini.sidebar-collapse .main-sidebar:not(.sidebar-no-expand) .nav-sidebar > .nav-item > .nav-treeview {
            display: none !important;
        }
        
        /* Remove ALL circle markers in the submenu */
        .nav-sidebar .nav-treeview .nav-item i.far.fa-circle,
        .nav-sidebar .nav-treeview .nav-item i.nav-icon {
            display: none !important;
        }
        
        /* Properly align submenu items with no icons */
        .nav-sidebar .nav-treeview .nav-link {
            padding-left: 35px !important;
        }
        
        /* Improve dropdown appearance for submenus */
        .nav-sidebar .nav-treeview {
            padding-top: 5px;
            padding-bottom: 5px;
            background: rgba(0, 0, 0, 0.2);
        }
        
        /* Fix parent menu arrow indicator */
        .nav-sidebar .nav-item .fa-angle-left.right {
            margin-top: 3px;
            transition: transform 0.3s;
        }
        
        .nav-sidebar .nav-item.menu-open .fa-angle-left.right {
            transform: rotate(-90deg);
        }
        
        /* Clean up conflicting transition styles */
        .nav-treeview {
            display: none;
            background: rgba(0, 0, 0, 0.2);
            transition: none; /* Remove the conflicting transitions */
            max-height: none;
            overflow: visible;
        }
        
        /* Fix submenu animation for smoother transitions */
        .nav-sidebar .nav-treeview {
            padding: 0;
            margin: 0;
        }
        
        /* Remove max-height transition which makes it jerky */
        .menu-open > .nav-treeview {
            display: block;
        }
        
        /* Improve arrow rotation speed */
        .nav-sidebar .nav-link > .right, 
        .nav-sidebar .nav-link > p > .right {
            transition: transform 0.2s ease; /* Faster rotation */
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="../index.php" class="nav-link">Home</a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- User account menu -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-user"></i>
          <span class="ml-1"><?php echo $_SESSION['username'] ?? 'User'; ?></span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <a href="../auth/profile.php" class="dropdown-item">
            <i class="fas fa-user-cog mr-2"></i> Profile
          </a>
          <div class="dropdown-divider"></div>
          <a href="../auth/logout.php" class="dropdown-item">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
          </a>
        </div>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="../index.php" class="brand-link">
      <img src="https://adminlte.io/themes/v3/dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light">Campus Dining</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <?php include "../includes/sidebar_" . strtolower($_SESSION['role']) . ".php"; ?>
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0"><?php echo ucfirst($_SESSION['role'] ?? 'Dashboard'); ?></h1>
          </div>
        </div>
      </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <?php echo $content ?? ''; ?>
      </div>
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

  <footer class="main-footer">
    <strong>Copyright &copy; <?php echo date('Y'); ?> Campus Dining.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> 1.0.0
    </div>
  </footer>
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Overlay Scrollbars -->
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@1.13.1/js/OverlayScrollbars.min.js"></script>

<!-- Add this right before the closing </body> tag -->
<script>
$(document).ready(function() {
    // Initialize AdminLTE components
    if (typeof $.fn.overlayScrollbars !== 'undefined') {
        $('.sidebar').overlayScrollbars({
            scrollbars: {
                autoHide: 'leave',
                autoHideDelay: 200
            },
            className: 'os-theme-light'
        });
    }
    
    // Improved submenu toggle function to ensure smooth opening AND closing
    $('.nav-item.has-treeview > .nav-link').on('click', function(e) {
        e.preventDefault();
        var $menuItem = $(this).parent();
        var $submenu = $menuItem.find('.nav-treeview').first();
        
        if ($menuItem.hasClass('menu-open')) {
            // Close this menu - here was the issue, it wasn't properly animating closed
            $submenu.slideUp(200, function() {
                $menuItem.removeClass('menu-open');
                $(this).css('display', ''); // Reset inline style after animation
            });
        } else {
            // Close other open menus at the same level
            var $openMenus = $menuItem.siblings('.menu-open');
            $openMenus.find('.nav-treeview').first().slideUp(200, function() {
                $openMenus.removeClass('menu-open');
                $(this).css('display', ''); // Reset inline style after animation
            });
            
            // Open this menu
            $menuItem.addClass('menu-open');
            $submenu.hide().slideDown(200);
        }
    });
    
    // Set active menu item based on current page
    var currentUrl = window.location.pathname;
    $('.nav-sidebar .nav-link').each(function() {
        var linkUrl = $(this).attr('href');
        if (linkUrl && currentUrl.indexOf(linkUrl) !== -1) {
            $(this).addClass('active');
            var $parent = $(this).closest('.nav-item.has-treeview');
            if ($parent.length) {
                $parent.addClass('menu-open');
                $parent.children('.nav-link').addClass('active');
                $parent.find('> .nav-treeview').show();
            }
        }
    });
    
    // Close all menus on sidebar collapse
    $('[data-widget="pushmenu"]').on('click', function() {
        if (!$('body').hasClass('sidebar-collapse')) {
            // We're collapsing, close all submenus
            setTimeout(function() {
                $('.nav-item.has-treeview.menu-open > .nav-treeview').slideUp(100, function() {
                    $('.nav-item.has-treeview.menu-open').removeClass('menu-open');
                    $(this).css('display', ''); // Reset inline style
                });
            }, 50);
        }
    });
    
    // Fix the AdminLTE TreeView initialization
    try {
        if (typeof $.fn.Treeview === 'function') {
            // Remove the existing binding first to prevent conflicts
            $('.nav-item.has-treeview > .nav-link').off('click');
            
            $('[data-widget="treeview"]').Treeview({
                accordion: true,
                animationSpeed: 200, // Faster animation
                expandSidebar: false,
                sidebarButtonSelector: '[data-widget="pushmenu"]',
                trigger: '.nav-link',
                widget: 'Treeview'
            });
        }
    } catch (e) {
        console.log('Using manual treeview handling');
    }
});
</script>
</body>
</html> 