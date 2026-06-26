<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";
require_once "../includes/razorpay_config.php";

header('Content-Type: application/json');

$amount = (int)($_POST['amount'] ?? 0);
if ($amount <= 0) { echo json_encode(['error' => 'Invalid amount']); exit; }

// Razorpay expects amount in paise (1 INR = 100 paise)
$payload = json_encode([
    'amount'   => $amount * 100,
    'currency' => 'INR',
    'receipt'  => 'rcpt_' . uniqid(),
]);

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
