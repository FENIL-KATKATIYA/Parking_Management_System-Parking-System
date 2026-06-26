<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";
date_default_timezone_set('Asia/Kolkata');

$user_id = $_SESSION['user_id'];
$now     = time();
$message = $error = '';

// Extension success message
if (isset($_GET['extended']) && isset($_GET['new_exit'])) {
    $message = "Booking extended successfully! New exit time: " . htmlspecialchars($_GET['new_exit']);
}

// Cancel booking
if (isset($_GET['cancel'])) {
    $bid     = (int)$_GET['cancel'];
    $booking = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT slot_id, status FROM bookings WHERE id=$bid AND user_id=$user_id"));
    if (!$booking) {
        $error = "Booking not found.";
    } elseif ($booking['status'] !== 'booked') {
        $error = "Only active bookings can be cancelled.";
    } else {
        mysqli_query($conn, "UPDATE bookings SET status='cancelled' WHERE id=$bid AND user_id=$user_id");
        mysqli_query($conn, "UPDATE slots SET status='available' WHERE id={$booking['slot_id']}
            AND id NOT IN (SELECT slot_id FROM bookings WHERE status='booked')");
        $message = "Booking #$bid cancelled successfully.";
    }
}

$filter = $_GET['filter'] ?? 'all';
$where  = "b.user_id=$user_id";
if ($filter === 'upcoming')  $where .= " AND b.status='booked' AND COALESCE(b.start_time,b.created_at) > NOW()";
elseif ($filter === 'active')    $where .= " AND b.status='booked' AND COALESCE(b.start_time,b.created_at) <= NOW() AND DATE_ADD(COALESCE(b.start_time,b.created_at), INTERVAL b.hours HOUR) > NOW()";
elseif ($filter === 'completed') $where .= " AND b.status='completed'";
elseif ($filter === 'cancelled') $where .= " AND b.status='cancelled'";
elseif ($filter === 'booked')    $where .= " AND b.status='booked'";

$result = mysqli_query($conn,
"SELECT b.*, l.location_name, s.slot_number,
        COALESCE((SELECT SUM(p2.amount) FROM payments p2 WHERE p2.booking_id = b.id AND p2.payment_type='extension'), 0) AS ext_amount
 FROM bookings b
 JOIN locations l ON b.location_id=l.id
 JOIN slots s ON b.slot_id=s.id
 WHERE $where ORDER BY b.id DESC");

$filters = [
    'all'       => 'All',
    'active'    => 'Active Now',
    'upcoming'  => 'Upcoming',
    'booked'    => 'Booked',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking History - Smart Parking</title>
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
        <h1>&#128203; Booking History</h1>
        <a href="book_slot.php" class="btn btn-primary btn-sm">+ New Booking</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success">&#9989; <?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">&#9888; <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <?php foreach ($filters as $key => $label): ?>
        <a href="?filter=<?= $key ?>" class="filter-tab <?= $filter===$key?'active':'' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Bookings</h2>
            <span style="font-size:13px;color:#666;"><?= mysqli_num_rows($result) ?> record(s)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Location</th><th>Slot</th>
                        <th>&#128994; Entry</th><th>&#128308; Exit</th>
                        <th>Hrs</th><th>Amount</th><th>Status</th>
                        <th>Time Left</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($result) === 0): ?>
                    <tr><td colspan="10" style="text-align:center;color:#999;padding:32px;">
                        No bookings found. <a href="book_slot.php">Book your first slot!</a>
                    </td></tr>
                <?php else: ?>
                    <?php while ($row = mysqli_fetch_assoc($result)):
                        $st        = strtolower($row['status'] ?? 'booked');
                        $raw       = !empty($row['start_time']) ? $row['start_time'] : $row['created_at'];
                        $start_ts  = strtotime($raw);
                        $end_ts    = $start_ts + ((int)$row['hours'] * 3600);
                        $diff      = $end_ts - $now;
                        $is_active   = $st==='booked' && $now>=$start_ts && $diff>0;
                        $is_upcoming = $st==='booked' && $now<$start_ts;
                        $can_cancel  = $st==='booked';
                    ?>
                    <tr class="<?= $is_active?'row-active-booking':'' ?>">
                        <td>#<?= $row['id'] ?></td>
                        <td>&#128205; <?= htmlspecialchars($row['location_name']) ?></td>
                        <td><strong><?= htmlspecialchars($row['slot_number']) ?></strong></td>
                        <td style="font-size:13px;"><?= date('d M Y',$start_ts) ?><br><strong><?= date('h:i A',$start_ts) ?></strong></td>
                        <td style="font-size:13px;"><?= date('d M Y',$end_ts) ?><br><strong><?= date('h:i A',$end_ts) ?></strong></td>
                        <td><?= $row['hours'] ?>h</td>
                        <td>
                            <?php
                            $orig_amt = (int)$row['amount'];
                            $ext_amt  = (int)$row['ext_amount'];
                            $total    = $orig_amt + $ext_amt;
                            if ($ext_amt > 0): ?>
                                <div style="font-size:12px;color:#666;">Base: <strong>&#8377;<?= $orig_amt ?></strong></div>
                                <div style="font-size:12px;color:#e65100;">+Ext: <strong>&#8377;<?= $ext_amt ?></strong></div>
                                <div style="font-size:13px;color:#1a237e;font-weight:800;border-top:1px solid #e0e0e0;margin-top:3px;padding-top:3px;">&#8377;<?= $total ?></div>
                            <?php else: ?>
                                <strong>&#8377;<?= $orig_amt ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $badge = $st==='booked' ? 'success' : ($st==='cancelled'?'danger':'warning');
                            $label = $is_upcoming ? 'Upcoming' : ($is_active ? 'Active' : ucfirst($st));
                            ?>
                            <span class="badge badge-<?= $badge ?>"><?= $label ?></span>
                        </td>
                        <td>
                            <?php if ($is_active): ?>
                                <span class="time-remaining active" data-end="<?= $end_ts ?>">
                                    <?php $d=$diff; $h=floor($d/3600);$m=floor(($d%3600)/60);$s=$d%60;
                                    echo $h>0?$h.'h '.str_pad($m,2,'0',STR_PAD_LEFT).'m '.str_pad($s,2,'0',STR_PAD_LEFT).'s':($m>0?$m.'m '.str_pad($s,2,'0',STR_PAD_LEFT).'s':$s.'s'); ?>
                                </span>
                            <?php elseif ($is_upcoming): ?>
                                <?php $d=$start_ts-$now;$h=floor($d/3600);$m=floor(($d%3600)/60);$s=$d%60;
                                $cd=$h>0?$h.'h '.str_pad($m,2,'0',STR_PAD_LEFT).'m '.str_pad($s,2,'0',STR_PAD_LEFT).'s':($m>0?$m.'m '.str_pad($s,2,'0',STR_PAD_LEFT).'s':$s.'s'); ?>
                                <span class="time-upcoming" data-start="<?= $start_ts ?>" data-end="<?= $end_ts ?>"
                                      style="display:inline-block;font-size:12px;font-weight:700;color:#2e7d32;background:#e8f5e9;padding:3px 10px;border-radius:20px;border:1px solid #a5d6a7;">
                                    Starts in <?= $cd ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#bbb;font-size:12px;">&#8212;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($is_active): ?>
                                <a href="extend_booking.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm" title="Extend booking">&#9203; Extend</a>
                            <?php endif; ?>
                            <a href="receipt.php?id=<?= $row['id'] ?>" class="btn btn-outline btn-sm" title="View Receipt">&#128203; Receipt</a>
                            <?php if ($can_cancel): ?>
                                <a href="?cancel=<?= $row['id'] ?>&filter=<?= $filter ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Cancel booking #<?= $row['id'] ?>? This cannot be undone.')">
                                   &#10005; Cancel
                                </a>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<script>
function pad(n){return String(n).padStart(2,'0');}
function fmt(s){if(s<=0)return'0s';const h=Math.floor(s/3600),m=Math.floor((s%3600)/60),sc=s%60;return h>0?h+'h '+pad(m)+'m '+pad(sc)+'s':m>0?m+'m '+pad(sc)+'s':sc+'s';}
function tick(){
    const now=Math.floor(Date.now()/1000);
    document.querySelectorAll('.time-remaining.active[data-end]').forEach(el=>{
        const d=parseInt(el.dataset.end)-now;
        if(d<=0){el.outerHTML='<span class="badge badge-warning">Time Up</span>';return;}
        el.textContent=fmt(d);
        d<600?el.classList.add('expiring'):el.classList.remove('expiring');
    });
    document.querySelectorAll('.time-upcoming[data-start]').forEach(el=>{
        const ds=parseInt(el.dataset.start)-now;
        if(ds<=0){
            const de=parseInt(el.dataset.end)-now;
            if(de>0){el.className='time-remaining active';el.dataset.end=el.dataset.end;el.removeAttribute('data-start');el.style='';el.textContent=fmt(de);}
            else el.outerHTML='<span class="badge badge-warning">Time Up</span>';
            return;
        }
        el.textContent='Starts in '+fmt(ds);
    });
}
tick();setInterval(tick,1000);
</script>
</body>
</html>
