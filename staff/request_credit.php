<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $vendor_id = $_POST['vendor_id'];
        $requested_limit = $_POST['requested_limit'];
        $reason = $_POST['reason'];
        
        // Check if there's already a pending request
        $stmt = $conn->prepare("SELECT id, status FROM credit_account_requests 
                              WHERE user_id = ? AND vendor_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['user_id'], $vendor_id]);
        
        if ($stmt->fetch()) {
            throw new Exception("You already have a pending credit request with this vendor.");
        }
        
        // Check if user already has an active credit account
        $stmt = $conn->prepare("SELECT id, status FROM credit_accounts 
                              WHERE user_id = ? AND vendor_id = ?");
        $stmt->execute([$_SESSION['user_id'], $vendor_id]);
        
        if ($account = $stmt->fetch()) {
            if ($account['status'] === 'active') {
                throw new Exception("You already have an active credit account with this vendor.");
            } else {
                throw new Exception("You have a blocked credit account with this vendor. Please contact the vendor.");
            }
        }
        
        // Get vendor's credit settings
        $stmt = $conn->prepare("SELECT default_staff_credit_limit, allow_credit_requests 
                              FROM vendor_credit_settings WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        // If no settings found, use system defaults
        if (!$settings) {
            $settings = [
                'default_staff_credit_limit' => 2000.00,
                'allow_credit_requests' => 1
            ];
        }

        // Check if credit requests are allowed
        if (!$settings['allow_credit_requests']) {
            throw new Exception("This vendor is not accepting credit requests at this time.");
        }

        // Validate against credit limits
        if ($requested_limit > $settings['default_staff_credit_limit']) {
            throw new Exception("Requested limit cannot exceed ₹" . number_format($settings['default_staff_credit_limit'], 2));
        }

        // Insert new request
        $stmt = $conn->prepare("INSERT INTO credit_account_requests 
                              (user_id, vendor_id, account_type, requested_limit, reason, status, created_at, updated_at) 
                              VALUES (?, ?, 'staff', ?, ?, 'pending', NOW(), NOW())");
        $stmt->execute([$_SESSION['user_id'], $vendor_id, $requested_limit, $reason]);
        
        $_SESSION['success'] = "Credit request submitted successfully!";
        header('Location: view_credit_requests.php');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get list of vendors with their credit settings
$stmt = $conn->prepare("
    SELECT v.id, u.username as vendor_name, vcs.default_staff_credit_limit, vcs.allow_credit_requests
    FROM vendors v 
    JOIN users u ON v.user_id = u.id 
    LEFT JOIN vendor_credit_settings vcs ON v.id = vcs.vendor_id
    WHERE v.approval_status = 'approved'
");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Request Credit Account";
ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Request Credit Account</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="vendor_id">Select Vendor</label>
                            <select class="form-control" id="vendor_id" name="vendor_id" required>
                                <option value="">Choose a vendor...</option>
                                <?php foreach ($vendors as $vendor): ?>
                                    <?php if ($vendor['allow_credit_requests']): ?>
                                        <option value="<?php echo $vendor['id']; ?>" 
                                                data-limit="<?php echo $vendor['default_staff_credit_limit']; ?>">
                                            <?php echo htmlspecialchars($vendor['vendor_name']); ?> 
                                            (Max: ₹<?php echo number_format($vendor['default_staff_credit_limit'], 2); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="requested_limit">Requested Credit Limit (₹)</label>
                            <input type="number" class="form-control" id="requested_limit" 
                                   name="requested_limit" min="100" step="100" required>
                            <small class="form-text text-muted">
                                Enter the credit limit you would like to request (minimum ₹100, maximum depends on vendor)
                            </small>
                        </div>

                        <script>
                        document.getElementById('vendor_id').addEventListener('change', function() {
                            var selectedOption = this.options[this.selectedIndex];
                            var maxLimit = selectedOption.getAttribute('data-limit');
                            var requestedLimitInput = document.getElementById('requested_limit');
                            requestedLimitInput.max = maxLimit;
                            requestedLimitInput.placeholder = "Maximum: ₹" + Number(maxLimit).toLocaleString();
                        });
                        </script>

                        <div class="form-group">
                            <label for="reason">Reason for Credit Request</label>
                            <textarea class="form-control" id="reason" name="reason" 
                                      rows="3" required></textarea>
                            <small class="form-text text-muted">
                                Please explain why you need a credit account
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary">Submit Request</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 