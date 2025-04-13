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

// Process transaction recording (repayment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    try {
        $conn->beginTransaction();
        
        $user_id = $_POST['user_id'];
        $amount = $_POST['amount'];
        $payment_method = $_POST['payment_method'];
        
        // Verify this user has a credit account with this vendor
        $stmt = $conn->prepare("SELECT id, current_balance FROM credit_accounts WHERE user_id = ? AND vendor_id = ?");
        $stmt->execute([$user_id, $vendor_id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            throw new Exception("No credit account found for this user");
        }
        
        // Record the transaction
        $stmt = $conn->prepare("
            INSERT INTO credit_transactions (user_id, vendor_id, order_id, amount, transaction_type, payment_method, created_at) 
            VALUES (?, ?, NULL, ?, 'repayment', ?, NOW())
        ");
        $stmt->execute([$user_id, $vendor_id, $amount, $payment_method]);
        
        // Update the credit account balance
        $new_balance = max(0, $account['current_balance'] - $amount);
        $stmt = $conn->prepare("
            UPDATE credit_accounts 
            SET current_balance = ?
            WHERE id = ?
        ");
        $stmt->execute([$new_balance, $account['id']]);
        
        // Add notification for student
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, message, status, created_at)
            VALUES (?, ?, 'unread', NOW())
        ");
        $message = "Payment received: ₹" . number_format($amount, 2) . " via " . ucfirst($payment_method) . ". New balance: ₹" . number_format($new_balance, 2);
        $stmt->execute([$user_id, $message]);
        
        $conn->commit();
        $_SESSION['success'] = "Payment recorded successfully";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Filter by student if specified
$user_filter = '';
$params = [$vendor_id];

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $user_filter = " AND ct.user_id = ?";
    $params[] = $_GET['user_id'];
    
    // Get student details
    $stmt = $conn->prepare("
        SELECT u.username, u.email, ca.credit_limit, ca.current_balance, ca.status
        FROM users u
        JOIN credit_accounts ca ON u.id = ca.user_id
        WHERE u.id = ? AND ca.vendor_id = ?
    ");
    $stmt->execute([$_GET['user_id'], $vendor_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get transactions
$stmt = $conn->prepare("
    SELECT 
        ct.*,
        u.username as vendor_name
    FROM credit_transactions ct
    JOIN vendors v ON ct.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE ct.vendor_id = ?
    ORDER BY ct.created_at DESC
");
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN ct.transaction_type = 'purchase' THEN ct.amount ELSE 0 END) as total_purchases,
        SUM(CASE WHEN ct.transaction_type = 'repayment' THEN ct.amount ELSE 0 END) as total_repayments,
        COUNT(DISTINCT ct.user_id) as unique_customers
    FROM credit_transactions ct
    WHERE ct.vendor_id = ?$user_filter
");
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all students with credit accounts for this vendor for dropdown
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.email, ca.current_balance
    FROM users u
    JOIN credit_accounts ca ON u.id = ca.user_id
    WHERE ca.vendor_id = ? AND ca.status = 'active'
    ORDER BY u.username
");
$stmt->execute([$vendor_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Credit Transactions';
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Credit Transactions</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="credit_accounts.php">Credit Accounts</a></li>
                    <li class="breadcrumb-item active">Transactions</li>
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
        
        <!-- Student Details (if filtering by student) -->
        <?php if (isset($student)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Student Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong><i class="fas fa-user mr-1"></i> Name</strong>
                            <p class="text-muted"><?php echo htmlspecialchars($student['username']); ?></p>
                        </div>
                        <div class="col-md-4">
                            <strong><i class="fas fa-envelope mr-1"></i> Email</strong>
                            <p class="text-muted"><?php echo htmlspecialchars($student['email']); ?></p>
                        </div>
                        <div class="col-md-4">
                            <strong><i class="fas fa-ban mr-1"></i> Status</strong>
                            <p>
                                <?php if ($student['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Blocked</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <strong><i class="fas fa-credit-card mr-1"></i> Credit Limit</strong>
                            <p class="text-muted">₹<?php echo number_format($student['credit_limit'], 2); ?></p>
                        </div>
                        <div class="col-md-4">
                            <strong><i class="fas fa-money-bill-wave mr-1"></i> Current Balance</strong>
                            <p class="text-muted">₹<?php echo number_format($student['current_balance'], 2); ?></p>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#recordPaymentModal">
                                <i class="fas fa-money-bill"></i> Record Payment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <a href="credit_accounts.php" class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Manage Credit Accounts
                </a>
                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#recordPaymentModal">
                    <i class="fas fa-money-bill"></i> Record Payment
                </button>
            </div>
        <?php endif; ?>
        
        <!-- Transactions Summary -->
        <div class="row">
            <div class="col-lg-4 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>₹<?php echo number_format($stats['total_purchases'] ?? 0, 2); ?></h3>
                        <p>Total Credit Purchases</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>₹<?php echo number_format($stats['total_repayments'] ?? 0, 2); ?></h3>
                        <p>Total Repayments</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>₹<?php echo number_format(($stats['total_purchases'] ?? 0) - ($stats['total_repayments'] ?? 0), 2); ?></h3>
                        <p>Outstanding Balance</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Form -->
        <div class="card collapsed-card">
            <div class="card-header">
                <h3 class="card-title">Filter Transactions</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="user_id">Student</label>
                                <select class="form-control" id="user_id" name="user_id">
                                    <option value="">All Students</option>
                                    <?php foreach ($students as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $s['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s['username']); ?> (Balance: ₹<?php echo number_format($s['current_balance'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 text-right">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="credit_transactions.php" class="btn btn-default">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Transactions Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transaction History</h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 150px;">
                        <input type="text" name="table_search" class="form-control float-right" placeholder="Search" id="transactionSearch">
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap" id="transactionsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Type</th>
                            <th>Order/Reference</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No transactions found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo $transaction['id']; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                    <td>
                                        <?php if ($transaction['transaction_type'] === 'purchase'): ?>
                                            <span class="badge badge-danger">Purchase</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Repayment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($transaction['receipt_number']): ?>
                                            <a href="order_details.php?receipt=<?php echo $transaction['receipt_number']; ?>">
                                                #<?php echo $transaction['receipt_number']; ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>₹<?php echo number_format($transaction['amount'], 2); ?></td>
                                    <td>
                                        <?php if ($transaction['payment_method'] === 'cash'): ?>
                                            <span class="badge badge-primary">Cash</span>
                                        <?php elseif ($transaction['payment_method'] === 'esewa'): ?>
                                            <span class="badge badge-info">eSewa</span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" role="dialog" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="record_payment" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" id="recordPaymentModalLabel">Record Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="payment_user_id">Student</label>
                        <select class="form-control" id="payment_user_id" name="user_id" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $s): ?>
                                <?php if ($s['current_balance'] > 0): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $s['id']) ? 'selected' : ''; ?> data-balance="<?php echo $s['current_balance']; ?>">
                                        <?php echo htmlspecialchars($s['username']); ?> (Balance: ₹<?php echo number_format($s['current_balance'], 2); ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount (₹)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="1" step="0.01" required>
                        <small class="form-text text-muted">Enter the amount paid by the student</small>
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="esewa">eSewa</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$additionalScripts = '
<script>
$(document).ready(function() {
    // Search functionality
    $("#transactionSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#transactionsTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // Update max amount when student is selected
    $("#payment_user_id").change(function() {
        var selectedOption = $(this).find("option:selected");
        var balance = selectedOption.data("balance");
        if (balance) {
            $("#amount").attr("max", balance);
            $("#amount").val(balance);
        }
    });
    
    // Trigger change event if a student is pre-selected
    if ($("#payment_user_id").val()) {
        $("#payment_user_id").trigger("change");
    }
});
</script>
';

require_once '../includes/layout.php';
?> 