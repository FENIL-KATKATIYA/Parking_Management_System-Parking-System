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

$st    = !empty($data['start_time']) ? $data['start_time'] : $data['created_at'];
$st_ts = strtotime($st);
$et_ts = $st_ts + ($data['hours'] * 3600);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Downloading Receipt...</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
<p style="font-family:sans-serif;text-align:center;margin-top:60px;color:#555;">
    ⏳ Downloading your receipt...
</p>
<script>
window.addEventListener('load', function () {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'pt', format: 'a4' });

    const W = doc.internal.pageSize.getWidth();
    let y = 0;

    // Header background
    doc.setFillColor(26, 35, 126);
    doc.rect(0, 0, W, 100, 'F');

    // Header text
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(22);
    doc.setFont('helvetica', 'bold');
    doc.text('Smart Parking', W / 2, 38, { align: 'center' });

    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    doc.text('Official Parking Receipt', W / 2, 58, { align: 'center' });
    doc.text('Ref: <?= addslashes(htmlspecialchars($data['reference_id'])) ?>', W / 2, 76, { align: 'center' });

    y = 120;

    // Row helper
    function row(label, value) {
        doc.setDrawColor(220, 220, 220);
        doc.setFillColor(250, 250, 250);
        doc.rect(40, y - 14, W - 80, 24, 'FD');

        doc.setTextColor(100, 100, 100);
        doc.setFontSize(11);
        doc.setFont('helvetica', 'normal');
        doc.text(label, 52, y + 2);

        doc.setTextColor(30, 30, 30);
        doc.setFont('helvetica', 'bold');
        doc.text(String(value), W - 52, y + 2, { align: 'right' });

        y += 28;
    }

    row('Booking ID',      '#<?= $data['id'] ?>');
    row('Customer Name',   '<?= addslashes(htmlspecialchars($data['user_name'])) ?>');
    row('Email',           '<?= addslashes(htmlspecialchars($data['email'])) ?>');
    <?php if (!empty($data['phone'])): ?>
    row('Phone',           '<?= addslashes(htmlspecialchars($data['phone'])) ?>');
    <?php endif; ?>
    row('Vehicle',         '<?= addslashes(htmlspecialchars($data['vehicle_number'])) ?> (<?= addslashes(htmlspecialchars($data['vehicle_type'])) ?>)');
    row('Location',        '<?= addslashes(htmlspecialchars($data['location_name'])) ?>');
    row('Slot Number',     '<?= addslashes(htmlspecialchars($data['slot_number'])) ?>');
    row('Entry Time',      '<?= date('d M Y, h:i A', $st_ts) ?>');
    row('Exit Time',       '<?= date('d M Y, h:i A', $et_ts) ?>');
    row('Duration',        '<?= $data['hours'] ?> Hour<?= $data['hours'] > 1 ? 's' : '' ?>');
    row('Rate',            'Rs.<?= $data['rate_per_hour'] ?>/hr');
    row('Payment Method',  '<?= addslashes(htmlspecialchars($data['method'] ?? 'UPI')) ?>');
    row('Payment Date',    '<?= date('d M Y, h:i A', strtotime($data['paid_at'])) ?>');
    row('Status',          '<?= ucfirst(strtolower($data['status'])) ?>');

    // Total box
    y += 10;
    doc.setFillColor(235, 240, 255);
    doc.setDrawColor(26, 35, 126);
    doc.roundedRect(40, y, W - 80, 44, 6, 6, 'FD');

    doc.setTextColor(100, 100, 100);
    doc.setFontSize(12);
    doc.setFont('helvetica', 'normal');
    doc.text('Total Amount Paid', 56, y + 27);

    doc.setTextColor(26, 35, 126);
    doc.setFontSize(20);
    doc.setFont('helvetica', 'bold');
    doc.text('Rs.<?= number_format($data['amount']) ?>', W - 56, y + 27, { align: 'right' });

    // Footer
    y += 70;
    doc.setDrawColor(200, 200, 200);
    doc.setLineDashPattern([3, 3], 0);
    doc.line(40, y, W - 40, y);
    doc.setLineDashPattern([], 0);

    doc.setTextColor(160, 160, 160);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text('Thank you for using Smart Parking! Generated on <?= date('d M Y, h:i A') ?>', W / 2, y + 18, { align: 'center' });

    // Auto download
    doc.save('receipt_booking_<?= $bid ?>.pdf');

    // Redirect back after download with flag to prevent re-download
    setTimeout(function () {
        window.location.href = 'booking_summary.php?booking_id=<?= $bid ?>&downloaded=1';
    }, 1500);
});
</script>
</body>
</html>
