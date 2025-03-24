<?php
session_start();

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
        // TODO: Implement new database connection and authentication
        // For now, redirect to appropriate dashboard based on role
        $role = 'student'; // This should come from your new database
        $_SESSION['user_id'] = 1; // This should come from your new database
        $_SESSION['user_name'] = 'Test User'; // This should come from your new database
        $_SESSION['user_role'] = $role;
        
        // Set session cookie parameters for security
        session_set_cookie_params([
            'lifetime' => 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        // Redirect based on role
        switch ($role) {
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
        $_SESSION['errors'] = $errors;
        header('Location: ../index.php');
        exit();
    }
}