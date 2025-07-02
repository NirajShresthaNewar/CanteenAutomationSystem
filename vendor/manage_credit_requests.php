<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../auth/login.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    $_SESSION['error'] = "Vendor not found";
    header('Location: ../auth/logout.php');
    exit();
}

// Handle request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $request_id = $_POST['request_id'];
        $action = $_POST['action'];
        $notes = $_POST['notes'];
        $credit_limit = $_POST['credit_limit'] ?? null;

        $conn->beginTransaction();

        // Update request status
        $stmt = $conn->prepare("
            UPDATE credit_account_requests 
            SET status = ?, admin_notes = ?, updated_at = NOW() 
            WHERE id = ? AND vendor_id = ?
        ");
        $stmt->execute([$action, $notes, $request_id, $vendor['id']]);

        if ($action === 'approved' && $credit_limit) {
            // Get user info from request
            $stmt = $conn->prepare("
                SELECT user_id, account_type 
                FROM credit_account_requests 
                WHERE id = ?
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            // Create or update credit account
            $stmt = $conn->prepare("
                INSERT INTO credit_accounts 
                    (user_id, vendor_id, account_type, credit_limit, current_balance, status, created_at, updated_at) 
                VALUES 
                    (?, ?, ?, ?, 0, 'active', NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    credit_limit = ?, 
                    status = 'active',
                    updated_at = NOW()
            ");
            $stmt->execute([
                $request['user_id'], 
                $vendor['id'], 
                $request['account_type'], 
                $credit_limit,
                $credit_limit
            ]);
        }

        $conn->commit();
        $_SESSION['success'] = "Credit request has been " . $action;
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error processing request: " . $e->getMessage();
    }
}

// Get credit requests
$stmt = $conn->prepare("
    SELECT cr.*, 
           u.username as requester_name,
           u.email as requester_email,
           ss.role as user_role,
           ca.current_balance,
           ca.credit_limit as current_limit
    FROM credit_account_requests cr
    JOIN users u ON cr.user_id = u.id
    JOIN staff_students ss ON u.id = ss.user_id
    LEFT JOIN credit_accounts ca ON cr.user_id = ca.user_id AND cr.vendor_id = ca.vendor_id
    WHERE cr.vendor_id = ?
    ORDER BY cr.created_at DESC
");
$stmt->execute([$vendor['id']]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Manage Credit Requests";
ob_start();
?>

<div class="container mt-4">
    <h2>Credit Requests</h2>

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

    <?php if (empty($requests)): ?>
        <div class="card">
            <div class="card-body text-center">
                <p>No credit requests found.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Requester</th>
                        <th>Role</th>
                        <th>Requested Limit</th>
                        <th>Current Limit</th>
                        <th>Current Balance</th>
                        <th>Status</th>
                        <th>Requested On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($request['requester_name']); ?>
                                <br>
                                <small class="text-muted"><?php echo $request['requester_email']; ?></small>
                            </td>
                            <td><?php echo ucfirst($request['user_role']); ?></td>
                            <td>₹<?php echo number_format($request['requested_limit'], 2); ?></td>
                            <td>
                                <?php if ($request['current_limit']): ?>
                                    ₹<?php echo number_format($request['current_limit'], 2); ?>
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
                                <?php if ($request['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-sm btn-success" 
                                            data-toggle="modal" data-target="#approveModal<?php echo $request['id']; ?>">
                                        Approve
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            data-toggle="modal" data-target="#rejectModal<?php echo $request['id']; ?>">
                                        Reject
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            data-toggle="tooltip" data-placement="top" 
                                            title="<?php echo htmlspecialchars($request['admin_notes']); ?>">
                                        <i class="fas fa-info-circle"></i> Notes
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Approve Modal -->
                        <div class="modal fade" id="approveModal<?php echo $request['id']; ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <form method="POST" action="">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Approve Credit Request</h5>
                                            <button type="button" class="close" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="approved">
                                            
                                            <div class="form-group">
                                                <label for="credit_limit">Credit Limit</label>
                                                <input type="number" class="form-control" id="credit_limit" 
                                                       name="credit_limit" min="100" step="100" 
                                                       value="<?php echo $request['requested_limit']; ?>" required>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="notes">Notes</label>
                                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success">Approve</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?php echo $request['id']; ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <form method="POST" action="">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Credit Request</h5>
                                            <button type="button" class="close" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="rejected">
                                            
                                            <div class="form-group">
                                                <label for="notes">Reason for Rejection</label>
                                                <textarea class="form-control" id="notes" name="notes" rows="3" required></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Reject</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
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