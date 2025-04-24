<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Initialize variables for additional styles and scripts
$additionalStyles = '';
$additionalScripts = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Canteen Automation System'; ?></title>
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@1.13.1/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    
    <!-- Additional page-specific styles -->
    <?php if (!empty($additionalStyles)) echo $additionalStyles; ?>

    <!-- Core Scripts - Load jQuery first -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Additional page-specific scripts that depend on jQuery -->
    <?php if (!empty($additionalScripts)) echo $additionalScripts; ?>
    
    <!-- Bootstrap and other core scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@1.13.1/js/OverlayScrollbars.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

    <!-- Add this right after the CSS links and before closing </head> tag -->
    <style>
        /* AdminLTE Sidebar Fixes */
        .main-sidebar {
            width: 250px;
            transition: width .3s ease-in-out;
        }

        .nav-sidebar .nav-treeview {
            display: none;
        }

        .nav-sidebar .menu-open > .nav-treeview {
            display: block;
        }

        /* Fix for collapsed state */
        .sidebar-collapse .main-sidebar .nav-sidebar .nav-treeview {
            display: none !important;
        }

        .sidebar-collapse .main-sidebar:hover .nav-sidebar .menu-open > .nav-treeview {
            display: block !important;
        }

        /* Content margins */
        .content-wrapper {
            margin-left: 250px;
            transition: margin-left .3s ease-in-out;
        }

        .sidebar-collapse .content-wrapper {
            margin-left: 4.6rem;
        }

        /* Transitions */
        .nav-sidebar .nav-link p,
        .nav-sidebar .nav-treeview,
        .nav-sidebar .nav-item i {
            transition: margin-left .3s linear, opacity .3s ease, visibility .3s ease;
        }

        /* Mobile Responsive */
        @media (max-width: 991.98px) {
            .main-sidebar {
                margin-left: -250px;
            }

            .sidebar-open .main-sidebar {
                margin-left: 0;
            }

            .content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
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
          <a href="../<?php echo strtolower($_SESSION['role']); ?>/profile.php" class="dropdown-item">
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

<script>
$(document).ready(function() {
    // Initialize OverlayScrollbars
    if (typeof $.fn.overlayScrollbars !== 'undefined') {
        $('.sidebar').overlayScrollbars({
            className: 'os-theme-light',
            sizeAutoCapable: true,
            scrollbars: {
                autoHide: 'leave'
            }
        });
    }

    // Handle sidebar collapse
    $('[data-widget="pushmenu"]').on('click', function() {
        // Close all submenus when sidebar collapses
        if (!$('body').hasClass('sidebar-collapse')) {
            $('.nav-item.menu-open').each(function() {
                $(this).removeClass('menu-open');
                $(this).find('.nav-treeview').css('display', 'none');
            });
        }
    });

    // Set active states on load
    const currentPath = window.location.pathname;
    const currentPage = currentPath.split('/').pop();
    
    $('.nav-link').each(function() {
        const href = $(this).attr('href');
        if (href && href.endsWith(currentPage)) {
            $(this).addClass('active');
            
            const $parentMenu = $(this).closest('.has-treeview');
            if ($parentMenu.length) {
                $parentMenu.addClass('menu-open');
            }
        }
    });
});
</script>
</body>
</html> 