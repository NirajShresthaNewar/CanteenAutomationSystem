<?php
session_start();
require_once '../connection/db_connection.php';
require_once '../includes/profile_template.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Get user data from database
try {
    // Fetch user info with school details
    $stmt = $conn->prepare("
        SELECT u.*, ss.school_id, s.name as school_name 
        FROM users u
        JOIN staff_students ss ON u.id = ss.user_id
        JOIN schools s ON ss.school_id = s.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        $_SESSION['error'] = "Student information not found";
        header('Location: dashboard.php');
        exit();
    }
    
    // Role specific data
    $roleSpecificData = [
        'School' => $userData['school_name']
    ];
    
    // Sensitive information that can't be edited
    $readOnlyFields = ['email']; // Students can change their username and contact info
    
    // Generate profile page content
    $content = displayProfile($userData, $roleSpecificData, $readOnlyFields);
    
    // Page title
    $pageTitle = "My Profile";
    
    // Additional CSS for profile page
    $additionalStyles = '
    <style>
        .profile-user-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 3px solid #adb5bd;
            margin: 0 auto;
            padding: 3px;
        }
        
        .nav-pills .nav-link:not(.active):hover {
            color: #007bff;
        }
    </style>
    ';
    
    // Additional scripts for profile page
    $additionalScripts = '
    <script>
        $(document).ready(function() {
            // Add the following code if you want the name of the file appear on select
            $(".custom-file-input").on("change", function() {
                var fileName = $(this).val().split("\\\\").pop();
                $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
            });
            
            // Form validation for password change
            $("#new_password, #confirm_password").on("keyup", function() {
                if ($("#new_password").val() != $("#confirm_password").val()) {
                    $("#confirm_password").addClass("is-invalid").removeClass("is-valid");
                } else {
                    $("#confirm_password").addClass("is-valid").removeClass("is-invalid");
                }
            });
        });
    </script>
    ';
    
    // Include layout
    include '../includes/layout.php';
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: dashboard.php');
    exit();
}
?> 