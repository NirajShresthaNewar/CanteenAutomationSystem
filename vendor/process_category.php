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

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add':
                // Validate required fields
                if (empty($_POST['name'])) {
                    throw new Exception("Category name is required.");
                }

                // Check if category name already exists for this vendor
                $stmt = $conn->prepare("SELECT category_id FROM menu_categories WHERE name = ? AND vendor_id = ?");
                $stmt->execute([$_POST['name'], $vendor_id]);
                if ($stmt->fetch()) {
                    throw new Exception("A category with this name already exists.");
                }

                // Insert new category
                $stmt = $conn->prepare("
                    INSERT INTO menu_categories (vendor_id, name, description)
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $vendor_id,
                    $_POST['name'],
                    $_POST['description'] ?? null
                ]);
                
                $category_id = $conn->lastInsertId();
                $_SESSION['success'] = "Category added successfully.";
                
                // Check if this is an AJAX request
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Category added successfully.',
                        'id' => $category_id,
                        'name' => $_POST['name']
                    ]);
                    exit;
                }
                break;

            case 'update':
                if (empty($_POST['category_id'])) {
                    throw new Exception("Category ID is required for update.");
                }

                // Verify category belongs to vendor
                $stmt = $conn->prepare("SELECT category_id FROM menu_categories WHERE category_id = ? AND vendor_id = ?");
                $stmt->execute([$_POST['category_id'], $vendor_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("Category not found or unauthorized.");
                }

                // Check if new name already exists for this vendor (excluding current category)
                $stmt = $conn->prepare("
                    SELECT category_id 
                    FROM menu_categories 
                    WHERE name = ? AND vendor_id = ? AND category_id != ?
                ");
                $stmt->execute([$_POST['name'], $vendor_id, $_POST['category_id']]);
                if ($stmt->fetch()) {
                    throw new Exception("A category with this name already exists.");
                }

                // Update category
                $stmt = $conn->prepare("
                    UPDATE menu_categories 
                    SET name = ?, description = ?
                    WHERE category_id = ? AND vendor_id = ?
                ");

                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'] ?? null,
                    $_POST['category_id'],
                    $vendor_id
                ]);

                $_SESSION['success'] = "Category updated successfully.";
                break;

            case 'delete':
                if (empty($_POST['category_id'])) {
                    throw new Exception("Category ID is required for deletion.");
                }

                // Verify category belongs to vendor and has no items
                $stmt = $conn->prepare("
                    SELECT c.category_id, COUNT(m.item_id) as item_count
                    FROM menu_categories c
                    LEFT JOIN menu_items m ON c.category_id = m.category_id
                    WHERE c.category_id = ? AND c.vendor_id = ?
                    GROUP BY c.category_id
                ");
                $stmt->execute([$_POST['category_id'], $vendor_id]);
                $category = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$category) {
                    throw new Exception("Category not found or unauthorized.");
                }

                if ($category['item_count'] > 0) {
                    throw new Exception("Cannot delete category with existing menu items.");
                }

                // Delete category
                $stmt = $conn->prepare("DELETE FROM menu_categories WHERE category_id = ? AND vendor_id = ?");
                $stmt->execute([$_POST['category_id'], $vendor_id]);

                $_SESSION['success'] = "Category deleted successfully.";
                break;

            default:
                throw new Exception("Invalid action specified.");
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
    // Check if this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Redirect back to categories page (only for non-AJAX requests)
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header('Location: categories.php');
    exit();
} 