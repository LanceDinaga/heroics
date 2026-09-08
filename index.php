<?php
// index.php - Main Dashboard
require 'db.php';

if (!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}

$logged_user = $_SESSION['username'];
$user_role   = $_SESSION['role'] ?? 'staff';
$active_tab  = $_GET['tab'] ?? 'balance';

// --- ACTION HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    $action = $_POST['action'] ?? '';
    $custom_date = !empty($_POST['custom_date']) ? $_POST['custom_date'] . ' ' . date('H:i:s') : date('Y-m-d H:i:s');

    if ($action === 'add_balance') {
        $account_name = trim($_POST['account_name']);
        $topup_added  = floatval($_POST['topup_added']);
        $note         = trim($_POST['note'] ?? '');
        if (!empty($account_name) && $topup_added > 0) {
            $stmt = $pdo->prepare("INSERT INTO balance_logs (account_name, topup_added, note, logged_by, date_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$account_name, $topup_added, $note, $logged_user, $custom_date]);
        }
        header("Location: index.php?tab=balance");
        exit;
    }

    if ($action === 'add_expense') {
        $item_name = trim($_POST['item_name']);
        $amount    = floatval($_POST['amount']);
        $note      = trim($_POST['note'] ?? '');
        if (!empty($item_name) && $amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO expense_logs (item_name, amount, note, logged_by, date_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$item_name, $amount, $note, $logged_user, $custom_date]);
        }
        header("Location: index.php?tab=expense");
        exit;
    }

    if ($action === 'add_counter') {
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
        $note  = trim($_POST['note'] ?? '');

        $cash_total  = ($c1000 * 1000) + ($c500 * 500) + ($c200 * 200) + ($c100 * 100) + ($c50 * 50) + ($c20 * 20) + ($c10 * 10) + ($c5 * 5) + ($c1 * 1);
        $grand_total = $cash_total + $gcash;

        $stmt = $pdo->prepare("INSERT INTO cash_counter_logs (c1000, c500, c200, c100, c50, c20, c10, c5, c1, gcash, grand_total, note, logged_by, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$c1000, $c500, $c200, $c100, $c50, $c20, $c10, $c5, $c1, $gcash, $grand_total, $note, $logged_user, $custom_date]);
        header("Location: index.php?tab=today");
        exit;
    }

    if ($action === 'update_row') {
        $table_name = $_POST['table_name'];
        $row_id     = intval($_POST['row_id']);
        $note       = trim($_POST['note'] ?? '');
        
        $allowed = ['balance_logs', 'expense_logs', 'cash_counter_logs'];
        if (in_array($table_name, $allowed)) {
            $edit_date = !empty($_POST['edit_date']) ? $_POST['edit_date'] . ' ' . date('H:i:s') : null;

            if ($table_name === 'balance_logs') {
                $acc = trim($_POST['account_name']);
                $top = floatval($_POST['topup_added']);
                $sql = "UPDATE balance_logs SET account_name = ?, topup_added = ?, note = ?" . ($edit_date ? ", date_time = ?" : "") . " WHERE id = ?";
                $params = $edit_date ? [$acc, $top, $note, $edit_date, $row_id] : [$acc, $top, $note, $row_id];
                $pdo->prepare($sql)->execute($params);
            } elseif ($table_name === 'expense_logs') {
                $item = trim($_POST['item_name']);
                $amt  = floatval($_POST['amount']);
                $sql  = "UPDATE expense_logs SET item_name = ?, amount = ?, note = ?" . ($edit_date ? ", date_time = ?" : "") . " WHERE id = ?";
                $params = $edit_date ? [$item, $amt, $note, $edit_date, $row_id] : [$item, $amt, $note, $row_id];
                $pdo->prepare($sql)->execute($params);
            } elseif ($table_name === 'cash_counter_logs') {
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

                $sql = "UPDATE cash_counter_logs SET c1000=?, c500=?, c200=?, c100=?, c50=?, c20=?, c10=?, c5=?, c1=?, gcash=?, grand_total=?, note=?" . ($edit_date ? ", date_time=?" : "") . " WHERE id=?";
                $params = [ $c1000, $c500, $c200, $c100, $c50, $c20, $c10, $c5, $c1, $gcash, $grand_total, $note ];
                if ($edit_date) { $params[] = $edit_date; }
                $params[] = $row_id;
                $pdo->prepare($sql)->execute($params);
            }
        }
        header("Location: index.php?tab=" . $active_tab);
        exit;
    }
}

