<?php
require_once '../config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Get the image path from query parameter
$imagePath = isset($_GET['path']) ? $_GET['path'] : null;

if (!$imagePath) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Image path not provided']);
    exit();
}

// Construct the full path (adjust the base path according to your server structure)
$fullPath = __DIR__ . '/../../uploads/profile/' . basename($imagePath);

// Check if file exists
if (!file_exists($fullPath)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Image not found']);
    exit();
}

// Get image mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fullPath);
finfo_close($finfo);

// Set headers for image display
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=86400'); // Cache for 24 hours

// Output the image
readfile($fullPath);
exit(); 