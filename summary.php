<?php
// summary.php - Daily Activity Summary with Keyword & Full Date Search
session_start();
if (!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

$logged_user = $_SESSION['username'];
$user_role   = $_SESSION['role'] ?? 'staff';

$balances = $pdo->query("SELECT *, 'topup' AS record_type FROM balance_logs ORDER BY date_time DESC")->fetchAll(PDO::FETCH_ASSOC);
$expenses = $pdo->query("SELECT *, 'expense' AS record_type FROM expense_logs ORDER BY date_time DESC")->fetchAll(PDO::FETCH_ASSOC);
$counters = $pdo->query("SELECT *, 'counter' AS record_type FROM cash_counter_logs ORDER BY date_time DESC")->fetchAll(PDO::FETCH_ASSOC);

$all_logs = array_merge($balances, $expenses, $counters);
$daily_summary = [];

foreach ($all_logs as $log) {
    $date_key = date('Y-m-d', strtotime($log['date_time']));
    $date_formatted = date('F j, Y', strtotime($log['date_time']));

    if (!isset($daily_summary[$date_key])) {
        $daily_summary[$date_key] = [
            'formatted_date' => $date_formatted,
            'items' => []
        ];
    }

    $time_str = date('g:i A', strtotime($log['date_time']));

    if ($log['record_type'] === 'expense') {
        $daily_summary[$date_key]['items'][] = [
            'time' => $time_str,
            'type' => 'expense',
            'text' => htmlspecialchars($log['item_name']) . " — ₱" . number_format($log['amount'], 2) . " (Expenses)",
            'logged_by' => $log['logged_by']
        ];
    } elseif ($log['record_type'] === 'topup') {
        $daily_summary[$date_key]['items'][] = [
            'time' => $time_str,
            'type' => 'topup',
            'text' => htmlspecialchars($log['account_name']) . " — ₱" . number_format($log['topup_added'], 2) . " (Extra Topup)",
            'logged_by' => $log['logged_by']
        ];
    } elseif ($log['record_type'] === 'counter') {
        $c1000 = intval($log['c1000'] ?? 0);
        $c500  = intval($log['c500'] ?? 0);
        $c200  = intval($log['c200'] ?? 0);
        $c100  = intval($log['c100'] ?? 0);
        $c50   = intval($log['c50'] ?? 0);
        $c20   = intval($log['c20'] ?? 0);
        $c10   = intval($log['c10'] ?? 0);
        $c5    = intval($log['c5'] ?? 0);
        $c1    = intval($log['c1'] ?? 0);
        
        $cash_total  = ($c1000 * 1000) + ($c500 * 500) + ($c200 * 200) + ($c100 * 100) + ($c50 * 50) + ($c20 * 20) + ($c10 * 10) + ($c5 * 5) + ($c1 * 1);
        $gcash_total = floatval($log['gcash'] ?? 0);
        $grand_total = floatval($log['grand_total'] ?? ($cash_total + $gcash_total));

        $counter_text = "₱" . number_format($cash_total, 2) . " Cash, ₱" . number_format($gcash_total, 2) . " GCash — ₱" . number_format($grand_total, 2) . " Grand Total";

        $daily_summary[$date_key]['items'][] = [
            'time' => $time_str,
            'type' => 'counter',
            'text' => $counter_text,
            'logged_by' => $log['logged_by']
        ];
    }
}

krsort($daily_summary);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Daily Summary - Heroics Gaming Lounge</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .search-input, .date-picker {
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            color: #fff;
            font-size: 0.95rem;
        }
        .search-input {
            flex: 2;
            min-width: 200px;
        }
        .date-picker {
            flex: 1;
            min-width: 150px;
            color-scheme: dark;
        }
        .search-input:focus, .date-picker:focus {
            outline: none;
            border-color: var(--neon-blue, #00d2ff);
            box-shadow: 0 0 8px rgba(0, 210, 255, 0.3);
        }
        .day-card {
            background: var(--bg-card, #1a1a2e);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 5px solid var(--neon-blue, #00d2ff);
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 20px;
        }
        .day-title {
            color: var(--neon-pink, #ff007f);
            font-size: 1.2rem;
            margin-top: 0;
            margin-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 8px;
        }
        .summary-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .summary-item {
            padding: 8px 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .tag-expense { color: #ff4d4d; font-weight: bold; }
        .tag-topup { color: #00ffcc; font-weight: bold; }
        .tag-counter { color: #00d2ff; font-weight: bold; }
        .user-tag { font-size: 0.85rem; color: #888; font-style: italic; }
    </style>
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

    <div class="nav-buttons">
        <a href="index.php?tab=balance" class="nav-btn">+ Extra Topup</a>
        <a href="index.php?tab=expense" class="nav-btn">+ Add Expense</a>
        <a href="index.php?tab=today" class="nav-btn">Today (Cash Counter)</a>
        <a href="summary.php" class="nav-btn active">Daily Summary</a>

        <?php if ($user_role === 'admin'): ?>
            <a href="users.php" class="nav-btn" style="border-color: #ff007f; color: #ff007f;">+ Manage Users</a>
        <?php endif; ?>
    </div>

    <div class="box">
        <h3>Daily Operations Summary</h3>
        
        <div class="search-box">
            <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search keyword, user, item, or amount..." onkeyup="filterSummary()">
            <input type="date" id="datePicker" class="date-picker" onchange="filterSummary()" title="Filter by specific date">
            <button class="btn" onclick="clearFilters()" style="padding: 10px 15px; font-size: 0.85rem;">Reset Filters</button>
        </div>

        <?php if (empty($daily_summary)): ?>
            <p>No activity logged yet.</p>
        <?php else: ?>
            <div id="summaryContainer">
                <?php foreach ($daily_summary as $date_key => $date_group): ?>
                    <div class="day-card" data-date="<?= $date_key ?>" data-formatted-date="<?= strtolower($date_group['formatted_date']) ?>">
                        <h4 class="day-title">📅 <?= $date_group['formatted_date'] ?></h4>
                        <ul class="summary-list">
                            <?php foreach ($date_group['items'] as $item): ?>
                                <li class="summary-item">
                                    <div>
                                        <span style="color: #888; font-size: 0.85rem; margin-right: 10px;"><?= $item['time'] ?></span>
                                        <span class="tag-<?= $item['type'] ?>">
                                            <?= $item['text'] ?>
                                        </span>
                                    </div>
                                    <span class="user-tag">by <?= htmlspecialchars($item['logged_by']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
            <p id="noResults" style="display: none; color: #888; text-align: center; padding: 20px;">No matching records found for this query or date.</p>
        <?php endif; ?>
    </div>

    <script>
        function filterSummary() {
            let query = document.getElementById('searchInput').value.toLowerCase().trim();
            let selectedDate = document.getElementById('datePicker').value;
            let dayCards = document.querySelectorAll('.day-card');
            let hasVisibleCards = false;

            dayCards.forEach(function(card) {
                let cardDateKey = card.getAttribute('data-date');
                let cardFormattedDate = card.getAttribute('data-formatted-date');
                let items = card.querySelectorAll('.summary-item');
                let cardHasMatch = false;

                let matchesDate = !selectedDate || cardDateKey === selectedDate;

                items.forEach(function(item) {
                    let itemText = item.innerText.toLowerCase();
                    let matchesText = !query || itemText.includes(query) || cardFormattedDate.includes(query);

                    if (matchesDate && matchesText) {
                        item.style.display = '';
                        cardHasMatch = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (cardHasMatch) {
                    card.style.display = '';
                    hasVisibleCards = true;
                } else {
                    card.style.display = 'none';
                }
            });

            document.getElementById('noResults').style.display = hasVisibleCards ? 'none' : '';
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('datePicker').value = '';
            filterSummary();
        }
    </script>
    
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