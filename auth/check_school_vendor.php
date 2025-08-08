<?php
session_start();
require_once '../connection/db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $school_id = trim($_POST['school_id'] ?? '');
    
    if (empty($school_id)) {
        echo json_encode(['error' => 'School ID is required']);
        exit();
    }
    
    try {
        // Check if school already has a vendor (approved or pending)
        $stmt = $conn->prepare("SELECT v.id, u.username FROM vendors v 
                              JOIN users u ON v.user_id = u.id 
                              WHERE v.school_id = ? AND v.approval_status IN ('approved', 'pending')");
        $stmt->execute([$school_id]);
        $existing_vendor = $stmt->fetch();
        
        echo json_encode([
            'hasVendor' => !empty($existing_vendor),
            'vendorName' => $existing_vendor ? $existing_vendor['username'] : null
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
