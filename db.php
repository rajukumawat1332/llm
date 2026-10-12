<?php
/**
 * db.php — shared database connection for HireSwipe
 * Include this at the top of any PHP file that needs the database.
 */

$DB_HOST = 'localhost';
$DB_NAME = 'hireswipe';
$DB_USER = 'student';       // change to your MySQL username
$DB_PASS = 'student';           // change to your MySQL password

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Don't leak DB details to the browser in production — log instead.
    die('Database connection failed: ' . $e->getMessage());
}
