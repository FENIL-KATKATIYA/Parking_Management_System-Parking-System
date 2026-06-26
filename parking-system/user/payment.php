<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";
require_once "../includes/razorpay_config.php";

if (!isset($_GET['slot_id'], $_GET['location_id'], $_GET['vehicle_id'], $_GET['hours'], $_GET['start_time'])) {
    header("Location: book_slot.php"); exit;
}

$user_id     = $_SESSION['user_id'];
$slot_id     = (int)$_GET['slot_id'];
$location_id = (int)$_GET['location_id'];
$vehicle_id  = (int)$_GET['vehicle_id'];
$hours       = max(1, min(12, (int)$_GET['hours']));

date_default_timezone_set('Asia/Kolkata');
$start_time  = date('Y-m-d H:i:s', strtotime($_GET['start_time']));
$end_time    = date('Y-m-d H:i:s', strtotime($start_time) + ($hours * 3600));

$loc  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM locations WHERE id=$location_id"));
$slot = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM slots WHERE id=$slot_id"));
$rate = isset($loc['rate_per_hour']) && $loc['rate_per_hour'] > 0 ? (int)$loc['rate_per_hour'] : 20;
$amount = $hours * $rate;
$ref = "BK" . strtoupper(substr(md5(uniqid()), 0, 8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment - Smart Parking</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">
<?php $active_page = 'book'; include "../includes/user_sidebar.php"; ?>

<div class="main-content">
    <div class="topbar"><h1>💳 Complete Payment</h1></div>

    <!-- Step Indicator -->
    <div class="flow-steps">
        <div class="flow-step done"><div class="step-num">✓</div><div class="step-label">Select Location</div></div>
        <div class="flow-connector done"></div>
        <div class="flow-step done"><div class="step-num">✓</div><div class="step-label">Choose Slot</div></div>
        <div class="flow-connector done"></div>
        <div class="flow-step done"><div class="step-num">✓</div><div class="step-label">Set Duration</div></div>
        <div class="flow-connector done"></div>
        <div class="flow-step active"><div class="step-num">4</div><div class="step-label">Payment</div></div>
    </div>

    <div class="payment-card animate-in">
        <div class="payment-header">
            <h2>💳 Scan & Pay</h2>
            <p style="opacity:.8;margin-bottom:8px;">Booking Reference: <strong><?= $ref ?></strong></p>
            <div class="amount">₹<?= $amount ?></div>
            <p style="opacity:.7;font-size:13px;margin-top:4px;"><?= $hours ?> hour<?= $hours>1?'s':'' ?> × ₹<?= $rate ?>/hr</p>
        </div>

        <div class="payment-body">
            <!-- Booking Summary -->
            <div style="background:#f5f7ff;border-radius:8px;padding:14px;margin-bottom:20px;font-size:14px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span style="color:#666;">Location</span>
                    <strong><?= htmlspecialchars($loc['location_name'] ?? '') ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span style="color:#666;">Slot</span>
                    <strong><?= htmlspecialchars($slot['slot_number'] ?? '') ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span style="color:#666;">🟢 Entry</span>
                    <strong><?= date('d M Y, h:i A', strtotime($start_time)) ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#666;">🔴 Exit</span>
                    <strong><?= date('d M Y, h:i A', strtotime($end_time)) ?></strong>
                </div>
            </div>

            <!-- Razorpay Pay Button -->
            <div style="text-align:center;margin-top:10px;">
                <button id="rzp-btn" style="background:#2e7d32;color:#fff;border:none;padding:14px 40px;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;width:100%;">💳 Pay ₹<?= $amount ?> Now</button>
            </div>

            <!-- Hidden form for server-side verification -->
            <form id="payForm" action="payment_success.php" method="POST" style="display:none;">
                <input type="hidden" name="ref"                value="<?= $ref ?>">
                <input type="hidden" name="slot_id"            value="<?= $slot_id ?>">
                <input type="hidden" name="location_id"        value="<?= $location_id ?>">
                <input type="hidden" name="vehicle_id"         value="<?= $vehicle_id ?>">
                <input type="hidden" name="hours"              value="<?= $hours ?>">
                <input type="hidden" name="amount"             value="<?= $amount ?>">
                <input type="hidden" name="start_time"         value="<?= htmlspecialchars($start_time) ?>">
                <input type="hidden" name="razorpay_order_id"  id="rzp_order_id">
                <input type="hidden" name="razorpay_payment_id" id="rzp_payment_id">
                <input type="hidden" name="razorpay_signature" id="rzp_signature">
            </form>
        </div>
    </div>
</div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('rzp-btn').addEventListener('click', function () {
    this.disabled = true;
    this.textContent = 'Creating order...';

    const formData = new FormData();
    formData.append('amount', '<?= $amount ?>');

    fetch('create_order.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(order => {
            if (order.error) { alert('Error: ' + order.error); return; }

            const options = {
                key:         '<?= RAZORPAY_KEY_ID ?>',
                amount:      order.amount,
                currency:    'INR',
                name:        'Smart Parking',
                description: 'Slot Booking - <?= htmlspecialchars($slot['slot_number'] ?? '') ?>',
                order_id:    order.id,
                handler: function (response) {
                    document.getElementById('rzp_order_id').value   = response.razorpay_order_id;
                    document.getElementById('rzp_payment_id').value = response.razorpay_payment_id;
                    document.getElementById('rzp_signature').value  = response.razorpay_signature;
                    document.getElementById('payForm').submit();
                },
                prefill: { name: '', email: '', contact: '' },
                theme: { color: '#1a237e' }
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (resp) {
                alert('Payment failed: ' + resp.error.description);
                document.getElementById('rzp-btn').disabled = false;
                document.getElementById('rzp-btn').textContent = '💳 Pay ₹<?= $amount ?> Now';
            });
            rzp.open();
        })
        .catch(() => {
            alert('Could not connect to payment gateway. Please try again.');
            document.getElementById('rzp-btn').disabled = false;
            document.getElementById('rzp-btn').textContent = '💳 Pay ₹<?= $amount ?> Now';
        });
});
</script>
</body>
</html>
