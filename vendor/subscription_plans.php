<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../index.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

// Handle form submission for creating/updating plans
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                $stmt = $conn->prepare("
                    INSERT INTO subscription_plans 
                    (vendor_id, name, description, price, duration_days, features, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $vendor_id,
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['price'],
                    $_POST['duration_days'],
                    json_encode([
                        'daily_meals' => $_POST['daily_meals'],
                        'priority_service' => isset($_POST['priority_service']) ? true : false,
                        'discount' => $_POST['discount']
                    ])
                ]);
                $_SESSION['success'] = "Subscription plan created successfully";
            } elseif ($_POST['action'] === 'update') {
                $stmt = $conn->prepare("
                    UPDATE subscription_plans 
                    SET name = ?, description = ?, price = ?, duration_days = ?, 
                        features = ?, updated_at = NOW()
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['price'],
                    $_POST['duration_days'],
                    json_encode([
                        'daily_meals' => $_POST['daily_meals'],
                        'priority_service' => isset($_POST['priority_service']) ? true : false,
                        'discount' => $_POST['discount']
                    ]),
                    $_POST['plan_id'],
                    $vendor_id
                ]);
                $_SESSION['success'] = "Subscription plan updated successfully";
            }
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$page_title = 'Subscription Plans';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Subscription Plans</h1>
            </div>
            <div class="col-sm-6">
                <button type="button" class="btn btn-primary float-right" data-toggle="modal" data-target="#addPlanModal">
                    <i class="fas fa-plus"></i> Add New Plan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <h5><i class="icon fas fa-check"></i> Success!</h5>
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <h5><i class="icon fas fa-ban"></i> Alert!</h5>
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Available Plans</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Price</th>
                            <th>Duration</th>
                            <th>Features</th>
                            <th>Active Subscribers</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare("
                            SELECT sp.*, 
                                   COUNT(us.id) as active_subscribers
                            FROM subscription_plans sp
                            LEFT JOIN user_subscriptions us ON sp.id = us.plan_id AND us.status = 'active'
                            WHERE sp.vendor_id = ?
                            GROUP BY sp.id
                            ORDER BY sp.price ASC
                        ");
                        $stmt->execute([$vendor_id]);
                        
                        while ($plan = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $features = json_decode($plan['features'], true);
                            if ($features === null) {
                                $features = [
                                    'daily_meals' => 0,
                                    'priority_service' => false,
                                    'discount' => 0
                                ];
                            }
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($plan['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($plan['description']) . "</td>";
                            echo "<td>₹" . number_format($plan['price'], 2) . "</td>";
                            echo "<td>" . $plan['duration_days'] . " days</td>";
                            echo "<td>
                                    <ul class='list-unstyled'>
                                        <li><i class='fas fa-utensils'></i> " . ($features['daily_meals'] ?? 0) . " meals/day</li>
                                        <li><i class='fas fa-star'></i> " . (($features['priority_service'] ?? false) ? 'Priority Service' : 'Standard Service') . "</li>
                                        <li><i class='fas fa-percent'></i> " . ($features['discount'] ?? 0) . "% discount</li>
                                    </ul>
                                  </td>";
                            echo "<td>" . $plan['active_subscribers'] . "</td>";
                            echo "<td>
                                    <button type='button' class='btn btn-primary btn-sm' data-toggle='modal' data-target='#editPlanModal" . $plan['id'] . "'>
                                        <i class='fas fa-edit'></i> Edit
                                    </button>
                                  </td>";
                            echo "</tr>";

                            // Edit Modal for each plan
                            echo "
                            <div class='modal fade' id='editPlanModal" . $plan['id'] . "' tabindex='-1' role='dialog' aria-labelledby='editPlanModalLabel" . $plan['id'] . "' aria-hidden='true'>
                                <div class='modal-dialog' role='document'>
                                    <div class='modal-content'>
                                        <div class='modal-header'>
                                            <h5 class='modal-title' id='editPlanModalLabel" . $plan['id'] . "'>Edit Subscription Plan</h5>
                                            <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                                                <span aria-hidden='true'>&times;</span>
                                            </button>
                                        </div>
                                        <form method='POST'>
                                            <div class='modal-body'>
                                                <input type='hidden' name='action' value='update'>
                                                <input type='hidden' name='plan_id' value='" . $plan['id'] . "'>
                                                
                                                <div class='form-group'>
                                                    <label for='name" . $plan['id'] . "'>Plan Name</label>
                                                    <input type='text' class='form-control' id='name" . $plan['id'] . "' name='name' value='" . htmlspecialchars($plan['name']) . "' required>
                                                </div>
                                                
                                                <div class='form-group'>
                                                    <label for='description" . $plan['id'] . "'>Description</label>
                                                    <textarea class='form-control' id='description" . $plan['id'] . "' name='description' rows='3' required>" . htmlspecialchars($plan['description']) . "</textarea>
                                                </div>
                                                
                                                <div class='form-group'>
                                                    <label for='price" . $plan['id'] . "'>Price (₹)</label>
                                                    <input type='number' class='form-control' id='price" . $plan['id'] . "' name='price' min='0' step='100' value='" . $plan['price'] . "' required>
                                                </div>
                                                
                                                <div class='form-group'>
                                                    <label for='duration_days" . $plan['id'] . "'>Duration (days)</label>
                                                    <input type='number' class='form-control' id='duration_days" . $plan['id'] . "' name='duration_days' min='1' value='" . $plan['duration_days'] . "' required>
                                                </div>
                                                
                                                <div class='form-group'>
                                                    <label for='daily_meals" . $plan['id'] . "'>Daily Meals</label>
                                                    <input type='number' class='form-control' id='daily_meals" . $plan['id'] . "' name='daily_meals' min='1' value='" . ($features['daily_meals'] ?? 1) . "' required>
                                                </div>
                                                
                                                <div class='form-group'>
                                                    <div class='custom-control custom-switch'>
                                                        <input type='checkbox' class='custom-control-input' id='priority_service" . $plan['id'] . "' name='priority_service'" . (($features['priority_service'] ?? false) ? ' checked' : '') . ">
                                                        <label class='custom-control-label' for='priority_service" . $plan['id'] . "'>Priority Service</label>
                                                    </div>
                                                </div>
                                                
                                                <div class='form-group'>
                                                    <label for='discount" . $plan['id'] . "'>Discount (%)</label>
                                                    <input type='number' class='form-control' id='discount" . $plan['id'] . "' name='discount' min='0' max='100' value='" . ($features['discount'] ?? 0) . "' required>
                                                </div>
                                            </div>
                                            <div class='modal-footer'>
                                                <button type='button' class='btn btn-secondary' data-dismiss='modal'>Close</button>
                                                <button type='submit' class='btn btn-primary'>Save changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1" role="dialog" aria-labelledby="addPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPlanModalLabel">Add New Subscription Plan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="name">Plan Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price (₹)</label>
                        <input type="number" class="form-control" id="price" name="price" min="0" step="100" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration_days">Duration (days)</label>
                        <input type="number" class="form-control" id="duration_days" name="duration_days" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="daily_meals">Daily Meals</label>
                        <input type="number" class="form-control" id="daily_meals" name="daily_meals" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="priority_service" name="priority_service">
                            <label class="custom-control-label" for="priority_service">Priority Service</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="discount">Discount (%)</label>
                        <input type="number" class="form-control" id="discount" name="discount" min="0" max="100" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Create Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 