<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get active subscription
$stmt = $conn->prepare("
    SELECT us.*, sp.name as plan_name, sp.description, sp.price, sp.vendor_id,
           JSON_EXTRACT(sp.features, '$') as plan_features
    FROM user_subscriptions us
    JOIN subscription_plans sp ON us.plan_id = sp.id
    WHERE us.user_id = ? AND us.status = 'active'
    AND us.end_date > NOW()
");
$stmt->execute([$user_id]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    $_SESSION['error'] = "No active subscription found.";
    header('Location: meal_subscription.php');
    exit();
}

// Get meal slots
$stmt = $conn->prepare("
    SELECT * FROM meal_slots 
    WHERE vendor_id = ? AND is_active = 1
    ORDER BY start_time
");
$stmt->execute([$subscription['vendor_id']]);
$meal_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get meal combos
$stmt = $conn->prepare("
    SELECT mc.*, ms.name as slot_name, ms.start_time, ms.end_time 
    FROM subscription_meal_combos mc
    JOIN meal_slots ms ON ms.vendor_id = mc.vendor_id
    WHERE mc.vendor_id = ? AND mc.is_active = 1
    ORDER BY ms.start_time, mc.is_vegetarian, mc.name
");
$stmt->execute([$subscription['vendor_id']]);
$all_combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize combos by meal slot
$combos_by_slot = [];
foreach ($all_combos as $combo) {
    $slot_name = $combo['slot_name'];
    if (!isset($combos_by_slot[$slot_name])) {
        $combos_by_slot[$slot_name] = [
            'start_time' => $combo['start_time'],
            'end_time' => $combo['end_time'],
            'combos' => []
        ];
    }
    $combos_by_slot[$slot_name]['combos'][] = $combo;
}

$page_title = 'Subscription Menu';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Subscription Menu</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <!-- Subscription Info -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?php echo htmlspecialchars($subscription['plan_name']); ?> - Menu Options
                </h3>
                <div class="card-tools">
                    <a href="meal_preferences.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-cog"></i> Set Meal Preferences
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filter Options -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary active" data-filter="all">All</button>
                            <button type="button" class="btn btn-outline-success" data-filter="veg">Vegetarian</button>
                            <button type="button" class="btn btn-outline-danger" data-filter="nonveg">Non-Vegetarian</button>
                        </div>
                    </div>
                </div>

                <!-- Menu Items by Meal Slot -->
                <div class="row">
                    <?php foreach ($combos_by_slot as $slot_name => $slot_data): ?>
                    <div class="col-12 mb-4">
                        <h4 class="border-bottom pb-2">
                            <?php echo htmlspecialchars($slot_name); ?>
                            <small class="text-muted">
                                (<?php 
                                    echo date('g:i A', strtotime($slot_data['start_time'])) . ' - ' . 
                                         date('g:i A', strtotime($slot_data['end_time'])); 
                                ?>)
                            </small>
                        </h4>
                        <div class="row">
                            <?php foreach ($slot_data['combos'] as $combo): ?>
                            <div class="col-md-6 col-lg-4 mb-3 combo-card" 
                                 data-type="<?php echo $combo['is_vegetarian'] ? 'veg' : 'nonveg'; ?>">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?php echo htmlspecialchars($combo['name']); ?>
                                            <?php if ($combo['is_vegetarian']): ?>
                                                <span class="badge badge-success">Veg</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Non-Veg</span>
                                            <?php endif; ?>
                                        </h5>
                                        <p class="card-text">
                                            <?php echo htmlspecialchars($combo['description']); ?>
                                        </p>
                                        <p class="card-text">
                                            <strong>Price: </strong>₹<?php echo number_format($combo['price'], 2); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.combo-card .card {
    transition: transform 0.2s;
    border: 1px solid rgba(0,0,0,0.125);
}
.combo-card .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.badge {
    font-size: 0.8rem;
    padding: 0.4em 0.6em;
    margin-left: 0.5rem;
}
</style>

<!-- Custom JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('[data-filter]');
    const comboCards = document.querySelectorAll('.combo-card');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');

            const filterValue = this.getAttribute('data-filter');

            comboCards.forEach(card => {
                if (filterValue === 'all') {
                    card.style.display = '';
                } else {
                    card.style.display = card.getAttribute('data-type') === filterValue ? '' : 'none';
                }
            });
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 