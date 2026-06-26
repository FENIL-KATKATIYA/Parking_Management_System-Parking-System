<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";

if (!isset($_GET['location_id'], $_GET['vehicle_id'])) {
    header("Location: book_slot.php"); exit;
}

$location_id = (int)$_GET['location_id'];
$vehicle_id  = (int)$_GET['vehicle_id'];
$user_id     = $_SESSION['user_id'];

$loc     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM locations WHERE id=$location_id"));
if (!$loc) { header("Location: book_slot.php"); exit; }

$vehicle = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM vehicles WHERE id=$vehicle_id AND user_id=$user_id"));
if (!$vehicle) { header("Location: book_slot.php"); exit; }

$rate = isset($loc['rate_per_hour']) && $loc['rate_per_hour'] > 0 ? (int)$loc['rate_per_hour'] : 20;

date_default_timezone_set('Asia/Kolkata');
$now_rounded = ceil(time() / 900) * 900;
$min_dt      = date('Y-m-d\TH:i', $now_rounded);
$max_dt      = date('Y-m-d\TH:i', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Select Time - Smart Parking</title>
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
    <div class="topbar"><h1>&#9201; Select Entry Time &amp; Duration</h1></div>

    <!-- Step Indicator -->
    <div class="flow-steps">
        <div class="flow-step done"><div class="step-num">&#10003;</div><div class="step-label">Location</div></div>
        <div class="flow-connector done"></div>
        <div class="flow-step active"><div class="step-num">2</div><div class="step-label">Set Time</div></div>
        <div class="flow-connector"></div>
        <div class="flow-step"><div class="step-num">3</div><div class="step-label">Choose Slot</div></div>
        <div class="flow-connector"></div>
        <div class="flow-step"><div class="step-num">4</div><div class="step-label">Payment</div></div>
    </div>

    <div class="card" style="max-width:500px;margin:0 auto;">
        <div class="card-header">
            <h2>&#128336; When do you want to park?</h2>
            <a href="book_slot.php" class="btn btn-outline btn-sm">&larr; Back</a>
        </div>

        <!-- Location Info -->
        <div style="background:#f5f7ff;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:14px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span style="color:#666;">Location</span>
                <strong>&#128205; <?= htmlspecialchars($loc['location_name']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;">
                <span style="color:#666;">Rate</span>
                <strong>&#8377;<?= $rate ?>/hour</strong>
            </div>
        </div>

        <form action="select_slot.php" method="GET" onsubmit="return validateForm()">
            <input type="hidden" name="location_id" value="<?= $location_id ?>">
            <input type="hidden" name="vehicle_id"  value="<?= $vehicle_id ?>">

            <!-- Entry Time -->
            <div class="form-group">
                <label>&#128336; Entry Time <span style="color:#c62828;font-size:12px;">*</span></label>
                <input type="datetime-local" id="start_time" name="start_time"
                       class="form-control" required
                       min="<?= $min_dt ?>" max="<?= $max_dt ?>"
                       value="<?= $min_dt ?>"
                       oninput="updateSummary()" onchange="updateSummary()">
                <div id="time-warn" class="alert alert-danger" style="display:none;margin-top:8px;padding:8px 12px;font-size:13px;">
                    &#9888; Entry time cannot be in the past.
                </div>
            </div>

            <!-- Duration -->
            <div class="form-group">
                <label>&#9201; Duration (Hours)</label>
                <select id="hours" name="hours" class="form-control" onchange="updateSummary()" required>
                    <option value="">&#8212; Select duration &#8212;</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?> Hour<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Live Summary -->
            <div id="summary-box" style="display:none;background:linear-gradient(135deg,#1a237e,#3949ab);border-radius:10px;padding:20px;margin-bottom:20px;color:#fff;">
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;font-size:13px;opacity:.85;">
                    <span>&#128994; Entry</span>
                    <strong id="sum-entry">&#8212;</strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:14px;font-size:13px;opacity:.85;">
                    <span>&#128308; Exit</span>
                    <strong id="sum-exit">&#8212;</strong>
                </div>
                <div style="border-top:1px solid rgba(255,255,255,.2);padding-top:14px;text-align:center;">
                    <div style="font-size:12px;opacity:.7;margin-bottom:4px;">Estimated Amount</div>
                    <div id="price" style="color:#ffd600;font-size:36px;font-weight:800;">&#8377;0</div>
                </div>
            </div>

            <button type="submit" id="submit-btn" class="btn btn-primary btn-block" disabled>
                View Available Slots &rarr;
            </button>
        </form>
    </div>
</div>
</div>

<script>
const rate   = <?= $rate ?>;
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function formatDT(dtStr) {
    const [dp, tp] = dtStr.split('T');
    const [y, m, d] = dp.split('-').map(Number);
    const [hh, mm]  = tp.split(':').map(Number);
    const ampm = hh >= 12 ? 'PM' : 'AM';
    const h12  = hh % 12 || 12;
    return d + ' ' + MONTHS[m-1] + ' ' + y + ', ' + h12 + ':' + String(mm).padStart(2,'0') + ' ' + ampm;
}

function addHours(dtStr, hours) {
    const [dp, tp] = dtStr.split('T');
    const [y, mo, d] = dp.split('-').map(Number);
    const [hh, mm]   = tp.split(':').map(Number);
    const ms = new Date(y, mo-1, d, hh, mm).getTime() + hours * 3600000;
    const dt = new Date(ms);
    return dt.getFullYear() + '-' +
        String(dt.getMonth()+1).padStart(2,'0') + '-' +
        String(dt.getDate()).padStart(2,'0') + 'T' +
        String(dt.getHours()).padStart(2,'0') + ':' +
        String(dt.getMinutes()).padStart(2,'0');
}

function updateSummary() {
    const st    = document.getElementById('start_time').value;
    const hours = parseInt(document.getElementById('hours').value) || 0;
    const box   = document.getElementById('summary-box');
    const btn   = document.getElementById('submit-btn');
    const warn  = document.getElementById('time-warn');

    if (!st) { box.style.display = 'none'; btn.disabled = true; return; }

    const [dp, tp] = st.split('T');
    const [y,mo,d] = dp.split('-').map(Number);
    const [hh,mm]  = tp.split(':').map(Number);
    const selMs    = new Date(y, mo-1, d, hh, mm).getTime();

    if (selMs < Date.now() - 60000) {
        warn.style.display = 'flex';
        box.style.display  = 'none';
        btn.disabled = true;
        return;
    }
    warn.style.display = 'none';

    if (!hours) { box.style.display = 'none'; btn.disabled = true; return; }

    document.getElementById('sum-entry').textContent = formatDT(st);
    document.getElementById('sum-exit').textContent  = formatDT(addHours(st, hours));
    document.getElementById('price').textContent     = '\u20b9' + (hours * rate);
    box.style.display = 'block';
    btn.disabled = false;
}

function validateForm() {
    const st = document.getElementById('start_time').value;
    const h  = document.getElementById('hours').value;
    if (!st || !h) { alert('Please select entry time and duration.'); return false; }
    return true;
}

updateSummary();
</script>
</body>
</html>
