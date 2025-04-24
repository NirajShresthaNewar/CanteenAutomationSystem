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

if (!$vendor) {
    header('Location: ../index.php');
    exit();
}

$vendor_id = $vendor['id'];

// Handle payment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    try {
        $conn->beginTransaction();

        // Validate inputs
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        $payment_status = filter_input(INPUT_POST, 'payment_status', FILTER_SANITIZE_STRING);
        $cash_received = filter_input(INPUT_POST, 'cash_received', FILTER_VALIDATE_FLOAT);
        $payment_notes = filter_input(INPUT_POST, 'payment_notes', FILTER_SANITIZE_STRING);

        if (!$order_id) {
            throw new Exception("Invalid order ID");
        }

        if (!in_array($payment_status, ['pending', 'paid', 'cancelled'])) {
            throw new Exception("Invalid payment status");
        }

        // Get order details for validation
        $stmt = $conn->prepare("SELECT total_amount, payment_status FROM orders WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$order_id, $vendor_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception("Order not found");
        }

        // Validate cash received amount for paid status
        if ($payment_status === 'paid' && (!$cash_received || $cash_received < $order['total_amount'])) {
            throw new Exception("Cash received must be equal to or greater than the total amount");
        }

        // Update order payment status
        $stmt = $conn->prepare("
            UPDATE orders 
            SET payment_status = ?,
                cash_received = ?,
                payment_notes = ?,
                payment_updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND vendor_id = ?
        ");
        $stmt->execute([
            $payment_status,
            $cash_received,
            $payment_notes,
            $order_id,
            $vendor_id
        ]);

        // Create payment history record
        $stmt = $conn->prepare("
            INSERT INTO payment_history (
                order_id, 
                previous_status, 
                new_status, 
                amount_received, 
                notes, 
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $order_id,
            $order['payment_status'],
            $payment_status,
            $cash_received,
            $payment_notes,
            $_SESSION['user_id']
        ]);

        // Create notification for student
        $message = match($payment_status) {
            'paid' => "Your payment for order #" . $order_id . " has been received.",
            'cancelled' => "Your payment for order #" . $order_id . " has been cancelled.",
            default => "Payment status for order #" . $order_id . " has been updated to " . ucfirst($payment_status)
        };

        $stmt = $conn->prepare("
            INSERT INTO order_notifications (order_id, user_id, message, type) 
            SELECT id, user_id, ?, 'order_placed'
            FROM orders WHERE id = ?
        ");
        $stmt->execute([$message, $order_id]);

        $conn->commit();
        $_SESSION['success'] = "Payment status updated successfully";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error updating payment status: " . $e->getMessage();
    }
    header("Location: manage_payments.php");
    exit();
}

// Fetch cash orders with payment history
$sql = "SELECT o.id, o.receipt_number, o.total_amount, o.payment_status, 
    o.cash_received, o.payment_notes, o.payment_updated_at,
    u.username as customer_name,
    COALESCE(o.payment_notes, ph.notes) as payment_notes,
    o.payment_updated_at as last_payment_update,
    GROUP_CONCAT(CONCAT(mi.name, ' (', oi.quantity, ')') SEPARATOR ', ') as order_items
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
LEFT JOIN order_items oi ON o.id = oi.order_id
LEFT JOIN menu_items mi ON oi.menu_item_id = mi.item_id
LEFT JOIN (
    SELECT order_id, 
           GROUP_CONCAT(notes ORDER BY created_at DESC SEPARATOR '\n') as notes
    FROM payment_history
    GROUP BY order_id
) ph ON o.id = ph.order_id
WHERE o.vendor_id = ? AND o.payment_method = 'cash'
GROUP BY o.id
ORDER BY o.payment_updated_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$vendor_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Manage Payments";
ob_start();
?>

<!-- Content Header -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Manage Payments</h1>
            </div>
        </div>
    </div>
</section>

