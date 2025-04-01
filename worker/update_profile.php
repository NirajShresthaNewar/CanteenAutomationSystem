<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a worker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header('Location: ../index.php');
    exit();
}

// Create uploads directory if it doesn't exist
$uploadDir = "../uploads/profile/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Get worker ID
$stmt = $conn->prepare("SELECT id FROM workers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$worker = $stmt->fetch(PDO::FETCH_ASSOC);
$worker_id = $worker['id'];

// Process profile information update
if (isset($_POST['update_profile'])) {
    try {
        $username = trim($_POST['username']);
        $contact_number = trim($_POST['contact_number']);
        
        // Validate inputs
        if (empty($username)) {
            throw new Exception("Name cannot be empty");
        }
        
        if (empty($contact_number)) {
            throw new Exception("Contact number cannot be empty");
        }
        
        $conn->beginTransaction();
        
        // Update user information
        $stmt = $conn->prepare("UPDATE users SET username = ?, contact_number = ? WHERE id = ?");
        $stmt->execute([$username, $contact_number, $_SESSION['user_id']]);
        
        $conn->commit();
        $_SESSION['success'] = "Profile information updated successfully";
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: profile.php');
    exit();
}

// Process password update
if (isset($_POST['update_password'])) {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All password fields are required");
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match");
        }
        
        if (strlen($new_password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $user['password'])) {
            throw new Exception("Current password is incorrect");
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
        
        $_SESSION['success'] = "Password updated successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: profile.php');
    exit();
}

// Process profile image update
if (isset($_POST['update_image'])) {
    try {
        if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new Exception("No file uploaded");
        }
        
        $file = $_FILES['profile_image'];
        
        // Check for errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed with error code: " . $file['error']);
        }
        
        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception("File size exceeds the maximum limit of 2MB");
        }
        
        // Get file info
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($fileInfo, $file['tmp_name']);
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png'];
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Only JPG and PNG files are allowed");
        }
        
        // Generate a unique filename
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uniqueName = 'worker_' . $worker_id . '_' . uniqid() . '.' . $fileExt;
        $targetFile = $uploadDir . $uniqueName;
        
        // Move the file to the uploads directory
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
            throw new Exception("Failed to upload file");
        }
        
        // Update profile picture in database
        $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
        $stmt->execute([$uniqueName, $_SESSION['user_id']]);
        
        $_SESSION['success'] = "Profile picture updated successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: profile.php');
    exit();
}

// Redirect if no action was taken
header('Location: profile.php');
exit();
?> 