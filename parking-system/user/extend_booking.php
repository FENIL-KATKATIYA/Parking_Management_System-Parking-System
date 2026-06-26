<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";
require_once "../includes/razorpay_config.php";
date_default_timezone_set('Asia/Kolkata');

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) { header("Location: booking_history.php"); exit; }
$bid = (int)$_GET['id'];

$booking = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT b.*, l.location_name, l.rate_per_hour, s.slot_number
     FROM bookings b
     JOIN locations l ON b.location_id = l.id
     JOIN slots s ON b.slot_id = s.id
     WHERE b.id=$bid AND b.user_id=$user_id AND b.status='booked' LIMIT 1"));

if (!$booking) { header("Location: booking_history.php"); exit; }

$raw      = !empty($booking['start_time']) ? $booking['start_time'] : $booking['created_at'];
$start_ts = strtotime($raw);
$end_ts   = $start_ts + ($booking['hours'] * 3600);
$now      = time();

if ($now < $start_ts || $now >= $end_ts) {
    header("Location: booking_history.php?err=notactive"); exit;
}

$rate       = (int)$booking['rate_per_hour'];
$max_extend = 6;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Extend Booking - Smart Parking</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">
<?php $active_page = 'history'; include "../includes/user_sidebar.php"; ?>
<div class="main-content">
    <div class="topbar">
        <h1>&#9203; Extend Booking #<?= $bid ?></h1>
        <a href="booking_history.php" class="btn btn-outline btn-sm">&larr; Back</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;max-width:800px;">

        <!-- Current Booking Info -->
        <div class="card">
            <div class="card-header"><h2>&#128203; Current Booking</h2></div>
            <table class="booking-detail-table">
                <tr><td>Location</td><td>&#128205; <?= htmlspecialchars($booking['location_name']) ?></td></tr>
                <tr><td>Slot</td><td><strong><?= htmlspecialchars($booking['slot_number']) ?></strong></td></tr>
                <tr><td>&#128994; Entry</td><td><?= date('h:i A, d M Y', $start_ts) ?></td></tr>
                <tr><td>&#128308; Current Exit</td><td><strong><?= date('h:i A, d M Y', $end_ts) ?></strong></td></tr>
                <tr><td>Total Hours</td><td><?= $booking['hours'] ?> hr(s)</td></tr>
                <tr><td>Rate</td><td>&#8377;<?= $rate ?>/hr</td></tr>
                <tr><td>Time Left</td>
                    <td><span class="time-remaining active" id="tleft" data-end="<?= $end_ts ?>">
                        <?php $d=$end_ts-$now;$h=floor($d/3600);$m=floor(($d%3600)/60);$s=$d%60;
                        echo $h>0?$h.'h '.str_pad($m,2,'0',STR_PAD_LEFT).'m '.str_pad($s,2,'0',STR_PAD_LEFT).'s':($m>0?$m.'m '.str_pad($s,2,'0',STR_PAD_LEFT).'s':$s.'s'); ?>
                    </span></td>
                </tr>
            </table>
        </div>

        <!-- Extend Form -->
        <div class="card">
            <div class="card-header"><h2>&#10133; Add More Hours</h2></div>

            <div class="form-group">
                <label>Extra Hours (max <?= $max_extend ?>)</label>
                <select class="form-control" id="extHours" onchange="updatePreview()">
                    <?php for ($i=1;$i<=$max_extend;$i++): ?>
                    <option value="<?= $i ?>"><?= $i ?> Hour<?= $i>1?'s':'' ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div id="ext-preview" style="background:#f5f7ff;border-radius:8px;padding:14px;margin-bottom:18px;font-size:14px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span style="color:#666;">New Exit Time</span>
                    <strong id="newExit"><?= date('h:i A, d M', $end_ts + 3600) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#666;">Extra Charge</span>
                    <strong style="color:#2e7d32;" id="extraCharge">&#8377;<?= $rate ?></strong>
                </div>
            </div>

            <button id="rzp-btn" class="btn btn-primary btn-block">&#9203; Extend &amp; Pay</button>

            <!-- Hidden form submitted after Razorpay success -->
            <form id="extendForm" action="extend_success.php" method="POST" style="display:none;">
                <input type="hidden" name="booking_id"          value="<?= $bid ?>">
                <input type="hidden" name="extra_hours"         id="f_extra_hours">
                <input type="hidden" name="extra_amount"        id="f_extra_amount">
                <input type="hidden" name="razorpay_order_id"   id="f_order_id">
                <input type="hidden" name="razorpay_payment_id" id="f_payment_id">
                <input type="hidden" name="razorpay_signature"  id="f_signature">
            </form>
        </div>
    </div>
