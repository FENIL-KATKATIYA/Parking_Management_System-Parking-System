<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";

if (!isset($_GET['location_id'], $_GET['vehicle_id'], $_GET['start_time'], $_GET['hours'])) {
    header("Location: book_slot.php"); exit;
}

$location_id = (int)$_GET['location_id'];
$vehicle_id  = (int)$_GET['vehicle_id'];
$user_id     = $_SESSION['user_id'];

date_default_timezone_set('Asia/Kolkata');
$hours      = max(1, min(12, (int)$_GET['hours']));
$start_time = date('Y-m-d H:i:s', strtotime($_GET['start_time']));
$end_time   = date('Y-m-d H:i:s', strtotime($start_time) + ($hours * 3600));

$loc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM locations WHERE id=$location_id"));
if (!$loc) { header("Location: book_slot.php"); exit; }

$vehicle = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM vehicles WHERE id=$vehicle_id AND user_id=$user_id"));
if (!$vehicle) { header("Location: book_slot.php"); exit; }

$vtype = strtolower($vehicle['type'] ?? 'car');

$slot_type_needed = 'any';
if (in_array($vtype, ['car','suv','sedan','hatchback','electric car'])) $slot_type_needed = 'car';
elseif (in_array($vtype, ['bike','scooter','electric bike','bicycle','auto rickshaw'])) $slot_type_needed = 'bike';
elseif (in_array($vtype, ['truck','bus','van'])) $slot_type_needed = 'heavy';

$type_info = [
    'car'   => ['label'=>'Car Slot',    'icon'=>'🚗', 'color'=>'#1565c0', 'bg'=>'#e3f2fd'],
    'bike'  => ['label'=>'Bike Slot',   'icon'=>'🏍️', 'color'=>'#2e7d32', 'bg'=>'#e8f5e9'],
    'heavy' => ['label'=>'Heavy Slot',  'icon'=>'🚛', 'color'=>'#e65100', 'bg'=>'#fff8e1'],
    'any'   => ['label'=>'Any Vehicle', 'icon'=>'🅿️', 'color'=>'#6a1b9a', 'bg'=>'#f3e5f5'],
];

$rate = isset($loc['rate_per_hour']) && $loc['rate_per_hour'] > 0 ? (int)$loc['rate_per_hour'] : 20;

// Fetch all slots for this location
$slots_res = mysqli_query($conn, "SELECT * FROM slots WHERE location_id=$location_id ORDER BY slot_type, slot_number");
$all_slots = [];
$compatible_count = 0;
while ($s = mysqli_fetch_assoc($slots_res)) {
    $stype = $s['slot_type'] ?? 'any';
    $s['_compatible'] = ($stype === 'any' || $stype === $slot_type_needed);
    if ($s['_compatible']) $compatible_count++;
    $all_slots[] = $s;
}

// Format display times
$display_start = date('d M Y, h:i A', strtotime($start_time));
$display_end   = date('d M Y, h:i A', strtotime($end_time));
$amount        = $hours * $rate;

// Sanitise for SQL
$st_sql = mysqli_real_escape_string($conn, $start_time);
$et_sql = mysqli_real_escape_string($conn, $end_time);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Select Slot - Smart Parking</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.slot-type-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 8px;
    margin: 16px 0 10px;
    width: fit-content;
}
.slot-box-incompatible {
    opacity: .35;
    cursor: not-allowed !important;
    position: relative;
}
.slot-box-incompatible::after {
    content: '✕';
    position: absolute;
    top: 2px; right: 4px;
    font-size: 10px;
    color: #c62828;
    font-weight: 900;
}
.vehicle-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #e8eaf6;
    color: #1a237e;
    border: 1px solid #c5cae9;
    border-radius: 20px;
    padding: 5px 14px;
    font-size: 13px;
    font-weight: 700;
}
.time-window-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg,#1a237e,#3949ab);
    color: #fff;
    border-radius: 10px;
    padding: 12px 18px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.time-window-bar .tw-sep { opacity: .5; }
