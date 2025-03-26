<?php
session_start();
require_once '../connection/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    try {
        // Get user data including role
        $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug: Print user data
        error_log("User data: " . print_r($user, true));

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Debug: Print role and session
            error_log("Role: " . $user['role']);
            error_log("Session data: " . print_r($_SESSION, true));

            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    error_log("Redirecting to admin dashboard");
                    header('Location: ../admin/dashboard.php');
                    break;
                case 'vendor':
                    header('Location: ../vendor/dashboard.php');
                    break;
                case 'worker':
                    header('Location: ../worker/dashboard.php');
                    break;
                case 'student':
                    header('Location: ../student/dashboard.php');
                    break;
                case 'staff':
                    header('Location: ../staff/dashboard.php');
                    break;
                default:
                    $_SESSION['login_errors'] = ['Invalid user role'];
                    header('Location: ../index.php');
            }
            exit;
        } else {
            $_SESSION['login_errors'] = ['Invalid email or password'];
            header('Location: ../index.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['login_errors'] = ['Database error: ' . $e->getMessage()];
        header('Location: ../index.php');
        exit;
    }
}