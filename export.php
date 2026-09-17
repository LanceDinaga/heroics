<?php
// export.php - Export Data Filtered by Shift Period
require 'db.php';

if (!isset($_SESSION['loggedin'])) {
    header("Location: login.php");
    exit;
}

$period_id = isset($_GET['period_id']) ? intval($_GET['period_id']) : null;
$shift_id = isset($_GET['shift_id']) ? intval($_GET['shift_id']) : null;
logActivity($pdo, 'export_data', 'Exported application data.', ['period_id' => $period_id, 'shift_id' => $shift_id]);

// Determine filename
$filename = 'Export_Data_All_' . date('Y-m-d') . '.xls';
if ($shift_id) {
    $stmtP = $pdo->prepare("SELECT p.start_date FROM shifts s JOIN shift_periods p ON s.shift_period_id = p.id WHERE s.id = ?");
    $stmtP->execute([$shift_id]);
    $pStartDate = $stmtP->fetchColumn();
    if ($pStartDate) {
        $cleanLabel = date('M_j', strtotime($pStartDate)) . '_Shift_' . $shift_id;
        $filename = "Export_Data_{$cleanLabel}.xls";
    }
} elseif ($period_id) {
    $stmtP = $pdo->prepare("SELECT start_date FROM shift_periods WHERE id = ?");
    $stmtP->execute([$period_id]);
    $pStartDate = $stmtP->fetchColumn();
    if ($pStartDate) {
        $cleanLabel = date('M_j', strtotime($pStartDate));
        $filename = "Export_Data_{$cleanLabel}.xls";
    }
}

