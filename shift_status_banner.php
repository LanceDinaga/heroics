<?php
// shift_status_banner.php - Persistent reminder of the current shift
function renderShiftBanner($pdo) {
    try {
        $shift = $pdo->query("SELECT s.*, p.label AS period_label
                               FROM shifts s JOIN shift_periods p ON s.shift_period_id = p.id
                               WHERE s.status = 'open' ORDER BY s.id DESC LIMIT 1")->fetch();
    } catch (PDOException $e) {
        return; 
    }

    if ($shift) {
        $color = $shift['shift_type'] === 'morning' ? '#ffc107' : '#8a2be2';
        echo '<div style="background:rgba(0,0,0,0.25); border:1px solid ' . $color . '; border-left:4px solid ' . $color . '; border-radius:8px; padding:10px 16px; margin-bottom:15px; font-size:13px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">';
        echo '<span>🟢 <b style="color:' . $color . ';">' . ucfirst(htmlspecialchars($shift['shift_type'])) . ' Shift</b> active &mdash; Shift ' . htmlspecialchars($shift['period_label']) . ' &middot; opened ' . date('g:i A', strtotime($shift['opened_at'])) . ' by ' . htmlspecialchars($shift['opened_by']) . '</span>';
        echo '<a href="shifts.php" style="color:#fff; text-decoration:underline; font-size:12px;">Go to Shift &rarr;</a>';
        echo '</div>';
    } else {
        echo '<div style="background:rgba(220,53,69,0.1); border:1px solid #dc3545; border-left:4px solid #dc3545; border-radius:8px; padding:10px 16px; margin-bottom:15px; font-size:13px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">';
        echo '<span>⚠️ No shift is currently open.</span>';
        echo '<a href="shifts.php" style="color:#fff; text-decoration:underline; font-size:12px;">Start a Shift &rarr;</a>';
        echo '</div>';
    }
}
?>