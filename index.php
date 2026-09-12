<?php
// index.php - Main Dashboard (Cash Counter, Topups, Expenses)
require 'db.php';
require 'shift_status_banner.php';

if (!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}

$logged_user = $_SESSION['username'];
$user_role   = $_SESSION['role'] ?? 'staff';

// Get active shift status
$open_shift = false;
try {
    $stmt = $pdo->query("SELECT s.*, p.label AS period_label FROM shifts s JOIN shift_periods p ON s.shift_period_id = p.id WHERE s.status = 'open' ORDER BY s.id DESC LIMIT 1");
    $open_shift = $stmt->fetch();
} catch (PDOException $e) {}

$active_tab = $_GET['tab'] ?? 'today';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $action = $_POST['action'] ?? '';

    // CASH COUNTER SUBMISSION
    if ($action === 'save_counter' && $open_shift) {
        $c1000 = intval($_POST['c1000'] ?? 0);
        $c500  = intval($_POST['c500'] ?? 0);
        $c200  = intval($_POST['c200'] ?? 0);
        $c100  = intval($_POST['c100'] ?? 0);
        $c50   = intval($_POST['c50'] ?? 0);
        $c20   = intval($_POST['c20'] ?? 0);
        $c10   = intval($_POST['c10'] ?? 0);
        $c5    = intval($_POST['c5'] ?? 0);
        $c1    = intval($_POST['c1'] ?? 0);
        $gcash = floatval($_POST['gcash'] ?? 0);

        $cash_total  = ($c1000 * 1000) + ($c500 * 500) + ($c200 * 200) + ($c100 * 100) + ($c50 * 50) + ($c20 * 20) + ($c10 * 10) + ($c5 * 5) + ($c1 * 1);
        $grand_total = $cash_total + $gcash;

        // Save entry into cash counter log
        $stmt = $pdo->prepare("INSERT INTO cash_counter_logs (shift_id, c1000, c500, c200, c100, c50, c20, c10, c5, c1, gcash, grand_total, logged_by, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$open_shift['id'], $c1000, $c500, $c200, $c100, $c50, $c20, $c10, $c5, $c1, $gcash, $grand_total, $logged_user]);

        // AUTOMATICALLY UPDATE SHIFT'S STARTING CASH & GCASH FROM THIS INITIAL COUNT
        $updateShift = $pdo->prepare("UPDATE shifts SET starting_cash = ?, starting_gcash = ? WHERE id = ?");
        $updateShift->execute([$cash_total, $gcash, $open_shift['id']]);

        header("Location: index.php?tab=today");
        exit;
    }

    // EXTRA TOPUP SUBMISSION (WITH NOTE FIELD)
    if ($action === 'add_topup' && $open_shift) {
        $account_name = trim($_POST['account_name'] ?? '');
        $topup_added  = floatval($_POST['topup_added'] ?? 0);
        $note         = trim($_POST['note'] ?? '');

        if ($account_name !== '' && $topup_added > 0) {
            $stmt = $pdo->prepare("INSERT INTO balance_logs (shift_id, account_name, topup_added, note, logged_by, date_time) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$open_shift['id'], $account_name, $topup_added, $note, $logged_user]);
        }
        header("Location: index.php?tab=balance");
        exit;
    }

    // ADD EXPENSE SUBMISSION
    if ($action === 'add_expense') {
        $item_name      = trim($_POST['item_name'] ?? '');
        $amount         = floatval($_POST['amount'] ?? 0);
        $payment_method = $_POST['payment_method'] === 'gcash' ? 'gcash' : 'cash';
        $shift_id       = $open_shift ? $open_shift['id'] : null;

        if ($item_name !== '' && $amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO expense_logs (shift_id, item_name, amount, payment_method, logged_by, date_time) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$shift_id, $item_name, $amount, $payment_method, $logged_user]);
        }
        header("Location: index.php?tab=expense");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Heroics Gaming Lounge</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>

<div class="header">
    <div class="brand-section">
        <a href="shifts.php"><img src="heroics-title.png" alt="Heroics Gaming Lounge" class="brand-title" style="height:45px;width:auto;"></a>
    </div>
    <div class="header-user">
        Logged in as: <b><?= htmlspecialchars($logged_user) ?></b> (<?= ucfirst($user_role) ?>) | 
        <a href="logout.php">Logout</a>
    </div>
</div>

<?php renderShiftBanner($pdo); ?>

<div class="nav-buttons">
    <a href="index.php?tab=today" class="nav-btn <?= $active_tab === 'today' ? 'active' : '' ?>">Today (Cash Counter)</a>
    <a href="shifts.php" class="nav-btn">Shifts</a>
    <a href="index.php?tab=balance" class="nav-btn <?= $active_tab === 'balance' ? 'active' : '' ?>">+ Extra Topup</a>
    <a href="index.php?tab=expense" class="nav-btn <?= $active_tab === 'expense' ? 'active' : '' ?>">+ Add Expense</a>
    <a href="summary.php" class="nav-btn">Daily Summary</a>
    <?php if ($user_role === 'admin'): ?>
        <a href="users.php" class="nav-btn" style="border-color:#ff007f; color:#ff007f;">+ Manage Users</a>
    <?php endif; ?>
</div>

<div class="content">

    <?php if ($active_tab === 'today'): ?>
        <?php if (!$open_shift): ?>
            <div class="box" style="border-left: 4px solid #dc3545; text-align: center; padding: 30px;">
                <h3 style="color: #dc3545; margin-top: 0;">🔒 Shift Required</h3>
                <p>You cannot access the Cash Counter without an active open shift.</p>
                <a href="shifts.php" class="btn btn-green" style="display: inline-block; margin-top: 10px; text-decoration: none;">Go to Shifts & Start Shift &rarr;</a>
            </div>
        <?php else: ?>
            <div class="box">
                <h3>Cash Counter (Active Shift: <?= ucfirst(htmlspecialchars($open_shift['shift_type'])) ?>)</h3>
                <p style="font-size:12px; color:var(--text-muted); margin-bottom:15px;">Saving this count will set/update your active shift's starting Cash and GCash balance.</p>
                <form method="POST" action="index.php?tab=today">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="save_counter">
                    
                    <div class="form-grid">
                        <div><label>₱1,000</label><input type="number" name="c1000" min="0" value="0"></div>
                        <div><label>₱500</label><input type="number" name="c500" min="0" value="0"></div>
                        <div><label>₱200</label><input type="number" name="c200" min="0" value="0"></div>
                        <div><label>₱100</label><input type="number" name="c100" min="0" value="0"></div>
                        <div><label>₱50</label><input type="number" name="c50" min="0" value="0"></div>
                        <div><label>₱20</label><input type="number" name="c20" min="0" value="0"></div>
                        <div><label>₱10</label><input type="number" name="c10" min="0" value="0"></div>
                        <div><label>₱5</label><input type="number" name="c5" min="0" value="0"></div>
                        <div><label>₱1</label><input type="number" name="c1" min="0" value="0"></div>
                        <div><label>GCash Balance</label><input type="number" step="0.01" name="gcash" min="0" value="0.00"></div>
                    </div>
                    <button type="submit" class="btn btn-green" style="margin-top: 15px;">Save Counter & Update Shift Balance</button>
                </form>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'balance'): ?>
        <?php if (!$open_shift): ?>
            <div class="box" style="border-left: 4px solid #dc3545; text-align: center; padding: 30px;">
                <h3 style="color: #dc3545; margin-top: 0;">🔒 Shift Required</h3>
                <p>You cannot process Extra Topups without an active open shift.</p>
                <a href="shifts.php" class="btn btn-green" style="display: inline-block; margin-top: 10px; text-decoration: none;">Go to Shifts & Start Shift &rarr;</a>
            </div>
        <?php else: ?>
            <div class="box">
                <h3>+ Extra Topup</h3>
                <form method="POST" action="index.php?tab=balance" class="form-grid" style="align-items:end;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="add_topup">
                    <div>
                        <label>Account / Customer Name</label>
                        <input type="text" name="account_name" placeholder="e.g. Player_1" required>
                    </div>
                    <div>
                        <label>Topup Amount (₱)</label>
                        <input type="number" step="0.01" name="topup_added" placeholder="e.g. 0.50" required>
                    </div>
                    <div>
                        <label>Note / Reason (optional)</label>
                        <input type="text" name="note" placeholder="e.g. autodeduction need topup for promo">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-green" style="width:100%;">Add Topup</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'expense'): ?>
        <div class="box">
            <h3>+ Add Expense</h3>
            <p style="font-size:12px; color:var(--text-muted); margin-bottom:15px;">Expenses can be logged at any time regardless of open shifts.</p>
            <form method="POST" action="index.php?tab=expense" class="form-grid" style="align-items:end;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="add_expense">
                <div>
                    <label>Item Name / Description</label>
                    <input type="text" name="item_name" placeholder="e.g. Tissue, Coffee, Water" required>
                </div>
                <div>
                    <label>Amount (₱)</label>
                    <input type="number" step="0.01" name="amount" placeholder="e.g. 150" required>
                </div>
                <div>
                    <label>Payment Method</label>
                    <select name="payment_method">
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-green" style="width:100%;">Log Expense</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

</div>

</body>
</html>