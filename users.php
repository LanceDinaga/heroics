<?php
// users.php - Admin User Management Panel
require 'db.php';
require 'shift_status_banner.php';

if (!isset($_SESSION['loggedin']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

$msg = '';
$error = '';
$logged_user = $_SESSION['username'];

// --- ACTIONS: CREATE / UPDATE / DELETE --- //

if (isset($_POST['action']) && $_POST['action'] === 'create_user') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $new_user = trim($_POST['username']);
    $new_pass = trim($_POST['password']);
    $new_role = $_POST['role'] ?? 'staff';

    if (!empty($new_user) && !empty($new_pass)) {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$new_user, $hashed, $new_role]);
            $msg = "User '{$new_user}' created successfully!";
        } catch (PDOException $e) {
            $error = "Username already exists.";
        }
    } else {
        $error = "Username and password are required.";
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'update_user') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $user_id = intval($_POST['user_id']);
    $up_user = trim($_POST['username']);
    $up_pass = trim($_POST['password']);
    $up_role = $_POST['role'];

    if (!empty($up_user)) {
        try {
            if (!empty($up_pass)) {
                $hashed = password_hash($up_pass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                $stmt->execute([$up_user, $hashed, $up_role, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt->execute([$up_user, $up_role, $user_id]);
            }

            if ($user_id == ($_SESSION['user_id'] ?? 0)) {
                $_SESSION['username'] = $up_user;
                $_SESSION['role'] = $up_role;
            }

            $msg = "User details updated successfully!";
        } catch (PDOException $e) {
            $error = "Username '{$up_user}' is already taken.";
        }
    }
}

if (isset($_GET['delete_id']) && isset($_GET['csrf_token'])) {
    if ($_GET['csrf_token'] === $_SESSION['csrf_token']) {
        $del_id = intval($_GET['delete_id']);

        $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt->execute([$del_id]);
        $target_user = $stmt->fetch();

        if ($target_user) {
            if ($target_user['role'] === 'admin') {
                $error = "Security Policy: Admin accounts cannot be deleted.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$del_id]);
                header("Location: users.php?msg=deleted");
                exit;
            }
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg = "Staff account deleted successfully!";
}

$users = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Management - Heroics Gaming Lounge</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <script>
        function toggleEdit(id) {
            let viewRow = document.getElementById('view_' + id);
            let editRow = document.getElementById('edit_' + id);
            if (viewRow.style.display === 'none') {
                viewRow.style.display = '';
                editRow.style.display = 'none';
            } else {
                viewRow.style.display = 'none';
                editRow.style.display = '';
            }
        }
    </script>
</head>
<body>

    <div class="header">
        <div class="brand-section">
            <a href="shifts.php"><img src="heroics-title.png" alt="Heroics Gaming Lounge" class="brand-title" style="height: 45px; width: auto; filter: drop-shadow(0 0 8px var(--primary-purple));"></a>
        </div>
        <div class="header-user">
            Logged in as: <b><?= htmlspecialchars($logged_user); ?></b> (<?= ucfirst($_SESSION['role']); ?>) | 
            <a href="index.php">Dashboard</a> | 
            <a href="shifts.php">Shifts</a> |
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <?php renderShiftBanner($pdo); ?>

    <?php if ($msg): ?><div class="box" style="border-left: 4px solid #00ffcc; padding: 12px; margin-bottom: 15px; color:#00ffcc;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="box" style="border-left: 4px solid #ff4d4d; padding: 12px; margin-bottom: 15px; color:#ff4d4d;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="box" style="border-left: 4px solid var(--neon-pink);">
        <h3>+ Create New User</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="create_user">
            <div class="form-grid">
                <input type="text" name="username" placeholder="Username" required autocomplete="off">
                <input type="password" name="password" placeholder="Password" required>
                <select name="role" required>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <br>
            <button type="submit" class="btn btn-green">Create User</button>
        </form>
    </div>

    <div class="box">
        <h3>User Accounts Directory</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr id="view_<?= $u['id'] ?>">
                    <td>#<?= $u['id'] ?></td>
                    <td><b><?= htmlspecialchars($u['username']) ?></b></td>
                    <td>
                        <span style="color: <?= $u['role'] === 'admin' ? 'var(--neon-pink)' : 'var(--neon-blue)'; ?>; font-weight: bold;">
                            <?= strtoupper($u['role'] ?? 'staff') ?>
                        </span>
                    </td>
                    <td><?= !empty($u['created_at']) ? date('M j, Y, g:i A', strtotime($u['created_at'])) : 'N/A' ?></td>
                    <td>
                        <button class="btn" style="padding: 5px 10px; font-size:12px;" onclick="toggleEdit(<?= $u['id'] ?>)">Edit / Pass</button>
                        <?php if (($u['role'] ?? '') !== 'admin'): ?>
                            <a href="users.php?delete_id=<?= $u['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" onclick="return confirm('Delete staff account \'<?= htmlspecialchars($u['username']) ?>\'?')" class="btn-danger" style="text-decoration:none; display:inline-block;">Delete</a>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 11px; font-style: italic;">Protected (Admin)</span>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr id="edit_<?= $u['id'] ?>" class="edit-row" style="display:none;">
                    <td colspan="5">
                        <form method="POST" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="update_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="text" name="username" value="<?= htmlspecialchars($u['username']) ?>" required>
                            <select name="role">
                                <option value="staff" <?= ($u['role'] ?? 'staff') === 'staff' ? 'selected' : '' ?>>Staff</option>
                                <option value="admin" <?= ($u['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <input type="password" name="password" placeholder="New Password">
                            <button type="submit" class="btn btn-green" style="padding: 5px 10px; font-size:12px;">Save</button>
                            <button type="button" class="btn-danger" onclick="toggleEdit(<?= $u['id'] ?>)" style="padding: 5px 10px; font-size:12px;">Cancel</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    let isInternalNavigation = false;

    document.addEventListener('click', function (e) {
        if (e.target.closest('a, button, input[type="submit"]')) {
            isInternalNavigation = true;
        }
    }, true);

    document.addEventListener('submit', function () {
        isInternalNavigation = true;
    }, true);

    window.addEventListener('pagehide', function () {
        if (!isInternalNavigation) {
            navigator.sendBeacon('logout.php');
        }
    });
</script>

</body>
</html>