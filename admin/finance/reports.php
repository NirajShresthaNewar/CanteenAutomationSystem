<?php
$page_title = 'Financial Reports';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Financial Reports</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-right">
                    <button type="button" class="btn btn-success mr-2" id="exportReport">
                        <i class="bi bi-download"></i> Export Report
                    </button>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#dateRangeModal">
                        <i class="bi bi-calendar"></i> Select Date Range
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
                        <h3>₱0.00</h3>
                        <p>Total Revenue</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-cash"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
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
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>₱0.00</h3>
                        <p>Average Order Value</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>₱0.00</h3>
                        <p>Total Refunds</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Revenue Overview</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Revenue by Category</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Reports -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Detailed Financial Report</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Revenue</th>
                                    <th>Orders</th>
                                    <th>Average Order Value</th>
                                    <th>Refunds</th>
                                    <th>Net Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center">No data available</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Date Range Modal -->
<div class="modal fade" id="dateRangeModal" tabindex="-1" role="dialog" aria-labelledby="dateRangeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dateRangeModalLabel">Select Date Range</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="dateRangeForm">
                    <div class="form-group">
                        <label for="reportStartDate">Start Date</label>
                        <input type="date" class="form-control" id="reportStartDate" required>
                    </div>
                    <div class="form-group">
                        <label for="reportEndDate">End Date</label>
                        <input type="date" class="form-control" id="reportEndDate" required>
                    </div>
                    <div class="form-group">
                        <label for="reportType">Report Type</label>
                        <select class="form-control" id="reportType">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="generateReport">Generate Report</button>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: [],
        datasets: [{
            label: 'Revenue',
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

// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryChart = new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: [],
        datasets: [{
            data: [],
            backgroundColor: [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 205, 86)',
                'rgb(75, 192, 192)'
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