<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

// Get credit accounts
$stmt = $conn->prepare("
    SELECT ca.*, u.username as vendor_name, vcs.payment_terms
    FROM credit_accounts ca
    JOIN vendors v ON ca.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    LEFT JOIN vendor_credit_settings vcs ON v.id = vcs.vendor_id
    WHERE ca.user_id = ?
    ORDER BY ca.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$credit_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending requests
$stmt = $conn->prepare("
    SELECT car.*, u.username as vendor_name
    FROM credit_account_requests car
    JOIN vendors v ON car.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE car.user_id = ? AND car.status = 'pending'
    ORDER BY car.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Credit Accounts & Requests";
ob_start();
?>

<div class="container mt-4">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Active Credit Accounts -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Active Credit Accounts</h3>
            <a href="request_credit.php" class="btn btn-primary">Request New Credit</a>
        </div>
        <div class="card-body">
            <?php if (empty($credit_accounts)): ?>
                <p class="text-muted">You don't have any active credit accounts.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>Credit Limit</th>
                                <th>Current Balance</th>
                                <th>Status</th>
                                <th>Payment Terms</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($credit_accounts as $account): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($account['vendor_name']); ?></td>
                                    <td>₹<?php echo number_format($account['credit_limit'], 2); ?></td>
                                    <td>₹<?php echo number_format($account['current_balance'], 2); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $account['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($account['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($account['payment_terms'] ?? 'Standard terms apply'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pending Requests -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pending Credit Requests</h3>
        </div>
        <div class="card-body">
            <?php if (empty($pending_requests)): ?>
                <p class="text-muted">You don't have any pending credit requests.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>Requested Limit</th>
                                <th>Submitted On</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['vendor_name']); ?></td>
                                    <td>₹<?php echo number_format($request['requested_limit'], 2); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <span class="badge badge-warning">Pending</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 