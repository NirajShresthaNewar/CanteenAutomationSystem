<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get active subscription if any
$stmt = $conn->prepare("
    SELECT us.*, sp.name as plan_name, sp.description, sp.price, sp.discount_percentage,
           sp.features, sp.vendor_id
    FROM user_subscriptions us
    JOIN subscription_plans sp ON us.plan_id = sp.id
    WHERE us.user_id = ? AND us.status = 'active'
    AND us.end_date > NOW()
");
$stmt->execute([$user_id]);
$active_subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all available subscription plans
$stmt = $conn->prepare("
    SELECT * FROM subscription_plans 
    WHERE vendor_id = ? 
    ORDER BY price
");
$stmt->execute([1]); // Assuming vendor_id 1 for now
$subscription_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle subscription purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    $plan_id = $_POST['plan_id'];
    $payment_method = $_POST['payment_method'];
    
    try {
        $conn->beginTransaction();
        
        // Get plan details
        $stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan) {
            throw new Exception("Invalid subscription plan");
        }
        
        // Calculate dates
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
        
        // Create subscription
        $stmt = $conn->prepare("
            INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $stmt->execute([$user_id, $plan_id, $start_date, $end_date]);
        
        // Record transaction
        $stmt = $conn->prepare("
            INSERT INTO subscription_transactions (user_id, plan_id, amount, payment_method, status, created_at)
            VALUES (?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([$user_id, $plan_id, $plan['price'], $payment_method]);
        
        $conn->commit();
        $_SESSION['success'] = "Successfully subscribed to {$plan['name']}!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

$page_title = 'Meal Subscription';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Meal Subscription</h1>
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

        <!-- Active Subscription -->
        <?php if ($active_subscription): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Active Subscription</h3>
            </div>
            <div class="card-body">
                <h4><?php echo htmlspecialchars($active_subscription['plan_name']); ?></h4>
                <p><?php echo htmlspecialchars($active_subscription['description']); ?></p>
                <p><strong>Discount: </strong><?php echo $active_subscription['discount_percentage']; ?>% off on all orders</p>
                <p><strong>Valid until: </strong><?php echo date('F j, Y', strtotime($active_subscription['end_date'])); ?></p>
                <div class="mt-3">
                    <a href="order_food.php" class="btn btn-primary">
                        Order Food Now
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Available Plans -->
        <div class="row">
            <?php foreach ($subscription_plans as $plan): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo htmlspecialchars($plan['name']); ?></h3>
                    </div>
                    <div class="card-body">
                        <h4 class="text-center mb-4">₹<?php echo number_format($plan['price'], 2); ?></h4>
                        <p><?php echo htmlspecialchars($plan['description']); ?></p>
                        <ul class="list-unstyled">
                            <?php foreach (explode(',', $plan['features']) as $feature): ?>
                            <li><i class="fas fa-check text-success mr-2"></i><?php echo htmlspecialchars($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <?php if (!$active_subscription): ?>
                        <button type="button" class="btn btn-primary btn-block" 
                                onclick="showSubscribeModal(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name']); ?>', <?php echo $plan['price']; ?>)">
                            Subscribe Now
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Subscribe Modal -->
<div class="modal fade" id="subscribeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="plan_id" id="modalPlanId">
                <div class="modal-header">
                    <h5 class="modal-title">Subscribe to <span id="modalPlanName"></span></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Total Amount: ₹<span id="modalPlanPrice"></span></p>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="esewa">eSewa</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="subscribe" class="btn btn-primary">Confirm Subscription</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showSubscribeModal(planId, planName, planPrice) {
    document.getElementById('modalPlanId').value = planId;
    document.getElementById('modalPlanName').textContent = planName;
    document.getElementById('modalPlanPrice').textContent = planPrice.toFixed(2);
    $('#subscribeModal').modal('show');
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 