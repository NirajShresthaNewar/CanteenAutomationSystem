<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $vendor_id = $_POST['vendor_id'];
        $requested_limit = $_POST['requested_limit'];
        $reason = $_POST['reason'];
        
        // Check if there's already a pending request
        $stmt = $conn->prepare("SELECT id FROM credit_account_requests 
                              WHERE user_id = ? AND vendor_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['user_id'], $vendor_id]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "You already have a pending credit request with this vendor.";
        } else {
            // Insert new request
            $stmt = $conn->prepare("INSERT INTO credit_account_requests 
                                  (user_id, vendor_id, requested_limit, reason, status, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())");
            $stmt->execute([$_SESSION['user_id'], $vendor_id, $requested_limit, $reason]);
            
            $_SESSION['success'] = "Credit request submitted successfully!";
            header('Location: view_credit_requests.php');
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error submitting request: " . $e->getMessage();
    }
}

// Get list of vendors
$stmt = $conn->prepare("
    SELECT v.id, u.username as vendor_name 
    FROM vendors v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.approval_status = 'approved'
");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Request Credit Account";
ob_start();
?>

<div class="container mt-4">
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

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="vendor_id">Select Vendor</label>
                            <select class="form-control" id="vendor_id" name="vendor_id" required>
                                <option value="">Choose a vendor...</option>
                                <?php foreach ($vendors as $vendor): ?>
                                    <option value="<?php echo $vendor['id']; ?>">
                                        <?php echo htmlspecialchars($vendor['vendor_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="requested_limit">Requested Credit Limit (₹)</label>
                            <input type="number" class="form-control" id="requested_limit" 
                                   name="requested_limit" min="100" step="100" required>
                            <small class="form-text text-muted">
                                Enter the credit limit you would like to request (minimum ₹100)
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason for Credit Request</label>
                            <textarea class="form-control" id="reason" name="reason" 
                                      rows="3" required></textarea>
                            <small class="form-text text-muted">
                                Please explain why you need a credit account
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary">Submit Request</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 