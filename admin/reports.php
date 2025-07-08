<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../connection/db_connection.php';

$page_title = 'Financial Reports';
ob_start();

// Get date range from query parameters or default to last 30 days
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Get all vendors for filter
$vendors_query = $conn->query("SELECT v.id, u.username as vendor_name FROM vendors v JOIN users u ON v.user_id = u.id ORDER BY u.username");
$vendors = $vendors_query->fetchAll(PDO::FETCH_ASSOC);

// Get vendor ID from query parameters
$vendor_id = isset($_GET['vendor_id']) ? $_GET['vendor_id'] : '';

// Build base conditions for queries
$conditions = ["DATE(o.order_date) BETWEEN ? AND ?"];
$params = [$start_date, $end_date];

if ($vendor_id) {
    $conditions[] = "o.vendor_id = ?";
    $params[] = $vendor_id;
}

$where_clause = implode(" AND ", $conditions);

// Get daily revenue data
$daily_revenue_query = "
    SELECT 
        DATE(o.order_date) as date,
        SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) as revenue,
        COUNT(*) as total_orders,
        SUM(CASE WHEN o.payment_status = 'paid' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN o.payment_method = 'cash' AND o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) as cash_revenue,
        SUM(CASE WHEN o.payment_method = 'khalti' AND o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) as khalti_revenue,
        SUM(CASE WHEN o.payment_method = 'credit' AND o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) as credit_revenue
    FROM orders o
    WHERE $where_clause
    GROUP BY DATE(o.order_date)
    ORDER BY date
";

$stmt = $conn->prepare($daily_revenue_query);
$stmt->execute($params);
$daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$dates = [];
$revenue = [];
$orders = [];
$cash_revenue = [];
$khalti_revenue = [];
$credit_revenue = [];

foreach ($daily_data as $day) {
    $dates[] = date('M d', strtotime($day['date']));
    $revenue[] = floatval($day['revenue']);
    $orders[] = intval($day['total_orders']);
    $cash_revenue[] = floatval($day['cash_revenue']);
    $khalti_revenue[] = floatval($day['khalti_revenue']);
    $credit_revenue[] = floatval($day['credit_revenue']);
}

// Calculate summary statistics
$total_revenue = array_sum($revenue);
$total_orders = array_sum($orders);
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
$total_cash = array_sum($cash_revenue);
$total_khalti = array_sum($khalti_revenue);
$total_credit = array_sum($credit_revenue);

// Get top vendors by revenue
$top_vendors_query = "
    SELECT 
        u.username as vendor_name,
        COUNT(*) as total_orders,
        SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) as total_revenue
    FROM orders o
    JOIN vendors v ON o.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE $where_clause
    GROUP BY v.id, u.username
    ORDER BY total_revenue DESC
    LIMIT 5
";

$stmt = $conn->prepare($top_vendors_query);
$stmt->execute($params);
$top_vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Financial Reports</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <!-- Filters Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Filters</h3>
            </div>
            <div class="card-body">
                <form method="GET" id="filterForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Vendor</label>
                                <select class="form-control" name="vendor_id">
                                    <option value="">All Vendors</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?php echo $vendor['id']; ?>" <?php echo $vendor_id == $vendor['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">Apply Filter</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>₹<?php echo number_format($total_revenue, 2); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $total_orders; ?></h3>
                        <p>Total Orders</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>₹<?php echo number_format($avg_order_value, 2); ?></h3>
                        <p>Average Order Value</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo count($daily_data); ?></h3>
                        <p>Active Days</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <!-- Revenue Chart -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Revenue Trends</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" style="min-height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Payment Methods Distribution -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Payment Methods</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="paymentMethodsChart" style="min-height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Vendors Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Top Performing Vendors</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>Total Orders</th>
                                <th>Total Revenue</th>
                                <th>Average Order Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_vendors as $vendor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vendor['vendor_name']); ?></td>
                                    <td><?php echo number_format($vendor['total_orders']); ?></td>
                                    <td>₹<?php echo number_format($vendor['total_revenue'], 2); ?></td>
                                    <td>₹<?php echo number_format($vendor['total_revenue'] / $vendor['total_orders'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dates); ?>,
            datasets: [{
                label: 'Daily Revenue',
                data: <?php echo json_encode($revenue); ?>,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // Payment Methods Chart
    const paymentCtx = document.getElementById('paymentMethodsChart').getContext('2d');
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: ['Cash', 'Khalti', 'Credit'],
            datasets: [{
                data: [
                    <?php echo $total_cash; ?>,
                    <?php echo $total_khalti; ?>,
                    <?php echo $total_credit; ?>
                ],
                backgroundColor: [
                    'rgb(75, 192, 192)',
                    'rgb(54, 162, 235)',
                    'rgb(255, 205, 86)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 