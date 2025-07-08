<?php
session_start();
require_once '../connection/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

try {
    $username = $_POST['username'] ?? '';
    $contact = $_POST['contact'] ?? '';
    
    // Basic validation
    if (empty($username) || empty($contact)) {
        echo json_encode(['success' => false, 'message' => 'Username and contact are required']);
        exit();
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Update basic info
    $stmt = $conn->prepare("
        UPDATE users 
        SET username = ?, contact_number = ?
        WHERE id = ?
    ");
    $stmt->execute([$username, $contact, $_SESSION['user_id']]);
    
    // Handle profile picture upload if provided
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic'];
        $fileName = $file['name'];
        $fileType = $file['type'];
        $fileTmpName = $file['tmp_name'];
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($fileType, $allowedTypes)) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, JPEG & PNG files are allowed.']);
            exit();
        }
        
        // Generate unique filename
        $newFileName = $_SESSION['user_id'] . '_' . uniqid() . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
        $uploadPath = '../uploads/profile/' . $newFileName;
        
        // Create directory if it doesn't exist
        if (!file_exists('../uploads/profile/')) {
            mkdir('../uploads/profile/', 0777, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($fileTmpName, $uploadPath)) {
            // Update profile picture in database
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$newFileName, $_SESSION['user_id']]);
            
            // Delete old profile picture if exists
            $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $oldPic = $stmt->fetchColumn();
            
            if ($oldPic && $oldPic !== $newFileName && file_exists('../uploads/profile/' . $oldPic)) {
                unlink('../uploads/profile/' . $oldPic);
            }
        } else {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to upload profile picture']);
            exit();
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 