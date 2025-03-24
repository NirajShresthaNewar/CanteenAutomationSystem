<?php
session_start();
require_once '../connection/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Debug: Print POST data
    error_log("POST Data: " . print_r($_POST, true));
    
    // Common fields validation
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $school = trim($_POST['school'] ?? ''); // New field

    // Debug: Print validated data
    error_log("Validated Data: " . print_r([
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'school' => $school
    ], true));

    // Validate common fields
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($contact_number)) $errors[] = "Contact number is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($password)) $errors[] = "Password is required";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (empty($role)) $errors[] = "Role is required";
    if (empty($school)) $errors[] = "School is required";

    // Role-specific validation
    switch ($role) {
        case 'vendor':
            if (empty($_POST['location'])) $errors[] = "Location is required for vendors";
            break;
        case 'worker':
            if (empty($_POST['position'])) $errors[] = "Position is required for workers";
            if (empty($_POST['shift_schedule'])) $errors[] = "Shift schedule is required for workers";
            break;
        case 'student':
            if (empty($_POST['student_number'])) $errors[] = "Student number is required";
            if (empty($_POST['program'])) $errors[] = "Program is required";
            if (empty($_POST['year_of_study'])) $errors[] = "Year of study is required";
            break;
        case 'staff':
            if (empty($_POST['employee_number'])) $errors[] = "Employee number is required";
            if (empty($_POST['department'])) $errors[] = "Department is required";
            if (empty($_POST['office_location'])) $errors[] = "Office location is required";
            break;
    }

    // Debug: Print validation errors if any
    if (!empty($errors)) {
        error_log("Validation Errors: " . print_r($errors, true));
    }

    if (empty($errors)) {
        try {
            // Check if database connection exists
            if (!isset($conn) || !$conn) {
                throw new Exception("Database connection not established");
            }

            // Debug: Print database connection status
            error_log("Database connection established successfully");

            $conn->beginTransaction();

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Debug: Print SQL query for users table
            $user_query = "INSERT INTO users (username, email, school, address, password, role, verified, created_at) VALUES (?, ?, ?, ?, ?, ?, FALSE, NOW())";
            error_log("User Query: " . $user_query);
            error_log("User Values: " . print_r([$username, $email, $school, $address, $hashed_password, $role], true));

            // Insert into users table
            $stmt = $conn->prepare($user_query);
            $stmt->execute([$username, $email, $school, $address, $hashed_password, $role]);
            $user_id = $conn->lastInsertId();

            // Debug: Print user_id
            error_log("User ID created: " . $user_id);

            // Insert role-specific data
            switch ($role) {
                case 'vendor':
                    $stmt = $conn->prepare("INSERT INTO vendors (user_id, contact_number, location, license_number, verified) VALUES (?, ?, ?, ?, FALSE)");
                    $stmt->execute([$user_id, $contact_number, $_POST['location'], $_POST['license_number'] ?? null]);
                    break;
                case 'worker':
                    $stmt = $conn->prepare("INSERT INTO workers (user_id, position, shift_schedule, verified) VALUES (?, ?, ?, FALSE)");
                    $stmt->execute([$user_id, $_POST['position'], $_POST['shift_schedule']]);
                    break;
                case 'student':
                    $stmt = $conn->prepare("INSERT INTO students (user_id, student_number, program, year_of_study, verified, active) VALUES (?, ?, ?, ?, FALSE, 1)");
                    $stmt->execute([$user_id, $_POST['student_number'], $_POST['program'], $_POST['year_of_study']]);
                    break;
                case 'staff':
                    $stmt = $conn->prepare("INSERT INTO staff (user_id, employee_number, department, office_location, active) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$user_id, $_POST['employee_number'], $_POST['department'], $_POST['office_location']]);
                    break;
            }

            $conn->commit();

            // Debug: Print successful registration
            error_log("Registration successful for user: " . $username);

            // Set session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['verified'] = false;
            $_SESSION['school'] = $school;

            // Redirect based on role
            switch ($role) {
                case 'vendor':
                    header("Location: ../vendor/dashboard.php");
                    break;
                case 'worker':
                    header("Location: ../worker/dashboard.php");
                    break;
                case 'student':
                    header("Location: ../student/dashboard.php");
                    break;
                case 'staff':
                    header("Location: ../staff/dashboard.php");
                    break;
                default:
                    header("Location: ../index.php");
            }
            exit();

        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollBack();
            }
            $errors[] = "Registration failed: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    }

    if (!empty($errors)) {
        $_SESSION['signup_errors'] = $errors;
        header("Location: ../index.php");
        exit();
    }
}
?> 