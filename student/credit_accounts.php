<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Process credit account request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_credit'])) {
    try {
        $vendor_id = $_POST['vendor_id'];
        $reason = $_POST['reason'];
        $requested_limit = !empty($_POST['requested_limit']) ? floatval($_POST['requested_limit']) : null;
        
        // Check if already has a credit account or pending request with this vendor
        $stmt = $conn->prepare("
            SELECT 'account' as type FROM credit_accounts 
            WHERE user_id = ? AND vendor_id = ?
            UNION
            SELECT 'request' as type FROM credit_account_requests 
            WHERE user_id = ? AND vendor_id = ? AND status = 'pending'
        ");
        $stmt->execute([$user_id, $vendor_id, $user_id, $vendor_id]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            if ($exists['type'] === 'account') {
                throw new Exception("You already have a credit account with this vendor.");
            } else {
                throw new Exception("You already have a pending credit request with this vendor.");
            }
        }
        
        // Check if vendor allows credit requests
        $stmt = $conn->prepare("
            SELECT allow_student_credit_requests 
            FROM vendor_credit_settings 
            WHERE vendor_id = ?
        ");
        $stmt->execute([$vendor_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings || !$settings['allow_student_credit_requests']) {
            throw new Exception("This vendor does not accept credit account requests at this time.");
        }
        
        // Submit request
        $stmt = $conn->prepare("
            INSERT INTO credit_account_requests (
                user_id, vendor_id, requested_limit, reason, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        $stmt->execute([$user_id, $vendor_id, $requested_limit, $reason]);
        
        // Notify vendor
        $stmt = $conn->prepare("
            INSERT INTO notifications (
                user_id, message, status, created_at
            ) 
            SELECT user_id, ?, 'unread', NOW()
            FROM vendors
            WHERE id = ?
        ");
        $stmt->execute([
            "New credit account request from " . $_SESSION['username'],
            $vendor_id
        ]);
        
        $_SESSION['success'] = "Credit account request submitted successfully. You will be notified when it is processed.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: credit_accounts.php');
    exit();
}

// Get student's active credit accounts
$stmt = $conn->prepare("
    SELECT ca.*, u.username as vendor_name
    FROM credit_accounts ca
    JOIN vendors v ON ca.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE ca.user_id = ?
    ORDER BY ca.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending credit requests
$stmt = $conn->prepare("
    SELECT car.*, u.username as vendor_name
    FROM credit_account_requests car
    JOIN vendors v ON car.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE car.user_id = ? AND car.status = 'pending'
    ORDER BY car.created_at DESC
");
$stmt->execute([$user_id]);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vendors with credit settings that allow student requests
$stmt = $conn->prepare("
    SELECT v.id, u.username as name
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    JOIN vendor_credit_settings vcs ON v.id = vcs.vendor_id
    WHERE vcs.allow_credit_requests = 1
    AND v.id NOT IN (
        SELECT vendor_id FROM credit_accounts WHERE user_id = ?
    )
    AND v.id NOT IN (
        SELECT vendor_id FROM credit_account_requests 
        WHERE user_id = ? AND status = 'pending'
    )
    ORDER BY u.username
");
$stmt->execute([$user_id, $user_id]);
$eligible_vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Credit Accounts';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">My Credit Accounts</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Credit Accounts</li>
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
        
        <!-- Credit Accounts -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">My Credit Accounts</h3>
                <?php if (!empty($eligible_vendors)): ?>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#requestCreditModal">
                        <i class="fas fa-plus"></i> Request Credit Account
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($accounts) && empty($pending_requests)): ?>
                    <div class="alert alert-info">
                        <i class="icon fas fa-info-circle"></i> You don't have any credit accounts yet.
                        <?php if (!empty($eligible_vendors)): ?>
                            Click "Request Credit Account" to apply for a credit account with a vendor.
                        <?php else: ?>
                            No vendors are currently accepting credit account requests.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Pending Requests -->
                    <?php if (!empty($pending_requests)): ?>
                        <h5>Pending Requests</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Vendor</th>
                                        <th>Requested Limit</th>
                                        <th>Reason</th>
                                        <th>Date Requested</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['vendor_name']); ?></td>
                                            <td>
                                                <?php if ($request['requested_limit']): ?>
                                                    ₹<?php echo number_format($request['requested_limit'], 2); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Default</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo nl2br(htmlspecialchars($request['reason'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                            <td><span class="badge badge-warning">Pending</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <hr>
                    <?php endif; ?>
                    
                    <!-- Active Accounts -->
                    <?php if (!empty($accounts)): ?>
                        <div class="row">
                            <?php foreach ($accounts as $account): ?>
                                <div class="col-md-6">
                                    <div class="card <?php echo $account['status'] === 'active' ? 'bg-light' : 'bg-light border-danger'; ?>">
                                        <div class="card-header">
                                            <h3 class="card-title"><?php echo htmlspecialchars($account['vendor_name']); ?></h3>
                                            <div class="card-tools">
                                                <?php if ($account['status'] === 'active'): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Blocked</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Credit Limit</span>
                                                        <span class="info-box-number">₹<?php echo number_format($account['credit_limit'], 2); ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-box-content">
                                                        <span class="info-box-text">Current Balance</span>
                                                        <span class="info-box-number <?php echo $account['current_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                            ₹<?php echo number_format($account['current_balance'], 2); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <div class="progress">
                                                    <?php 
                                                        $percentage = 0;
                                                        if ($account['credit_limit'] > 0) {
                                                            $percentage = min(100, ($account['current_balance'] / $account['credit_limit']) * 100);
                                                        }
                                                        $color = 'success';
                                                        if ($percentage > 50) $color = 'warning';
                                                        if ($percentage > 80) $color = 'danger';
                                                    ?>
                                                    <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                                                         style="width: <?php echo $percentage; ?>%" 
                                                         aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    Available Credit: ₹<?php echo number_format($account['credit_limit'] - $account['current_balance'], 2); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <a href="credit_transactions.php?account_id=<?php echo $account['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-history"></i> View Transactions
                                            </a>
                                            <?php if ($account['status'] === 'active' && $account['current_balance'] > 0): ?>
                                                <a href="payment_options.php?account_id=<?php echo $account['id']; ?>" class="btn btn-sm btn-success float-right">
                                                    <i class="fas fa-money-bill"></i> Make Payment
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Credit Account Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">About Credit Accounts</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-credit-card"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">What is a Credit Account?</span>
                                <span class="info-box-description">
                                    A credit account allows you to make purchases from a vendor and pay for them later. 
                                    Each account has a credit limit that determines the maximum amount you can owe.
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-shopping-cart"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Making Purchases</span>
                                <span class="info-box-description">
                                    When placing an order, you can select "Pay with Credit" as your payment method if you have 
                                    an active credit account with sufficient available credit.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Making Payments</span>
                                <span class="info-box-description">
                                    You can make payments on your outstanding balance at any time. Payments can be made via cash directly 
                                    to the vendor or through online payment options.
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Account Status</span>
                                <span class="info-box-description">
                                    If your account becomes blocked, you won't be able to make purchases on credit until you make a payment 
                                    and the vendor reactivates your account.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Request Credit Account Modal -->
<?php if (!empty($eligible_vendors)): ?>
<div class="modal fade" id="requestCreditModal" tabindex="-1" role="dialog" aria-labelledby="requestCreditModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="request_credit" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" id="requestCreditModalLabel">Request Credit Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="vendor_id">Select Vendor</label>
                        <select class="form-control" id="vendor_id" name="vendor_id" required>
                            <option value="">-- Select Vendor --</option>
                            <?php foreach ($eligible_vendors as $vendor): ?>
                                <option value="<?php echo $vendor['id']; ?>">
                                    <?php echo htmlspecialchars($vendor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="requested_limit">Requested Credit Limit (Optional)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₹</span>
                            </div>
                            <input type="number" class="form-control" id="requested_limit" name="requested_limit" 
                                   min="100" step="100" placeholder="Leave blank for default limit">
                        </div>
                        <small class="form-text text-muted">
                            If left blank, the vendor's default credit limit will be applied if your request is approved.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="reason">Reason for Request</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" 
                                  placeholder="Explain why you need a credit account with this vendor" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 