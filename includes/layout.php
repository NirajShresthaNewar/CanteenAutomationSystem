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
    <!-- Add SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Add Sidebar CSS -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    
    <!-- Additional page-specific styles -->
    <?php if (!empty($additionalStyles)) echo $additionalStyles; ?>

    <style>
        /* Kitchen Orders specific styles */
        .order-card {
            transition: all 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .badge {
            font-size: 0.9em;
            padding: 0.5em 0.8em;
        }
        .table td {
            vertical-align: middle;
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            margin: 0.1rem;
        }
        .order-time {
            font-size: 0.9em;
            color: #6c757d;
        }
        .order-items {
            font-size: 0.9em;
            line-height: 1.4;
        }
        .modal-lg {
            max-width: 800px;
        }
    </style>

    <!-- Core Scripts - Load jQuery first -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Add SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
        body {
            overflow-x: hidden;
        }
        .main-sidebar {
            width: 250px;
        }
        .content-wrapper {
            margin-left: 250px;
        }
        .sidebar-collapse .main-sidebar {
            margin-left: -250px;
        }
        .sidebar-collapse .content-wrapper {
            margin-left: 0;
        }
        @media (max-width: 991.98px) {
            .main-sidebar {
                margin-left: -250px;
            }
            .content-wrapper {
                margin-left: 0;
            }
            .sidebar-open .main-sidebar {
                margin-left: 0;
            }
        }
        .nav-sidebar .nav-link p {
            margin-left: 10px;
        }
        .nav-sidebar > .nav-item .nav-icon {
            width: 20px;
            text-align: center;
        }

        /* Badge styles */
        .badge {
            padding: 0.4em 0.8em;
            font-size: 85%;
            font-weight: 500;
            border-radius: 0.25rem;
        }
        .bg-purple {
            background-color: #6f42c1;
            color: #fff;
        }
        .bg-success {
            background-color: #28a745;
            color: #fff;
        }
        .bg-info {
            background-color: #17a2b8;
            color: #fff;
        }
        .bg-warning {
            background-color: #ffc107;
            color: #000;
        }
        .bg-danger {
            background-color: #dc3545;
            color: #fff;
        }
        .bg-primary {
            background-color: #007bff;
            color: #fff;
        }
        .bg-secondary {
            background-color: #6c757d;
            color: #fff;
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

<!-- Assign Worker Modal -->
<div class="modal fade" id="assignWorkerModal" tabindex="-1" role="dialog" aria-labelledby="assignWorkerModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignWorkerModalLabel">Assign Worker</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="assignWorkerForm" method="POST" action="../vendor/assign_worker.php">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="icon fas fa-info"></i> Order Details</h6>
                        <p id="orderDetailsText"></p>
                    </div>
                    <input type="hidden" name="order_id" id="assignOrderId">
                    <div class="form-group">
                        <label for="worker_id">Select Worker</label>
                        <select class="form-control" id="worker_id" name="worker_id" required>
                            <option value="">-- Select Worker --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Worker</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" role="dialog" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationModalLabel">Order Ready!</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="notificationMessage">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" id="viewOrderBtn">View Order</a>
            </div>
        </div>
    </div>
</div>

<!-- Add this right before the closing body tag -->
<script>
// Function to handle assign worker button click
function handleAssignWorker(orderId) {
    // Clear previous data
    document.getElementById('assignOrderId').value = '';
    document.getElementById('orderDetailsText').textContent = '';
    document.getElementById('worker_id').innerHTML = '<option value="">-- Select Worker --</option>';

    // Fetch workers and order details
    fetch(`../vendor/assign_worker.php?order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Set order details
                document.getElementById('assignOrderId').value = data.order.id;
                document.getElementById('orderDetailsText').textContent = 
                    `Order #${data.order.receipt_number} - Customer: ${data.order.customer_name}`;

                // Populate workers dropdown
                const select = document.getElementById('worker_id');
                data.workers.forEach(worker => {
                    const option = document.createElement('option');
                    option.value = worker.id;
                    option.textContent = `${worker.username} (${worker.contact_number})`;
                    select.appendChild(option);
                });

                // Show modal
                $('#assignWorkerModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load worker data'
            });
        });
}

// Handle form submission
document.getElementById('assignWorkerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../vendor/assign_worker.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        $('#assignWorkerModal').modal('hide');
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: data.message
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        $('#assignWorkerModal').modal('hide');
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to assign worker'
        });
    });
});
</script>

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
<script>
// Function to check for new notifications
function checkNotifications() {
    fetch('../student/check_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.hasNotification) {
                // Update modal content
                document.getElementById('notificationMessage').textContent = data.message;
                document.getElementById('viewOrderBtn').href = data.link;
                
                // Show notification modal
                $('#notificationModal').modal('show');
                
                // Play notification sound
                let audio = new Audio('../assets/notification.mp3');
                audio.play();
                
                // Request permission for browser notification
                if (Notification.permission === "granted") {
                    new Notification("Order Ready!", {
                        body: data.message,
                        icon: "../assets/favicon.ico"
                    });
                } else if (Notification.permission !== "denied") {
                    Notification.requestPermission().then(permission => {
                        if (permission === "granted") {
                            new Notification("Order Ready!", {
                                body: data.message,
                                icon: "../assets/favicon.ico"
                            });
                        }
                    });
                }
            }
        })
        .catch(error => console.error('Error checking notifications:', error));
}

// Check for notifications every 30 seconds
setInterval(checkNotifications, 30000);

// Check immediately on page load
document.addEventListener('DOMContentLoaded', checkNotifications);
</script>
<?php endif; ?>
</body>
</html> 