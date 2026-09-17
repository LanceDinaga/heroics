<?php
require 'db.php';

if (!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

$action_filter = trim($_GET['action'] ?? '');
$username_filter = trim($_GET['username'] ?? '');
$date_filter = trim($_GET['date'] ?? '');

$conditions = [];
$parameters = [];
if ($action_filter !== '') {
    $conditions[] = 'action = ?';
    $parameters[] = $action_filter;
}
if ($username_filter !== '') {
    $conditions[] = 'username LIKE ?';
    $parameters[] = '%' . $username_filter . '%';
}
if ($date_filter !== '') {
    $conditions[] = 'DATE(created_at) = ?';
    $parameters[] = $date_filter;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$stmt = $pdo->prepare("SELECT * FROM activity_logs {$where} ORDER BY id DESC LIMIT 500");
$stmt->execute($parameters);
$logs = $stmt->fetchAll();

$log_file = __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'activity.log';
$file_lines = [];
if (is_readable($log_file)) {
    $file_lines = array_slice(file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -500);
    $file_lines = array_reverse($file_lines);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Activity Logs - Heroics Gaming Lounge</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        .log-table { width:100%; border-collapse:collapse; background:var(--card-bg); }
        .log-table th, .log-table td { padding:10px; border:1px solid var(--border-color); text-align:left; vertical-align:top; }
        .log-table th { color:var(--neon-blue); }
        .log-description { max-width:420px; word-break:break-word; }
        .filter-row { display:flex; flex-wrap:wrap; gap:10px; align-items:end; margin-bottom:20px; }
        .filter-row label { display:flex; flex-direction:column; gap:5px; color:var(--text-muted); font-size:12px; }
        .file-log { white-space:pre-wrap; word-break:break-word; max-height:500px; overflow:auto; background:#0f0a1e; padding:15px; border-radius:8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand-section">
            <a href="shifts.php"><img src="heroics-title.png" alt="Heroics Gaming Lounge" class="brand-title" style="height:45px;width:auto;"></a>
        </div>
        <div class="header-user">
            Logged in as: <b><?= htmlspecialchars($_SESSION['username']) ?></b> (Admin) |
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="nav-buttons">
        <a href="index.php?tab=today" class="nav-btn">Dashboard</a>
        <a href="shifts.php" class="nav-btn">Shifts</a>
        <a href="summary.php" class="nav-btn">Daily Summary</a>
        <a href="users.php" class="nav-btn">Manage Users</a>
        <a href="activity_logs.php" class="nav-btn active">Activity Logs</a>
    </div>

    <div class="content">
        <h2>Activity Logs</h2>
        <p style="color:var(--text-muted);">The database and protected file log are both copies of application activity.</p>

        <form method="GET" class="filter-row">
            <label>Username
                <input type="text" name="username" value="<?= htmlspecialchars($username_filter) ?>">
            </label>
            <label>Action
                <input type="text" name="action" value="<?= htmlspecialchars($action_filter) ?>">
            </label>
            <label>Date
                <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">
            </label>
            <button type="submit" class="btn">Filter</button>
            <a href="activity_logs.php" class="btn" style="text-decoration:none;">Clear</a>
        </form>

        <h3>Database Activity (latest 500)</h3>
        <div style="overflow-x:auto;">
            <table class="log-table">
                <thead><tr><th>Date/time</th><th>User</th><th>Action</th><th>Description</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                        <td><?= htmlspecialchars($log['username']) ?></td>
                        <td><?= htmlspecialchars($log['action']) ?></td>
                        <td class="log-description">
                            <?= htmlspecialchars($log['description']) ?>
                            <?php if ($log['details']): ?><br><small><?= htmlspecialchars($log['details']) ?></small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?><tr><td colspan="4">No database activity found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <h3 style="margin-top:30px;">File Activity (latest 500)</h3>
        <div class="file-log"><?php
            if ($file_lines) {
                foreach ($file_lines as $line) {
                    $file_entry = json_decode($line, true);
                    if (is_array($file_entry)) {
                        unset($file_entry['ip_address']);
                        $line = json_encode($file_entry, JSON_UNESCAPED_SLASHES);
                    }
                    echo htmlspecialchars($line) . PHP_EOL;
                }
            } else {
                echo 'No file activity found yet.';
            }
        ?></div>
    </div>
</body>
</html>
