<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a vendor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vendor') {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: ../auth/login.php');
    exit();
}

// Get vendor ID
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    $_SESSION['error'] = "Vendor not found";
    header('Location: ../auth/logout.php');
    exit();
}

$vendor_id = $vendor['id'];

// Build query conditions
$conditions = ["o.vendor_id = ?"];
$params = [$vendor_id];

// Handle status filter
$status = $_GET['status'] ?? 'all';
if ($status !== 'all') {
    $conditions[] = "COALESCE(ot.status, 'pending') = ?";
    $params[] = $status;
}

// Handle search
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $conditions[] = "(o.receipt_number LIKE ? OR u.username LIKE ?)";
    $params[] = $search;
    $params[] = $search;
}

// Handle date filter
if (!empty($_GET['date'])) {
    $conditions[] = "DATE(o.order_date) = ?";
    $params[] = $_GET['date'];
}

// Build the WHERE clause
$where_clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get orders with latest status
$stmt = $conn->prepare("
    SELECT 
        o.*,
            u.username as customer_name,
        u.contact_number as customer_phone,
            COALESCE(ot.status, 'pending') as current_status,
        ot.notes as status_notes,
        ot.status_changed_at,
        odd.order_type,
        odd.delivery_location,
        odd.building_name,
        odd.floor_number,
        odd.room_number,
        odd.contact_number,
        odd.table_number,
        odd.delivery_instructions,
        oa.worker_id,
        w.name as worker_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_delivery_details odd ON o.id = odd.order_id
    LEFT JOIN (
        SELECT ot1.*
        FROM order_tracking ot1
        INNER JOIN (
            SELECT order_id, MAX(status_changed_at) as max_date
            FROM order_tracking
            GROUP BY order_id
        ) ot2 ON ot1.order_id = ot2.order_id AND ot1.status_changed_at = ot2.max_date
    ) ot ON o.id = ot.order_id
    LEFT JOIN order_assignments oa ON o.id = oa.order_id AND oa.status = 'assigned'
    LEFT JOIN workers w ON oa.worker_id = w.id
    {$where_clause}
    ORDER BY ot.status_changed_at DESC, o.order_date DESC
");

// Disable statement caching
$stmt->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Store the fetch time
$_SESSION['orders_fetch_time'] = time();

// Get order counts by status
function getOrderCountByStatus($conn, $vendor_id, $status) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
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
        WHERE o.vendor_id = ?
        AND COALESCE(ot.status, 'pending') = ?
    ");
    $stmt->execute([$vendor_id, $status]);
    return $stmt->fetch(PDO::FETCH_COLUMN);
    }

// Helper function for delivery details
function getDeliveryDetails($order) {
    $details = [];
    
    if ($order['order_type'] === 'delivery') {
        $details[] = "Location: " . htmlspecialchars($order['delivery_location']);
        if ($order['building_name']) $details[] = "Building: " . htmlspecialchars($order['building_name']);
        if ($order['floor_number']) $details[] = "Floor: " . htmlspecialchars($order['floor_number']);
        if ($order['room_number']) $details[] = "Room: " . htmlspecialchars($order['room_number']);
        $details[] = "Contact: " . htmlspecialchars($order['contact_number']);
        if ($order['delivery_instructions']) {
            $details[] = "Instructions: " . htmlspecialchars($order['delivery_instructions']);
        }
    } elseif ($order['order_type'] === 'dine_in') {
        $details[] = "Table: " . htmlspecialchars($order['table_number']);
    }
    
    return implode("<br>", $details);
}

// Helper functions for badges
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending': return 'bg-warning';
        case 'accepted': return 'bg-info';
        case 'in_progress': return 'bg-primary';
        case 'ready': return 'bg-success';
        case 'completed': return 'bg-success';
        case 'cancelled': return 'bg-danger';
        case 'rejected': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function getPaymentBadgeClass($payment_method) {
    switch ($payment_method) {
        case 'credit': return 'bg-success';
        case 'esewa': return 'bg-info';
        default: return 'bg-warning';
    }
}

function getPaymentMethodName($payment_method) {
    switch ($payment_method) {
        case 'credit': return 'Credit Account';
        case 'esewa': return 'Online Payment (eSewa)';
        default: return 'Cash on Delivery';
        }
    }

// Return the data
$_SESSION['orders'] = $orders;
header('Location: manage_orders.php');
exit();
?> 