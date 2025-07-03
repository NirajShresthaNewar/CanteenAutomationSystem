<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $vendor_id = $_POST['vendor_id'];
        $requested_limit = $_POST['requested_limit'];
        $reason = $_POST['reason'];

        // Get vendor's credit settings
        $stmt = $conn->prepare("
            SELECT default_student_credit_limit, allow_credit_requests 
            FROM vendor_credit_settings 
            WHERE vendor_id = ?
        ");
        $stmt->execute([$vendor_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        // If no settings found, use system defaults
        if (!$settings) {
            $settings = [
                'default_student_credit_limit' => 1000.00,
                'allow_credit_requests' => 1
            ];
        }

        // Validate credit request
        if (!$settings['allow_credit_requests']) {
            throw new Exception("This vendor is not accepting credit requests at this time.");
        }

        if ($requested_limit < 100) {
            throw new Exception("Credit limit must be at least ₹100");
        }

        if ($requested_limit > $settings['default_student_credit_limit']) {
            throw new Exception("Requested limit cannot exceed ₹" . number_format($settings['default_student_credit_limit'], 2));
        }

        // Check for existing credit account
        $stmt = $conn->prepare("SELECT id, status FROM credit_accounts WHERE user_id = ? AND vendor_id = ?");
        $stmt->execute([$_SESSION['user_id'], $vendor_id]);
        
        if ($account = $stmt->fetch()) {
            if ($account['status'] === 'active') {
                throw new Exception("You already have an active credit account with this vendor.");
            } else {
                throw new Exception("You have a blocked credit account with this vendor. Please contact the vendor.");
            }
        }

        // Check for pending request
        $stmt = $conn->prepare("SELECT id FROM credit_account_requests WHERE user_id = ? AND vendor_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['user_id'], $vendor_id]);
        
        if ($stmt->fetch()) {
            throw new Exception("You already have a pending credit request with this vendor.");
        }

        // Insert the request
        $stmt = $conn->prepare("
            INSERT INTO credit_account_requests (
                user_id, vendor_id, requested_limit, reason, 
                status, account_type, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, 
                'pending', 'student', NOW(), NOW()
            )
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $vendor_id,
            $requested_limit,
            $reason
        ]);

        $_SESSION['success'] = "Credit request submitted successfully!";
        header("Location: credit_accounts.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get list of vendors with their credit settings
$stmt = $conn->prepare("
    SELECT 
        v.id, 
        u.username as vendor_name, 
        vcs.default_student_credit_limit,
        vcs.allow_credit_requests,
        vcs.payment_terms
    FROM vendors v 
    JOIN users u ON v.user_id = u.id 
    LEFT JOIN vendor_credit_settings vcs ON v.id = vcs.vendor_id
    WHERE v.approval_status = 'approved'
");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's existing credit accounts and balances
$stmt = $conn->prepare("
    SELECT ca.*, u.username as vendor_name
    FROM credit_accounts ca
    JOIN vendors v ON ca.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE ca.user_id = ? AND ca.status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$existing_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Request Credit Account";
ob_start();
?>

<div class="container mt-4">
    <?php if (!empty($existing_accounts)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Your Active Credit Accounts</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Vendor</th>
                                    <th>Credit Limit</th>
                                    <th>Current Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existing_accounts as $account): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($account['vendor_name']); ?></td>
                                    <td>₹<?php echo number_format($account['credit_limit'], 2); ?></td>
                                    <td>₹<?php echo number_format($account['current_balance'], 2); ?></td>
                                    <td>
                                        <?php if ($account['current_balance'] > 0): ?>
                                            <span class="badge badge-warning">Outstanding Balance</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Clear</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success">
                            <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="vendor_id">Select Vendor</label>
                            <select class="form-control" id="vendor_id" name="vendor_id" required>
                                <option value="">Choose a vendor...</option>
                                <?php foreach ($vendors as $vendor): ?>
                                    <option value="<?php echo $vendor['id']; ?>" 
                                            data-limit="<?php echo $vendor['default_student_credit_limit'] ?? 1000; ?>"
                                            data-terms="<?php echo htmlspecialchars($vendor['payment_terms'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                                        <?php if ($vendor['default_student_credit_limit']): ?>
                                            (Max limit: ₹<?php echo number_format($vendor['default_student_credit_limit'], 2); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="requested_limit">Requested Credit Limit (₹)</label>
                            <input type="number" class="form-control" id="requested_limit" 
                                   name="requested_limit" 
                                   min="100" 
                                   step="100"
                                   required>
                            <small class="text-muted">Minimum: ₹100</small>
                            <div id="limit-warning" class="text-danger" style="display: none;"></div>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason for Credit Request</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required 
                                    placeholder="Please explain why you need a credit account"></textarea>
                        </div>

                        <div id="vendor-terms" class="alert alert-info" style="display: none;">
                            <h5>Vendor Terms</h5>
                            <div id="terms-content"></div>
                        </div>

                        <button type="submit" class="btn btn-primary">Submit Request</button>
                        <a href="view_credit_requests.php" class="btn btn-secondary">Back</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('vendor_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const maxLimit = selectedOption.dataset.limit || 1000;
    const terms = selectedOption.dataset.terms;
    const requestedLimitInput = document.getElementById('requested_limit');
    const limitWarning = document.getElementById('limit-warning');
    const vendorTerms = document.getElementById('vendor-terms');
    const termsContent = document.getElementById('terms-content');

    // Update max attribute and placeholder
    requestedLimitInput.setAttribute('max', maxLimit);
    requestedLimitInput.setAttribute('placeholder', `Enter amount between ₹100 and ₹${maxLimit}`);

    // Show vendor terms if available
    if (terms) {
        termsContent.textContent = terms;
        vendorTerms.style.display = 'block';
    } else {
        vendorTerms.style.display = 'none';
    }

    // Add input validation
    requestedLimitInput.addEventListener('input', function() {
        const value = parseFloat(this.value);
        if (value < 100) {
            limitWarning.textContent = 'Minimum credit limit is ₹100';
            limitWarning.style.display = 'block';
            this.setCustomValidity('Minimum credit limit is ₹100');
        } else if (value > maxLimit) {
            limitWarning.textContent = `Maximum credit limit is ₹${maxLimit}`;
            limitWarning.style.display = 'block';
            this.setCustomValidity(`Maximum credit limit is ₹${maxLimit}`);
        } else {
            limitWarning.style.display = 'none';
            this.setCustomValidity('');
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 