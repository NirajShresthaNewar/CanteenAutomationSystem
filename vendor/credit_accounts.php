<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../auth/login.php');
    exit();
}

$vendor_id = get_vendor_id($conn, $_SESSION['user_id']);

// Get all credit accounts with user details
$stmt = $conn->prepare("
    SELECT ca.*, u.username, u.email, u.contact_number, u.role as user_type
    FROM credit_accounts ca
    JOIN users u ON ca.user_id = u.id
    WHERE ca.vendor_id = ?
    ORDER BY ca.created_at DESC
");
$stmt->execute([$vendor_id]);
$credit_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get eligible users (both students and staff without credit accounts)
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.email, u.role
    FROM users u
    LEFT JOIN credit_accounts ca ON u.id = ca.user_id AND ca.vendor_id = ?
    WHERE (u.role = 'student' OR u.role = 'staff')
    AND ca.id IS NULL
    ORDER BY u.username
");
$stmt->execute([$vendor_id]);
$eligible_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'update_credit':
                    // Update credit account
                    $stmt = $conn->prepare("
                        UPDATE credit_accounts 
                        SET credit_limit = ?, 
                            status = ?,
                            updated_at = NOW()
                        WHERE id = ? AND vendor_id = ?
                    ");
                    $stmt->execute([
                        $_POST['credit_limit'],
                        $_POST['status'],
                        $_POST['account_id'],
                        $vendor_id
                    ]);
                    
                    header("Location: credit_accounts.php?success=Account updated successfully");
                    exit();
                    break;

                case 'block_account':
                    // Block credit account
                    $stmt = $conn->prepare("
                        UPDATE credit_accounts 
                        SET status = 'blocked', 
                            updated_at = NOW()
                        WHERE id = ? AND vendor_id = ?
                    ");
                    $stmt->execute([$_POST['account_id'], $vendor_id]);
                    
                    header("Location: credit_accounts.php?success=Account blocked successfully");
                    exit();
                    break;

                case 'activate_account':
                    // Activate credit account
                    $stmt = $conn->prepare("
                        UPDATE credit_accounts 
                        SET status = 'active', 
                            updated_at = NOW()
                        WHERE id = ? AND vendor_id = ?
                    ");
                    $stmt->execute([$_POST['account_id'], $vendor_id]);
                    
                    header("Location: credit_accounts.php?success=Account activated successfully");
                    exit();
                    break;

                case 'delete_account':
                    // Check if account has zero balance
                    $stmt = $conn->prepare("
                        SELECT current_balance 
                        FROM credit_accounts 
                        WHERE id = ? AND vendor_id = ?
                    ");
                    $stmt->execute([$_POST['account_id'], $vendor_id]);
                    $account = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$account) {
                        throw new Exception("Account not found");
                    }

                    if ($account['current_balance'] != 0) {
                        throw new Exception("Cannot delete account with non-zero balance");
                    }

                    // Delete the account
                    $stmt = $conn->prepare("
                        DELETE FROM credit_accounts 
                        WHERE id = ? AND vendor_id = ? 
                        AND current_balance = 0
                    ");
                    $stmt->execute([$_POST['account_id'], $vendor_id]);
                    
                    header("Location: credit_accounts.php?success=Account deleted successfully");
                    exit();
                    break;
            }
        } catch (Exception $e) {
            header("Location: credit_accounts.php?error=" . urlencode($e->getMessage()));
            exit();
        }
    }
}

// Start output buffering
ob_start();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Credit Accounts Management</h1>
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
        
        <!-- Credit Accounts Overview -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo count($credit_accounts); ?></h3>
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
                            $active_accounts = array_filter($credit_accounts, function($account) {
                                return $account['status'] === 'active';
                            });
                        ?>
                        <h3><?php echo count($active_accounts); ?></h3>
                        <p>Active Accounts</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <?php 
                            $total_balance = array_reduce($credit_accounts, function($carry, $account) {
                                return $carry + $account['current_balance'];
                            }, 0);
                        ?>
                        <h3>₹<?php echo number_format($total_balance, 2); ?></h3>
                        <p>Total Outstanding Balance</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <?php 
                            $blocked_accounts = array_filter($credit_accounts, function($account) {
                                return $account['status'] === 'blocked';
                            });
                        ?>
                        <h3><?php echo count($blocked_accounts); ?></h3>
                        <p>Blocked Accounts</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-ban"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Credit Accounts Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Credit Accounts</h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 150px;">
                        <input type="text" name="table_search" class="form-control float-right" placeholder="Search" id="accountSearch">
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap" id="accountsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User Details</th>
                            <th>Type</th>
                            <th>Credit Limit</th>
                            <th>Current Balance (Owed)</th>
                            <th>Available Credit</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($credit_accounts)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No credit accounts found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($credit_accounts as $account): ?>
                                <tr>
                                    <td><?php echo $account['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($account['username']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($account['email']); ?></small><br>
                                        <small><?php echo htmlspecialchars($account['contact_number'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo ucfirst($account['user_type']); ?>
                                        </span>
                                    </td>
                                    <td>₹<?php echo number_format($account['credit_limit'], 2); ?></td>
                                    <td>₹<?php echo number_format($account['current_balance'], 2); ?></td>
                                    <td>₹<?php echo number_format($account['credit_limit'] - $account['current_balance'], 2); ?></td>
                                    <td>
                                        <?php if ($account['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Blocked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($account['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-info btn-sm edit-account" data-toggle="modal" data-target="#editCreditModal" data-account='<?php echo json_encode($account); ?>'>
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($account['status'] === 'active'): ?>
                                                <button type="button" class="btn btn-danger btn-sm block-account" data-toggle="modal" data-target="#blockAccountModal" data-account-id="<?php echo $account['id']; ?>">
                                                    <i class="fas fa-ban"></i> Block
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-success btn-sm activate-account" data-toggle="modal" data-target="#activateAccountModal" data-account-id="<?php echo $account['id']; ?>">
                                                    <i class="fas fa-check"></i> Activate
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-danger btn-sm delete-account" 
                                                    data-toggle="modal" 
                                                    data-target="#deleteAccountModal" 
                                                    data-account-id="<?php echo $account['id']; ?>"
                                                    <?php echo $account['current_balance'] != 0 ? 'disabled' : ''; ?>
                                                    title="<?php echo $account['current_balance'] != 0 ? 'Cannot delete account with balance' : 'Delete account'; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            <a href="credit_transactions.php?user_id=<?php echo $account['user_id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-list"></i> Transactions
                                            </a>
                                        </div>
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

<!-- Edit Credit Account Modal -->
<div class="modal fade" id="editCreditModal" tabindex="-1" role="dialog" aria-labelledby="editCreditModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_credit">
                <input type="hidden" name="account_id" id="edit_account_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCreditModalLabel">Edit Credit Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>User</label>
                        <p id="edit_user_name" class="form-control-static"></p>
                    </div>
                    <div class="form-group">
                        <label for="edit_credit_limit">Credit Limit (₹)</label>
                        <input type="number" class="form-control" id="edit_credit_limit" name="credit_limit" min="100" step="100" required>
                    </div>
                    <div class="form-group">
                        <label>Current Balance</label>
                        <p id="edit_current_balance" class="form-control-static"></p>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select class="form-control" id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="blocked">Blocked</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Block Account Modal -->
<div class="modal fade" id="blockAccountModal" tabindex="-1" role="dialog" aria-labelledby="blockAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="block_account">
                <input type="hidden" name="account_id" id="block_account_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="blockAccountModalLabel">Block Credit Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to block this credit account? The user will not be able to make purchases on credit until the account is reactivated.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Block Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Activate Account Modal -->
<div class="modal fade" id="activateAccountModal" tabindex="-1" role="dialog" aria-labelledby="activateAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="activate_account">
                <input type="hidden" name="account_id" id="activate_account_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="activateAccountModalLabel">Activate Credit Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to activate this credit account? The user will be able to make purchases on credit.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Activate Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" role="dialog" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete_account">
                <input type="hidden" name="account_id" id="delete_account_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAccountModalLabel">Delete Credit Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this credit account? This action cannot be undone.</p>
                    <p class="text-danger">Note: You can only delete accounts with zero balance.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Search functionality
    $("#accountSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#accountsTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // Edit account modal
    $(".edit-account").click(function() {
        var account = $(this).data("account");
        $("#edit_account_id").val(account.id);
        $("#edit_user_name").text(account.username + " (" + account.user_type + " - " + account.email + ")");
        $("#edit_credit_limit").val(account.credit_limit);
        $("#edit_current_balance").text("₹" + parseFloat(account.current_balance).toFixed(2));
        $("#edit_status").val(account.status);
    });
    
    // Block account modal
    $(".block-account").click(function() {
        $("#block_account_id").val($(this).data("account-id"));
    });
    
    // Activate account modal
    $(".activate-account").click(function() {
        $("#activate_account_id").val($(this).data("account-id"));
    });

    // Delete account modal
    $(".delete-account").click(function() {
        $("#delete_account_id").val($(this).data("account-id"));
    });
});
</script>

<?php
$content = ob_get_clean();
include '../includes/layout.php';
?> 