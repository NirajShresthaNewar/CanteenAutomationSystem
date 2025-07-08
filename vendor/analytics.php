<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../auth/login.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

// Get total sales for today
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as today_sales
    FROM orders
    WHERE vendor_id = ?
    AND DATE(order_date) = CURDATE()
    AND payment_status = 'paid'
");
$stmt->execute([$vendor_id]);
$today_sales = $stmt->fetch(PDO::FETCH_ASSOC)['today_sales'];

// Get total sales for current month
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as month_sales
    FROM orders
    WHERE vendor_id = ?
    AND MONTH(order_date) = MONTH(CURDATE())
    AND YEAR(order_date) = YEAR(CURDATE())
    AND payment_status = 'paid'
");
$stmt->execute([$vendor_id]);
$month_sales = $stmt->fetch(PDO::FETCH_ASSOC)['month_sales'];

// Get total orders for today
$stmt = $conn->prepare("
    SELECT COUNT(*) as today_orders
    FROM orders
    WHERE vendor_id = ?
    AND DATE(order_date) = CURDATE()
");
$stmt->execute([$vendor_id]);
$today_orders = $stmt->fetch(PDO::FETCH_ASSOC)['today_orders'];

// Get daily sales for the last 7 days
$stmt = $conn->prepare("
    SELECT 
        DATE(order_date) as date,
        COALESCE(SUM(total_amount), 0) as daily_total,
        COUNT(*) as order_count
    FROM orders
    WHERE vendor_id = ?
    AND order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND payment_status = 'paid'
    GROUP BY DATE(order_date)
    ORDER BY date ASC
");
$stmt->execute([$vendor_id]);
$daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top selling items
$stmt = $conn->prepare("
    SELECT 
        mi.name,
        COUNT(*) as order_count,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.subtotal) as total_sales
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.item_id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.vendor_id = ?
    AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY mi.item_id
    ORDER BY total_quantity DESC
    LIMIT 5
");
$stmt->execute([$vendor_id]);
$top_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Sales & Analytics';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Sales & Analytics</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <!-- Info boxes -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-shopping-cart"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Today's Orders</span>
                        <span class="info-box-number"><?php echo $today_orders; ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Today's Sales</span>
                        <span class="info-box-number">₹<?php echo number_format($today_sales, 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-calendar-alt"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Monthly Sales</span>
                        <span class="info-box-number">₹<?php echo number_format($month_sales, 2); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-danger"><i class="fas fa-chart-line"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Avg. Daily Sales</span>
                        <span class="info-box-number">₹<?php echo number_format($month_sales / date('j'), 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sales Chart -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Sales Last 7 Days</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" style="min-height: 300px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Items -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Top Selling Items</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($top_items as $item): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo $item['order_count']; ?> orders (<?php echo $item['total_quantity']; ?> items)
                                        </small>
                                    </div>
                                    <span class="badge badge-success">₹<?php echo number_format($item['total_sales'], 2); ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Sales Chart
const salesData = <?php echo json_encode($daily_sales); ?>;
const dates = salesData.map(item => item.date);
const sales = salesData.map(item => item.daily_total);
const orders = salesData.map(item => item.order_count);

const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: dates,
        datasets: [{
            label: 'Daily Sales (₹)',
            data: sales,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1,
            yAxisID: 'y'
        }, {
            label: 'Number of Orders',
            data: orders,
            borderColor: 'rgb(255, 99, 132)',
            tension: 0.1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Sales Amount (₹)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Number of Orders'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 