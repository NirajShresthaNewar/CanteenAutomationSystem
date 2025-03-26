<?php
session_start();

// Verify role access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Dashboard content
$content = '
<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-3 col-6">
        <!-- small box -->
        <div class="small-box bg-info">
            <div class="inner">
                <h3>150</h3>
                <p>Total Users</p>
            </div>
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <!-- small box -->
        <div class="small-box bg-success">
            <div class="inner">
                <h3>53</h3>
                <p>Active Vendors</p>
            </div>
            <div class="icon">
                <i class="fas fa-store"></i>
            </div>
            <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <!-- small box -->
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>44</h3>
                <p>New Orders</p>
            </div>
            <div class="icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <!-- small box -->
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>65</h3>
                <p>Pending Issues</p>
            </div>
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<!-- Main row -->
<div class="row">
    <!-- Left col -->
    <section class="col-lg-7 connectedSortable">
        <!-- Custom tabs (Charts with tabs)-->
        <div class="card">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-chart-line mr-1"></i>
                    Sales Overview
                </h3>
            </div>
            <div class="card-body">
                <canvas id="sales-chart" height="300"></canvas>
            </div>
        </div>
    </section>

    <!-- Right col -->
    <section class="col-lg-5 connectedSortable">
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-history mr-1"></i>
                    Recent Activity
                </h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Vendor 1</td>
                                <td>New registration</td>
                                <td>2 min ago</td>
                            </tr>
                            <tr>
                                <td>Student 1</td>
                                <td>Placed order</td>
                                <td>5 min ago</td>
                            </tr>
                            <tr>
                                <td>Worker 1</td>
                                <td>Completed order</td>
                                <td>10 min ago</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
// Sales Chart
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById("sales-chart").getContext("2d");
    new Chart(ctx, {
        type: "line",
        data: {
            labels: ["January", "February", "March", "April", "May", "June"],
            datasets: [{
                label: "Sales",
                data: [65, 59, 80, 81, 56, 55],
                borderColor: "#007bff",
                tension: 0.3,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>
';

// Include layout
require_once '../includes/layout.php';
?> 