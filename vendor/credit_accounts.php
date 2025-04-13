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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $conn->beginTransaction();
            
            if ($_POST['action'] === 'add_credit') {
                // Add new credit account
                $user_id = $_POST['user_id'];
                $credit_limit = $_POST['credit_limit'];
                
                // Check if account already exists
                $stmt = $conn->prepare("SELECT id FROM credit_accounts WHERE user_id = ? AND vendor_id = ?");
                $stmt->execute([$user_id, $vendor_id]);
                
                if ($stmt->fetch()) {
                    $_SESSION['error'] = "Credit account already exists for this student";
                } else {
                    // Create new credit account
                    $stmt = $conn->prepare("
                        INSERT INTO credit_accounts 
                        (user_id, vendor_id, credit_limit, current_balance, status, created_at) 
                        VALUES (?, ?, ?, 0.00, 'active', NOW())
                    ");
                    $stmt->execute([$user_id, $vendor_id, $credit_limit]);
                    
                    // Add notification for student
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, message, status, created_at)
                        VALUES (?, ?, 'unread', NOW())
                    ");
                    $message = "You have been approved for credit with a limit of ₹" . number_format($credit_limit, 2);
                    $stmt->execute([$user_id, $message]);
                    
                    $_SESSION['success'] = "Credit account created successfully";
                }
            } elseif ($_POST['action'] === 'update_credit') {
                // Update credit account
                $account_id = $_POST['account_id'];
                $credit_limit = $_POST['credit_limit'];
                $status = $_POST['status'];
                
                // First check if account belongs to this vendor
                $stmt = $conn->prepare("SELECT user_id FROM credit_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$account_id, $vendor_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    // Update account
                    $stmt = $conn->prepare("
                        UPDATE credit_accounts 
                        SET credit_limit = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$credit_limit, $status, $account_id]);
                    
                    // Add notification for student
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, message, status, created_at)
                        VALUES (?, ?, 'unread', NOW())
                    ");
                    $message = "Your credit account has been updated. New limit: ₹" . number_format($credit_limit, 2) . ". Status: " . ucfirst($status);
                    $stmt->execute([$result['user_id'], $message]);
                    
                    $_SESSION['success'] = "Credit account updated successfully";
                } else {
                    $_SESSION['error'] = "Invalid credit account";
                }
            } elseif ($_POST['action'] === 'block_account') {
                // Block credit account
                $account_id = $_POST['account_id'];
                
                // First check if account belongs to this vendor
                $stmt = $conn->prepare("SELECT user_id FROM credit_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$account_id, $vendor_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    // Block account
                    $stmt = $conn->prepare("
                        UPDATE credit_accounts 
                        SET status = 'blocked'
                        WHERE id = ?
                    ");
                    $stmt->execute([$account_id]);
                    
                    // Add notification for student
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, message, status, created_at)
                        VALUES (?, ?, 'unread', NOW())
                    ");
                    $message = "Your credit account has been blocked. Please contact the vendor for more information.";
                    $stmt->execute([$result['user_id'], $message]);
                    
                    $_SESSION['success'] = "Credit account blocked successfully";
                } else {
                    $_SESSION['error'] = "Invalid credit account";
                }
            } elseif ($_POST['action'] === 'activate_account') {
                // Activate credit account
                $account_id = $_POST['account_id'];
                
                // First check if account belongs to this vendor
                $stmt = $conn->prepare("SELECT user_id FROM credit_accounts WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$account_id, $vendor_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    // Activate account
                    $stmt = $conn->prepare("
                        UPDATE credit_accounts 
                        SET status = 'active'
                        WHERE id = ?
                    ");
                    $stmt->execute([$account_id]);
                    
                    // Add notification for student
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, message, status, created_at)
                        VALUES (?, ?, 'unread', NOW())
                    ");
                    $message = "Your credit account has been activated.";
                    $stmt->execute([$result['user_id'], $message]);
                    
                    $_SESSION['success'] = "Credit account activated successfully";
                } else {
                    $_SESSION['error'] = "Invalid credit account";
                }
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
}

// Fetch all credit accounts for this vendor
$stmt = $conn->prepare("
    SELECT 
        ca.*,
        u.username as vendor_name
    FROM credit_accounts ca
    JOIN vendors v ON ca.vendor_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE ca.vendor_id = ?
    ORDER BY ca.created_at DESC
");
$stmt->execute([$vendor_id]);
$credit_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get students who don't have credit accounts yet
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.email, u.contact_number 
    FROM users u
    JOIN staff_students ss ON u.id = ss.user_id
    WHERE ss.school_id = (SELECT school_id FROM vendors WHERE id = ?)
    AND ss.role = 'student'
    AND ss.approval_status = 'approved'
    AND u.id NOT IN (
        SELECT user_id FROM credit_accounts WHERE vendor_id = ?
    )
");
$stmt->execute([$vendor_id, $vendor_id]);
$eligible_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Credit Accounts Management';
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
        
        <!-- Add New Credit Account Button -->
        <div class="mb-3">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCreditModal">
                <i class="fas fa-plus"></i> Add New Credit Account
            </button>
        </div>
        
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
                            <th>Student</th>
                            <th>Credit Limit</th>
                            <th>Current Balance</th>
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
                                        <small><?php echo htmlspecialchars($account['contact_number']); ?></small>
                                    </td>
                                    <td>₹<?php echo number_format($account['credit_limit'], 2); ?></td>
                                    <td>₹<?php echo number_format($account['current_balance'], 2); ?></td>
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

<!-- Add Credit Account Modal -->
<div class="modal fade" id="addCreditModal" tabindex="-1" role="dialog" aria-labelledby="addCreditModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_credit">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCreditModalLabel">Add New Credit Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="user_id">Select Student</label>
                        <select class="form-control" id="user_id" name="user_id" required>
                            <option value="">Select Student</option>
                            <?php foreach ($eligible_students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['username'] . ' (' . $student['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="credit_limit">Credit Limit (₹)</label>
                        <input type="number" class="form-control" id="credit_limit" name="credit_limit" min="100" step="100" value="1000" required>
                        <small class="form-text text-muted">Set the maximum amount the student can borrow</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
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
                        <label>Student</label>
                        <p id="edit_student_name" class="form-control-static"></p>
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
                    <p>Are you sure you want to block this credit account? The student will not be able to make purchases on credit until the account is reactivated.</p>
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
                    <p>Are you sure you want to activate this credit account? The student will be able to make purchases on credit.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Activate Account</button>
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
        $("#edit_student_name").text(account.username + " (" + account.email + ")");
        $("#edit_credit_limit").val(account.credit_limit);
        $("#edit_current_balance").text("₹" + parseFloat(account.current_balance).toFixed(2));
        $("#edit_status").val(account.status);
    });
    
    // Block account modal
    $(".block-account").click(function() {
        var accountId = $(this).data("account-id");
        $("#block_account_id").val(accountId);
    });
    
    // Activate account modal
    $(".activate-account").click(function() {
        var accountId = $(this).data("account-id");
        $("#activate_account_id").val(accountId);
    });
});
</script>
';

require_once '../includes/layout.php';
?> 