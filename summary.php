<?php
// summary.php - Daily & Shift Summary Report with Per-Period Excel Export
require 'db.php';

if (!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}

$logged_user = $_SESSION['username'];
$user_role   = $_SESSION['role'] ?? 'staff';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $action  = $_POST['action'] ?? '';
    $shift_id = intval($_POST['shift_id'] ?? 0);
    $record_id = intval($_POST['record_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);

    if ($action === 'unlock_summary' && $shift_id > 0) {
        if (hash_equals('heroics', (string) ($_POST['summary_password'] ?? ''))) {
            $_SESSION['summary_edit_shift_id'] = $shift_id;
            setFlashMessage('Summary editing unlocked for this shift.');
        } else {
            setFlashMessage('Incorrect summary password.');
        }
        header("Location: summary.php");
        exit;
    }

    if ($action === 'lock_summary' && $shift_id > 0) {
        if (isset($_SESSION['summary_edit_shift_id']) && intval($_SESSION['summary_edit_shift_id']) === $shift_id) {
            unset($_SESSION['summary_edit_shift_id']);
            setFlashMessage('Summary editing finished.');
        }
        header("Location: summary.php");
        exit;
    }

    $can_edit_shift = isset($_SESSION['summary_edit_shift_id']) && intval($_SESSION['summary_edit_shift_id']) === $shift_id;
    if ($can_edit_shift && $shift_id > 0 && $amount > 0) {
        if ($action === 'add_sale') {
            $payment_method = ($_POST['payment_method'] ?? '') === 'gcash' ? 'gcash' : 'cash';
            $note = trim($_POST['note'] ?? '');
            $stmt = $pdo->prepare("INSERT INTO shift_log_entries (shift_id, amount, payment_method, note, logged_by, date_time) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$shift_id, $amount, $payment_method, $note, $logged_user]);
            setFlashMessage('Sales entry added.');
            header("Location: summary.php");
            exit;
        }

        if ($action === 'add_topup') {
            $account_name = trim($_POST['account_name'] ?? '');
            $note = trim($_POST['note'] ?? '');
            if ($account_name !== '') {
                $stmt = $pdo->prepare("INSERT INTO balance_logs (shift_id, account_name, topup_added, note, logged_by, date_time) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$shift_id, $account_name, $amount, $note, $logged_user]);
                setFlashMessage('Extra topup added.');
            }
            header("Location: summary.php");
            exit;
        }

        if ($action === 'add_expense') {
            $item_name = trim($_POST['item_name'] ?? '');
            $payment_method = ($_POST['payment_method'] ?? '') === 'gcash' ? 'gcash' : 'cash';
            if ($item_name !== '') {
                $stmt = $pdo->prepare("INSERT INTO expense_logs (shift_id, item_name, amount, payment_method, logged_by, date_time) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$shift_id, $item_name, $amount, $payment_method, $logged_user]);
                setFlashMessage('Expense added.');
            }
            header("Location: summary.php");
            exit;
        }
    }

    if ($can_edit_shift && $shift_id > 0 && $record_id > 0 && $amount > 0) {
        if ($action === 'update_sale') {
            $payment_method = ($_POST['payment_method'] ?? '') === 'gcash' ? 'gcash' : 'cash';
            $note = trim($_POST['note'] ?? '');
            $stmt = $pdo->prepare("UPDATE shift_log_entries SET amount = ?, payment_method = ?, note = ? WHERE id = ? AND shift_id = ?");
            $stmt->execute([$amount, $payment_method, $note, $record_id, $shift_id]);
            setFlashMessage('Sales entry updated.');
        } elseif ($action === 'update_topup') {
            $account_name = trim($_POST['account_name'] ?? '');
            $note = trim($_POST['note'] ?? '');
            if ($account_name !== '') {
                $stmt = $pdo->prepare("UPDATE balance_logs SET account_name = ?, topup_added = ?, note = ? WHERE id = ? AND shift_id = ?");
                $stmt->execute([$account_name, $amount, $note, $record_id, $shift_id]);
                setFlashMessage('Extra topup updated.');
            }
        } elseif ($action === 'update_expense') {
            $item_name = trim($_POST['item_name'] ?? '');
            $payment_method = ($_POST['payment_method'] ?? '') === 'gcash' ? 'gcash' : 'cash';
            if ($item_name !== '') {
                $stmt = $pdo->prepare("UPDATE expense_logs SET item_name = ?, amount = ?, payment_method = ? WHERE id = ? AND shift_id = ?");
                $stmt->execute([$item_name, $amount, $payment_method, $record_id, $shift_id]);
                setFlashMessage('Expense updated.');
            }
        }
    }

    header("Location: summary.php");
    exit;
}

// Fetch Shift Periods and calculate internal metrics
$periods_data = [];
$flash_message = getFlashMessage();
try {
    $periods = $pdo->query("SELECT * FROM shift_periods ORDER BY id DESC")->fetchAll();
    foreach ($periods as $p) {
        $stmt = $pdo->prepare("SELECT * FROM shifts WHERE shift_period_id = ? ORDER BY FIELD(shift_type,'morning','night')");
        $stmt->execute([$p['id']]);
        $shifts = $stmt->fetchAll();

        foreach ($shifts as &$s) {
            $shift_id = $s['id'];

            // 1. Shift Sales Entries
            $sales_stmt = $pdo->prepare("SELECT payment_method, COALESCE(SUM(amount),0) as total FROM shift_log_entries WHERE shift_id = ? GROUP BY payment_method");
            $sales_stmt->execute([$shift_id]);
            $sales = ['cash' => 0.0, 'gcash' => 0.0];
            foreach ($sales_stmt->fetchAll() as $row) {
                $sales[$row['payment_method']] = floatval($row['total']);
            }
            $s['cash_sales']  = $sales['cash'];
            $s['gcash_sales'] = $sales['gcash'];
            $s['entry_sales'] = $sales['cash'] + $sales['gcash'];

            // 2. Extra Topups
            $topup_stmt = $pdo->prepare("SELECT COALESCE(SUM(topup_added),0) as total FROM balance_logs WHERE shift_id = ?");
            $topup_stmt->execute([$shift_id]);
            $s['extra_topups'] = floatval($topup_stmt->fetchColumn());

            // 3. Expenses
            $exp_stmt = $pdo->prepare("SELECT COALESCE(payment_method,'cash') as method, COALESCE(SUM(amount),0) as total FROM expense_logs WHERE shift_id = ? GROUP BY payment_method");
            $exp_stmt->execute([$shift_id]);
            $expenses = ['cash' => 0.0, 'gcash' => 0.0];
            foreach ($exp_stmt->fetchAll() as $row) {
                $expenses[$row['method']] = floatval($row['total']);
            }
            $s['cash_expenses']  = $expenses['cash'];
            $s['gcash_expenses'] = $expenses['gcash'];

            $entries_stmt = $pdo->prepare("SELECT id, amount, payment_method, note, logged_by, date_time FROM shift_log_entries WHERE shift_id = ? ORDER BY date_time ASC, id ASC");
            $entries_stmt->execute([$shift_id]);
            $s['entries'] = $entries_stmt->fetchAll();

            $topups_detail_stmt = $pdo->prepare("SELECT id, account_name, topup_added, note, logged_by, date_time FROM balance_logs WHERE shift_id = ? ORDER BY date_time ASC, id ASC");
            $topups_detail_stmt->execute([$shift_id]);
            $s['topups'] = $topups_detail_stmt->fetchAll();

            $expenses_detail_stmt = $pdo->prepare("SELECT id, item_name, amount, payment_method, logged_by, date_time FROM expense_logs WHERE shift_id = ? ORDER BY date_time ASC, id ASC");
            $expenses_detail_stmt->execute([$shift_id]);
            $s['expenses'] = $expenses_detail_stmt->fetchAll();
            $s['can_edit'] = isset($_SESSION['summary_edit_shift_id']) && intval($_SESSION['summary_edit_shift_id']) === $shift_id;

            // 4. Computed Metrics
            $s['pondo_total']  = $s['entry_sales'] + $s['extra_topups'];
            $s['gross_volume'] = $s['starting_cash'] + $s['entry_sales'];
            
            // Drawer Endings
            if ($s['status'] === 'closed') {
                $s['calc_ending_cash']  = floatval($s['ending_cash']);
                $s['calc_ending_gcash'] = floatval($s['ending_gcash']);
            } else {
                $s['calc_ending_cash']  = $s['starting_cash'] + $s['cash_sales'] - $s['cash_expenses'];
                $s['calc_ending_gcash'] = $s['starting_gcash'] + $s['gcash_sales'] - $s['gcash_expenses'];
            }
        }
        unset($s);

        $periods_data[] = [
            'period' => $p,
            'shifts' => $shifts
        ];
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Daily Summary - Heroics Gaming Lounge</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        .summary-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; margin-bottom: 25px; }
        .shift-block { background: #0f0a1e; border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; margin-top: 15px; }
        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 12px; }
        .metric-card { background: #160e2e; padding: 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); }
        .metric-card .title { font-size: 11px; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
        .metric-card .val { font-size: 18px; font-weight: bold; margin-top: 4px; color: #fff; }
        .highlight-pondo { border: 1px solid var(--neon-blue); background: rgba(0, 210, 255, 0.05); }
        .highlight-gross { border: 1px solid #00ffcc; background: rgba(0, 255, 204, 0.05); }
        .tag-badge { font-size: 11px; padding: 2px 8px; border-radius: 12px; text-transform: uppercase; font-weight: bold; }
        .tag-morning { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .tag-night { background: rgba(138, 43, 226, 0.2); color: #b388ff; }
        .summary-detail-title { margin: 22px 0 10px; color: var(--neon-pink); font-size: 15px; }
        .summary-table-wrap { overflow-x: auto; }
        .summary-table-wrap table { min-width: 760px; }
        .summary-table-wrap input, .summary-table-wrap select { min-width: 90px; padding: 7px; }
    </style>
</head>
<body>
<?php if ($flash_message): ?>
    <div class="flash-message"><?= htmlspecialchars($flash_message) ?></div>
<?php endif; ?>

<div class="header">
    <div class="brand-section">
        <a href="shifts.php"><img src="heroics-title.png" alt="Heroics Gaming Lounge" class="brand-title" style="height:45px;width:auto;"></a>
    </div>
    <div class="header-user">
        Logged in as: <b><?= htmlspecialchars($logged_user) ?></b> (<?= ucfirst($user_role) ?>) | 
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="nav-buttons">
    <a href="index.php?tab=today" class="nav-btn">Today (Cash Counter)</a>
    <a href="shifts.php" class="nav-btn">Shifts</a>
    <a href="index.php?tab=balance" class="nav-btn">+ Extra Topup</a>
    <a href="index.php?tab=expense" class="nav-btn">+ Add Expense</a>
    <a href="summary.php" class="nav-btn active">Daily Summary</a>
    <?php if ($user_role === 'admin'): ?>
        <a href="users.php" class="nav-btn" style="border-color:#ff007f;color:#ff007f;">+ Manage Users</a>
    <?php endif; ?>
</div>

<div class="content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0;">Daily Shift & Pondo Summaries</h2>
        <!-- FULL DATABASE EXPORT OPTION -->
        <a href="export.php" class="btn btn-green" style="text-decoration:none; padding: 10px 18px; font-size:14px; font-weight:bold;">
            📥 Export All Data (Full Backup)
        </a>
    </div>

    <?php if (empty($periods_data)): ?>
        <div class="box"><p style="color:var(--text-muted);">No recorded shift periods found.</p></div>
    <?php else: ?>
        <?php foreach ($periods_data as $data): ?>
            <div class="summary-card">
                <!-- SHIFT PERIOD HEADER WITH UPPER-RIGHT EXPORT BUTTON -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 12px; margin-bottom: 15px;">
                    <h3 style="color:var(--neon-pink); margin:0;">📅 Shift Period: <?= htmlspecialchars($data['period']['label']) ?></h3>
                    
                    <a href="export.php?period_id=<?= $data['period']['id'] ?>" class="btn btn-green" style="text-decoration:none; padding: 6px 14px; font-size:12px; font-weight:bold;">
                        📥 Export Data
                    </a>
                </div>

                <?php foreach ($data['shifts'] as $s): ?>
                    <div class="shift-block <?= $s['can_edit'] ? '' : 'summary-readonly' ?>">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span class="tag-badge tag-<?= $s['shift_type'] ?>"><?= ucfirst($s['shift_type']) ?> Shift</span>
                                <span style="font-size:12px; color:var(--text-muted); margin-left:8px;">Status: <b><?= strtoupper($s['status']) ?></b></span>
                            </div>
                            <form method="POST" action="summary.php" id="unlock_<?= $s['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">
                                <?php if ($s['can_edit']): ?>
                                    <input type="hidden" name="action" value="lock_summary">
                                    <button type="submit" class="btn" style="padding:6px 14px; font-size:12px;">Done Editing</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="unlock_summary">
                                    <input type="hidden" name="summary_password" id="password_<?= $s['id'] ?>">
                                    <button type="button" class="btn" style="padding:6px 14px; font-size:12px;" onclick="unlockSummary(<?= $s['id'] ?>)">Edit</button>
                                <?php endif; ?>
                            </form>
                        </div>

                        <div class="metric-grid">
                            <!-- 1. PONDO COMPARISON METRIC -->
                            <div class="metric-card highlight-pondo">
                                <div class="title" style="color:var(--neon-blue);">Pondo Total Sales</div>
                                <div class="val" style="color:var(--neon-blue);">₱<?= number_format($s['pondo_total'], 2) ?></div>
                                <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">Entries (₱<?= number_format($s['entry_sales'],2) ?>) + Extra Topup (₱<?= number_format($s['extra_topups'],2) ?>)</div>
                            </div>

                            <!-- 2. TOTAL GROSS FLOAT VOLUME -->
                            <div class="metric-card highlight-gross">
                                <div class="title" style="color:#00ffcc;">Total Gross Volume</div>
                                <div class="val" style="color:#00ffcc;">₱<?= number_format($s['gross_volume'], 2) ?></div>
                                <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">Starting Cash + Total Sales Entries</div>
                            </div>

                            <!-- 3. STARTING & ENDING CASH BALANCES -->
                            <div class="metric-card">
                                <div class="title">Starting Cash</div>
                                <div class="val">₱<?= number_format($s['starting_cash'], 2) ?></div>
                                <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">GCash: ₱<?= number_format($s['starting_gcash'],2) ?></div>
                            </div>

                            <div class="metric-card">
                                <div class="title">Ending Cash</div>
                                <div class="val" style="color:#00ffcc;">₱<?= number_format($s['calc_ending_cash'], 2) ?></div>
                                <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">GCash: ₱<?= number_format($s['calc_ending_gcash'],2) ?></div>
                            </div>

                            <!-- 4. TOTAL SALES ENTRIES & EXPENSES -->
                            <div class="metric-card">
                                <div class="title">Shift Sales Entries</div>
                                <div class="val">₱<?= number_format($s['entry_sales'], 2) ?></div>
                                <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">Cash: ₱<?= number_format($s['cash_sales'],2) ?> | GCash: ₱<?= number_format($s['gcash_sales'],2) ?></div>
                            </div>

                            <div class="metric-card">
                                <div class="title">Cash Expenses</div>
                                <div class="val" style="color:#ff4d4d;">₱<?= number_format($s['cash_expenses'], 2) ?></div>
                                <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">GCash Exp: ₱<?= number_format($s['gcash_expenses'],2) ?></div>
                            </div>
                        </div>

                        <h4 class="summary-detail-title">Sales Entries</h4>
                        <?php if (empty($s['entries'])): ?>
                            <p style="color:var(--text-muted);">No sales entries recorded.</p>
                        <?php else: ?>
                            <div class="summary-table-wrap">
                                <table>
                                    <thead><tr><th>Amount</th><th>Payment Method</th><th>Note</th><th>Logged By</th><th>Date / Time</th><th>Save</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($s['entries'] as $entry): ?>
                                        <tr>
                                            <?php $form_id = 'sale_' . $entry['id']; ?>
                                            <td><form id="<?= $form_id ?>" method="POST" action="summary.php"></form><input form="<?= $form_id ?>" type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input form="<?= $form_id ?>" type="hidden" name="action" value="update_sale"><input form="<?= $form_id ?>" type="hidden" name="shift_id" value="<?= $s['id'] ?>"><input form="<?= $form_id ?>" type="hidden" name="record_id" value="<?= $entry['id'] ?>"><input form="<?= $form_id ?>" type="number" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($entry['amount']) ?>" required <?= $s['can_edit'] ? '' : 'disabled' ?>></td>
                                            <td><select form="<?= $form_id ?>" name="payment_method"><option value="cash" <?= $entry['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option><option value="gcash" <?= $entry['payment_method'] === 'gcash' ? 'selected' : '' ?>>GCash</option></select></td>
                                            <td><input form="<?= $form_id ?>" type="text" name="note" value="<?= htmlspecialchars($entry['note'] ?? '') ?>"></td>
                                            <td><?= htmlspecialchars($entry['logged_by']) ?></td>
                                            <td><?= htmlspecialchars($entry['date_time']) ?></td>
                                            <td><button form="<?= $form_id ?>" type="submit" class="btn btn-green" style="padding:6px 10px;">Save</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if ($s['can_edit']): ?>
                            <form method="POST" action="summary.php" class="form-grid" style="align-items:end; margin-top:12px;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="add_sale">
                                <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">
                                <div><label>Amount</label><input type="number" name="amount" step="0.01" min="0.01" placeholder="e.g. 200" required></div>
                                <div><label>Payment Method</label><select name="payment_method"><option value="cash">Cash</option><option value="gcash">GCash</option></select></div>
                                <div><label>Note</label><input type="text" name="note" placeholder="Optional"></div>
                                <div><button type="submit" class="btn btn-green" style="width:100%;">+ Add Entry</button></div>
                            </form>
                        <?php endif; ?>

                        <h4 class="summary-detail-title">Extra Topups</h4>
                        <?php if (empty($s['topups'])): ?>
                            <p style="color:var(--text-muted);">No extra topups recorded.</p>
                        <?php else: ?>
                            <div class="summary-table-wrap">
                                <table>
                                    <thead><tr><th>Account / Customer</th><th>Amount</th><th>Note</th><th>Logged By</th><th>Date / Time</th><th>Save</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($s['topups'] as $topup): ?>
                                        <tr>
                                            <?php $form_id = 'topup_' . $topup['id']; ?>
                                            <td><form id="<?= $form_id ?>" method="POST" action="summary.php"></form><input form="<?= $form_id ?>" type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input form="<?= $form_id ?>" type="hidden" name="action" value="update_topup"><input form="<?= $form_id ?>" type="hidden" name="shift_id" value="<?= $s['id'] ?>"><input form="<?= $form_id ?>" type="hidden" name="record_id" value="<?= $topup['id'] ?>"><input form="<?= $form_id ?>" type="text" name="account_name" value="<?= htmlspecialchars($topup['account_name']) ?>" required></td>
                                            <td><input form="<?= $form_id ?>" type="number" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($topup['topup_added']) ?>" required></td>
                                            <td><input form="<?= $form_id ?>" type="text" name="note" value="<?= htmlspecialchars($topup['note'] ?? '') ?>"></td>
                                            <td><?= htmlspecialchars($topup['logged_by']) ?></td>
                                            <td><?= htmlspecialchars($topup['date_time']) ?></td>
                                            <td><button form="<?= $form_id ?>" type="submit" class="btn btn-green" style="padding:6px 10px;">Save</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if ($s['can_edit']): ?>
                            <form method="POST" action="summary.php" class="form-grid" style="align-items:end; margin-top:12px;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="add_topup">
                                <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">
                                <div><label>Account / Customer</label><input type="text" name="account_name" placeholder="e.g. Player_1" required></div>
                                <div><label>Amount</label><input type="number" name="amount" step="0.01" min="0.01" placeholder="e.g. 0.40" required></div>
                                <div><label>Note</label><input type="text" name="note" placeholder="Optional"></div>
                                <div><button type="submit" class="btn btn-green" style="width:100%;">+ Add Topup</button></div>
                            </form>
                        <?php endif; ?>

                        <h4 class="summary-detail-title">Expenses</h4>
                        <?php if (empty($s['expenses'])): ?>
                            <p style="color:var(--text-muted);">No expenses recorded.</p>
                        <?php else: ?>
                            <div class="summary-table-wrap">
                                <table>
                                    <thead><tr><th>Item / Description</th><th>Amount</th><th>Payment Method</th><th>Logged By</th><th>Date / Time</th><th>Save</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($s['expenses'] as $expense): ?>
                                        <tr>
                                            <?php $form_id = 'expense_' . $expense['id']; ?>
                                            <td><form id="<?= $form_id ?>" method="POST" action="summary.php"></form><input form="<?= $form_id ?>" type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input form="<?= $form_id ?>" type="hidden" name="action" value="update_expense"><input form="<?= $form_id ?>" type="hidden" name="shift_id" value="<?= $s['id'] ?>"><input form="<?= $form_id ?>" type="hidden" name="record_id" value="<?= $expense['id'] ?>"><input form="<?= $form_id ?>" type="text" name="item_name" value="<?= htmlspecialchars($expense['item_name']) ?>" required></td>
                                            <td><input form="<?= $form_id ?>" type="number" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($expense['amount']) ?>" required></td>
                                            <td><select form="<?= $form_id ?>" name="payment_method"><option value="cash" <?= ($expense['payment_method'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>Cash</option><option value="gcash" <?= $expense['payment_method'] === 'gcash' ? 'selected' : '' ?>>GCash</option></select></td>
                                            <td><?= htmlspecialchars($expense['logged_by']) ?></td>
                                            <td><?= htmlspecialchars($expense['date_time']) ?></td>
                                            <td><button form="<?= $form_id ?>" type="submit" class="btn btn-green" style="padding:6px 10px;">Save</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if ($s['can_edit']): ?>
                            <form method="POST" action="summary.php" class="form-grid" style="align-items:end; margin-top:12px;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="add_expense">
                                <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">
                                <div><label>Item / Description</label><input type="text" name="item_name" placeholder="e.g. Coffee" required></div>
                                <div><label>Amount</label><input type="number" name="amount" step="0.01" min="0.01" placeholder="e.g. 150.00" required></div>
                                <div><label>Payment Method</label><select name="payment_method"><option value="cash">Cash</option><option value="gcash">GCash</option></select></div>
                                <div><button type="submit" class="btn btn-green" style="width:100%;">+ Add Expense</button></div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function unlockSummary(shiftId) {
    const password = prompt('Enter the summary edit password:');
    if (password !== null && password !== '') {
        document.getElementById('password_' + shiftId).value = password;
        document.getElementById('unlock_' + shiftId).submit();
    }
}

document.querySelectorAll('.summary-readonly').forEach(function (shift) {
    shift.querySelectorAll('.summary-table-wrap input:not([type="hidden"]), .summary-table-wrap select').forEach(function (field) {
        field.disabled = true;
    });
    shift.querySelectorAll('.summary-table-wrap button[type="submit"]').forEach(function (button) {
        button.style.display = 'none';
    });
});
</script>

</body>
</html>