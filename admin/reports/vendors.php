<?php
$page_title = 'Vendor Reports';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Vendor Reports</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-right">
                    <button type="button" class="btn btn-success mr-2" id="exportVendors">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#filterVendorsModal">
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
                        <p>Total Vendors</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-shop"></i>
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
                        <p>Active Vendors</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>0</h3>
                        <p>Pending Approvals</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Vendor Performance</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Vendor Categories</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vendor Performance Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Vendor Performance Details</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Vendor ID</th>
                            <th>Business Name</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Total Orders</th>
                            <th>Total Revenue</th>
                            <th>Rating</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="text-center">No vendor data available</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Filter Vendors Modal -->
<div class="modal fade" id="filterVendorsModal" tabindex="-1" role="dialog" aria-labelledby="filterVendorsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filterVendorsModalLabel">Filter Vendors</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="filterVendorsForm">
                    <div class="form-group">
                        <label for="vendorCategory">Category</label>
                        <select class="form-control" id="vendorCategory">
                            <option value="">All Categories</option>
                            <option value="Food">Food</option>
                            <option value="Beverages">Beverages</option>
                            <option value="Snacks">Snacks</option>
                            <option value="Desserts">Desserts</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="vendorStatus">Status</label>
                        <select class="form-control" id="vendorStatus">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="vendorPerformance">Performance Level</label>
                        <select class="form-control" id="vendorPerformance">
                            <option value="">All Levels</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyVendorFilter">Apply Filter</button>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Performance Chart
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
const performanceChart = new Chart(performanceCtx, {
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
        labels: ['Food', 'Beverages', 'Snacks', 'Desserts'],
        datasets: [{
            data: [0, 0, 0, 0],
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