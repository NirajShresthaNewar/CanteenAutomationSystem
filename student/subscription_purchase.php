<?php
session_start();
require_once '../connection/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Check for existing active subscription
$stmt = $conn->prepare("
    SELECT us.*, sp.name as plan_name, sp.vendor_id
    FROM user_subscriptions us
    JOIN subscription_plans sp ON us.plan_id = sp.id
    WHERE us.user_id = ? 
    AND us.status = 'active'
    AND us.end_date > NOW()
");
$stmt->execute([$_SESSION['user_id']]);
$active_subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'])) {
    $plan_id = $_POST['plan_id'];
    
    // Verify if user already has an active subscription
    if ($active_subscription) {
        $_SESSION['error'] = "You already have an active subscription plan. Please wait for it to expire or cancel it before purchasing a new one.";
        header('Location: subscription_portal.php');
        exit();
    }
    
    // Get plan details
    $stmt = $conn->prepare("
        SELECT * FROM subscription_plans 
        WHERE id = ?
    ");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        $_SESSION['error'] = "Invalid subscription plan.";
        header('Location: subscription_portal.php');
        exit();
    }
    
    try {
        $conn->beginTransaction();
        
        // Create subscription transaction record
        $stmt = $conn->prepare("
            INSERT INTO subscription_transactions (
                user_id, plan_id, amount, payment_method, status, created_at
            ) VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $plan_id,
            $plan['price'],
            $_POST['payment_method']
        ]);
        
        $transaction_id = $conn->lastInsertId();
        
        // Create subscription record
        $stmt = $conn->prepare("
            INSERT INTO user_subscriptions (
                user_id, plan_id, start_date, end_date, status, created_at, updated_at
            ) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active', NOW(), NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $plan_id,
            $plan['duration_days']
        ]);
        
        $conn->commit();
        
        $_SESSION['success'] = "Subscription purchased successfully!";
        header('Location: subscription_portal.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error processing subscription. Please try again.";
        header('Location: subscription_portal.php');
        exit();
    }
}

// Get available subscription plans
$stmt = $conn->prepare("
    SELECT * FROM subscription_plans 
    WHERE vendor_id = ? 
    ORDER BY price ASC
");
$stmt->execute([$_GET['vendor_id'] ?? 1]); // Default to vendor_id 1 if not specified
$subscription_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
?> 