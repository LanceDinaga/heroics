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

function logActivity($pdo, $action, $description, $details = []) {
    $username = $_SESSION['username'] ?? 'guest';
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $created_at = date('Y-m-d H:i:s');
    $details_json = $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null;

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs
             (user_id, username, action, description, details, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user_id, $username, $action, $description, $details_json, $ip_address, $user_agent, $created_at]);
    } catch (PDOException $e) {
        error_log('Activity database log failed: ' . $e->getMessage());
    }

    $log_directory = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
    $log_file = $log_directory . DIRECTORY_SEPARATOR . 'activity.log';
    if (is_dir($log_directory) || @mkdir($log_directory, 0755, true)) {
        $line = json_encode([
            'created_at' => $created_at,
            'user_id' => $user_id,
            'username' => $username,
            'action' => $action,
            'description' => $description,
            'details' => $details,
            'ip_address' => $ip_address,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (@file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log('Activity file log failed: unable to write ' . $log_file);
        }
    } else {
        error_log('Activity file log failed: unable to create ' . $log_directory);
    }
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
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS activity_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                username VARCHAR(100) NOT NULL,
                action VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_activity_logs_created_at (created_at),
                INDEX idx_activity_logs_username (username),
                INDEX idx_activity_logs_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (\PDOException $e) {
        error_log('Activity table setup failed: ' . $e->getMessage());
    }
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}