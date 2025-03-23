<?php
$page_title = 'Order Reports';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Order Reports</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-right">
                    <button type="button" class="btn btn-success mr-2" id="exportOrders">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#filterOrdersModal">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>0</h3>
                        <p>Total Orders</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-cart"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>₱0.00</h3>
                        <p>Total Revenue</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-cash"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>0</h3>
                        <p>Pending Orders</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-clock"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>0</h3>
                        <p>Cancelled Orders</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Orders Overview</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="ordersChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Order Status Distribution</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Orders Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Order Details</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="text-center">No orders found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Filter Orders Modal -->
<div class="modal fade" id="filterOrdersModal" tabindex="-1" role="dialog" aria-labelledby="filterOrdersModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filterOrdersModalLabel">Filter Orders</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="filterOrdersForm">
                    <div class="form-group">
                        <label for="orderDateRange">Date Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="orderStartDate">
                            <div class="input-group-append">
                                <span class="input-group-text">to</span>
                            </div>
                            <input type="date" class="form-control" id="orderEndDate">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="orderStatus">Status</label>
                        <select class="form-control" id="orderStatus">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="orderType">Order Type</label>
                        <select class="form-control" id="orderType">
                            <option value="">All Types</option>
                            <option value="individual">Individual</option>
                            <option value="bulk">Bulk</option>
                            <option value="subscription">Subscription</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyOrderFilter">Apply Filter</button>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Orders Chart
const ordersCtx = document.getElementById('ordersChart').getContext('2d');
const ordersChart = new Chart(ordersCtx, {
    type: 'line',
    data: {
        labels: [],
        datasets: [{
            label: 'Orders',
            data: [],
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Processing', 'Completed', 'Cancelled'],
        datasets: [{
            data: [0, 0, 0, 0],
            backgroundColor: [
                'rgb(255, 205, 86)',
                'rgb(54, 162, 235)',
                'rgb(75, 192, 192)',
                'rgb(255, 99, 132)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../../includes/layout.php';
?> 