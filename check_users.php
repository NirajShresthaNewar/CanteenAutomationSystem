<?php
require_once 'connection/db_connection.php';

echo "<h2>Users with student role:</h2>";
$stmt = $conn->query("SELECT id, username, email, role, approval_status FROM users WHERE role='student'");
echo "<pre>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
echo "</pre>";

echo "<h2>Student entries in staff_students table:</h2>";
$stmt = $conn->query("SELECT ss.id, ss.user_id, ss.school_id, ss.role, ss.approval_status, u.username, u.email 
                      FROM staff_students ss 
                      JOIN users u ON ss.user_id = u.id 
                      WHERE ss.role='student'");
echo "<pre>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
echo "</pre>";
?> 