<?php

// Check if payment method is credit and validate credit account
if ($payment_method === 'credit') {
    // Check if user has an active credit account with this vendor
    $stmt = $conn->prepare("
        SELECT ca.id, ca.credit_limit, ca.current_balance, ca.status
        FROM credit_accounts ca
        WHERE ca.user_id = ? AND ca.vendor_id = ? AND ca.status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id]);
    $credit_account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$credit_account) {
        $_SESSION['error'] = "You don't have an active credit account with this vendor.";
        header("Location: checkout.php");
        exit();
    }
    
    // Check if user has enough available credit
    $available_credit = $credit_account['credit_limit'] - $credit_account['current_balance'];
    if ($available_credit < $total_amount) {
        $_SESSION['error'] = "Insufficient credit available. Your available credit is ₹" . number_format($available_credit, 2);
        header("Location: checkout.php");
        exit();
    }
    
    $credit_account_id = $credit_account['id'];
} else {
    $credit_account_id = null;
}

// ... existing code for inserting order ...

// If this is a credit purchase, update credit account and record transaction
if ($payment_method === 'credit') {
    try {
        // Update credit account balance
        $stmt = $conn->prepare("
            UPDATE credit_accounts
            SET current_balance = current_balance + ?
            WHERE id = ?
        ");
        $stmt->execute([$total_amount, $credit_account_id]);
        
        // Record credit transaction
        $stmt = $conn->prepare("
            INSERT INTO credit_transactions (
                user_id, vendor_id, order_id, amount, transaction_type, payment_method, created_at
            ) VALUES (?, ?, ?, ?, 'purchase', 'credit', NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $vendor_id, $order_id, $total_amount]);
        
        // Update order with credit account info
        $stmt = $conn->prepare("
            UPDATE orders
            SET credit_account_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$credit_account_id, $order_id]);
    } catch (Exception $e) {
        // Log the error but don't stop order processing
        error_log("Error processing credit transaction: " . $e->getMessage());
    }
}

// ... existing code ... 