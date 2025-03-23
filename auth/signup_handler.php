<?php
session_start();
require_once '../connection/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Get and sanitize input
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    $school = filter_input(INPUT_POST, 'school', FILTER_SANITIZE_STRING);
    $worker_position = filter_input(INPUT_POST, 'worker_position', FILTER_SANITIZE_STRING);

    // Validation
    if (empty($name)) {
        $errors[] = "Full name is required.";
    }

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

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($role)) {
        $errors[] = "Role is required.";
    }

    if (empty($school)) {
        $errors[] = "School selection is required.";
    }

    // Validate worker position if role is worker
    if ($role === 'worker' && empty($worker_position)) {
        $errors[] = "Worker position is required.";
    }

    if (empty($errors)) {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already registered.";
            } else {
                // Begin transaction
                $pdo->beginTransaction();

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert user
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, school) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashed_password, $role, $school]);

                $user_id = $pdo->lastInsertId();

                // If role is worker, insert worker position
                if ($role === 'worker') {
                    $stmt = $pdo->prepare("INSERT INTO workers (user_id, position) VALUES (?, ?)");
                    $stmt->execute([$user_id, $worker_position]);
                }

                // Commit transaction
                $pdo->commit();

                // Set success message
                $_SESSION['success_message'] = "Registration successful! Please login.";
                header('Location: ../index.php');
                exit();
            }
        } catch(PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = "Registration failed. Please try again later.";
            error_log("Registration error: " . $e->getMessage());
        }
    }

    // If errors exist, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['signup_errors'] = $errors;
        $_SESSION['signup_data'] = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'school' => $school,
            'worker_position' => $worker_position
        ];
        header('Location: ../index.php#signup');
        exit();
    }
}
?> 