<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle subscription purchase
if (isset($_POST['purchase_subscription'])) {
    try {
        $plan_id = $_POST['plan_id'];
        $payment_method = $_POST['payment_method'];
        
        // Start transaction
        $conn->beginTransaction();
        
        // Get plan details
        $stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($plan) {
            // Create subscription transaction
            $stmt = $conn->prepare("
                INSERT INTO subscription_transactions 
                (user_id, plan_id, amount, payment_method, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $plan_id, $plan['price'], $payment_method]);
            
            // Create user subscription
            $stmt = $conn->prepare("
                INSERT INTO user_subscriptions 
                (user_id, plan_id, start_date, end_date, status, created_at, updated_at)
                VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active', NOW(), NOW())
            ");
            $stmt->execute([$user_id, $plan_id, $plan['duration_days']]);
            
            $conn->commit();
            $_SESSION['success'] = "Subscription purchased successfully!";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error purchasing subscription: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$page_title = 'Subscription Portal';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Subscription Portal</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Current Subscription -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Current Subscription</h3>
            </div>
            <div class="card-body">
                <?php
                $stmt = $conn->prepare("
                    SELECT us.*, sp.name as plan_name, sp.description, sp.price, sp.features
                    FROM user_subscriptions us
                    JOIN subscription_plans sp ON us.plan_id = sp.id
                    WHERE us.user_id = ? AND us.status = 'active'
                    ORDER BY us.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $current_subscription = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($current_subscription):
                    // Initialize features as an empty array if null or invalid JSON
                    $features = [];
                    if (!empty($current_subscription['features'])) {
                        $decoded = json_decode($current_subscription['features'], true);
                        if (is_array($decoded)) {
                            $features = $decoded;
                        }
                    }
                ?>
                    <div class="row">
                        <div class="col-md-6">
                            <h4><?php echo htmlspecialchars($current_subscription['plan_name']); ?></h4>
                            <p><?php echo htmlspecialchars($current_subscription['description']); ?></p>
                            <p><strong>Valid until:</strong> <?php echo date('F j, Y', strtotime($current_subscription['end_date'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Features:</h5>
                            <ul>
                                <?php if (!empty($features)): ?>
                                    <?php foreach ($features as $feature): ?>
                                        <li><i class="fas fa-check text-success"></i> <?php echo htmlspecialchars($feature); ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><i class="fas fa-info-circle text-info"></i> Basic subscription features</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <p>You don't have an active subscription.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Plans -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Available Subscription Plans</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM subscription_plans ORDER BY price ASC");
                    $stmt->execute();
                    while ($plan = $stmt->fetch(PDO::FETCH_ASSOC)):
                        // Initialize features as an empty array if null or invalid JSON
                        $features = [];
                        if (!empty($plan['features'])) {
                            $decoded = json_decode($plan['features'], true);
                            if (is_array($decoded)) {
                                $features = $decoded;
                            }
                        }
                    ?>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                </div>
                                <div class="card-body">
                                    <h4 class="text-center">₹<?php echo number_format($plan['price'], 2); ?></h4>
                                    <p class="text-muted text-center"><?php echo $plan['duration_days']; ?> days</p>
                                    <p><?php echo htmlspecialchars($plan['description']); ?></p>
                                    <ul class="list-unstyled">
                                        <?php if (!empty($features)): ?>
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check text-success"></i> <?php echo htmlspecialchars($feature); ?></li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li><i class="fas fa-info-circle text-info"></i> Basic plan features</li>
                                        <?php endif; ?>
                                    </ul>
                                    <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#purchaseModal<?php echo $plan['id']; ?>">
                                        Purchase Plan
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Purchase Modal -->
                        <div class="modal fade" id="purchaseModal<?php echo $plan['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="purchaseModalLabel<?php echo $plan['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="purchaseModalLabel<?php echo $plan['id']; ?>">Purchase <?php echo htmlspecialchars($plan['name']); ?></h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                            <p><strong>Price:</strong> ₹<?php echo number_format($plan['price'], 2); ?></p>
                                            <p><strong>Duration:</strong> <?php echo $plan['duration_days']; ?> days</p>
                                            <div class="form-group">
                                                <label for="payment_method<?php echo $plan['id']; ?>">Payment Method</label>
                                                <select class="form-control" id="payment_method<?php echo $plan['id']; ?>" name="payment_method" required>
                                                    <option value="cash">Cash</option>
                                                    <option value="esewa">eSewa</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            <button type="submit" name="purchase_subscription" class="btn btn-primary">Confirm Purchase</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Subscription History -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Subscription History</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare("
                            SELECT us.*, sp.name as plan_name, sp.price, st.payment_method
                            FROM user_subscriptions us
                            JOIN subscription_plans sp ON us.plan_id = sp.id
                            LEFT JOIN subscription_transactions st ON us.user_id = st.user_id AND us.plan_id = st.plan_id
                            WHERE us.user_id = ?
                            ORDER BY us.created_at DESC
                        ");
                        $stmt->execute([$user_id]);
                        while ($subscription = $stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($subscription['start_date'])); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($subscription['end_date'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo ($subscription['status'] == 'active' ? 'success' : 'secondary'); ?>">
                                        <?php echo ucfirst($subscription['status']); ?>
                                    </span>
                                </td>
                                <td>₹<?php echo number_format($subscription['price'], 2); ?></td>
                                <td><?php echo ucfirst($subscription['payment_method'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$additionalStyles = '
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
';

$additionalScripts = '
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $(".table").DataTable();
});
</script>
';

include '../includes/layout.php';
?> 