.time-window-bar a { color: #ffd600; font-size: 12px; margin-left: auto; }
body.dark .vehicle-badge { background: #1a237e; color: #fff; border-color: #3949ab; }
body.dark .slot-type-header { filter: brightness(0.85); }
</style>
</head>
<body>
<div class="dash-wrapper">
<?php $active_page = 'book'; include "../includes/user_sidebar.php"; ?>

<div class="main-content">
    <div class="topbar"><h1>🅿️ Select Parking Slot</h1></div>

    <!-- Step Indicator -->
    <div class="flow-steps">
        <div class="flow-step done"><div class="step-num">✓</div><div class="step-label">Location</div></div>
        <div class="flow-connector done"></div>
        <div class="flow-step done"><div class="step-num">✓</div><div class="step-label">Set Time</div></div>
        <div class="flow-connector done"></div>
        <div class="flow-step active"><div class="step-num">3</div><div class="step-label">Choose Slot</div></div>
        <div class="flow-connector"></div>
        <div class="flow-step"><div class="step-num">4</div><div class="step-label">Payment</div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>📍 <?= htmlspecialchars($loc['location_name']) ?></h2>
            <a href="select_time.php?location_id=<?= $location_id ?>&vehicle_id=<?= $vehicle_id ?>" class="btn btn-outline btn-sm">← Change Time</a>
        </div>

        <?php if (isset($_GET['err'])): ?>
        <div class="alert alert-danger">
            <?php if ($_GET['err'] === 'maintenance'): ?>
                🔧 That slot is under maintenance. Please select a different slot.
            <?php elseif ($_GET['err'] === 'conflict'): ?>
                ⚠️ That slot was just booked by someone else for this time. Please select another.
            <?php elseif ($_GET['err'] === 'type'): ?>
                🚫 That slot type does not match your vehicle.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Selected Time Window -->
        <div class="time-window-bar">
            <span>🟢 <?= $display_start ?></span>
            <span class="tw-sep">→</span>
            <span>🔴 <?= $display_end ?></span>
            <span class="tw-sep">|</span>
            <span><?= $hours ?> hr<?= $hours > 1 ? 's' : '' ?> &nbsp;·&nbsp; ₹<?= $amount ?></span>
            <a href="select_time.php?location_id=<?= $location_id ?>&vehicle_id=<?= $vehicle_id ?>">✏️ Change</a>
        </div>

        <!-- Vehicle Info -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
            <span style="font-size:13px;color:var(--text-muted);font-weight:600;">Your Vehicle:</span>
            <span class="vehicle-badge">
                <?php
                $vicons = ['car'=>'🚗','suv'=>'🚙','sedan'=>'🚗','hatchback'=>'🚗','electric car'=>'⚡',
                           'bike'=>'🏍️','scooter'=>'🛵','electric bike'=>'⚡','bicycle'=>'🚲','auto rickshaw'=>'🛺',
                           'truck'=>'🚛','bus'=>'🚌','van'=>'🚐','other'=>'🚗'];
                echo ($vicons[$vtype] ?? '🚗') . ' ' . htmlspecialchars($vehicle['vehicle_number']) . ' — ' . htmlspecialchars($vehicle['type']);
                ?>
            </span>
        </div>

        <?php if ($compatible_count === 0): ?>
        <div class="alert alert-warning">
            ⚠️ No compatible slots for your vehicle type at this location.
        </div>
        <?php endif; ?>

        <!-- Legend -->
        <div class="slot-legend" style="flex-wrap:wrap;">
            <div class="legend-item"><div class="legend-dot available"></div> Available for your time</div>
            <div class="legend-item"><div class="legend-dot booked"></div> Booked during your time</div>
            <div class="legend-item"><div class="legend-dot maintenance"></div> Maintenance</div>
            <div class="legend-item"><div class="legend-dot selected"></div> Selected</div>
            <div class="legend-item">
                <div style="width:16px;height:16px;border-radius:4px;background:#eee;border:2px solid #ccc;opacity:.4;"></div>
                Incompatible
            </div>
        </div>

        <!-- Slots grouped by type -->
        <div style="padding:10px 0 20px;">
        <?php
        $grouped    = [];
        foreach ($all_slots as $s) {
            $grouped[$s['slot_type'] ?? 'any'][] = $s;
        }
        $type_order = ['car','bike','heavy','any'];

        foreach ($type_order as $stype):
            if (empty($grouped[$stype])) continue;
            $ti = $type_info[$stype];
        ?>
        <div class="slot-type-header" style="background:<?= $ti['bg'] ?>;color:<?= $ti['color'] ?>;">
            <?= $ti['icon'] ?> <?= $ti['label'] ?> Slots
            <?php if ($stype !== 'any' && $stype === $slot_type_needed): ?>
                <span style="font-size:11px;background:<?= $ti['color'] ?>;color:#fff;padding:2px 8px;border-radius:10px;margin-left:4px;">✓ Your Type</span>
            <?php elseif ($stype === 'any'): ?>
                <span style="font-size:11px;background:#6a1b9a;color:#fff;padding:2px 8px;border-radius:10px;margin-left:4px;">✓ Compatible</span>
            <?php else: ?>
                <span style="font-size:11px;background:#999;color:#fff;padding:2px 8px;border-radius:10px;margin-left:4px;">✕ Not for your vehicle</span>
            <?php endif; ?>
        </div>
        <div style="text-align:center;">
        <?php foreach ($grouped[$stype] as $s):
            $sid           = (int)$s['id'];
            $status        = strtolower($s['status']);
            $isMaintenance = $status === 'maintenance';

            // Check if any booking overlaps the user's requested time window
            $conflict = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id FROM bookings
                 WHERE slot_id=$sid AND status='booked'
                 AND COALESCE(start_time, created_at) < '$et_sql'
                 AND DATE_ADD(COALESCE(start_time, created_at), INTERVAL hours HOUR) > '$st_sql'
                 LIMIT 1"));

            $isBooked       = !$isMaintenance && $conflict;
            $isUnavailable  = $isMaintenance || $isBooked;
            $isIncompatible = !$s['_compatible'];
            $boxClass       = $isMaintenance ? 'maintenance' : ($isBooked ? 'booked' : 'available');
            if ($isIncompatible) $boxClass .= ' slot-box-incompatible';

            $titleText = $isMaintenance
                ? '🔧 Under Maintenance'
                : ($isBooked
                    ? '🔴 Booked during your time'
                    : ($isIncompatible ? '✕ Not for your vehicle' : '✓ Available — click to select'));
        ?>
            <div id="slot-<?= $sid ?>"
                 class="slot-box <?= $boxClass ?>"
                 data-id="<?= $sid ?>"
                 data-compatible="<?= $isIncompatible ? '0' : '1' ?>"
                 onclick="selectSlot(<?= $sid ?>, <?= $isUnavailable?'true':'false' ?>, '<?= $isMaintenance?'maintenance':'booked' ?>', <?= $isIncompatible?'true':'false' ?>)"
                 title="<?= htmlspecialchars($titleText) ?>">
                <?= htmlspecialchars($s['slot_number']) ?>
                <?php if ($isMaintenance): ?><div style="font-size:9px;">🔧</div><?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <?php if (empty($all_slots)): ?>
            <div class="alert alert-info" style="margin:20px auto;max-width:400px;">
                No slots found for this location.
            </div>
        <?php endif; ?>
        </div>

        <div id="selection-info" style="text-align:center;margin-bottom:16px;display:none;">
            <span class="badge badge-success" style="font-size:14px;padding:8px 16px;">
                ✅ Slot <span id="selected-label"></span> selected
            </span>
        </div>

        <form action="payment.php" method="GET" onsubmit="return validateSlot()">
            <input type="hidden" name="location_id" value="<?= $location_id ?>">
            <input type="hidden" name="vehicle_id"  value="<?= $vehicle_id ?>">
            <input type="hidden" name="start_time"  value="<?= htmlspecialchars($_GET['start_time']) ?>">
            <input type="hidden" name="hours"       value="<?= $hours ?>">
            <input type="hidden" name="slot_id" id="selectedSlot" value="">
            <div style="max-width:300px;margin:0 auto;">
                <button type="submit" class="btn btn-primary btn-block">Continue to Payment →</button>
            </div>
        </form>
    </div>
</div>
</div>

<script>
let selectedSlotId = null;

function selectSlot(slotId, isUnavailable, reason, isIncompatible) {
    if (isIncompatible) {
        alert('🚫 This slot is not compatible with your vehicle type.');
        return;
    }
    if (isUnavailable) {
        const msg = reason === 'maintenance'
            ? '🔧 This slot is under maintenance and cannot be booked.'
            : '🔴 This slot is already booked during your selected time. Please choose another.';
        alert(msg); return;
    }
    if (selectedSlotId !== null) {
        const prev = document.getElementById('slot-' + selectedSlotId);
        if (prev) { prev.classList.remove('selected'); prev.classList.add('available'); }
    }
    selectedSlotId = slotId;
    const el = document.getElementById('slot-' + slotId);
    el.classList.remove('available');
    el.classList.add('selected');
    document.getElementById('selectedSlot').value = slotId;
    document.getElementById('selected-label').textContent = el.textContent.trim();
    document.getElementById('selection-info').style.display = 'block';
}

function validateSlot() {
    if (!document.getElementById('selectedSlot').value) {
        alert('Please select a parking slot first.');
        return false;
    }
    return true;
}
</script>
</body>
</html>
