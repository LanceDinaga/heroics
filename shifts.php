<?php
// shifts.php - Shift & Shift Log Management
require 'db.php';

if (!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}

$logged_user = $_SESSION['username'];
$user_role   = $_SESSION['role'] ?? 'staff';

function getOpenShift($pdo) {
    try {
        return $pdo->query("SELECT s.*, p.label AS period_label, p.start_date, p.end_date
                             FROM shifts s JOIN shift_periods p ON s.shift_period_id = p.id
                             WHERE s.status = 'open' ORDER BY s.id DESC LIMIT 1")->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function getLastShift($pdo) {
    try {
        return $pdo->query("SELECT s.*, p.label AS period_label, p.start_date, p.end_date
                             FROM shifts s JOIN shift_periods p ON s.shift_period_id = p.id
                             ORDER BY s.id DESC LIMIT 1")->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function getShiftSales($pdo, $shift_id) {
    $totals = ['cash' => 0.0, 'gcash' => 0.0];
    try {
        $stmt = $pdo->prepare("SELECT payment_method, COALESCE(SUM(amount),0) AS total FROM shift_log_entries WHERE shift_id = ? GROUP BY payment_method");
        $stmt->execute([$shift_id]);
        foreach ($stmt->fetchAll() as $row) {
            $totals[$row['payment_method']] = floatval($row['total']);
        }
    } catch (PDOException $e) {}
    return $totals;
}

function getShiftExpenses($pdo, $shift_id) {
    $totals = ['cash' => 0.0, 'gcash' => 0.0];
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(payment_method,'cash') AS payment_method, COALESCE(SUM(amount),0) AS total FROM expense_logs WHERE shift_id = ? GROUP BY payment_method");
        $stmt->execute([$shift_id]);
        foreach ($stmt->fetchAll() as $row) {
            $totals[$row['payment_method']] = floatval($row['total']);
        }
    } catch (PDOException $e) {}
    return $totals;
}

function computeEnding($starting_cash, $starting_gcash, $sales, $expenses) {
    return [
        'cash'  => $starting_cash  + $sales['cash']  - $expenses['cash'],
        'gcash' => $starting_gcash + $sales['gcash'] - $expenses['gcash'],
    ];
}

function suggestedShiftType($pdo) {
    $today = date('Y-m-d');
    $last  = getLastShift($pdo);
    if ($last && $last['status'] === 'closed' && $last['shift_type'] === 'morning' && $last['start_date'] === $today) {
        return 'night';
    }
    return 'morning';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'start_shift') {
        $open = getOpenShift($pdo);
        if (!$open) {
            $shift_type = $_POST['shift_type'] === 'night' ? 'night' : 'morning';
            $today      = date('Y-m-d');
            $last       = getLastShift($pdo);

            // Determine if a new period is needed
            $is_new_period = true;
            if ($shift_type === 'night' && $last && $last['shift_type'] === 'morning' && $last['start_date'] === $today) {
                $is_new_period = false;
            }

            if ($is_new_period) {
                $start_date = $today;
                $end_date   = date('Y-m-d', strtotime($start_date . ' +1 day'));
                $label      = date('M j', strtotime($start_date));

                $existing = $pdo->prepare("SELECT id FROM shift_periods WHERE start_date = ?");
                $existing->execute([$start_date]);
                $existingPeriod = $existing->fetch();

                if ($existingPeriod) {
                    $shift_period_id = $existingPeriod['id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO shift_periods (start_date, end_date, label) VALUES (?, ?, ?)");
                    $stmt->execute([$start_date, $end_date, $label]);
                    $shift_period_id = $pdo->lastInsertId();
                }
            } else {
                $shift_period_id = $last['shift_period_id'];
            }

            // Start shift with 0.00 starting balances initially (will auto-update when cash counter is submitted)
            $stmt = $pdo->prepare("INSERT INTO shifts (shift_period_id, shift_type, starting_cash, starting_gcash, status, opened_by, opened_at) VALUES (?, ?, 0, 0, 'open', ?, NOW())");
            $stmt->execute([$shift_period_id, $shift_type, $logged_user]);
            setFlashMessage('Shift started.');
        }
        
        // REDIRECT DIRECTLY TO CASH COUNTER UPON STARTING SHIFT
        header("Location: index.php?tab=today");
        exit;
    }

    if ($action === 'add_entry') {
        $shift_id = intval($_POST['shift_id']);
        $amount   = floatval($_POST['amount']);
        $method   = $_POST['payment_method'] === 'gcash' ? 'gcash' : 'cash';
        $note     = trim($_POST['note'] ?? '');

        $check = $pdo->prepare("SELECT id FROM shifts WHERE id = ? AND status = 'open'");
        $check->execute([$shift_id]);
        if ($check->fetch() && $amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO shift_log_entries (shift_id, amount, payment_method, note, logged_by, date_time) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$shift_id, $amount, $method, $note, $logged_user]);
            setFlashMessage('Shift sale saved.');
        }
        header("Location: shifts.php");
        exit;
    }

    if ($action === 'update_entry') {
        $entry_id = intval($_POST['entry_id']);
        $amount   = floatval($_POST['amount']);
        $method   = $_POST['payment_method'] === 'gcash' ? 'gcash' : 'cash';
        $note     = trim($_POST['note'] ?? '');
        $stmt = $pdo->prepare("UPDATE shift_log_entries SET amount = ?, payment_method = ?, note = ? WHERE id = ?");
        $stmt->execute([$amount, $method, $note, $entry_id]);
        setFlashMessage('Shift sale updated.');
        header("Location: shifts.php");
        exit;
    }

    if ($action === 'close_shift') {
        $shift_id   = intval($_POST['shift_id']);
        $close_note = trim($_POST['close_note'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ? AND status = 'open'");
        $stmt->execute([$shift_id]);
        $shift = $stmt->fetch();

        if ($shift) {
            $sales    = getShiftSales($pdo, $shift_id);
            $expenses = getShiftExpenses($pdo, $shift_id);
            $ending   = computeEnding(floatval($shift['starting_cash']), floatval($shift['starting_gcash']), $sales, $expenses);

            $stmt = $pdo->prepare("UPDATE shifts SET status='closed', ending_cash=?, ending_gcash=?, closed_by=?, closed_at=NOW(), notes=? WHERE id=?");
            $stmt->execute([$ending['cash'], $ending['gcash'], $logged_user, $close_note, $shift_id]);
            setFlashMessage('Shift closed.');
        }
        header("Location: shifts.php");
        exit;
    }
}

if (isset($_GET['delete_entry']) && isset($_GET['csrf_token'])) {
    if ($_GET['csrf_token'] === $_SESSION['csrf_token']) {
        $stmt = $pdo->prepare("DELETE FROM shift_log_entries WHERE id = ?");
        $stmt->execute([intval($_GET['delete_entry'])]);
        setFlashMessage('Shift sale deleted.');
    }
    header("Location: shifts.php");
    exit;
}

$open_shift = getOpenShift($pdo);
$active_entries  = [];
$active_sales    = ['cash' => 0, 'gcash' => 0];
$active_expenses = ['cash' => 0, 'gcash' => 0];
$active_ending   = ['cash' => 0, 'gcash' => 0];

if ($open_shift) {
    $stmt = $pdo->prepare("SELECT * FROM shift_log_entries WHERE shift_id = ? ORDER BY date_time ASC, id ASC");
    $stmt->execute([$open_shift['id']]);
    $active_entries = $stmt->fetchAll();

    $active_sales    = getShiftSales($pdo, $open_shift['id']);
    $active_expenses = getShiftExpenses($pdo, $open_shift['id']);
    $active_ending   = computeEnding(floatval($open_shift['starting_cash']), floatval($open_shift['starting_gcash']), $active_sales, $active_expenses);
}

$history = [];
try {
    $periods = $pdo->query("SELECT * FROM shift_periods ORDER BY id DESC")->fetchAll();
    foreach ($periods as $p) {
        $stmt = $pdo->prepare("SELECT * FROM shifts WHERE shift_period_id = ? ORDER BY FIELD(shift_type,'morning','night')");
        $stmt->execute([$p['id']]);
        $shifts_in_period = $stmt->fetchAll();

        $period_total_sales = 0;
        foreach ($shifts_in_period as &$s) {
            $s['sales']    = getShiftSales($pdo, $s['id']);
            $s['expenses'] = getShiftExpenses($pdo, $s['id']);
            $period_total_sales += $s['sales']['cash'] + $s['sales']['gcash'];
        }
        unset($s);

        $history[] = [
            'period'      => $p,
            'shifts'      => $shifts_in_period,
            'total_sales' => $period_total_sales,
        ];
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Shifts - Heroics Gaming Lounge</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        .shift-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:14px; margin-bottom:15px; }
        .stat-box { background:#0f0a1e; border:1px solid var(--border-color); border-radius:8px; padding:14px; }
        .stat-box .label { font-size:11px; text-transform:uppercase; color:var(--text-muted); letter-spacing:1px; }
        .stat-box .value { font-size:20px; font-weight:bold; color:#fff; margin-top:4px; }
        .value.cash { color:#00ffcc; }
        .value.gcash { color:var(--neon-blue); }
        .shift-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; }
        .shift-badge.morning { background:rgba(255,193,7,0.15); color:#ffc107; border:1px solid #ffc107; }
        .shift-badge.night { background:rgba(138,43,226,0.2); color:#b388ff; border:1px solid #8a2be2; }
        .period-card { background:var(--card-bg); border:1px solid var(--border-color); border-radius:10px; padding:20px; margin-bottom:18px; }
        .period-card h4 { margin-top:0; color:var(--neon-pink); }
        .subshift { background:#0f0a1e; border-radius:8px; padding:14px; margin-bottom:10px; border-left:3px solid #8a2be2; }
        .tag-cash { color:#00ffcc; font-weight:bold; }
        .tag-gcash { color:var(--neon-blue); font-weight:bold; }
    </style>
</head>
<body>
<?php $flash_message = getFlashMessage(); ?>
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
    <a href="shifts.php" class="nav-btn active">Shifts</a>
    <a href="index.php?tab=balance" class="nav-btn">+ Extra Topup</a>
    <a href="index.php?tab=expense" class="nav-btn">+ Add Expense</a>
    <a href="summary.php" class="nav-btn">Daily Summary</a>
    <?php if ($user_role === 'admin'): ?>
        <a href="users.php" class="nav-btn" style="border-color:#ff007f;color:#ff007f;">+ Manage Users</a>
    <?php endif; ?>
</div>

<?php if ($open_shift): ?>
<div class="box" style="border-left:4px solid var(--primary-purple);">
    <h3>
        Active Shift — <span class="shift-badge <?= $open_shift['shift_type'] ?>"><?= ucfirst($open_shift['shift_type']) ?></span>
        &nbsp;Shift <?= htmlspecialchars($open_shift['period_label']) ?>
    </h3>
    <p style="color:var(--text-muted); font-size:13px;">Opened by <?= htmlspecialchars($open_shift['opened_by']) ?> at <?= date('g:i A, M j', strtotime($open_shift['opened_at'])) ?></p>

    <div class="shift-grid">
        <div class="stat-box"><div class="label">Starting Cash</div><div class="value">₱<?= number_format($open_shift['starting_cash'],2) ?></div></div>
        <div class="stat-box"><div class="label">Starting GCash</div><div class="value">₱<?= number_format($open_shift['starting_gcash'],2) ?></div></div>
        <div class="stat-box"><div class="label">Cash Sales (so far)</div><div class="value cash">₱<?= number_format($active_sales['cash'],2) ?></div></div>
        <div class="stat-box"><div class="label">GCash Sales (so far)</div><div class="value gcash">₱<?= number_format($active_sales['gcash'],2) ?></div></div>
        <div class="stat-box"><div class="label">Cash Expenses</div><div class="value" style="color:#ff4d4d;">₱<?= number_format($active_expenses['cash'],2) ?></div></div>
        <div class="stat-box"><div class="label">GCash Expenses</div><div class="value" style="color:#ff4d4d;">₱<?= number_format($active_expenses['gcash'],2) ?></div></div>
        <div class="stat-box"><div class="label">Projected Ending Cash</div><div class="value cash">₱<?= number_format($active_ending['cash'],2) ?></div></div>
        <div class="stat-box"><div class="label">Projected Ending GCash</div><div class="value gcash">₱<?= number_format($active_ending['gcash'],2) ?></div></div>
    </div>

    <h3 style="font-size:15px;">+ Log a Sale (Shift Log)</h3>
    <form method="POST" action="shifts.php" class="form-grid" style="align-items:end;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="add_entry">
        <input type="hidden" name="shift_id" value="<?= $open_shift['id'] ?>">
        <div>
            <label style="font-size:11px;color:var(--text-muted);">Amount</label>
            <input type="number" step="0.01" min="0.01" name="amount" placeholder="e.g. 200" required>
        </div>
        <div>
            <label style="font-size:11px;color:var(--text-muted);">Payment Method</label>
            <select name="payment_method" required>
                <option value="cash">Cash</option>
                <option value="gcash">GCash</option>
            </select>
        </div>
        <div>
            <label style="font-size:11px;color:var(--text-muted);">Note (optional)</label>
            <input type="text" name="note" placeholder="e.g. PC 5 rental">
        </div>
        <div>
            <button type="submit" class="btn btn-green" style="width:100%;">Add Entry</button>
        </div>
    </form>

    <h3 style="font-size:15px; margin-top:20px;">Shift Log Entries</h3>
    <?php if (empty($active_entries)): ?>
        <p style="color:var(--text-muted);">No entries logged yet this shift.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>#</th><th>Time</th><th>Amount</th><th>Method</th><th>Note</th><th>By</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($active_entries as $i => $e): ?>
                <tr id="view_e_<?= $e['id'] ?>">
                    <td>Entry <?= $i+1 ?></td>
                    <td><?= date('g:i A', strtotime($e['date_time'])) ?></td>
                    <td>₱<?= number_format($e['amount'],2) ?></td>
                    <td><span class="tag-<?= $e['payment_method'] ?>"><?= strtoupper($e['payment_method']) ?></span></td>
                    <td><?= htmlspecialchars($e['note'] ?: '-') ?></td>
                    <td><i><?= htmlspecialchars($e['logged_by']) ?></i></td>
                    <td>
                        <button class="btn" style="padding:5px 10px;font-size:12px;" onclick="toggleEdit(<?= $e['id'] ?>)">Edit</button>
                        <a href="shifts.php?delete_entry=<?= $e['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" onclick="return confirm('Delete this entry?')" class="btn-danger" style="text-decoration:none;display:inline-block;">Delete</a>
                    </td>
                </tr>
                <tr id="edit_e_<?= $e['id'] ?>" class="edit-row" style="display:none;">
                    <td colspan="7">
                        <form method="POST" action="shifts.php" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="update_entry">
                            <input type="hidden" name="entry_id" value="<?= $e['id'] ?>">
                            <input type="number" step="0.01" name="amount" value="<?= $e['amount'] ?>" style="width:100px;">
                            <select name="payment_method">
                                <option value="cash" <?= $e['payment_method']==='cash'?'selected':'' ?>>Cash</option>
                                <option value="gcash" <?= $e['payment_method']==='gcash'?'selected':'' ?>>GCash</option>
                            </select>
                            <input type="text" name="note" value="<?= htmlspecialchars($e['note'] ?? '') ?>" style="width:150px;">
                            <button type="submit" class="btn btn-green" style="padding:5px 10px;font-size:12px;">Save</button>
                            <button type="button" class="btn-danger" onclick="toggleEdit(<?= $e['id'] ?>)">Cancel</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <form method="POST" action="shifts.php" style="margin-top:20px; border-top:1px solid var(--border-color); padding-top:15px;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="close_shift">
        <input type="hidden" name="shift_id" value="<?= $open_shift['id'] ?>">
        <label style="font-size:11px;color:var(--text-muted);display:block;margin-bottom:6px;">Closing Note (optional)</label>
        <input type="text" name="close_note" placeholder="e.g. End of morning shift, handed to Dominic" style="margin-bottom:12px;">
        <button type="submit" class="btn" style="background:linear-gradient(135deg,#dc3545,#a71d2a);" onclick="return confirm('Close this shift?\n\nEnding Cash: ₱<?= number_format($active_ending['cash'],2) ?>\nEnding GCash: ₱<?= number_format($active_ending['gcash'],2) ?>\n\nThis cannot be undone.')">Close <?= ucfirst($open_shift['shift_type']) ?> Shift</button>
    </form>
</div>

<?php else:
    $defaultType = suggestedShiftType($pdo);
?>
<div class="box" style="border-left:4px solid var(--neon-blue);">
    <h3>Start a Shift</h3>
    <p style="color:var(--text-muted);font-size:13px;">Starting a shift will immediately open the Cash Counter page for your initial count.</p>

    <form method="POST" action="shifts.php" class="form-grid" style="align-items:end;">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="start_shift">
        <div>
            <label style="font-size:11px;color:var(--text-muted);">Select Shift</label>
            <select name="shift_type" required>
                <option value="morning" <?= $defaultType === 'morning' ? 'selected' : '' ?>>Morning Shift</option>
                <option value="night" <?= $defaultType === 'night' ? 'selected' : '' ?>>Night Shift</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-green" style="width:100%;">Start Shift & Go to Cash Counter &rarr;</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="box">
    <h3>Shift History</h3>
    <?php if (empty($history)): ?>
        <p style="color:var(--text-muted);">No shift periods recorded yet.</p>
    <?php else: ?>
        <?php foreach ($history as $h): ?>
            <div class="period-card">
                <h4>📅 Shift <?= htmlspecialchars(date('M j', strtotime($h['period']['start_date']))) ?></h4>
                <p style="color:var(--neon-blue); font-weight:bold; margin-top:-8px;">Total Sales: ₱<?= number_format($h['total_sales'],2) ?></p>
                <?php foreach ($h['shifts'] as $s): ?>
                    <div class="subshift">
                        <span class="shift-badge <?= $s['shift_type'] ?>"><?= ucfirst($s['shift_type']) ?></span>
                        <span style="font-size:12px; color:var(--text-muted); margin-left:8px;">
                            <?= $s['status']==='open' ? 'In progress' : ('Closed ' . date('g:i A', strtotime($s['closed_at']))) ?>
                        </span>
                        <div class="shift-grid" style="margin-top:10px; margin-bottom:0;">
                            <div class="stat-box"><div class="label">Start Cash</div><div class="value">₱<?= number_format($s['starting_cash'],2) ?></div></div>
                            <div class="stat-box"><div class="label">Start GCash</div><div class="value">₱<?= number_format($s['starting_gcash'],2) ?></div></div>
                            <div class="stat-box"><div class="label">Cash Sales</div><div class="value cash">₱<?= number_format($s['sales']['cash'],2) ?></div></div>
                            <div class="stat-box"><div class="label">GCash Sales</div><div class="value gcash">₱<?= number_format($s['sales']['gcash'],2) ?></div></div>
                            <div class="stat-box"><div class="label">Expenses (Cash / GCash)</div><div class="value" style="color:#ff4d4d; font-size:16px;">₱<?= number_format($s['expenses']['cash'],2) ?> / ₱<?= number_format($s['expenses']['gcash'],2) ?></div></div>
                            <div class="stat-box"><div class="label">End Cash</div><div class="value cash"><?= $s['status']==='closed' ? '₱'.number_format($s['ending_cash'],2) : '—' ?></div></div>
                            <div class="stat-box"><div class="label">End GCash</div><div class="value gcash"><?= $s['status']==='closed' ? '₱'.number_format($s['ending_gcash'],2) : '—' ?></div></div>
                        </div>
                        <?php if (!empty($s['notes'])): ?>
                            <p style="font-size:12px; color:var(--text-muted); margin-bottom:0; margin-top:8px;">Note: <?= htmlspecialchars($s['notes']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleEdit(id) {
    let viewRow = document.getElementById('view_e_' + id);
    let editRow = document.getElementById('edit_e_' + id);
    if (viewRow.style.display === 'none') {
        viewRow.style.display = '';
        editRow.style.display = 'none';
    } else {
        viewRow.style.display = 'none';
        editRow.style.display = '';
    }
}

</script>

</body>
</html>