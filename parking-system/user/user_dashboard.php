<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";
date_default_timezone_set('Asia/Kolkata');

$user_id = $_SESSION['user_id'];
$now     = time();

$total  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payments WHERE user_id=$user_id"))['t'];
$vcount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM vehicles WHERE user_id=$user_id"))['t'];
$bcount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM bookings WHERE user_id=$user_id"))['t'];

// This month vs last month spend
$this_month = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payments WHERE user_id=$user_id AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"))['t'];
$last_month = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM payments WHERE user_id=$user_id AND MONTH(created_at)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(NOW()-INTERVAL 1 MONTH)"))['t'];

// Active booking right now
$active_booking = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT b.*, l.location_name, s.slot_number
 FROM bookings b
 JOIN locations l ON b.location_id=l.id
 JOIN slots s ON b.slot_id=s.id
 WHERE b.user_id=$user_id AND b.status='booked'
 AND COALESCE(b.start_time,b.created_at) <= NOW()
 AND DATE_ADD(COALESCE(b.start_time,b.created_at), INTERVAL b.hours HOUR) > NOW()
 LIMIT 1"));

// Upcoming booking
$upcoming_booking = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT b.*, l.location_name, s.slot_number
 FROM bookings b
 JOIN locations l ON b.location_id=l.id
 JOIN slots s ON b.slot_id=s.id
 WHERE b.user_id=$user_id AND b.status='booked'
 AND COALESCE(b.start_time,b.created_at) > NOW()
 ORDER BY COALESCE(b.start_time,b.created_at) ASC LIMIT 1"));

