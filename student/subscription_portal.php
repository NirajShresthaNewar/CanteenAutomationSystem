<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current subscription with complete plan details
$stmt = $conn->prepare("
    SELECT us.*, sp.price as current_price, sp.name as current_plan,
           sp.duration_days, sp.discount_percentage, sp.features,
           sp.description as plan_description,
           us.end_date, us.start_date
    FROM user_subscriptions us
    JOIN subscription_plans sp ON us.plan_id = sp.id
    WHERE us.user_id = ? AND us.status = 'active'
    ORDER BY us.created_at DESC LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$current_subscription = $stmt->fetch(PDO::FETCH_ASSOC);

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
        
        if (!$plan) {
            throw new Exception("Invalid subscription plan");
        }
        
        // Check for existing active subscription
        $stmt = $conn->prepare("SELECT * FROM user_subscriptions WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $existing_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate dates
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
        
        if ($existing_subscription) {
            // Handle upgrade
            if ($plan['price'] <= $existing_subscription['current_price']) {
                throw new Exception("You can only upgrade to a higher tier plan");
            }
            
            // Update existing subscription to expired
            $stmt = $conn->prepare("UPDATE user_subscriptions SET status = 'expired' WHERE id = ?");
            $stmt->execute([$existing_subscription['id']]);
        }
        
        // Create new subscription
        $stmt = $conn->prepare("
            INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $plan_id, $start_date, $end_date]);
        
        // Record the transaction
        $stmt = $conn->prepare("
            INSERT INTO subscription_transactions (user_id, plan_id, amount, payment_method, status, created_at)
            VALUES (?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $plan_id, $plan['price'], $payment_method]);
        
        $conn->commit();
        
        // Redirect to avoid form resubmission
        header("Location: subscription_portal.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
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
                            <h4><?php echo htmlspecialchars($current_subscription['current_plan']); ?></h4>
                            <p><?php echo htmlspecialchars($current_subscription['plan_description']); ?></p>
                            <p><strong>Valid until:</strong> <?php echo date('F j, Y', strtotime($current_subscription['end_date'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Features:</h5>
                            <ul>
                                <?php if (!empty($features)): ?>
                                    <?php foreach ($features as $feature): ?>
                                        <li><i class="fas fa-check text-success"></i> <?php echo htmlspecialchars(trim($feature)); ?></li>
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
                <?php if ($current_subscription): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You have an active <?php echo htmlspecialchars($current_subscription['current_plan']); ?> subscription.
                        You can only upgrade to a higher tier plan. Lower tier plans will be available after your current subscription expires.
                    </div>
                <?php endif; ?>
                <div class="row">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM subscription_plans ORDER BY price ASC");
                    $stmt->execute();
                    while ($plan = $stmt->fetch(PDO::FETCH_ASSOC)):
                        $features = [];
                        if (!empty($plan['features'])) {
                            $decoded = json_decode($plan['features'], true);
                            if (is_array($decoded)) {
                                $features = $decoded;
                            }
                        }
                        
                        // Calculate upgrade cost if applicable
                        $upgrade_info = null;
                        if ($current_subscription && $plan['price'] > $current_subscription['current_price']) {
                            // Safely calculate remaining days
                            $remaining_days = max(0, (strtotime($current_subscription['end_date']) - time()) / (60 * 60 * 24));
                            
                            // Safely calculate daily rate
                            $daily_rate = 0;
                            if (!empty($current_subscription['duration_days']) && $current_subscription['duration_days'] > 0) {
                                $daily_rate = $current_subscription['current_price'] / $current_subscription['duration_days'];
                            }
                            
                            // Calculate refund
                            $refund_amount = $daily_rate * $remaining_days;
                            
                            // Calculate upgrade cost
                            $upgrade_cost = $plan['price'] - $refund_amount;
                            $upgrade_cost = max(0, min($upgrade_cost, $plan['price'])); // Ensure cost is between 0 and plan price
                            
                            $upgrade_info = [
                                'remaining_days' => round($remaining_days),
                                'refund_amount' => $refund_amount,
                                'upgrade_cost' => $upgrade_cost,
                                'new_end_date' => date('F j, Y', strtotime("+{$plan['duration_days']} days"))
                            ];
                        }
                        
                        // Determine if plan should be disabled
                        $is_disabled = $current_subscription && $plan['price'] <= $current_subscription['current_price'];
                    ?>
                        <div class="col-md-4">
                            <div class="card <?php echo $is_disabled ? 'bg-light' : ''; ?>">
                                <div class="card-header">
                                    <h3 class="card-title"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                </div>
                                <div class="card-body">
                                    <h4 class="text-center">₹<?php echo number_format($plan['price'], 2); ?></h4>
                                    <p class="text-muted text-center"><?php echo $plan['duration_days']; ?> days</p>
                                    <p><?php echo htmlspecialchars($plan['description']); ?></p>
                                    <?php if ($upgrade_info): ?>
                                        <div class="alert alert-info">
                                            <h6>Upgrade Details:</h6>
                                            <p>
                                                Current plan ends: <?php echo date('F j, Y', strtotime($current_subscription['end_date'])); ?><br>
                                                New plan duration: <?php echo $plan['duration_days']; ?> days<br>
                                                New end date: <?php echo $upgrade_info['new_end_date']; ?><br>
                                                <hr>
                                                New plan price: ₹<?php echo number_format($plan['price'], 2); ?><br>
                                                Refund from current plan: -₹<?php echo number_format($upgrade_info['refund_amount'], 2); ?><br>
                                                <strong>Final upgrade cost: ₹<?php echo number_format($upgrade_info['upgrade_cost'], 2); ?></strong>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    <ul class="list-unstyled">
                                        <?php if (!empty($features)): ?>
                                            <?php foreach ($features as $feature): ?>
                                                <li><i class="fas fa-check text-success"></i> <?php echo htmlspecialchars($feature); ?></li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li><i class="fas fa-info-circle text-info"></i> Basic plan features</li>
                                        <?php endif; ?>
                                    </ul>
                                    <button type="button" class="btn btn-primary btn-block" 
                                            <?php echo $is_disabled ? 'disabled' : ''; ?>
                                            data-toggle="modal" data-target="#purchaseModal<?php echo $plan['id']; ?>">
                                        <?php echo $current_subscription && $plan['price'] > $current_subscription['current_price'] ? 'Upgrade Plan' : 'Purchase Plan'; ?>
                                    </button>
                                    <?php if ($is_disabled): ?>
                                        <small class="text-muted d-block text-center mt-2">
                                            Available after current subscription expires
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Purchase Modal -->
                        <div class="modal fade" id="purchaseModal<?php echo $plan['id']; ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <?php echo $upgrade_info ? 'Upgrade to ' : 'Purchase '; ?>
                                            <?php echo htmlspecialchars($plan['name']); ?>
                                        </h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <?php if ($upgrade_info): ?>
                                            <div class="alert alert-info">
                                                <h6>Upgrade Details:</h6>
                                                <p>
                                                    Current plan ends: <?php echo date('F j, Y', strtotime($current_subscription['end_date'])); ?><br>
                                                    New plan duration: <?php echo $plan['duration_days']; ?> days<br>
                                                    New end date: <?php echo $upgrade_info['new_end_date']; ?><br>
                                                    <hr>
                                                    New plan price: ₹<?php echo number_format($plan['price'], 2); ?><br>
                                                    Refund from current plan: -₹<?php echo number_format($upgrade_info['refund_amount'], 2); ?><br>
                                                    <strong>Final upgrade cost: ₹<?php echo number_format($upgrade_info['upgrade_cost'], 2); ?></strong>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        <form action="" method="POST">
                                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                            <div class="form-group">
                                                <label for="payment_method">Payment Method</label>
                                                <select name="payment_method" id="payment_method" class="form-control" required>
                                                    <option value="">Select Payment Method</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="khalti">Khalti</option>
                                                </select>
                                            </div>
                                            <button type="submit" name="purchase_subscription" class="btn btn-primary">
                                                <?php echo $upgrade_info ? 'Confirm Upgrade' : 'Confirm Purchase'; ?>
                                            </button>
                                        </form>
                                    </div>
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
                <?php
                // Get subscription history
                $stmt = $conn->prepare("
                    SELECT us.id, us.start_date, us.end_date, us.status,
                           sp.name as plan_name, sp.price,
                           st.payment_method, st.status as payment_status
                    FROM user_subscriptions us
                    JOIN subscription_plans sp ON us.plan_id = sp.id
                    LEFT JOIN subscription_transactions st ON st.user_id = us.user_id AND st.plan_id = sp.id
                    WHERE us.user_id = ? 
                    GROUP BY us.id  /* Group by subscription ID to avoid duplicates */
                    ORDER BY us.created_at DESC
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $subscription_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Display subscription history
                echo '<h5 class="mt-4">Subscription History</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
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
                        <tbody>';
                foreach ($subscription_history as $subscription) {
                    // Only show active subscriptions or format based on status
                    echo '<tr>
                        <td>' . htmlspecialchars($subscription['plan_name']) . '</td>
                        <td>' . date('Y-m-d', strtotime($subscription['start_date'])) . '</td>
                        <td>' . date('Y-m-d', strtotime($subscription['end_date'])) . '</td>
                        <td><span class="badge badge-' . ($subscription['status'] == 'active' ? 'success' : 'secondary') . '">' 
                        . ucfirst($subscription['status']) . '</span></td>
                        <td>₹' . number_format($subscription['price'], 2) . '</td>
                        <td>' . ucfirst($subscription['payment_method'] ?? 'N/A') . '</td>
                    </tr>';
                }
                echo '</tbody></table></div>';
                ?>
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