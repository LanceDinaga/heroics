<?php
// login.php
session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'] ?? 'staff'; // Fetches role from DB dynamically

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Heroics Gaming Lounge</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        body { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            position: relative;
            overflow: hidden;
            background-color: var(--bg-dark);
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('helmet.png');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            opacity: 0.15;
            filter: drop-shadow(0 0 25px rgba(138, 43, 226, 0.4));
            z-index: 1;
        }

        .login-card { 
            position: relative;
            z-index: 2;
            background: rgba(19, 13, 36, 0.88);
            backdrop-filter: blur(12px);
            padding: 40px; 
            border-radius: 12px; 
            border: 1px solid var(--border-color); 
            box-shadow: 0 0 35px rgba(138, 43, 226, 0.35); 
            width: 340px; 
            text-align: center; 
        }

        .login-card img.card-logo { 
            height: 90px; 
            margin-bottom: 10px; 
            filter: drop-shadow(0 0 10px var(--primary-purple)); 
        }

        .login-card h2 { 
            color: #fff; 
            font-size: 20px; 
            margin-bottom: 20px; 
            letter-spacing: 1.5px;
        }

        .input-group { 
            margin-bottom: 15px; 
            text-align: left; 
        }

        .input-group label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
            color: var(--text-muted); 
            font-size: 12px; 
            text-transform: uppercase; 
        }

        .error-msg { 
            color: #ff4d4d; 
            font-size: 14px; 
            margin-bottom: 15px; 
        }
    </style>
</head>
<body>
    <div class="login-card">
        <img src="helmet.png" alt="Heroics Helmet Logo" class="card-logo">
        <h2>STAFF LOGIN</h2>
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" required autocomplete="off">
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn" style="width: 100%; margin-top: 10px;">Sign In</button>
        </form>
    </div>
</body>
</html>