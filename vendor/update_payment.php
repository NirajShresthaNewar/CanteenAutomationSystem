<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: manage_payments.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    $_SESSION['error'] = "Vendor not found";
    header('Location: manage_payments.php');
    exit();
}

// Check if this is a form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Display the payment form
    $order_id = $_POST['order_id'] ?? '';
    $receipt_number = $_POST['receipt_number'] ?? '';
    $total_amount = $_POST['total_amount'] ?? '';

    if (!$order_id || !$receipt_number || !$total_amount) {
        $_SESSION['error'] = "Missing required information";
        header('Location: manage_payments.php');
        exit();
    }

    $page_title = 'Update Payment';
    ob_start();
    ?>

    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Update Payment</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="manage_payments.php">Manage Payments</a></li>
                        <li class="breadcrumb-item active">Update Payment</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
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

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Payment Details</h3>
                        </div>
                        <div class="card-body">
                            <form action="process_payment.php" method="POST">
                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
                                
                                <div class="form-group">
                                    <label>Receipt Number</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($receipt_number); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label>Total Amount</label>
                                    <input type="text" class="form-control" value="₹<?php echo number_format($total_amount, 2); ?>" readonly>
                                    <input type="hidden" name="total_amount" value="<?php echo $total_amount; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="cash_received">Cash Received</label>
                                    <input type="number" class="form-control" id="cash_received" name="cash_received" 
                                           step="0.01" min="<?php echo $total_amount; ?>" required
                                           onchange="calculateChange(this.value, <?php echo $total_amount; ?>)">
                                </div>
                                
                                <div class="form-group">
                                    <label>Change</label>
                                    <input type="text" class="form-control" id="change_amount" readonly>
                                </div>

                                <div class="form-group">
                                    <label for="payment_notes">Notes (Optional)</label>
                                    <textarea class="form-control" id="payment_notes" name="payment_notes" rows="3"></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <a href="manage_payments.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Update Payment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function calculateChange(cashReceived, totalAmount) {
        const change = parseFloat(cashReceived) - parseFloat(totalAmount);
        document.getElementById('change_amount').value = change >= 0 ? '₹' + change.toFixed(2) : '';
    }
    </script>

    <?php
    $content = ob_get_clean();
    require_once '../includes/layout.php';
    exit();
}

// If not a POST request, redirect back to manage payments
header('Location: manage_payments.php');
exit();
?> 