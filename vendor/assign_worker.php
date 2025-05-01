<?php
session_start();
require_once '../connection/db_connection.php';

// Function to send JSON response and exit
function sendJsonResponse($data) {
    ob_clean(); // Clear any output
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    sendJsonResponse(['success' => false, 'message' => 'Unauthorized access']);
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    sendJsonResponse(['success' => false, 'message' => 'Vendor not found']);
}

// Handle AJAX request for worker list
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['order_id'])) {
    try {
        // Get order details
        $stmt = $conn->prepare("
            SELECT o.*, u.username as customer_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ? AND o.vendor_id = ?
        ");
        $stmt->execute([$_GET['order_id'], $vendor['id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            sendJsonResponse(['success' => false, 'message' => "Order not found or access denied"]);
        }

        // Get available workers
        $stmt = $conn->prepare("
            SELECT w.id, u.username, u.contact_number
            FROM workers w
            JOIN users u ON w.user_id = u.id
            WHERE w.vendor_id = ?
            ORDER BY u.username
        ");
        $stmt->execute([$vendor['id']]);
        $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJsonResponse([
            'success' => true,
            'order' => [
                'id' => $order['id'],
                'receipt_number' => $order['receipt_number'],
                'customer_name' => $order['customer_name']
            ],
            'workers' => $workers
        ]);

    } catch (Exception $e) {
        error_log("Error in assign_worker.php GET: " . $e->getMessage());
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Handle form submission for worker assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['order_id']) || !isset($_POST['worker_id'])) {
            sendJsonResponse(['success' => false, 'message' => "Missing required fields"]);
        }

        $order_id = $_POST['order_id'];
        $worker_id = $_POST['worker_id'];

        // Start transaction
        $conn->beginTransaction();

        // Verify order belongs to this vendor and get current status
        $stmt = $conn->prepare("
            SELECT o.*, COALESCE(ot.status, 'pending') as current_status
            FROM orders o
            LEFT JOIN (
                SELECT ot1.*
                FROM order_tracking ot1
                INNER JOIN (
                    SELECT order_id, MAX(status_changed_at) as max_date
                    FROM order_tracking
                    GROUP BY order_id
                ) ot2 ON ot1.order_id = ot2.order_id AND ot1.status_changed_at = ot2.max_date
            ) ot ON o.id = ot.order_id
            WHERE o.id = ? AND o.vendor_id = ?
        ");
        $stmt->execute([$order_id, $vendor['id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception("Order not found or access denied");
        }

        // Verify worker belongs to this vendor
        $stmt = $conn->prepare("
            SELECT w.*, u.username 
            FROM workers w 
            JOIN users u ON w.user_id = u.id
            WHERE w.id = ? AND w.vendor_id = ? AND w.approval_status = 'approved'
        ");
        $stmt->execute([$worker_id, $vendor['id']]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$worker) {
            throw new Exception("Invalid worker selected");
        }

        // Get the latest active assignment for this order
        $stmt = $conn->prepare("
            SELECT id, worker_id, status
            FROM order_assignments
            WHERE order_id = ? AND status != 'delivered'
            ORDER BY assigned_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$order_id]);
        $existing_assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_assignment) {
            if ($existing_assignment['worker_id'] == $worker_id) {
                throw new Exception("Order is already assigned to this worker");
            }

            // Update the existing assignment
            $stmt = $conn->prepare("
                UPDATE order_assignments 
                SET worker_id = ?,
                    status = 'assigned',
                    assigned_at = CURRENT_TIMESTAMP,
                    picked_up_at = NULL,
                    delivered_at = NULL
                WHERE id = ?
            ");
            $stmt->execute([$worker_id, $existing_assignment['id']]);

            // Log the reassignment
            $stmt = $conn->prepare("
                INSERT INTO order_tracking (
                    order_id,
                    status,
                    notes,
                    updated_by,
                    status_changed_at
                ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $order_id,
                $order['current_status'],
                "Order reassigned to worker: " . $worker['username'],
                $_SESSION['user_id']
            ]);
        } else {
            // Create new assignment only if no existing assignment
            $stmt = $conn->prepare("
                INSERT INTO order_assignments (
                    order_id,
                    worker_id,
                    status,
                    assigned_at
                ) VALUES (?, ?, 'assigned', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$order_id, $worker_id]);

            // Log the initial assignment
            $stmt = $conn->prepare("
                INSERT INTO order_tracking (
                    order_id,
                    status,
                    notes,
                    updated_by,
                    status_changed_at
                ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $order_id,
                $order['current_status'],
                "Order assigned to worker: " . $worker['username'],
                $_SESSION['user_id']
            ]);
        }

        // Commit transaction
        $conn->commit();
        
        sendJsonResponse(['success' => true, 'message' => 'Worker ' . ($existing_assignment ? 're' : '') . 'assigned successfully']);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error in assign_worker.php POST: " . $e->getMessage());
        sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
    }
}

// If we get here, it's an invalid request
sendJsonResponse(['success' => false, 'message' => 'Invalid request method']);
?> 