// Set headers for Native Excel Spreadsheet
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style>
    body, table { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
    th { background-color: #1a252f; color: #ffffff; font-weight: bold; border: 0.5pt solid #cccccc; padding: 5px; text-align: left; }
    td { border: 0.5pt solid #e0e0e0; padding: 5px; mso-number-format:"\@"; }
    .num { mso-number-format:"\#\,\#\#0\.00"; text-align: right; }
    .section-title { font-size: 14pt; font-weight: bold; color: #007bff; background-color: #eef5ff; padding: 8px; border: 1pt solid #b8daff; }
</style>
</head>
<body>

<!-- 1. FULL SHIFT SUMMARY BACKUP -->
<table>
    <tr><td colspan="17" class="section-title">=== 1. FULL SHIFT SUMMARY BACKUP ===</td></tr>
    <tr>
        <th>Shift ID</th><th>Shift Period</th><th>Shift Type</th><th>Status</th>
        <th>Opened By</th><th>Opened At</th><th>Closed By</th><th>Closed At</th>
        <th>Starting Cash</th><th>Starting GCash</th><th>Ending Cash</th><th>Ending GCash</th>
        <th>Cash Sales</th><th>GCash Sales</th><th>Extra Topups</th><th>Cash Expenses</th><th>GCash Expenses</th>
    </tr>
    <?php
    $sql1 = "SELECT s.id, DATE_FORMAT(p.start_date, '%b %e'), s.shift_type, s.status, s.opened_by,
            DATE_FORMAT(s.opened_at, '%Y-%m-%d %H:%i') AS opened_at, COALESCE(s.closed_by, ''), 
            IF(s.closed_at IS NULL, '', DATE_FORMAT(s.closed_at, '%Y-%m-%d %H:%i')) AS closed_at,
            s.starting_cash, s.starting_gcash, s.ending_cash, s.ending_gcash,
            COALESCE((SELECT SUM(amount) FROM shift_log_entries WHERE shift_id = s.id AND payment_method = 'cash'), 0),
            COALESCE((SELECT SUM(amount) FROM shift_log_entries WHERE shift_id = s.id AND payment_method = 'gcash'), 0),
            COALESCE((SELECT SUM(topup_added) FROM balance_logs WHERE shift_id = s.id), 0),
            COALESCE((SELECT SUM(amount) FROM expense_logs WHERE shift_id = s.id AND payment_method = 'cash'), 0),
            COALESCE((SELECT SUM(amount) FROM expense_logs WHERE shift_id = s.id AND payment_method = 'gcash'), 0)
        FROM shifts s JOIN shift_periods p ON s.shift_period_id = p.id";
    if ($shift_id) { $sql1 .= " WHERE s.id = " . $shift_id; }
    elseif ($period_id) { $sql1 .= " WHERE s.shift_period_id = " . $period_id; }
    $sql1 .= " ORDER BY s.id ASC";

    $stmt1 = $pdo->query($sql1);
    while ($r = $stmt1->fetch(PDO::FETCH_NUM)): ?>
        <tr>
            <td><?= $r[0] ?></td><td><?= $r[1] ?></td><td><?= $r[2] ?></td><td><?= $r[3] ?></td>
            <td><?= $r[4] ?></td><td><?= $r[5] ?></td><td><?= $r[6] ?></td><td><?= $r[7] ?></td>
            <td class="num"><?= number_format($r[8], 2) ?></td><td class="num"><?= number_format($r[9], 2) ?></td>
            <td class="num"><?= number_format($r[10], 2) ?></td><td class="num"><?= number_format($r[11], 2) ?></td>
            <td class="num"><?= number_format($r[12], 2) ?></td><td class="num"><?= number_format($r[13], 2) ?></td>
            <td class="num"><?= number_format($r[14], 2) ?></td><td class="num"><?= number_format($r[15], 2) ?></td>
            <td class="num"><?= number_format($r[16], 2) ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<br/><br/>

<!-- 2. TOTAL SALES SUMMARY (CASH + GCASH) -->
<table>
    <tr><td colspan="6" class="section-title">=== 2. TOTAL SALES SUMMARY (CASH + GCASH) ===</td></tr>
    <tr>
        <th>Shift ID</th><th>Shift Period</th><th>Shift Type</th>
        <th>Cash Sales (PHP)</th><th>GCash Sales (PHP)</th><th>Total Sales (PHP)</th>
    </tr>
    <?php
    $sql_sales = "SELECT 
            s.id, DATE_FORMAT(p.start_date, '%b %e'), s.shift_type,
            COALESCE((SELECT SUM(amount) FROM shift_log_entries WHERE shift_id = s.id AND payment_method = 'cash'), 0) AS cash_sales,
            COALESCE((SELECT SUM(amount) FROM shift_log_entries WHERE shift_id = s.id AND payment_method = 'gcash'), 0) AS gcash_sales
        FROM shifts s JOIN shift_periods p ON s.shift_period_id = p.id";
    if ($shift_id) { $sql_sales .= " WHERE s.id = " . $shift_id; }
    elseif ($period_id) { $sql_sales .= " WHERE s.shift_period_id = " . $period_id; }
    $sql_sales .= " ORDER BY s.id ASC";

    $stmt_sales = $pdo->query($sql_sales);
    while ($r = $stmt_sales->fetch(PDO::FETCH_ASSOC)): 
        $total_sales = floatval($r['cash_sales']) + floatval($r['gcash_sales']);
    ?>
        <tr>
            <td><?= $r['id'] ?></td><td><?= $r['label'] ?></td><td><?= ucfirst($r['shift_type']) ?></td>
            <td class="num"><?= number_format($r['cash_sales'], 2) ?></td>
            <td class="num"><?= number_format($r['gcash_sales'], 2) ?></td>
            <td class="num" style="font-weight:bold; color:#007bff;"><?= number_format($total_sales, 2) ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<br/><br/>

<!-- 3. CASH AND SALES LOG -->
<table>
    <tr><td colspan="8" class="section-title">=== 3. CASH AND SALES LOG ===</td></tr>
    <tr>
        <th>Log ID</th><th>Shift Period</th><th>Shift Type</th><th>Amount (PHP)</th>
        <th>Payment Method</th><th>Note / Particular</th><th>Logged By</th><th>Date / Time</th>
    </tr>
    <?php
    $sql2 = "SELECT e.id, DATE_FORMAT(p.start_date, '%b %e'), s.shift_type, e.amount, e.payment_method, e.note, e.logged_by,
            DATE_FORMAT(e.date_time, '%Y-%m-%d %H:%i') FROM shift_log_entries e 
            JOIN shifts s ON e.shift_id = s.id JOIN shift_periods p ON s.shift_period_id = p.id";
    if ($shift_id) { $sql2 .= " WHERE s.id = " . $shift_id; }
    elseif ($period_id) { $sql2 .= " WHERE s.shift_period_id = " . $period_id; }
    $sql2 .= " ORDER BY e.id ASC";

    $stmt2 = $pdo->query($sql2);
    while ($r = $stmt2->fetch(PDO::FETCH_NUM)): ?>
        <tr>
            <td><?= $r[0] ?></td><td><?= $r[1] ?></td><td><?= $r[2] ?></td>
            <td class="num"><?= number_format($r[3], 2) ?></td><td><?= $r[4] ?></td><td><?= htmlspecialchars($r[5] ?? '') ?></td>
            <td><?= $r[6] ?></td><td><?= $r[7] ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<br/><br/>

<!-- 4. EXTRA TOPUPS LOG -->
<table>
    <tr><td colspan="8" class="section-title">=== 4. EXTRA TOPUPS LOG ===</td></tr>
    <tr>
        <th>Log ID</th><th>Shift Period</th><th>Shift Type</th><th>Account Name</th>
        <th>Topup Amount (PHP)</th><th>Note / Reason</th><th>Logged By</th><th>Date / Time</th>
    </tr>
    <?php
    $sql3 = "SELECT b.id, COALESCE(DATE_FORMAT(p.start_date, '%b %e'), 'N/A'), COALESCE(s.shift_type, 'N/A'), b.account_name, b.topup_added, b.note, b.logged_by,
            DATE_FORMAT(b.date_time, '%Y-%m-%d %H:%i') FROM balance_logs b 
            LEFT JOIN shifts s ON b.shift_id = s.id LEFT JOIN shift_periods p ON s.shift_period_id = p.id";
    if ($shift_id) { $sql3 .= " WHERE s.id = " . $shift_id; }
    elseif ($period_id) { $sql3 .= " WHERE s.shift_period_id = " . $period_id; }
    $sql3 .= " ORDER BY b.id ASC";

    $stmt3 = $pdo->query($sql3);
    while ($r = $stmt3->fetch(PDO::FETCH_NUM)): ?>
        <tr>
            <td><?= $r[0] ?></td><td><?= $r[1] ?></td><td><?= $r[2] ?></td><td><?= htmlspecialchars($r[3]) ?></td>
            <td class="num"><?= number_format($r[4], 2) ?></td><td><?= htmlspecialchars($r[5] ?? '') ?></td>
            <td><?= $r[6] ?></td><td><?= $r[7] ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<br/><br/>

<!-- 5. EXPENSES LOG -->
<table>
    <tr><td colspan="8" class="section-title">=== 5. EXPENSES LOG ===</td></tr>
    <tr>
        <th>Log ID</th><th>Shift Period</th><th>Shift Type</th><th>Item Description</th>
        <th>Amount (PHP)</th><th>Payment Method</th><th>Logged By</th><th>Date / Time</th>
    </tr>
    <?php
    $sql4 = "SELECT x.id, COALESCE(DATE_FORMAT(p.start_date, '%b %e'), 'N/A'), COALESCE(s.shift_type, 'N/A'), x.item_name, x.amount, x.payment_method, x.logged_by,
            DATE_FORMAT(x.date_time, '%Y-%m-%d %H:%i') FROM expense_logs x 
            LEFT JOIN shifts s ON x.shift_id = s.id LEFT JOIN shift_periods p ON s.shift_period_id = p.id";
    if ($shift_id) { $sql4 .= " WHERE s.id = " . $shift_id; }
    elseif ($period_id) { $sql4 .= " WHERE s.shift_period_id = " . $period_id; }
    $sql4 .= " ORDER BY x.id ASC";

    $stmt4 = $pdo->query($sql4);
    while ($r = $stmt4->fetch(PDO::FETCH_NUM)): ?>
        <tr>
            <td><?= $r[0] ?></td><td><?= $r[1] ?></td><td><?= $r[2] ?></td><td><?= htmlspecialchars($r[3]) ?></td>
            <td class="num"><?= number_format($r[4], 2) ?></td><td><?= $r[5] ?></td><td><?= $r[6] ?></td><td><?= $r[7] ?></td>
        </tr>
    <?php endwhile; ?>
</table>

</body>
</html>