// --- DELETE HANDLER ---
if (isset($_GET['delete_tbl']) && isset($_GET['del_id']) && isset($_GET['csrf_token'])) {
    if ($_GET['csrf_token'] === $_SESSION['csrf_token']) {
        $tbl = $_GET['delete_tbl'];
        $id  = intval($_GET['del_id']);
        $allowed = ['balance_logs', 'expense_logs', 'cash_counter_logs'];
        if (in_array($tbl, $allowed)) {
            $stmt = $pdo->prepare("DELETE FROM {$tbl} WHERE id = ?");
            $stmt->execute([$id]);
        }
    }
    header("Location: index.php?tab=" . $active_tab);
    exit;
}

// --- FETCH DATA ---
$balances = $pdo->query("SELECT * FROM balance_logs ORDER BY date_time DESC")->fetchAll(PDO::FETCH_ASSOC);
$expenses = $pdo->query("SELECT * FROM expense_logs ORDER BY date_time DESC")->fetchAll(PDO::FETCH_ASSOC);
$counters = $pdo->query("SELECT * FROM cash_counter_logs ORDER BY date_time DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Heroics Gaming Lounge - Dashboard</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <script>
        function toggleEdit(id) {
            var viewRow = document.getElementById('view_' + id);
            var editRow = document.getElementById('edit_' + id);
            if (viewRow.style.display === 'none') {
                viewRow.style.display = '';
                editRow.style.display = 'none';
            } else {
                viewRow.style.display = 'none';
                editRow.style.display = '';
            }
        }

        function calcTodayTotal() {
            let denoms = [1000, 500, 200, 100, 50, 20, 10, 5, 1];
            let cashTotal = 0;
            denoms.forEach(d => {
                let count = parseInt(document.getElementById('c' + d).value) || 0;
                let sub = count * d;
                cashTotal += sub;
                document.getElementById('sub_' + d).innerText = '₱' + sub.toFixed(2);
            });

            let gcash = parseFloat(document.getElementById('gcash').value) || 0;
            document.getElementById('sub_gcash').innerText = '₱' + gcash.toFixed(2);

            let grandTotal = cashTotal + gcash;
            document.getElementById('grand_total_display').innerText = '₱' + grandTotal.toFixed(2);
        }
    </script>
</head>
<body>

    <div class="header">
        <div class="brand-section">
            <img src="heroics-title.png" alt="Heroics Gaming Lounge" class="brand-title" style="height: 45px; width: auto;">
        </div>
        <div class="header-user">
            Logged in as: <b><?= htmlspecialchars($logged_user); ?></b> (<?= ucfirst($user_role) ?>) | 
            <a href="index.php">Dashboard</a> | 
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="nav-buttons">
        <a href="index.php?tab=balance" class="nav-btn <?= $active_tab === 'balance' ? 'active' : '' ?>">+ Extra Topup</a>
        <a href="index.php?tab=expense" class="nav-btn <?= $active_tab === 'expense' ? 'active' : '' ?>">+ Add Expense</a>
        <a href="index.php?tab=today" class="nav-btn <?= $active_tab === 'today' ? 'active' : '' ?>">Today (Cash Counter)</a>
        <a href="summary.php" class="nav-btn">Daily Summary</a>
        
        <?php if ($user_role === 'admin'): ?>
            <a href="users.php" class="nav-btn" style="border-color: #ff007f; color: #ff007f;">+ Manage Users</a>
        <?php endif; ?>
    </div>

    <!-- TAB 1: EXTRA TOPUP -->
    <?php if ($active_tab === 'balance'): ?>
    <div class="box">
        <h3>Log Extra Topup</h3>
        <form method="POST" action="index.php?tab=balance">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="action" value="add_balance">
            <div class="form-group" style="margin-bottom: 12px;">
                <label style="display:block; font-size:12px; color:#aaa;">Account Name:</label>
                <input type="text" name="account_name" required placeholder="e.g. PC-01 / Customer Name">
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label style="display:block; font-size:12px; color:#aaa;">Amount (₱):</label>
                <input type="number" step="0.01" name="topup_added" required placeholder="0.00">
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label style="display:block; font-size:12px; color:#aaa;">Note / Remark (Optional):</label>
                <input type="text" name="note" placeholder="e.g. Free Time/Automatic Deduction">
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:12px; color:#aaa;">Date (Optional - defaults to today):</label>
                <input type="date" name="custom_date" style="color-scheme: dark; padding: 8px; width: 100%; border-radius: 4px; border: 1px solid #444; background: #1a1a2e; color: #fff;">
            </div>
            <button type="submit" class="btn btn-green">Add Topup</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Account Name</th>
                <th>Amount Added</th>
                <th>Note</th>
                <th>Logged By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($balances as $r): ?>
            <tr id="view_b_<?= $r['id'] ?>">
                <td><?= date('F j, Y, g:i A', strtotime($r['date_time'])) ?></td>
                <td><?= htmlspecialchars($r['account_name']) ?></td>
                <td style="color: #00ffcc; font-weight: bold;">₱<?= number_format($r['topup_added'], 2) ?></td>
                <td><small style="color: #bbb;"><?= htmlspecialchars($r['note'] ?? '-') ?></small></td>
                <td><i><?= htmlspecialchars($r['logged_by']) ?></i></td>
                <td>
                    <button class="btn" style="padding: 5px 10px; font-size:12px;" onclick="toggleEdit('b_<?= $r['id'] ?>')">Edit</button>
                    <a href="index.php?tab=balance&delete_tbl=balance_logs&del_id=<?= $r['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" onclick="return confirm('Delete this log?')" class="btn-danger" style="text-decoration:none; display:inline-block; padding: 5px 10px; font-size:12px;">Delete</a>
                </td>
            </tr>
            <tr id="edit_b_<?= $r['id'] ?>" class="edit-row" style="display:none;">
                <td colspan="6">
                    <form method="POST" action="index.php?tab=balance" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_row">
                        <input type="hidden" name="table_name" value="balance_logs">
                        <input type="hidden" name="row_id" value="<?= $r['id'] ?>">
                        <input type="date" name="edit_date" value="<?= date('Y-m-d', strtotime($r['date_time'])) ?>" style="color-scheme: dark; padding: 5px;">
                        <input type="text" name="account_name" value="<?= htmlspecialchars($r['account_name']) ?>" required>
                        <input type="number" step="0.01" name="topup_added" value="<?= $r['topup_added'] ?>" required>
                        <input type="text" name="note" value="<?= htmlspecialchars($r['note'] ?? '') ?>" placeholder="Note">
                        <button type="submit" class="btn btn-green" style="padding: 5px 10px; font-size:12px;">Save</button>
                        <button type="button" class="btn-danger" onclick="toggleEdit('b_<?= $r['id'] ?>')" style="padding: 5px 10px; font-size:12px;">Cancel</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- TAB 2: EXPENSES -->
    <?php if ($active_tab === 'expense'): ?>
    <div class="box">
        <h3>Log Expense</h3>
        <form method="POST" action="index.php?tab=expense">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="add_expense">
            <div class="form-group" style="margin-bottom: 12px;">
                <label style="display:block; font-size:12px; color:#aaa;">Item / Expense Description:</label>
                <input type="text" name="item_name" required placeholder="e.g. Snacks / Supplies">
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label style="display:block; font-size:12px; color:#aaa;">Amount (₱):</label>
                <input type="number" step="0.01" name="amount" required placeholder="0.00">
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label style="display:block; font-size:12px; color:#aaa;">Note / Remark (Optional):</label>
                <input type="text" name="note" placeholder="e.g. Receipt #1042 / Bought from store">
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:12px; color:#aaa;">Date (Optional - defaults to today):</label>
                <input type="date" name="custom_date" style="color-scheme: dark; padding: 8px; width: 100%; border-radius: 4px; border: 1px solid #444; background: #1a1a2e; color: #fff;">
            </div>
            <button type="submit" class="btn btn-green">Add Expense</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Item / Description</th>
                <th>Amount</th>
                <th>Note</th>
                <th>Logged By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expenses as $r): ?>
            <tr id="view_e_<?= $r['id'] ?>">
                <td><?= date('F j, Y, g:i A', strtotime($r['date_time'])) ?></td>
                <td><?= htmlspecialchars($r['item_name']) ?></td>
                <td style="color: #ff4d4d; font-weight: bold;">₱<?= number_format($r['amount'], 2) ?></td>
                <td><small style="color: #bbb;"><?= htmlspecialchars($r['note'] ?? '-') ?></small></td>
                <td><i><?= htmlspecialchars($r['logged_by']) ?></i></td>
                <td>
                    <button class="btn" style="padding: 5px 10px; font-size:12px;" onclick="toggleEdit('e_<?= $r['id'] ?>')">Edit</button>
                    <a href="index.php?tab=expense&delete_tbl=expense_logs&del_id=<?= $r['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" onclick="return confirm('Delete this expense?')" class="btn-danger" style="text-decoration:none; display:inline-block; padding: 5px 10px; font-size:12px;">Delete</a>
                </td>
            </tr>
            <tr id="edit_e_<?= $r['id'] ?>" class="edit-row" style="display:none;">
                <td colspan="6">
                    <form method="POST" action="index.php?tab=expense" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_row">
                        <input type="hidden" name="table_name" value="expense_logs">
                        <input type="hidden" name="row_id" value="<?= $r['id'] ?>">
                        <input type="date" name="edit_date" value="<?= date('Y-m-d', strtotime($r['date_time'])) ?>" style="color-scheme: dark; padding: 5px;">
                        <input type="text" name="item_name" value="<?= htmlspecialchars($r['item_name']) ?>" required>
                        <input type="number" step="0.01" name="amount" value="<?= $r['amount'] ?>" required>
                        <input type="text" name="note" value="<?= htmlspecialchars($r['note'] ?? '') ?>" placeholder="Note">
                        <button type="submit" class="btn btn-green" style="padding: 5px 10px; font-size:12px;">Save</button>
                        <button type="button" class="btn-danger" onclick="toggleEdit('e_<?= $r['id'] ?>')" style="padding: 5px 10px; font-size:12px;">Cancel</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- TAB 3: TODAY (CASH & GCASH COUNTER) -->
    <?php if ($active_tab === 'today'): ?>
    <div class="box">
        <h3>Today's Cash & GCash Counter</h3>
        <form method="POST" action="index.php?tab=today">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="add_counter">
            <table class="denom-table" style="box-shadow:none; border:none;">
                <thead>
                    <tr>
                        <th>Denomination / Account</th>
                        <th>Count / Amount</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $denoms = [1000, 500, 200, 100, 50, 20, 10, 5, 1];
                    foreach ($denoms as $d): 
                    ?>
                    <tr>
                        <td><b>₱<?= $d ?></b></td>
                        <td><input type="number" id="c<?= $d ?>" name="c<?= $d ?>" value="0" min="0" oninput="calcTodayTotal()"></td>
                        <td id="sub_<?= $d ?>">₱0.00</td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background-color: rgba(0, 210, 255, 0.05);">
                        <td><b style="color: var(--neon-blue);">GCash (₱)</b></td>
                        <td><input type="number" step="0.01" id="gcash" name="gcash" value="0.00" min="0" oninput="calcTodayTotal()"></td>
                        <td id="sub_gcash" style="color: var(--neon-blue); font-weight: bold;">₱0.00</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="form-group" style="margin-top: 15px; margin-bottom: 12px;">
                <label style="display:block; font-size:12px; color:#aaa;">Shift Note / Remark (Optional):</label>
                <input type="text" name="note" placeholder="e.g. End of morning shift / End of night shift">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; font-size:12px; color:#aaa;">Date (Optional - defaults to today):</label>
                <input type="date" name="custom_date" style="color-scheme: dark; padding: 8px; width: 100%; border-radius: 4px; border: 1px solid #444; background: #1a1a2e; color: #fff;">
            </div>

            <div class="total-display">
                Total Cash + GCash: <span id="grand_total_display">₱0.00</span>
            </div>
            <br>
            <button type="submit" class="btn btn-green">Submit Today's Count</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>DATE & TIME</th>
                <th>1000S</th>
                <th>500S</th>
                <th>200S</th>
                <th>100S</th>
                <th>50S</th>
                <th>20S</th>
                <th>10S</th>
                <th>5S</th>
                <th>1S</th>
                <th>CASH</th>
                <th>GCASH</th>
                <th>GRAND TOTAL</th>
                <th>NOTE</th>
                <th>LOGGED BY</th>
                <th>ACTIONS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($counters as $r): 
                $cash_total = ($r['c1000']*1000) + ($r['c500']*500) + ($r['c200']*200) + ($r['c100']*100) + ($r['c50']*50) + ($r['c20']*20) + ($r['c10']*10) + ($r['c5']*5) + ($r['c1']*1);
                $gcash_amt = floatval($r['gcash'] ?? 0);
            ?>
            <tr id="view_c_<?= $r['id'] ?>">
                <td><?= date('F j, Y, g:i A', strtotime($r['date_time'])) ?></td>
                <td><?= $r['c1000'] ?></td>
                <td><?= $r['c500'] ?></td>
                <td><?= $r['c200'] ?></td>
                <td><?= $r['c100'] ?></td>
                <td><?= $r['c50'] ?></td>
                <td><?= $r['c20'] ?></td>
                <td><?= $r['c10'] ?></td>
                <td><?= $r['c5'] ?></td>
                <td><?= $r['c1'] ?></td>
                <td style="color: #00ffcc; font-weight: bold;">₱<?= number_format($cash_total, 2) ?></td>
                <td style="color: var(--neon-blue); font-weight: bold;">₱<?= number_format($gcash_amt, 2) ?></td>
                <td style="color: var(--neon-pink); font-weight: bold;">₱<?= number_format($r['grand_total'], 2) ?></td>
                <td><small style="color: #bbb;"><?= htmlspecialchars($r['note'] ?? '-') ?></small></td>
                <td><i><?= htmlspecialchars($r['logged_by']) ?></i></td>
                <td>
                    <button class="btn" style="padding: 5px 10px; font-size:12px;" onclick="toggleEdit('c_<?= $r['id'] ?>')">EDIT</button>
                    <a href="index.php?tab=today&delete_tbl=cash_counter_logs&del_id=<?= $r['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" onclick="return confirm('Delete counter record?')" class="btn-danger" style="text-decoration:none; display:inline-block; padding: 5px 10px; font-size:12px;">Delete</a>
                </td>
            </tr>
            <tr id="edit_c_<?= $r['id'] ?>" class="edit-row" style="display:none;">
                <td colspan="16">
                    <form method="POST" action="index.php?tab=today" style="display:flex; gap:5px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="update_row">
                        <input type="hidden" name="table_name" value="cash_counter_logs">
                        <input type="hidden" name="row_id" value="<?= $r['id'] ?>">
                        <span style="font-size:12px; color:#aaa;">Date:</span>
                        <input type="date" name="edit_date" value="<?= date('Y-m-d', strtotime($r['date_time'])) ?>" style="color-scheme: dark; padding:2px;">
                        <span style="font-size:12px; color:#aaa;">Counts:</span>
                        <input type="number" min="0" name="c1000" value="<?= $r['c1000'] ?>" style="width:40px;" title="1000s">
                        <input type="number" min="0" name="c500" value="<?= $r['c500'] ?>" style="width:40px;" title="500s">
                        <input type="number" min="0" name="c200" value="<?= $r['c200'] ?>" style="width:40px;" title="200s">
                        <input type="number" min="0" name="c100" value="<?= $r['c100'] ?>" style="width:40px;" title="100s">
                        <input type="number" min="0" name="c50" value="<?= $r['c50'] ?>" style="width:40px;" title="50s">
                        <input type="number" min="0" name="c20" value="<?= $r['c20'] ?>" style="width:40px;" title="20s">
                        <input type="number" min="0" name="c10" value="<?= $r['c10'] ?>" style="width:40px;" title="10s">
                        <input type="number" min="0" name="c5" value="<?= $r['c5'] ?>" style="width:40px;" title="5s">
                        <input type="number" min="0" name="c1" value="<?= $r['c1'] ?>" style="width:40px;" title="1s">
                        <input type="number" step="0.01" min="0" name="gcash" value="<?= $r['gcash'] ?? 0 ?>" style="width:70px;" title="GCash">
                        <input type="text" name="note" value="<?= htmlspecialchars($r['note'] ?? '') ?>" placeholder="Note" style="width:100px;">
                        <button type="submit" class="btn btn-green" style="padding: 5px 10px; font-size:12px;">Save</button>
                        <button type="button" class="btn-danger" onclick="toggleEdit('c_<?= $r['id'] ?>')" style="padding: 5px 10px; font-size:12px;">Cancel</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <script>
    let isInternalNavigation = false;

    // Use capture phase (true) so this fires BEFORE the browser unloads
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