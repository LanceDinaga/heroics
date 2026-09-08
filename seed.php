<?php
// seed.php - Run once to seed/reset 'admin' and 'dominic' accounts
require 'db.php';

$accounts = [
    [
        'username' => 'admin',
        'password' => '21000219400',
        'role'     => 'admin'
    ],
    [
        'username' => 'dominic',
        'password' => 'petilla',
        'role'     => 'admin'
    ]
];

try {
    // Check if 'role' column exists in 'users' table
    $columnCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if (!$columnCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'staff'");
    }

    foreach ($accounts as $acc) {
        $username = $acc['username'];
        $password = $acc['password'];
        $role     = $acc['role'];

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = ?");
        $stmt->execute([strtolower($username)]);
        $user = $stmt->fetch();

        if ($user) {
            $updateStmt = $pdo->prepare("UPDATE users SET password = ?, role = ? WHERE id = ?");
            $updateStmt->execute([$hashed_password, $role, $user['id']]);
            echo "<p style='color: #00ffcc; font-family: sans-serif;'>User '<b>{$username}</b>' updated successfully with password and '{$role}' role!</p>";
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $insertStmt->execute([$username, $hashed_password, $role]);
            echo "<p style='color: #00d2ff; font-family: sans-serif;'>User '<b>{$username}</b>' created successfully as '{$role}'!</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: #ff4d4d; font-family: sans-serif;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>