<?php
require_once __DIR__ . '/../config/cors.php';
configureCORS();

// Get the image path from the query parameter
$image_path = isset($_GET['path']) ? $_GET['path'] : null;

if (!$image_path) {
    http_response_code(400);
    exit('Image path not provided');
}

// Construct the full path (adjust this based on your actual upload directory)
$full_path = __DIR__ . '/../../uploads/menu_items/' . basename($image_path);

// Check if file exists
if (!file_exists($full_path)) {
    http_response_code(404);
    exit('Image not found');
}

// Get image mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $full_path);
finfo_close($finfo);

// Set proper content type
header('Content-Type: ' . $mime_type);
header('Cache-Control: public, max-age=86400'); // Cache for 24 hours

// Output the image
readfile($full_path); 