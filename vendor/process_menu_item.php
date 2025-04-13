<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    header('Location: ../index.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$vendor_id = $vendor['id'];

// Handle file upload
function handleImageUpload($file, $old_image = null) {
    if (!isset($file['name']) || empty($file['name'])) {
        return $old_image;
    }

    $target_dir = "../uploads/menu_items/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;

    // Check if image file is actual image or fake image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        throw new Exception("File is not an image.");
    }

    // Check file size (limit to 5MB)
    if ($file['size'] > 5000000) {
        throw new Exception("Sorry, your file is too large.");
    }

    // Allow certain file formats
    if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        throw new Exception("Sorry, only JPG, JPEG, PNG & GIF files are allowed.");
    }

    // Upload file
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        throw new Exception("Sorry, there was an error uploading your file.");
    }

    // Delete old image if exists
    if ($old_image && file_exists('../' . $old_image)) {
        unlink('../' . $old_image);
    }

    return 'uploads/menu_items/' . $new_filename;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add':
                // Validate required fields
                if (empty($_POST['name']) || empty($_POST['price'])) {
                    throw new Exception("Name and price are required fields.");
                }

                // Handle image upload
                $image_path = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $image_path = handleImageUpload($_FILES['image']);
                }

                // Insert new menu item
                $stmt = $conn->prepare("
                    INSERT INTO menu_items (
                        vendor_id, name, description, price, image_path,
                        category_id, is_vegetarian, is_vegan, is_gluten_free,
                        is_available, availability_start, availability_end
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ");

                $stmt->execute([
                    $vendor_id,
                    $_POST['name'],
                    $_POST['description'] ?? null,
                    $_POST['price'],
                    $image_path,
                    $_POST['category_id'] ?: null,
                    isset($_POST['is_vegetarian']) ? 1 : 0,
                    isset($_POST['is_vegan']) ? 1 : 0,
                    isset($_POST['is_gluten_free']) ? 1 : 0,
                    isset($_POST['is_available']) ? 1 : 0,
                    $_POST['availability_start'] ?: null,
                    $_POST['availability_end'] ?: null
                ]);

                $_SESSION['success'] = "Menu item added successfully.";
                // If this was from the add_menu.php page, redirect to menu_items.php
                if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'add_menu.php') !== false) {
                    header('Location: menu_items.php');
                    exit();
                }
                break;

            case 'update':
                if (empty($_POST['item_id'])) {
                    throw new Exception("Item ID is required for update.");
                }

                // Verify item belongs to vendor
                $stmt = $conn->prepare("SELECT image_path FROM menu_items WHERE item_id = ? AND vendor_id = ?");
                $stmt->execute([$_POST['item_id'], $vendor_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item) {
                    throw new Exception("Menu item not found or unauthorized.");
                }

                // Handle image upload
                $image_path = $item['image_path'];
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $image_path = handleImageUpload($_FILES['image'], $image_path);
                }

                // Update menu item
                $stmt = $conn->prepare("
                    UPDATE menu_items SET
                        name = ?,
                        description = ?,
                        price = ?,
                        image_path = ?,
                        category_id = ?,
                        is_vegetarian = ?,
                        is_vegan = ?,
                        is_gluten_free = ?,
                        is_available = ?,
                        availability_start = ?,
                        availability_end = ?
                    WHERE item_id = ? AND vendor_id = ?
                ");

                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'] ?? null,
                    $_POST['price'],
                    $image_path,
                    $_POST['category_id'] ?: null,
                    isset($_POST['is_vegetarian']) ? 1 : 0,
                    isset($_POST['is_vegan']) ? 1 : 0,
                    isset($_POST['is_gluten_free']) ? 1 : 0,
                    isset($_POST['is_available']) ? 1 : 0,
                    $_POST['availability_start'] ?: null,
                    $_POST['availability_end'] ?: null,
                    $_POST['item_id'],
                    $vendor_id
                ]);

                $_SESSION['success'] = "Menu item updated successfully.";
                break;

            case 'delete':
                if (empty($_POST['item_id'])) {
                    throw new Exception("Item ID is required for deletion.");
                }

                // Verify item belongs to vendor and get image path
                $stmt = $conn->prepare("SELECT image_path FROM menu_items WHERE item_id = ? AND vendor_id = ?");
                $stmt->execute([$_POST['item_id'], $vendor_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item) {
                    throw new Exception("Menu item not found or unauthorized.");
                }

                // Delete menu item
                $stmt = $conn->prepare("DELETE FROM menu_items WHERE item_id = ? AND vendor_id = ?");
                $stmt->execute([$_POST['item_id'], $vendor_id]);

                // Delete image file if exists
                if ($item['image_path'] && file_exists('../' . $item['image_path'])) {
                    unlink('../' . $item['image_path']);
                }

                $_SESSION['success'] = "Menu item deleted successfully.";
                break;

            default:
                throw new Exception("Invalid action specified.");
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to menu items page
header('Location: menu_items.php');
exit(); 