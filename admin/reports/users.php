<?php
$page_title = 'User Reports';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">User Reports</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-right">
                    <button type="button" class="btn btn-success mr-2" id="exportUsers">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#filterUsersModal">
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
                        <p>Total Users</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>0</h3>
                        <p>Active Users</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-person-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>0</h3>
                        <p>New Users (This Month)</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-person-plus"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>0</h3>
                        <p>Inactive Users</p>
                    </div>
                    <div class="icon">
                        <i class="bi bi-person-x"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">User Registration Trends</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="registrationChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">User Role Distribution</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="roleChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Activity Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User Activity</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Total Orders</th>
                            <th>Total Spent</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="text-center">No user data available</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Filter Users Modal -->
<div class="modal fade" id="filterUsersModal" tabindex="-1" role="dialog" aria-labelledby="filterUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filterUsersModalLabel">Filter Users</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="filterUsersForm">
                    <div class="form-group">
                        <label for="userRole">User Role</label>
                        <select class="form-control" id="userRole">
                            <option value="">All Roles</option>
                            <option value="student">Student</option>
                            <option value="staff">Staff</option>
                            <option value="worker">Worker</option>
                            <option value="vendor">Vendor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="userStatus">Status</label>
                        <select class="form-control" id="userStatus">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="userActivity">Activity Level</label>
                        <select class="form-control" id="userActivity">
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
                <button type="button" class="btn btn-primary" id="applyUserFilter">Apply Filter</button>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Registration Chart
const registrationCtx = document.getElementById('registrationChart').getContext('2d');
const registrationChart = new Chart(registrationCtx, {
    type: 'line',
    data: {
        labels: [],
        datasets: [{
            label: 'New Registrations',
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

// Role Chart
const roleCtx = document.getElementById('roleChart').getContext('2d');
const roleChart = new Chart(roleCtx, {
    type: 'doughnut',
    data: {
        labels: ['Students', 'Staff', 'Workers', 'Vendors'],
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