<?php
// TODO: Implement new database connection
// This file will contain the database connection code for your new database structure

// Placeholder for new database connection
try {
    $host = 'localhost';
    $dbname = 'camups_dining';
    $username = 'root';
    $password = '';

    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?> 