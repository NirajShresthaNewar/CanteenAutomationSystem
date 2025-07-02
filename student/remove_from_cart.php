<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if (!isset($_POST['cart_item_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing cart item ID']);
    exit;
}

$cart_item_id = intval($_POST['cart_item_id']);

try {
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$cart_item_id, $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Item not found or not authorized']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 