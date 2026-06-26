<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";
require_once "../includes/razorpay_config.php";
date_default_timezone_set('Asia/Kolkata');

$user_id = (int)$_SESSION['user_id'];

if (!isset($_POST['booking_id'], $_POST['extra_hours'], $_POST['extra_amount'],
           $_POST['razorpay_order_id'], $_POST['razorpay_payment_id'], $_POST['razorpay_signature'])) {
    header("Location: booking_history.php"); exit;
}

$bid          = (int)$_POST['booking_id'];
$extra_hours  = max(1, min(6, (int)$_POST['extra_hours']));
$extra_amount = (int)$_POST['extra_amount'];
$order_id     = $_POST['razorpay_order_id'];
$payment_id   = $_POST['razorpay_payment_id'];
$signature    = $_POST['razorpay_signature'];

// Verify Razorpay signature
$expected = hash_hmac('sha256', $order_id . '|' . $payment_id, RAZORPAY_KEY_SECRET);
if (!hash_equals($expected, $signature)) {
    header("Location: extend_booking.php?id=$bid&err=invalid_signature"); exit;
}

// Fetch booking
$booking = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM bookings WHERE id=$bid AND user_id=$user_id AND status='booked' LIMIT 1"));
if (!$booking) { header("Location: booking_history.php"); exit; }

// Insert extension payment record linked to booking
$p = $conn->prepare("INSERT INTO payments (user_id, booking_id, amount, method, reference_id, payment_type) VALUES (?,?,?,'Razorpay',?,'extension')");
$p->bind_param("iiis", $user_id, $bid, $extra_amount, $payment_id);
$p->execute();

// Update booking hours
$new_hours = $booking['hours'] + $extra_hours;
$u = $conn->prepare("UPDATE bookings SET hours=? WHERE id=? AND user_id=?");
$u->bind_param("iii", $new_hours, $bid, $user_id);
$u->execute();

$raw     = !empty($booking['start_time']) ? $booking['start_time'] : $booking['created_at'];
$new_end = strtotime($raw) + ($new_hours * 3600);

header("Location: booking_history.php?extended=1&new_exit=" . urlencode(date('h:i A, d M Y', $new_end)));
exit;
?>
