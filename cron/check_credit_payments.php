<?php
require_once '../connection/db_connection.php';
require_once '../includes/functions.php';

try {
    $conn->beginTransaction();

    // Get all active credit accounts that are due for payment
    $stmt = $conn->prepare("
        SELECT 
            ca.id,
            ca.user_id,
            ca.vendor_id,
            ca.current_balance,
            ca.payment_due_date,
            u.email,
            u.username,
            v.id as vendor_id
        FROM credit_accounts ca
        JOIN users u ON ca.user_id = u.id
        JOIN vendors v ON ca.vendor_id = v.id
        WHERE ca.status = 'active'
        AND ca.current_balance > 0
        AND ca.payment_due_date <= CURDATE()
        AND ca.is_auto_blocked = FALSE
    ");
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($accounts as $account) {
        // Block the account
        $updateStmt = $conn->prepare("
            UPDATE credit_accounts 
            SET 
                status = 'blocked',
                is_auto_blocked = TRUE,
                auto_block_date = CURDATE()
            WHERE id = ?
        ");
        $updateStmt->execute([$account['id']]);

        // Record this in credit transactions
        $transactionStmt = $conn->prepare("
            INSERT INTO credit_transactions (
                user_id, 
                vendor_id, 
                type, 
                amount, 
                reference_type,
                reference_id,
                notes,
                created_at
            ) VALUES (?, ?, 'block', ?, 'system', NULL, 'Account automatically blocked due to overdue payment', NOW())
        ");
        $transactionStmt->execute([
            $account['user_id'],
            $account['vendor_id'],
            $account['current_balance']
        ]);

        // TODO: Send email notification to user and vendor
        // This would be implemented based on your email system
    }

    // Update payment due dates for the next month for all active accounts
    $updateDueDatesStmt = $conn->prepare("
        UPDATE credit_accounts 
        SET payment_due_date = DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)
        WHERE status = 'active'
        AND (payment_due_date IS NULL OR payment_due_date < CURDATE())
    ");
    $updateDueDatesStmt->execute();

    $conn->commit();
    echo "Successfully processed credit account payments and blocks.\n";

} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error in credit payment check cron: " . $e->getMessage());
    echo "Error processing credit accounts. Check error log for details.\n";
} 