</div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const rate      = <?= $rate ?>;
const endTs     = <?= $end_ts ?>;
const MONTHS    = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function fmtTs(ts) {
    const d = new Date(ts * 1000);
    const h = d.getHours(), m = d.getMinutes();
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12  = h % 12 || 12;
    return h12 + ':' + String(m).padStart(2,'0') + ' ' + ampm + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()];
}
function updatePreview() {
    const extra = parseInt(document.getElementById('extHours').value) || 1;
    document.getElementById('newExit').textContent     = fmtTs(endTs + extra * 3600);
    document.getElementById('extraCharge').textContent = '₹' + (extra * rate);
}
function pad(n){return String(n).padStart(2,'0');}
function fmt(s){if(s<=0)return'0s';const h=Math.floor(s/3600),m=Math.floor((s%3600)/60),sc=s%60;return h>0?h+'h '+pad(m)+'m '+pad(sc)+'s':m>0?m+'m '+pad(sc)+'s':sc+'s';}
function tick(){
    const now=Math.floor(Date.now()/1000);
    document.querySelectorAll('.time-remaining.active[data-end]').forEach(el=>{
        const d=parseInt(el.dataset.end)-now;
        if(d<=0){el.textContent='Time Up';return;}
        el.textContent=fmt(d);
        d<600?el.classList.add('expiring'):el.classList.remove('expiring');
    });
}
tick(); setInterval(tick, 1000);
updatePreview();

document.getElementById('rzp-btn').addEventListener('click', function () {
    const extra       = parseInt(document.getElementById('extHours').value) || 1;
    const extraAmount = extra * rate;

    this.disabled    = true;
    this.textContent = 'Creating order...';

    const formData = new FormData();
    formData.append('amount', extraAmount);

    fetch('create_order.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(order => {
            if (order.error) {
                alert('Error: ' + order.error);
                document.getElementById('rzp-btn').disabled    = false;
                document.getElementById('rzp-btn').textContent = '⏱ Extend & Pay';
                return;
            }

            const options = {
                key:         '<?= RAZORPAY_KEY_ID ?>',
                amount:      order.amount,
                currency:    'INR',
                name:        'Smart Parking',
                description: 'Extend Booking #<?= $bid ?> by ' + extra + ' hr(s)',
                order_id:    order.id,
                handler: function (response) {
                    document.getElementById('f_extra_hours').value  = extra;
                    document.getElementById('f_extra_amount').value = extraAmount;
                    document.getElementById('f_order_id').value     = response.razorpay_order_id;
                    document.getElementById('f_payment_id').value   = response.razorpay_payment_id;
                    document.getElementById('f_signature').value    = response.razorpay_signature;
                    document.getElementById('extendForm').submit();
                },
                prefill: { name: '', email: '', contact: '' },
                theme: { color: '#1a237e' }
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (resp) {
                alert('Payment failed: ' + resp.error.description);
                document.getElementById('rzp-btn').disabled    = false;
                document.getElementById('rzp-btn').textContent = '⏱ Extend & Pay';
            });
            rzp.open();
        })
        .catch(() => {
            alert('Could not connect to payment gateway. Please try again.');
            document.getElementById('rzp-btn').disabled    = false;
            document.getElementById('rzp-btn').textContent = '⏱ Extend & Pay';
        });
});
</script>
</body>
</html>
