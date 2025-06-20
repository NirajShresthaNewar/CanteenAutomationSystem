<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    try {
        $conn->beginTransaction();
        
        $plan_id = $_POST['plan_id'];
        $payment_method = $_POST['payment_method'];
        
        // Get plan details
        $stmt = $conn->prepare("
            SELECT sp.*, v.id as vendor_id 
            FROM subscription_plans sp
            JOIN vendors v ON sp.vendor_id = v.id
            WHERE sp.id = ? AND sp.is_active = 1
        ");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan) {
            throw new Exception("Invalid subscription plan selected.");
        }
        
        // Check if user already has an active subscription
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM user_subscriptions 
            WHERE user_id = ? AND status = 'active' 
            AND end_date > NOW()
        ");
        $stmt->execute([$user_id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("You already have an active subscription.");
        }
        
        // Create subscription transaction
        $stmt = $conn->prepare("
            INSERT INTO subscription_transactions 
            (user_id, plan_id, amount, payment_method, status, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $plan_id, $plan['price'], $payment_method, 'pending']);
        $transaction_id = $conn->lastInsertId();
        
        // Create user subscription
        $stmt = $conn->prepare("
            INSERT INTO user_subscriptions 
            (user_id, plan_id, start_date, end_date, status, created_at, updated_at)
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active', NOW(), NOW())
        ");
        $stmt->execute([$user_id, $plan_id, $plan['duration_days']]);
        $subscription_id = $conn->lastInsertId();
        
        // If payment method is cash, mark transaction as completed
        if ($payment_method === 'cash') {
            $stmt = $conn->prepare("
                UPDATE subscription_transactions 
                SET status = 'completed', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$transaction_id]);
            
            $_SESSION['success'] = "Subscription activated successfully! Please set your meal preferences.";
        } else {
            // For eSewa, redirect to payment page
            $_SESSION['pending_subscription'] = [
                'transaction_id' => $transaction_id,
                'subscription_id' => $subscription_id,
                'amount' => $plan['price']
            ];
            
            $conn->commit();
            header('Location: ../payment/esewa_handler.php');
            exit();
        }
        
        $conn->commit();
        header('Location: meal_preferences.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header('Location: meal_subscription.php');
        exit();
    }
} else {
    header('Location: meal_subscription.php');
    exit();
} 