<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";
require_once "../includes/PHPMailer/PHPMailer.php";
require_once "../includes/PHPMailer/SMTP.php";
require_once "../includes/PHPMailer/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json');

if (!isset($_GET['booking_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Missing booking ID']); exit;
}

$bid     = (int)$_GET['booking_id'];
$user_id = $_SESSION['user_id'];

$data = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT b.*, l.location_name, s.slot_number, u.name, u.email
     FROM bookings b
     JOIN locations l ON b.location_id = l.id
     JOIN slots s ON b.slot_id = s.id
     JOIN users u ON b.user_id = u.id
     WHERE b.id=$bid AND b.user_id=$user_id AND b.status='booked' LIMIT 1"));

if (!$data || empty($data['email'])) {
    echo json_encode(['success' => false, 'msg' => 'Booking not found or no email.']); exit;
}

$st    = !empty($data['start_time']) ? $data['start_time'] : $data['created_at'];
$st_ts = strtotime($st);
$et_ts = $st_ts + ($data['hours'] * 3600);

$entry  = date('h:i A, d M Y', $st_ts);
$exit   = date('h:i A, d M Y', $et_ts);
$amount = $data['amount'];

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'your_gmail@gmail.com';   // <-- replace with your Gmail
    $mail->Password   = 'your_app_password';       // <-- replace with Gmail App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('your_gmail@gmail.com', 'Smart Parking');
    $mail->addAddress($data['email'], $data['name']);
    $mail->isHTML(true);
    $mail->Subject = "Parking Reminder - Booking #$bid | Smart Parking";
    $mail->Body    = "
        <div style='font-family:sans-serif;max-width:500px;margin:auto;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;'>
            <div style='background:#1a237e;color:#fff;padding:20px;text-align:center;'>
                <h2 style='margin:0;'>&#128336; Parking Reminder</h2>
                <p style='margin:4px 0 0;opacity:.8;font-size:13px;'>Smart Parking</p>
            </div>
            <div style='padding:24px;'>
                <p>Dear <strong>{$data['name']}</strong>,</p>
                <p>This is a reminder for your upcoming parking booking.</p>
                <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                    <tr style='background:#f5f5f5;'><td style='padding:8px 12px;color:#666;'>Booking ID</td><td style='padding:8px 12px;font-weight:600;'>#$bid</td></tr>
                    <tr><td style='padding:8px 12px;color:#666;'>Location</td><td style='padding:8px 12px;font-weight:600;'>{$data['location_name']}</td></tr>
                    <tr style='background:#f5f5f5;'><td style='padding:8px 12px;color:#666;'>Slot</td><td style='padding:8px 12px;font-weight:600;'>{$data['slot_number']}</td></tr>
                    <tr><td style='padding:8px 12px;color:#666;'>Entry Time</td><td style='padding:8px 12px;font-weight:600;'>$entry</td></tr>
                    <tr style='background:#f5f5f5;'><td style='padding:8px 12px;color:#666;'>Exit Time</td><td style='padding:8px 12px;font-weight:600;'>$exit</td></tr>
                    <tr><td style='padding:8px 12px;color:#666;'>Amount Paid</td><td style='padding:8px 12px;font-weight:600;color:#2e7d32;'>&#8377;$amount</td></tr>
                </table>
                <p style='margin-top:20px;color:#555;'>Please arrive on time. Thank you for using Smart Parking!</p>
            </div>
            <div style='background:#f9f9f9;text-align:center;padding:12px;font-size:12px;color:#999;border-top:1px solid #eee;'>
                Smart Parking Team
            </div>
        </div>";

    $mail->send();
    echo json_encode(['success' => true, 'msg' => "Reminder sent to {$data['email']}"]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'msg' => 'Failed: ' . $mail->ErrorInfo]);
}
