<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit();
}

// Get user's credit requests
$stmt = $conn->prepare("
    SELECT cr.*, u.username as vendor_name,
           ca.current_balance, ca.credit_limit
    FROM credit_account_requests cr
    JOIN vendors v ON cr.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    LEFT JOIN credit_accounts ca ON cr.user_id = ca.user_id AND cr.vendor_id = ca.vendor_id
    WHERE cr.user_id = ?
    ORDER BY cr.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "My Credit Requests";
ob_start();
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-md-6">
            <h2>My Credit Requests</h2>
        </div>
        <div class="col-md-6 text-right">
            <a href="request_credit.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Credit Request
            </a>
        </div>
    </div>

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

    <?php if (empty($requests)): ?>
        <div class="card">
            <div class="card-body text-center">
                <p>You haven't made any credit requests yet.</p>
                <a href="request_credit.php" class="btn btn-primary">Request Credit</a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Requested Limit</th>
                        <th>Current Limit</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Requested On</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['vendor_name']); ?></td>
                            <td>₹<?php echo number_format($request['requested_limit'], 2); ?></td>
                            <td>
                                <?php if ($request['credit_limit']): ?>
                                    ₹<?php echo number_format($request['credit_limit'], 2); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($request['current_balance']): ?>
                                    ₹<?php echo number_format($request['current_balance'], 2); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo getStatusBadgeClass($request['status']); ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                            <td>
                                <?php if ($request['admin_notes']): ?>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            data-toggle="tooltip" data-placement="top" 
                                            title="<?php echo htmlspecialchars($request['admin_notes']); ?>">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function(){
    $('[data-toggle="tooltip"]').tooltip();
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 