<!-- Main content -->
<section class="content">
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
                <h3 class="card-title">Cash Payments</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="paymentsTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Customer</th>
                                <th>Order Items</th>
                                <th>Total Amount</th>
                                <th>Cash Received</th>
                                <th>Change</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <?php 
                                $change = $order['cash_received'] ? ($order['cash_received'] - $order['total_amount']) : 0;
                                $statusClass = match($order['payment_status']) {
                                    'paid' => 'badge-success',
                                    'cancelled' => 'badge-danger',
                                    default => 'badge-warning'
                                };
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['receipt_number']) ?></td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($order['order_items']) ?></td>
                                    <td>₹<?= number_format($order['total_amount'], 2) ?></td>
                                    <td><?= $order['cash_received'] ? '₹' . number_format($order['cash_received'], 2) : '-' ?></td>
                                    <td><?= $change > 0 ? '₹' . number_format($change, 2) : '-' ?></td>
                                    <td>
                                        <span class="badge <?= $statusClass ?>">
                                            <?= ucfirst($order['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($order['last_payment_update']): ?>
                                            <span data-toggle="tooltip" title="<?= date('M d, Y h:i:s A', strtotime($order['last_payment_update'])) ?>">
                                                <?= date('M d, Y h:i A', strtotime($order['last_payment_update'])) ?>
                                            </span>
                                            <?php if ($order['payment_notes']): ?>
                                                <i class="fas fa-info-circle ms-1" data-toggle="tooltip" 
                                                   title="<?= htmlspecialchars($order['payment_notes']) ?>"></i>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not updated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-toggle="modal" 
                                                data-target="#updatePaymentModal"
                                                data-order-id="<?= $order['id'] ?>"
                                                data-receipt="<?= $order['receipt_number'] ?>"
                                                data-total="<?= $order['total_amount'] ?>"
                                                data-status="<?= $order['payment_status'] ?>"
                                                data-cash-received="<?= $order['cash_received'] ?>">
                                            Update Payment
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info viewHistory"
                                                data-order-id="<?= $order['id'] ?>">
                                            History
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>

<!-- Update Payment Modal -->
<div class="modal fade" id="updatePaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Payment Status</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="manage_payments.php" method="POST" id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="modalOrderId">
                    <p>Update payment status for Receipt #<span id="modalReceipt"></span></p>
                    
                    <div class="mb-3">
                        <label for="payment_status" class="form-label">Payment Status</label>
                        <select name="payment_status" id="payment_status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="cashReceivedGroup">
                        <label for="cash_received" class="form-label">Cash Received</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₹</span>
                            </div>
                            <input type="number" step="0.01" min="0" class="form-control" 
                                   id="cash_received" name="cash_received">
                        </div>
                        <small class="text-muted">Enter the amount received from the customer</small>
                        <div class="invalid-feedback">Please enter a valid amount</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_notes" class="form-label">Notes (Optional)</label>
                        <textarea name="payment_notes" id="payment_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="alert alert-info" id="changeAlert" style="display: none;">
                        <strong>Change to give back:</strong> ₹<span id="changeAmount">0.00</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment History Modal -->
<div class="modal fade" id="paymentHistoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment History</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="timeline" id="paymentTimeline">
                    <!-- Timeline items will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable with export buttons
    $('#paymentsTable').DataTable({
        order: [[7, 'desc']], // Sort by last updated descending
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });

    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Handle modal data
    $('#updatePaymentModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var orderId = button.data('order-id');
        var receipt = button.data('receipt');
        var total = button.data('total');
        var status = button.data('status');
        var cashReceived = button.data('cash-received');
        
        var modal = $(this);
        modal.find('#modalOrderId').val(orderId);
        modal.find('#modalReceipt').text(receipt);
        modal.find('#payment_status').val(status);
        modal.find('#cash_received').val(cashReceived || '');
        
        // Store total amount for change calculation
        modal.find('#cash_received').data('total', total);
        
        updatePaymentFields(status);
        updateChangeDisplay(cashReceived, total);
    });

    // Handle payment status change
    $('#payment_status').change(function() {
        updatePaymentFields($(this).val());
    });

    // Calculate change when cash received is entered
    $('#cash_received').on('input', function() {
        var total = parseFloat($(this).data('total'));
        var received = parseFloat($(this).val()) || 0;
        updateChangeDisplay(received, total);
    });

    // View payment history
    $('.viewHistory').click(function() {
        var orderId = $(this).data('order-id');
        
        // Load payment history via AJAX
        $.get('get_payment_history.php', { order_id: orderId }, function(data) {
            $('#paymentTimeline').html(data);
            $('#paymentHistoryModal').modal('show');
        });
    });

    // Enhanced form validation
    $('#paymentForm').on('submit', function(e) {
        e.preventDefault();
        
        var status = $('#payment_status').val();
        var cashReceived = parseFloat($('#cash_received').val());
        var total = parseFloat($('#cash_received').data('total'));
        
        if (status === 'paid') {
            if (!cashReceived || isNaN(cashReceived)) {
                $('#cash_received').addClass('is-invalid');
                return false;
            }
            
            if (cashReceived < total) {
                if (!confirm('The cash received is less than the total amount. Are you sure you want to proceed?')) {
                    return false;
                }
            }
        }
        
        this.submit();
    });

    // Real-time validation for cash received
    $('#cash_received').on('input', function() {
        $(this).removeClass('is-invalid');
        if ($(this).val() && !isNaN($(this).val())) {
            $(this).addClass('is-valid');
        } else {
            $(this).removeClass('is-valid');
        }
    });
});

function updatePaymentFields(status) {
    if (status === 'paid') {
        $('#cashReceivedGroup').show();
        $('#cash_received').prop('required', true);
    } else {
        $('#cashReceivedGroup').hide();
        $('#cash_received').prop('required', false);
        $('#changeAlert').hide();
    }
}

function updateChangeDisplay(received, total) {
    var $alert = $('#changeAlert');
    var $amount = $('#changeAmount');
    
    if (received && total) {
        var change = received - total;
        $alert.show();
        
        if (change >= 0) {
            $alert.removeClass('alert-danger').addClass('alert-success')
                .find('strong').text('Change to give back:');
            $amount.text(change.toFixed(2));
        } else {
            $alert.removeClass('alert-success').addClass('alert-danger')
                .find('strong').text('Amount Short:');
            $amount.text(Math.abs(change).toFixed(2));
        }
    } else {
        $alert.hide();
    }
}
</script> 