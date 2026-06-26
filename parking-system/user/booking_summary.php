<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";

if (!isset($_GET['booking_id'])) {
    header("Location: user_dashboard.php"); exit;
}

$booking_id = (int)$_GET['booking_id'];

$q = mysqli_query($conn,
"SELECT b.*, l.location_name, s.slot_number, p.reference_id, p.method
 FROM bookings b
 JOIN locations l ON b.location_id = l.id
 JOIN slots s ON b.slot_id = s.id
 JOIN payments p ON b.payment_id = p.id
 WHERE b.id=$booking_id AND b.user_id={$_SESSION['user_id']}");

$data = mysqli_fetch_assoc($q);
if (!$data) { header("Location: user_dashboard.php"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking Confirmed - Smart Parking</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">
<?php $active_page = 'history'; include "../includes/user_sidebar.php"; ?>

<div class="main-content" style="display:flex;align-items:flex-start;justify-content:center;padding-top:40px;">
    <div class="success-card animate-in">
        <div class="success-header">
            <div class="check-icon">🎉</div>
            <h2>Booking Confirmed!</h2>
            <p style="opacity:.85;margin-top:6px;">Your parking slot has been reserved successfully.</p>
        </div>
        <div class="success-body">
            <table class="booking-detail-table">
                <tr><td>Booking ID</td><td><strong>#<?= $data['id'] ?></strong></td></tr>
                <tr><td>Location</td><td>📍 <?= htmlspecialchars($data['location_name']) ?></td></tr>
                <tr><td>Slot Number</td><td>🅿️ <?= htmlspecialchars($data['slot_number']) ?></td></tr>
                <tr><td>Duration</td><td>⏱️ <?= $data['hours'] ?> Hour<?= $data['hours']>1?'s':'' ?></td></tr>
                <?php
                    $st = $data['start_time'] ?? $data['created_at'];
                    $et = date('Y-m-d H:i:s', strtotime($st) + ($data['hours'] * 3600));
                ?>
                <tr><td>🟢 Entry Time</td><td><strong><?= date('d M Y, h:i A', strtotime($st)) ?></strong></td></tr>
                <tr><td>🔴 Exit Time</td><td><strong><?= date('d M Y, h:i A', strtotime($et)) ?></strong></td></tr>
                <tr><td>Amount Paid</td><td><strong style="color:#2e7d32;font-size:18px;">₹<?= $data['amount'] ?></strong></td></tr>
                <tr><td>Payment Method</td><td><?= htmlspecialchars($data['method'] ?? 'UPI') ?></td></tr>
                <tr><td>Reference ID</td><td><code style="background:#f5f5f5;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($data['reference_id']) ?></code></td></tr>
                <tr><td>Status</td><td><span class="badge badge-success">✅ Confirmed</span></td></tr>
            </table>

            <div style="display:flex;gap:10px;margin-top:24px;flex-wrap:wrap;">
                <a href="user_dashboard.php" class="btn btn-primary" style="flex:1;justify-content:center;">🏠 Dashboard</a>
                <a href="booking_history.php" class="btn btn-outline" style="flex:1;justify-content:center;">📋 My Bookings</a>
                <a href="receipt.php?id=<?= $data['id'] ?>" class="btn btn-outline" style="flex:1;justify-content:center;">🖨️ Receipt</a>
                <button onclick="sendReminder(<?= $data['id'] ?>)" id="reminderBtn" class="btn btn-accent" style="flex:1;justify-content:center;">🔔 Send Reminder</button>
            </div>
        </div>
    </div>
</div>
</div>
<script>
function sendReminder(bid) {
    const btn = document.getElementById('reminderBtn');
    btn.disabled = true;
    btn.textContent = 'Sending...';
    fetch('send_reminder.php?booking_id=' + bid)
        .then(r => r.json())
        .then(d => {
            alert(d.msg);
            btn.textContent = d.success ? '\u2705 Sent!' : '\u274C Failed';
        })
        .catch(() => { btn.textContent = '\u274C Error'; btn.disabled = false; });
}

// Auto-download receipt as PDF only once
window.addEventListener('load', function () {
    var params = new URLSearchParams(window.location.search);
    if (!params.get('downloaded')) {
        window.location.href = 'download_receipt.php?id=<?= $data['id'] ?>';
    }
});
</script>
</body>
</html>
