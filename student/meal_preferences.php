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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    try {
        $conn->beginTransaction();
        
        // Delete existing preferences for the week
        $stmt = $conn->prepare("
            DELETE FROM student_meal_preferences 
            WHERE user_id = ? AND subscription_id = ?
            AND week_start = ?
        ");
        $stmt->execute([$user_id, $subscription['id'], $_POST['week_start']]);
        
        // Insert new preferences
        $stmt = $conn->prepare("
            INSERT INTO student_meal_preferences 
            (user_id, subscription_id, meal_slot_id, combo_id, day_of_week, week_start)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($_POST['preferences'] as $day => $slots) {
            foreach ($slots as $slot_id => $combo_id) {
                if (!empty($combo_id)) {
                    $stmt->execute([
                        $user_id,
                        $subscription['id'],
                        $slot_id,
                        $combo_id,
                        $day,
                        $_POST['week_start']
                    ]);
                }
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Meal preferences updated successfully!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating preferences: " . $e->getMessage();
    }
}

// Get meal slots for this vendor
$stmt = $conn->prepare("
    SELECT * FROM meal_slots 
    WHERE vendor_id = ? AND is_active = 1
    ORDER BY start_time
");
$stmt->execute([$subscription['vendor_id']]);
$meal_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get meal combos for this vendor
$stmt = $conn->prepare("
    SELECT * FROM subscription_meal_combos 
    WHERE vendor_id = ? AND is_active = 1
    ORDER BY is_vegetarian, name
");
$stmt->execute([$subscription['vendor_id']]);
$meal_combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current week's preferences
$week_start = date('Y-m-d', strtotime('monday this week'));
$stmt = $conn->prepare("
    SELECT * FROM student_meal_preferences
    WHERE user_id = ? AND subscription_id = ?
    AND week_start = ?
");
$stmt->execute([$user_id, $subscription['id'], $week_start]);
$current_preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create preferences map for easy access
$preferences_map = [];
foreach ($current_preferences as $pref) {
    $preferences_map[$pref['day_of_week']][$pref['meal_slot_id']] = $pref['combo_id'];
}

$page_title = 'Meal Preferences';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Meal Preferences</h1>
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Set Weekly Meal Preferences - <?php echo htmlspecialchars($subscription['plan_name']); ?>
                </h3>
                <div class="card-tools">
                    <a href="view_subscription_menu.php" class="btn btn-info btn-sm">
                        <i class="fas fa-eye"></i> View Menu
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="week_start" value="<?php echo $week_start; ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <?php foreach ($meal_slots as $slot): ?>
                                        <th>
                                            <?php echo htmlspecialchars($slot['name']); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('g:i A', strtotime($slot['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($slot['end_time'])); ?>
                                            </small>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                foreach ($days as $day):
                                ?>
                                    <tr>
                                        <td><?php echo ucfirst($day); ?></td>
                                        <?php foreach ($meal_slots as $slot): ?>
                                            <td>
                                                <select name="preferences[<?php echo $day; ?>][<?php echo $slot['id']; ?>]" 
                                                        class="form-control">
                                                    <option value="">Select Combo</option>
                                                    <?php foreach ($meal_combos as $combo): ?>
                                                        <option value="<?php echo $combo['id']; ?>"
                                                            <?php echo (isset($preferences_map[$day][$slot['id']]) && 
                                                                      $preferences_map[$day][$slot['id']] == $combo['id']) 
                                                                      ? 'selected' : ''; ?>>
                                                            <?php 
                                                            echo htmlspecialchars($combo['name']); 
                                                            echo ' (₹' . number_format($combo['price'], 2) . ')';
                                                            echo $combo['is_vegetarian'] ? ' 🌱' : '';
                                                            ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-right mt-3">
                        <button type="submit" name="update_preferences" class="btn btn-primary">
                            Update Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 