// Monthly bookings for chart (last 6 months)
$monthly = mysqli_query($conn,
"SELECT DATE_FORMAT(COALESCE(start_time,created_at),'%b %Y') AS month,
        COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS revenue
 FROM bookings
 WHERE user_id=$user_id
 GROUP BY YEAR(COALESCE(start_time,created_at)), MONTH(COALESCE(start_time,created_at))
 ORDER BY YEAR(COALESCE(start_time,created_at)) ASC, MONTH(COALESCE(start_time,created_at)) ASC
 LIMIT 6");
$chart_labels = $chart_counts = $chart_revenue = [];
while ($r = mysqli_fetch_assoc($monthly)) {
    $chart_labels[]  = $r['month'];
    $chart_counts[]  = (int)$r['cnt'];
    $chart_revenue[] = (int)$r['revenue'];
}

$recent = mysqli_query($conn,
"SELECT b.*, l.location_name, s.slot_number FROM bookings b
 JOIN locations l ON b.location_id=l.id
 JOIN slots s ON b.slot_id=s.id
 WHERE b.user_id=$user_id ORDER BY b.id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Smart Parking</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">
<?php $active_page = 'dashboard'; include "../includes/user_sidebar.php"; ?>
<div class="main-content">
    <div class="topbar">
        <h1>&#128075; Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></h1>
        <div class="topbar-right">&#128197; <?= date('D, d M Y') ?></div>
    </div>

    <!-- Active Booking Widget -->
    <?php if ($active_booking):
        $raw_s   = !empty($active_booking['start_time']) ? $active_booking['start_time'] : $active_booking['created_at'];
        $s_ts    = strtotime($raw_s);
        $e_ts    = $s_ts + ((int)$active_booking['hours'] * 3600);
        $diff    = $e_ts - $now;
    ?>
    <div class="active-booking-widget">
        <div class="abw-left">
            <div class="abw-icon">&#127359;</div>
            <div>
                <div class="abw-title">You are currently parked!</div>
                <div class="abw-sub">&#128205; <?= htmlspecialchars($active_booking['location_name']) ?> &bull; Slot <strong><?= htmlspecialchars($active_booking['slot_number']) ?></strong></div>
                <div class="abw-sub">&#128308; Exit by <strong><?= date('h:i A', $e_ts) ?></strong></div>
            </div>
        </div>
        <div class="abw-right">
            <div class="abw-label">Time Remaining</div>
            <div class="abw-countdown time-remaining active" data-end="<?= $e_ts ?>">
                <?php $h=floor($diff/3600);$m=floor(($diff%3600)/60);$s=$diff%60;
                echo $h>0?$h.'h '.str_pad($m,2,'0',STR_PAD_LEFT).'m '.str_pad($s,2,'0',STR_PAD_LEFT).'s':($m>0?$m.'m '.str_pad($s,2,'0',STR_PAD_LEFT).'s':$s.'s'); ?>
            </div>
            <a href="booking_history.php" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;margin-top:8px;">View Details</a>
        </div>
    </div>
    <?php elseif ($upcoming_booking):
        $raw_s = !empty($upcoming_booking['start_time']) ? $upcoming_booking['start_time'] : $upcoming_booking['created_at'];
        $s_ts  = strtotime($raw_s);
        $diff  = $s_ts - $now;
    ?>
    <div class="active-booking-widget" style="background:linear-gradient(135deg,#2e7d32,#43a047);">
        <div class="abw-left">
            <div class="abw-icon">&#9203;</div>
            <div>
                <div class="abw-title">Upcoming Booking</div>
                <div class="abw-sub">&#128205; <?= htmlspecialchars($upcoming_booking['location_name']) ?> &bull; Slot <strong><?= htmlspecialchars($upcoming_booking['slot_number']) ?></strong></div>
                <div class="abw-sub">&#128994; Entry at <strong><?= date('h:i A, d M', $s_ts) ?></strong></div>
            </div>
        </div>
        <div class="abw-right">
            <div class="abw-label">Starts In</div>
            <div class="abw-countdown time-upcoming" data-start="<?= $s_ts ?>" data-end="<?= $s_ts + ($upcoming_booking['hours']*3600) ?>">
                <?php $h=floor($diff/3600);$m=floor(($diff%3600)/60);$s=$diff%60;
                echo $h>0?$h.'h '.str_pad($m,2,'0',STR_PAD_LEFT).'m '.str_pad($s,2,'0',STR_PAD_LEFT).'s':($m>0?$m.'m '.str_pad($s,2,'0',STR_PAD_LEFT).'s':$s.'s'); ?>
            </div>
            <a href="booking_history.php" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;margin-top:8px;">View Details</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">&#128176;</div>
            <div class="stat-info"><h3>Total Spent</h3><h2>&#8377;<?= number_format($total) ?></h2></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">&#128663;</div>
            <div class="stat-info"><h3>My Vehicles</h3><h2><?= $vcount ?></h2></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">&#128203;</div>
            <div class="stat-info"><h3>Total Bookings</h3><h2><?= $bcount ?></h2></div>
        </div>
        <div class="stat-card" style="border-left-color:<?= $this_month>=$last_month?'#2e7d32':'#c62828' ?>;">
            <div class="stat-icon" style="background:linear-gradient(135deg,<?= $this_month>=$last_month?'#2e7d32,#43a047':'#c62828,#e53935' ?>);">
                <?= $this_month>=$last_month?'&#128200;':'&#128201;' ?>
            </div>
            <div class="stat-info">
                <h3>This Month</h3>
                <h2>&#8377;<?= number_format($this_month) ?></h2>
                <p style="font-size:12px;color:#666;margin-top:2px;">
                    <?= $last_month>0 ? 'Last: &#8377;'.number_format($last_month) : 'First month!' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Spending Chart -->
    <?php if (!empty($chart_labels)): ?>
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h2>&#128202; My Booking Activity</h2></div>
        <div style="position:relative;height:220px;">
            <canvas id="userChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header"><h2>Quick Actions</h2></div>
        <div class="actions-grid">
            <a href="book_slot.php" class="action-card"><div class="ac-icon">&#127359;</div><div class="ac-label">Book a Slot</div></a>
            <a href="booking_history.php" class="action-card"><div class="ac-icon">&#128203;</div><div class="ac-label">My Bookings</div></a>
            <a href="add_vehicle.php" class="action-card"><div class="ac-icon">&#128663;</div><div class="ac-label">My Vehicles</div></a>
            <a href="profile.php" class="action-card"><div class="ac-icon">&#128100;</div><div class="ac-label">My Profile</div></a>
            <a href="support.php" class="action-card"><div class="ac-icon">&#127911;</div><div class="ac-label">Support</div></a>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="card">
        <div class="card-header">
            <h2>Recent Bookings</h2>
            <a href="booking_history.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Location</th><th>Slot</th><th>Hours</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (mysqli_num_rows($recent) === 0): ?>
                    <tr><td colspan="6" style="text-align:center;color:#999;padding:24px;">No bookings yet. <a href="book_slot.php">Book your first slot!</a></td></tr>
                <?php else: ?>
                    <?php while ($row = mysqli_fetch_assoc($recent)):
                        $st = strtolower($row['status']??'booked');
                        $bc = $st==='booked'?'success':($st==='cancelled'?'danger':'warning');
                    ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td>&#128205; <?= htmlspecialchars($row['location_name']) ?></td>
                        <td><?= htmlspecialchars($row['slot_number']) ?></td>
                        <td><?= $row['hours'] ?> hr<?= $row['hours']>1?'s':'' ?></td>
                        <td><strong>&#8377;<?= $row['amount'] ?></strong></td>
                        <td><span class="badge badge-<?= $bc ?>"><?= ucfirst($st) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php if (!empty($chart_labels)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('userChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Bookings',
            data: <?= json_encode($chart_counts) ?>,
            backgroundColor: 'rgba(26,35,126,0.75)',
            borderColor: '#1a237e',
            borderWidth: 2,
            borderRadius: 6,
            yAxisID: 'y'
        },{
            label: 'Amount Spent (₹)',
            data: <?= json_encode($chart_revenue) ?>,
            type: 'line',
            borderColor: '#f9a825',
            backgroundColor: 'rgba(249,168,37,0.1)',
            borderWidth: 2,
            pointBackgroundColor: '#f9a825',
            pointRadius: 4,
            tension: 0.4,
            yAxisID: 'y2'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { usePointStyle: true, padding: 16 } },
            tooltip: {
                backgroundColor: 'rgba(10,10,31,.92)',
                titleColor: '#fff',
                bodyColor: 'rgba(255,255,255,.8)',
                padding: 10,
                cornerRadius: 8,
                callbacks: {
                    label: function(ctx) {
                        if (ctx.dataset.label === 'Bookings')
                            return ' ' + ctx.parsed.y + ' booking(s)';
                        return ' ₹' + ctx.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y:  {
                beginAtZero: true,
                ticks: { stepSize: 1, font: { size: 12 } },
                grid: { color: 'rgba(0,0,0,0.05)' },
                title: { display: true, text: 'Bookings', font: { size: 12 } }
            },
            y2: {
                beginAtZero: true,
                position: 'right',
                grid: { drawOnChartArea: false },
                ticks: {
                    font: { size: 12 },
                    callback: function(v) {
                        return '₹' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v);
                    }
                },
                title: { display: true, text: 'Amount (₹)', font: { size: 12 } }
            },
            x: { grid: { display: false }, ticks: { font: { size: 12 } } }
        }
    }
});
</script>
<?php endif; ?>

<script>
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
    document.querySelectorAll('.time-upcoming[data-start]').forEach(el=>{
        const ds=parseInt(el.dataset.start)-now;
        if(ds<=0){el.textContent='Starting now!';return;}
        el.textContent=fmt(ds);
    });
}
tick();setInterval(tick,1000);
</script>
</body>
</html>
