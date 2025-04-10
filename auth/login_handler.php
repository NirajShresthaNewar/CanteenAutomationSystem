<?php
session_start();
require_once '../connection/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        // Validate input
        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = "Both email and password are required";
            header('Location: ../index.php');
            exit();
        }

        // Check user exists and is approved
        $stmt = $conn->prepare("SELECT u.*, 
            CASE 
                WHEN u.role = 'vendor' THEN v.approval_status
                WHEN u.role IN ('staff', 'student') THEN ss.approval_status
                WHEN u.role = 'worker' THEN w.approval_status
                ELSE u.approval_status
            END as final_approval_status
            FROM users u
            LEFT JOIN vendors v ON u.id = v.user_id AND u.role = 'vendor'
            LEFT JOIN staff_students ss ON u.id = ss.user_id AND u.role IN ('staff', 'student')
            LEFT JOIN workers w ON u.id = w.user_id AND u.role = 'worker'
            WHERE u.email = ?");
        
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug information for worker login issues
        if ($user && $user['role'] === 'worker') {
            // Check if worker record exists
            $workerStmt = $conn->prepare("SELECT * FROM workers WHERE user_id = ?");
            $workerStmt->execute([$user['id']]);
            $workerData = $workerStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$workerData) {
                $_SESSION['login_error'] = "Worker record not found. Please contact administrator.";
                header('Location: ../index.php');
                exit();
            }
            
            // Log the approval status from both tables
            error_log("Worker Login - User ID: " . $user['id'] . ", User Status: " . $user['approval_status'] . 
                      ", Worker Status: " . $workerData['approval_status'] . ", Final Status: " . $user['final_approval_status']);
        }

        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['login_error'] = "Invalid email or password";
            header('Location: ../index.php');
            exit();
        }

        // Check approval status
        if ($user['final_approval_status'] !== 'approved') {
            // Provide specific message based on status and role
            if ($user['final_approval_status'] === 'pending') {
                $roleText = ucfirst($user['role']);
                $_SESSION['login_error'] = "Your $roleText account is pending approval. Please contact the administrator or wait for approval.";
            } else if ($user['final_approval_status'] === 'rejected') {
                $roleText = ucfirst($user['role']);
                $_SESSION['login_error'] = "Your $roleText account has been rejected. Please contact the administrator for more information.";
            } else {
                $_SESSION['login_error'] = "Your account is not active. Please contact the administrator.";
            }
            header('Location: ../index.php');
            exit();
        }

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        // Redirect based on role
        switch($user['role']) {
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            case 'vendor':
                header('Location: ../vendor/dashboard.php');
                break;
            case 'staff':
                header('Location: ../staff/dashboard.php');
                break;
            case 'student':
                header('Location: ../student/dashboard.php');
                break;
            case 'worker':
                header('Location: ../worker/dashboard.php');
                break;
            default:
                header('Location: ../index.php');
        }
        exit();

    } catch(PDOException $e) {
        $_SESSION['login_error'] = "Login failed: " . $e->getMessage();
        header('Location: ../index.php');
        exit();
    }
}