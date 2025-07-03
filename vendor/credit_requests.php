<?php
session_start();
require_once '../connection/config.php';
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../auth/login.php');
    exit();
}

// Get vendor ID
$vendor_id = get_vendor_id($conn, $_SESSION['user_id']);

// Handle request status updates
if (isset($_POST['request_id']) && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';

    try {
        // Start transaction
        $conn->beginTransaction();

        if ($action === 'approve') {
            // Get credit limit from POST data
            $credit_limit = $_POST['credit_limit'] ?? null;
            
            // Validate credit limit
            if (!$credit_limit || $credit_limit < 100) {
                throw new Exception("Credit limit must be at least ₹100");
            }

            // Get request details
            $stmt = $conn->prepare("SELECT user_id, account_type FROM credit_account_requests WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$request_id, $vendor_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($request) {
                // Update request status
                $stmt = $conn->prepare("
                    UPDATE credit_account_requests 
                    SET status = 'approved', 
                        admin_notes = ?,
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$admin_notes, $request_id]);

                // Create credit account with the new credit limit and proper account type
                $stmt = $conn->prepare("
                    INSERT INTO credit_accounts (
                        user_id, vendor_id, account_type, credit_limit, current_balance,
                        status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, 0, 'active', NOW(), NOW())
                ");
                $stmt->execute([
                    $request['user_id'], 
                    $vendor_id, 
                    $request['account_type'],
                    $credit_limit
                ]);
            }
        } elseif ($action === 'reject') {
            // Update request status for rejection (no credit limit needed)
            $stmt = $conn->prepare("
                UPDATE credit_account_requests 
                SET status = 'rejected',
                    admin_notes = ?,
                    updated_at = NOW() 
                WHERE id = ? AND vendor_id = ?
            ");
            $stmt->execute([$admin_notes, $request_id, $vendor_id]);
        }

        $conn->commit();
        $_SESSION['success'] = "Request has been " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error processing request: " . $e->getMessage();
    }

    header('Location: credit_requests.php');
    exit();
}

// Fetch all credit requests for this vendor
$stmt = $conn->prepare("
    SELECT 
        cr.*, 
        u.username, 
        u.email,
        u.contact_number,
        cr.status as request_status,
        cr.reason as request_reason
    FROM credit_account_requests cr
    JOIN users u ON cr.user_id = u.id
    WHERE cr.vendor_id = ?
    ORDER BY cr.created_at DESC
");
$stmt->execute([$vendor_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Start output buffering
ob_start();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Credit Requests</h1>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pending Credit Requests</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Type</th>
                                <th>Requested Limit</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($request['username']); ?><br>
                                        <small><?php echo htmlspecialchars($request['email']); ?></small><br>
                                        <small><?php echo htmlspecialchars($request['contact_number']); ?></small>
                                    </td>
                                    <td><?php echo ucfirst($request['account_type']); ?></td>
                                    <td>₹<?php echo number_format($request['requested_limit'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($request['request_reason']); ?></td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        echo '<span class="badge badge-' . $status_class[$request['request_status']] . '">' 
                                            . ucfirst($request['request_status']) . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <?php if ($request['request_status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="showActionModal(<?php echo $request['id']; ?>, 'approve', <?php echo $request['requested_limit']; ?>)">
                                                Approve
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="showActionModal(<?php echo $request['id']; ?>, 'reject', <?php echo $request['requested_limit']; ?>)">
                                                Reject
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">No actions available</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No credit requests found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" role="dialog" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalLabel">Process Credit Request</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="actionForm" method="POST" action="">
                    <input type="hidden" name="request_id" id="request_id">
                    <input type="hidden" name="action" id="action">
                    
                    <div id="creditLimitGroup" class="form-group mb-3">
                        <label for="credit_limit">Approved Credit Limit (₹)</label>
                        <input type="number" class="form-control" id="credit_limit" name="credit_limit" min="100" step="100" required>
                        <small class="form-text text-muted">Enter the credit limit you want to approve (minimum ₹100)</small>
                    </div>

                    <div class="form-group">
                        <label for="admin_notes">Notes</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" required></textarea>
                    </div>

                    <p id="confirmationText" class="mt-3"></p>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmButton" onclick="submitForm()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
function showActionModal(requestId, action, requestedAmount) {
    $('#request_id').val(requestId);
    $('#action').val(action);
    
    if (action === 'approve') {
        $('#actionModalLabel').text('Approve Credit Request');
        $('#confirmationText').text('Are you sure you want to approve this credit request?');
        $('#confirmButton').removeClass('btn-danger').addClass('btn-success');
        $('#credit_limit').val(requestedAmount);
        $('#creditLimitGroup').show();
        $('#credit_limit').prop('required', true);
    } else {
        $('#actionModalLabel').text('Reject Credit Request');
        $('#confirmationText').text('Are you sure you want to reject this credit request?');
        $('#confirmButton').removeClass('btn-success').addClass('btn-danger');
        $('#creditLimitGroup').hide();
        $('#credit_limit').prop('required', false);
    }
    
    $('#actionModal').modal('show');
}

function submitForm() {
    // Validate form
    var form = document.getElementById('actionForm');
    if (form.checkValidity()) {
        form.submit();
    } else {
        // Trigger HTML5 validation
        $('<input type="submit">').hide().appendTo(form).click().remove();
    }
}

$(document).ready(function() {
    // Clear form when modal is closed
    $('#actionModal').on('hidden.bs.modal', function () {
        $('#actionForm')[0].reset();
        $('#confirmButton').removeClass('btn-danger').addClass('btn-primary');
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 