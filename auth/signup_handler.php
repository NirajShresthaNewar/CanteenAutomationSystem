<?php
session_start();
require_once '../connection/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get common fields
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $contact_number = trim($_POST['contact_number']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $role = trim($_POST['role']);
        $school_id = trim($_POST['school_id'] ?? 1); // Make school selection required
        
        // Validate required fields
        if (empty($username) || empty($email) || empty($contact_number) || 
            empty($password) || empty($confirm_password) || empty($role) || empty($school_id)) {
            $_SESSION['signup_errors'] = ["All fields are required"];
            header('Location: ../index.php');
            exit();
        }

        // Validate role
        $allowed_roles = ['vendor', 'staff', 'student', 'worker'];
        if (!in_array($role, $allowed_roles)) {
            $_SESSION['signup_errors'] = ["Invalid role selected"];
            header('Location: ../index.php');
            exit();
        }

        // Validate password
        if ($password !== $confirm_password) {
            $_SESSION['signup_errors'] = ["Passwords do not match"];
            header('Location: ../index.php');
            exit();
        }

        // Validate school selection
        if (empty($_POST['school_id'])) {
            $_SESSION['signup_errors'] = ["Please select a school"];
            header('Location: ../index.php');
            exit();
        }

        // Start transaction
        $conn->beginTransaction();

        // Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (username, email, contact_number, role, password, approval_status) 
                               VALUES (?, ?, ?, ?, ?, 'pending')");
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt->execute([$username, $email, $contact_number, $role, $hashed_password]);
        $user_id = $conn->lastInsertId();

        // Handle role-specific tables
        switch($role) {
            case 'vendor':
                $license_number = $_POST['license_number'] ?? null;
                $opening_hours = $_POST['opening_hours'] ?? '9:00 AM - 5:00 PM';
                $school_id = $_POST['school_id'];
                
                // Validate school exists
                $stmt = $conn->prepare("SELECT id FROM schools WHERE id = ?");
                $stmt->execute([$school_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("Selected school does not exist");
                }
                
                // Check if school already has a vendor (approved or pending)
                $stmt = $conn->prepare("SELECT v.id, u.username FROM vendors v 
                                      JOIN users u ON v.user_id = u.id 
                                      WHERE v.school_id = ? AND v.approval_status IN ('approved', 'pending')");
                $stmt->execute([$school_id]);
                $existing_vendor = $stmt->fetch();
                
                if ($existing_vendor) {
                    $conn->rollBack();
                    $_SESSION['signup_errors'] = ["This school already has a vendor associated with it. Only one vendor is allowed per school. Please contact the administrator if you need assistance."];
                    header('Location: ../index.php');
                    exit();
                }
                
                $stmt = $conn->prepare("INSERT INTO vendors (user_id, school_id, license_number, opening_hours, approval_status) 
                                      VALUES (?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $school_id, $license_number, $opening_hours]);
                break;

            case 'staff':
            case 'student':
                $stmt = $conn->prepare("INSERT INTO staff_students (user_id, school_id, role, approval_status) 
                                      VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $school_id, $role]);
                break;

            case 'worker':
                // For workers, we need a vendor_id
                $vendor_id = $_POST['vendor_id'] ?? null;
                if (empty($vendor_id)) {
                    throw new Exception("Vendor must be selected for workers");
                }
                $position = $_POST['position'] ?? 'kitchen_staff';
                
                $stmt = $conn->prepare("INSERT INTO workers (user_id, vendor_id, position, approval_status) 
                                      VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $vendor_id, $position]);
                break;
        }

        $conn->commit();
        $_SESSION['signup_success'] = "Registration successful! Please wait for approval.";
        header('Location: ../index.php');
        exit();

    } catch(PDOException $e) {
        $conn->rollBack();
        $_SESSION['signup_errors'] = ["Registration failed: " . $e->getMessage()];
        header('Location: ../index.php');
        exit();
    }
}
?> 