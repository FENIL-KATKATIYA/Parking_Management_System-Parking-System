<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";
require_once "../includes/razorpay_config.php";

if (!isset($_POST['slot_id'], $_POST['location_id'], $_POST['vehicle_id'], $_POST['hours'], $_POST['amount'], $_POST['start_time'])) {
    header("Location: user_dashboard.php"); exit;
}

// Verify Razorpay signature
$order_id   = $_POST['razorpay_order_id']  ?? '';
$payment_id = $_POST['razorpay_payment_id'] ?? '';
$signature  = $_POST['razorpay_signature']  ?? '';

if (empty($order_id) || empty($payment_id) || empty($signature)) {
    header("Location: payment.php?err=missing_payment"); exit;
}

$expected = hash_hmac('sha256', $order_id . '|' . $payment_id, RAZORPAY_KEY_SECRET);
if (!hash_equals($expected, $signature)) {
    header("Location: payment.php?err=invalid_signature"); exit;
}

date_default_timezone_set('Asia/Kolkata');

$user_id     = (int)$_SESSION['user_id'];
$slot_id     = (int)$_POST['slot_id'];
$location_id = (int)$_POST['location_id'];
$vehicle_id  = (int)$_POST['vehicle_id'];
$hours       = max(1, min(12, (int)$_POST['hours']));
$amount      = (int)$_POST['amount'];
$start_time  = date('Y-m-d H:i:s', strtotime($_POST['start_time']));
$end_time    = date('Y-m-d H:i:s', strtotime($start_time) + ($hours * 3600));
$ref         = preg_replace('/[^A-Z0-9]/', '', strtoupper($_POST['ref'] ?? ''));

if (empty($ref)) $ref = "BK" . strtoupper(substr(md5(uniqid()), 0, 8));

// Auto-release any expired slot bookings before processing
mysqli_query($conn,
    "UPDATE slots s
     SET s.status = 'available'
     WHERE s.status = 'booked'
     AND NOT EXISTS (
         SELECT 1 FROM bookings b
         WHERE b.slot_id = s.id AND b.status = 'booked'
         AND DATE_ADD(COALESCE(b.start_time, b.created_at), INTERVAL b.hours HOUR) > NOW()
     )");

// Guard: slot must not be under maintenance
$slot_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM slots WHERE id=$slot_id"));
if (!$slot_check || strtolower($slot_check['status']) === 'maintenance') {
    header("Location: book_slot.php?err=maintenance"); exit;
}

// Guard: no overlapping active booking for this slot (time-aware)
$conflict = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT id FROM bookings
     WHERE slot_id=$slot_id AND status='booked'
     AND COALESCE(start_time, created_at) < '$end_time'
     AND DATE_ADD(COALESCE(start_time, created_at), INTERVAL hours HOUR) > '$start_time'
     LIMIT 1"));
if ($conflict) {
    header("Location: select_slot.php?location_id=$location_id&vehicle_id=$vehicle_id&err=conflict"); exit;
}

// 1. Insert Payment
$rzp_payment_id = $_POST['razorpay_payment_id'];
$p_stmt = $conn->prepare("INSERT INTO payments (user_id, amount, method, reference_id, payment_type) VALUES (?,?,'Razorpay',?,'booking')");
$p_stmt->bind_param("iis", $user_id, $amount, $rzp_payment_id);
$p_stmt->execute();
$payment_id = $conn->insert_id;

// 2. Insert Booking
$b_stmt = $conn->prepare(
    "INSERT INTO bookings (user_id, vehicle_id, location_id, slot_id, hours, start_time, amount, payment_id, status)
     VALUES (?,?,?,?,?,?,?,?,'booked')"
);
$b_stmt->bind_param("iiiiisii", $user_id, $vehicle_id, $location_id, $slot_id, $hours, $start_time, $amount, $payment_id);
$b_stmt->execute();
$booking_id = $conn->insert_id;

// 3. Link payment back to booking_id
$conn->query("UPDATE payments SET booking_id=$booking_id WHERE id=$payment_id");

// 4. Only mark slot as physically 'booked' if the booking starts NOW (within 15 min).
//    Future bookings keep the slot 'available' so others can book non-overlapping windows.
$starts_soon = (strtotime($start_time) - time()) <= 900; // within 15 minutes
if ($starts_soon) {
    mysqli_query($conn, "UPDATE slots SET status='booked' WHERE id=$slot_id");
}

// 5. Redirect to summary
header("Location: booking_summary.php?booking_id=$booking_id");
exit;
?>
