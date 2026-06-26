<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";
date_default_timezone_set('Asia/Kolkata');

if (!isset($_GET['id'])) { header("Location: booking_history.php"); exit; }
$bid     = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

$data = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT b.*, l.location_name, l.rate_per_hour, s.slot_number,
            p.reference_id, p.method, p.created_at AS paid_at,
            u.name AS user_name, u.email, u.phone,
            v.vehicle_number, v.type AS vehicle_type
     FROM bookings b
     JOIN locations l ON b.location_id = l.id
     JOIN slots s ON b.slot_id = s.id
     JOIN payments p ON b.payment_id = p.id
     JOIN users u ON b.user_id = u.id
     JOIN vehicles v ON b.vehicle_id = v.id
     WHERE b.id=$bid AND b.user_id=$user_id"));

if (!$data) { header("Location: booking_history.php"); exit; }

$st     = !empty($data['start_time']) ? $data['start_time'] : $data['created_at'];
$st_ts  = strtotime($st);
$et_ts  = $st_ts + ($data['hours'] * 3600);
$status = strtolower($data['status']);
$badge  = $status === 'booked' ? '#2e7d32' : ($status === 'cancelled' ? '#c62828' : '#e65100');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt #<?= $bid ?> - Smart Parking</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.receipt-wrap { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.12); overflow: hidden; }
.receipt-header { background: linear-gradient(135deg, #1a237e, #3949ab); color: #fff; padding: 28px 32px; text-align: center; }
.receipt-header h2 { font-size: 22px; margin-bottom: 4px; }
.receipt-header .ref { font-size: 13px; opacity: .8; margin-top: 6px; }
.receipt-logo { font-size: 36px; margin-bottom: 8px; }
.receipt-body { padding: 28px 32px; }
.receipt-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
.receipt-row:last-child { border-bottom: none; }
.receipt-row .label { color: #666; font-weight: 500; }
.receipt-row .value { font-weight: 600; color: #212121; text-align: right; }
.receipt-total { background: #f5f7ff; border-radius: 8px; padding: 16px 20px; margin: 16px 0; display: flex; justify-content: space-between; align-items: center; }
.receipt-total .t-label { font-size: 15px; font-weight: 600; color: #666; }
.receipt-total .t-amount { font-size: 28px; font-weight: 800; color: #1a237e; }
.receipt-footer { text-align: center; padding: 16px 32px 24px; color: #999; font-size: 12px; border-top: 1px dashed #e0e0e0; }
.receipt-status { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; color: #fff; background: <?= $badge ?>; }
@media print {
    body { background: #fff !important; }
    .print-hide { display: none !important; }
    .receipt-wrap { box-shadow: none !important; border: 1px solid #ddd; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .sidebar { display: none !important; }
    .dash-wrapper { display: block !important; }
}
</style>
</head>
<body>
<div class="dash-wrapper">
<?php $active_page = 'history'; include "../includes/user_sidebar.php"; ?>
<div class="main-content">
    <div class="topbar print-hide">
        <h1>&#128203; Booking Receipt</h1>
        <div style="display:flex;gap:10px;">
            <a href="booking_history.php" class="btn btn-outline btn-sm">&larr; Back</a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">&#128424; Print / Save PDF</button>
        </div>
    </div>

    <div class="receipt-wrap">
        <div class="receipt-header">
            <div class="receipt-logo">&#127359;</div>
            <h2>Smart Parking</h2>
            <p style="opacity:.75;font-size:13px;margin-top:2px;">Official Parking Receipt</p>
            <div class="ref">Ref: <?= htmlspecialchars($data['reference_id']) ?></div>
        </div>

        <div class="receipt-body">
            <div class="receipt-row">
                <span class="label">Booking ID</span>
                <span class="value">#<?= $data['id'] ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Customer Name</span>
                <span class="value"><?= htmlspecialchars($data['user_name']) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Email</span>
                <span class="value"><?= htmlspecialchars($data['email']) ?></span>
            </div>
            <?php if (!empty($data['phone'])): ?>
            <div class="receipt-row">
                <span class="label">Phone</span>
                <span class="value"><?= htmlspecialchars($data['phone']) ?></span>
            </div>
            <?php endif; ?>
            <div class="receipt-row">
                <span class="label">Vehicle</span>
                <span class="value"><?= htmlspecialchars($data['vehicle_number']) ?> (<?= htmlspecialchars($data['vehicle_type']) ?>)</span>
            </div>
            <div class="receipt-row">
                <span class="label">Location</span>
                <span class="value">&#128205; <?= htmlspecialchars($data['location_name']) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Slot Number</span>
                <span class="value"><?= htmlspecialchars($data['slot_number']) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">&#128994; Entry Time</span>
                <span class="value"><?= date('d M Y, h:i A', $st_ts) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">&#128308; Exit Time</span>
                <span class="value"><?= date('d M Y, h:i A', $et_ts) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Duration</span>
                <span class="value"><?= $data['hours'] ?> Hour<?= $data['hours'] > 1 ? 's' : '' ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Rate</span>
                <span class="value">&#8377;<?= $data['rate_per_hour'] ?>/hr</span>
            </div>
            <div class="receipt-row">
                <span class="label">Payment Method</span>
                <span class="value"><?= htmlspecialchars($data['method'] ?? 'UPI') ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Payment Date</span>
                <span class="value"><?= date('d M Y, h:i A', strtotime($data['paid_at'])) ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Status</span>
                <span class="value"><span class="receipt-status"><?= ucfirst($status) ?></span></span>
            </div>

            <div class="receipt-total">
                <span class="t-label">Total Amount Paid</span>
                <span class="t-amount">&#8377;<?= number_format($data['amount']) ?></span>
            </div>
        </div>

        <div class="receipt-footer">
            Thank you for using Smart Parking! &bull; Generated on <?= date('d M Y, h:i A') ?>
        </div>
    </div>
</div>
</div>
</body>
</html>
