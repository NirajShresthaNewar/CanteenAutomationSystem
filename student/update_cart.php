<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if (!isset($_POST['cart_item_id']) || !isset($_POST['quantity'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$cart_item_id = intval($_POST['cart_item_id']);
$quantity = intval($_POST['quantity']);

if ($quantity < 1) {
    echo json_encode(['success' => false, 'error' => 'Quantity must be at least 1']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE cart_items 
        SET quantity = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$quantity, $cart_item_id, $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Item not found or not authorized']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 