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
    
    <!-- Additional page-specific styles -->
    <?php if (!empty($additionalStyles)) echo $additionalStyles; ?>

    <style>
        /* Sidebar specific styles */
        .nav-sidebar .nav-link p {
            font-size: 14px;
        }
        
        .nav-sidebar .nav-icon {
            font-size: 16px;
        }
        
        .nav-treeview {
            display: none;
        }
        
        .menu-open > .nav-treeview {
            display: block;
        }
        
        .nav-sidebar .nav-link > .right {
            transition: transform .3s ease-in-out;
        }
        
        .menu-open > .nav-link > .right {
            transform: rotate(-90deg);
        }
        
        .nav-sidebar .nav-treeview .nav-link {
            padding-left: 35px;
        }
        
        /* Fix for collapsed sidebar */
        .sidebar-collapse .main-sidebar:not(.sidebar-focused) .nav-treeview {
            display: none !important;
        }
        
        .sidebar-collapse .main-sidebar.sidebar-focused .nav-treeview,
        .sidebar-collapse .main-sidebar:hover .nav-treeview {
            display: none;
        }
        
        .sidebar-collapse .main-sidebar.sidebar-focused .menu-open > .nav-treeview,
        .sidebar-collapse .main-sidebar:hover .menu-open > .nav-treeview {
            display: block;
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

<!-- Core Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@1.13.1/js/OverlayScrollbars.min.js"></script>

<!-- Additional page-specific scripts -->
<?php if (!empty($additionalScripts)) echo $additionalScripts; ?>

<script src="../assets/js/sidebar.js"></script>
</body>
</html> 