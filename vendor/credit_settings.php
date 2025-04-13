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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        $default_student_credit_limit = $_POST['default_student_credit_limit'];
        $default_staff_credit_limit = $_POST['default_staff_credit_limit'];
        $allow_credit_requests = isset($_POST['allow_credit_requests']) ? 1 : 0;
        $payment_terms = $_POST['payment_terms'];
        $late_payment_policy = $_POST['late_payment_policy'];
        
        // Check if settings already exist
        $stmt = $conn->prepare("SELECT id FROM vendor_credit_settings WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        $existing_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_settings) {
            // Update existing settings
            $stmt = $conn->prepare("
                UPDATE vendor_credit_settings 
                SET default_student_credit_limit = ?,
                    default_staff_credit_limit = ?,
                    allow_credit_requests = ?,
                    payment_terms = ?,
                    late_payment_policy = ?,
                    updated_at = NOW()
                WHERE vendor_id = ?
            ");
            $stmt->execute([
                $default_student_credit_limit,
                $default_staff_credit_limit,
                $allow_credit_requests,
                $payment_terms,
                $late_payment_policy,
                $vendor_id
            ]);
        } else {
            // Insert new settings
            $stmt = $conn->prepare("
                INSERT INTO vendor_credit_settings 
                (vendor_id, default_student_credit_limit, default_staff_credit_limit, allow_credit_requests, payment_terms, late_payment_policy, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $vendor_id,
                $default_student_credit_limit,
                $default_staff_credit_limit,
                $allow_credit_requests,
                $payment_terms,
                $late_payment_policy
            ]);
        }
        
        $conn->commit();
        $_SESSION['success'] = "Credit settings updated successfully";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Get current settings
$stmt = $conn->prepare("
    SELECT * FROM vendor_credit_settings 
    WHERE vendor_id = ?
");
$stmt->execute([$vendor_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// If no settings exist, use defaults
if (!$settings) {
    $settings = [
        'default_student_credit_limit' => 1000.00,
        'default_staff_credit_limit' => 1000.00,
        'allow_credit_requests' => 1,
        'payment_terms' => 'Payment is due within 30 days of purchase.',
        'late_payment_policy' => 'Late payments may result in account suspension.'
    ];
}

$page_title = 'Credit Settings';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Credit Settings</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Credit Settings</li>
                </ol>
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
                <h3 class="card-title">Configure Credit Settings</h3>
            </div>
            <form method="POST">
                <div class="card-body">
                    <div class="form-group">
                        <label for="default_student_credit_limit">Default Student Credit Limit (₹)</label>
                        <input type="number" class="form-control" id="default_student_credit_limit" name="default_student_credit_limit" 
                               value="<?php echo $settings['default_student_credit_limit']; ?>" min="100" step="100" required>
                        <small class="form-text text-muted">Default credit limit for new student accounts</small>
                    </div>

                    <div class="form-group">
                        <label for="default_staff_credit_limit">Default Staff Credit Limit (₹)</label>
                        <input type="number" class="form-control" id="default_staff_credit_limit" name="default_staff_credit_limit" 
                               value="<?php echo $settings['default_staff_credit_limit']; ?>" min="100" step="100" required>
                        <small class="form-text text-muted">Default credit limit for new staff accounts</small>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="allow_credit_requests" 
                                   name="allow_credit_requests" <?php echo $settings['allow_credit_requests'] ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="allow_credit_requests">
                                Allow Credit Requests
                            </label>
                        </div>
                        <small class="form-text text-muted">Enable students and staff to request credit accounts</small>
                    </div>

                    <div class="form-group">
                        <label for="payment_terms">Payment Terms</label>
                        <textarea class="form-control" id="payment_terms" name="payment_terms" rows="3" required><?php echo htmlspecialchars($settings['payment_terms']); ?></textarea>
                        <small class="form-text text-muted">Specify your payment terms and conditions</small>
                    </div>

                    <div class="form-group">
                        <label for="late_payment_policy">Late Payment Policy</label>
                        <textarea class="form-control" id="late_payment_policy" name="late_payment_policy" rows="3" required><?php echo htmlspecialchars($settings['late_payment_policy']); ?></textarea>
                        <small class="form-text text-muted">Specify your policy for late payments</small>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>

        <!-- Credit Management Overview -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM credit_accounts WHERE vendor_id = ?");
                        $stmt->execute([$vendor_id]);
                        $total_accounts = $stmt->fetchColumn();
                        ?>
                        <h3><?php echo $total_accounts; ?></h3>
                        <p>Total Credit Accounts</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT COUNT(*) 
                            FROM credit_accounts ca 
                            JOIN staff_students ss ON ca.user_id = ss.user_id 
                            WHERE ca.vendor_id = ? AND ca.status = 'active' AND ss.role = 'student'
                        ");
                        $stmt->execute([$vendor_id]);
                        $student_accounts = $stmt->fetchColumn();
                        ?>
                        <h3><?php echo $student_accounts; ?></h3>
                        <p>Student Accounts</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT COUNT(*) 
                            FROM credit_accounts ca 
                            JOIN staff_students ss ON ca.user_id = ss.user_id 
                            WHERE ca.vendor_id = ? AND ca.status = 'active' AND ss.role = 'staff'
                        ");
                        $stmt->execute([$vendor_id]);
                        $staff_accounts = $stmt->fetchColumn();
                        ?>
                        <h3><?php echo $staff_accounts; ?></h3>
                        <p>Staff Accounts</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM credit_accounts WHERE vendor_id = ? AND status = 'blocked'");
                        $stmt->execute([$vendor_id]);
                        $blocked_accounts = $stmt->fetchColumn();
                        ?>
                        <h3><?php echo $blocked_accounts; ?></h3>
                        <p>Blocked Accounts</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-ban"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 