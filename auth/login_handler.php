<?php
session_start();
require_once '../connection/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $errors = [];

    // Client-side validation
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if (empty($errors)) {
        try {
            // Check user in database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['vendor_id'] = $user['vendor_id'];
                
                // Set session cookie parameters for security
                session_set_cookie_params([
                    'lifetime' => 3600, // 1 hour
                    'path' => '/',
                    'secure' => true, // Only transmit cookie over HTTPS
                    'httponly' => true, // Prevent JavaScript access
                    'samesite' => 'Strict' // Prevent CSRF
                ]);

                // Redirect based on role
                switch($user['role']) {
                    case 'admin':
                        header('Location: ../admin/dashboard.php');
                        break;
                    case 'vendor':
                        header('Location: ../vendor/dashboard.php');
                        break;
                    case 'worker':
                        header('Location: ../worker/dashboard.php');
                        break;
                    case 'staff':
                        header('Location: ../staff/dashboard.php');
                        break;
                    case 'student':
                        header('Location: ../student/dashboard.php');
                        break;
                    default:
                        header('Location: ../index.php');
                }
                exit();
            } else {
                $errors[] = "Invalid email or password.";
            }
        } catch(PDOException $e) {
            $errors[] = "Database error occurred. Please try again later.";
        }
    }

    // If errors exist, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        $_SESSION['login_email'] = $email; // Preserve email for form
        header('Location: ../index.php');
        exit();
    }
}