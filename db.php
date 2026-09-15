<?php
// db.php - Database connection & global session setup

date_default_timezone_set('Asia/Manila');

// Suppress server-level session directory permissions warnings on free hosting
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function setFlashMessage($message) {
    $_SESSION['flash_message'] = $message;
}

function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? '';
    unset($_SESSION['flash_message']);
    return $message;
}

$host = 'sql302.infinityfree.com'; // Or your InfinityFree MySQL Hostname (e.g., sqlXXX.epizy.com)
$db   = 'if0_42761603_heroics'; // Your database name
$user = 'if0_42761603';      // Your database username
$pass = 'dinaga123';  // Your database password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '+08:00'");
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}