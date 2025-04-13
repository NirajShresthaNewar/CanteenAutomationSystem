<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if account_id is provided and belongs to the user
$account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;

if ($account_id) {
    $stmt = $conn->prepare("
        SELECT ca.*, u.username as vendor_name, 
               vcs.payment_terms, vcs.late_payment_policy
        FROM credit_accounts ca
        JOIN vendors v ON ca.vendor_id = v.id
        JOIN users u ON v.user_id = u.id
        LEFT JOIN vendor_credit_settings vcs ON v.id = vcs.vendor_id
        WHERE ca.id = ? AND ca.user_id = ?
    ");
    $stmt->execute([$account_id, $user_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        $_SESSION['error'] = "Credit account not found or does not belong to you.";
        header('Location: credit_accounts.php');
        exit();
    }
} else {
    // No specific account selected, get all accounts for the user
    $stmt = $conn->prepare("
        SELECT ca.id, ca.vendor_id, u.username as vendor_name
        FROM credit_accounts ca
        JOIN vendors v ON ca.vendor_id = v.id
        JOIN users u ON v.user_id = u.id
        WHERE ca.user_id = ?
        ORDER BY u.username
    ");
    $stmt->execute([$user_id]);
    $all_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($all_accounts)) {
        $_SESSION['error'] = "You don't have any credit accounts.";
        header('Location: credit_accounts.php');
        exit();
    }
    
    // Default to the first account
    $stmt = $conn->prepare("
        SELECT ca.*, u.username as vendor_name, 
               vcs.payment_terms, vcs.late_payment_policy
        FROM credit_accounts ca
        JOIN vendors v ON ca.vendor_id = v.id
        JOIN users u ON v.user_id = u.id
        LEFT JOIN vendor_credit_settings vcs ON v.id = vcs.vendor_id
        WHERE ca.id = ?
    ");
    $stmt->execute([$all_accounts[0]['id']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    $account_id = $account['id'];
}

// Get transactions for the account
$stmt = $conn->prepare("
    SELECT ct.*, u.username as vendor_name
    FROM credit_transactions ct
    JOIN vendors v ON ct.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE ct.user_id = ?
    ORDER BY ct.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get transaction summary
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN transaction_type = 'purchase' THEN amount ELSE 0 END) as total_purchases,
        SUM(CASE WHEN transaction_type = 'repayment' THEN amount ELSE 0 END) as total_repayments,
        MAX(created_at) as last_transaction_date
    FROM credit_transactions
    WHERE user_id = ? AND vendor_id = ?
");
$stmt->execute([$user_id, $account['vendor_id']]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

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

        <!-- Account Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Account Information</h3>
                <?php if (isset($all_accounts) && count($all_accounts) > 1): ?>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 200px;">
                        <select class="form-control" id="account_selector" onchange="changeAccount(this.value)">
                            <?php foreach ($all_accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>" <?php echo ($acc['id'] == $account_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($acc['vendor_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Vendor</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($account['vendor_name']); ?></dd>
                            
                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">
                                <?php if ($account['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Blocked</span>
                                <?php endif; ?>
                            </dd>
                            
                            <dt class="col-sm-4">Credit Limit</dt>
                            <dd class="col-sm-8">₹<?php echo number_format($account['credit_limit'], 2); ?></dd>
                            
                            <dt class="col-sm-4">Current Balance</dt>
                            <dd class="col-sm-8">
                                <strong class="<?php echo $account['current_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                    ₹<?php echo number_format($account['current_balance'], 2); ?>
                                </strong>
                            </dd>
                            
                            <dt class="col-sm-4">Available Credit</dt>
                            <dd class="col-sm-8">₹<?php echo number_format($account['credit_limit'] - $account['current_balance'], 2); ?></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <?php if ($account['payment_terms']): ?>
                            <div class="callout callout-info">
                                <h5>Payment Terms</h5>
                                <p><?php echo nl2br(htmlspecialchars($account['payment_terms'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($account['late_payment_policy']): ?>
                            <div class="callout callout-warning">
                                <h5>Late Payment Policy</h5>
                                <p><?php echo nl2br(htmlspecialchars($account['late_payment_policy'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($account['status'] === 'active' && $account['current_balance'] > 0): ?>
                    <div class="text-center mt-3">
                        <a href="payment_options.php?account_id=<?php echo $account['id']; ?>" class="btn btn-success">
                            <i class="fas fa-money-bill"></i> Make Payment
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Transaction Summary -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $summary['total_transactions']; ?></h3>
                        <p>Total Transactions</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>₹<?php echo number_format($summary['total_purchases'], 2); ?></h3>
                        <p>Total Purchases</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>₹<?php echo number_format($summary['total_repayments'], 2); ?></h3>
                        <p>Total Repayments</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>₹<?php echo number_format($summary['total_purchases'] - $summary['total_repayments'], 2); ?></h3>
                        <p>Balance History</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                </div>
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
                            <th>Date</th>
                            <th>Type</th>
                            <th>Order Reference</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No transactions found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
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
                                        <?php elseif ($transaction['payment_method'] === 'credit'): ?>
                                            <span class="badge badge-warning">Credit</span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($transaction['transaction_type'] === 'purchase'): ?>
                                            <?php if ($transaction['receipt_number']): ?>
                                                <a href="order_details.php?receipt=<?php echo $transaction['receipt_number']; ?>" class="btn btn-xs btn-info">
                                                    View Order
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Payment received
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

<script>
function changeAccount(accountId) {
    window.location.href = "credit_transactions.php?account_id=" + accountId;
}

document.addEventListener("DOMContentLoaded", function() {
    // Search functionality for transaction table
    document.getElementById("transactionSearch").addEventListener("keyup", function() {
        const value = this.value.toLowerCase();
        const table = document.getElementById("transactionsTable");
        const rows = table.getElementsByTagName("tr");
        
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(value) ? "" : "none";
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 