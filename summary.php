<?php
// summary.php - Daily & Shift Summary Report with Per-Period Excel Export
require 'db.php';

if (!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}

$logged_user = $_SESSION['username'];
$user_role   = $_SESSION['role'] ?? 'staff';

// Fetch Shift Periods and calculate internal metrics
$periods_data = [];
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
    </style>
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
                    <div class="shift-block">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span class="tag-badge tag-<?= $s['shift_type'] ?>"><?= ucfirst($s['shift_type']) ?> Shift</span>
                                <span style="font-size:12px; color:var(--text-muted); margin-left:8px;">Status: <b><?= strtoupper($s['status']) ?></b></span>
                            </div>
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
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>