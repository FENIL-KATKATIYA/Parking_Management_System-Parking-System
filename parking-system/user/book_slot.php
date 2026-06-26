<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";

$user_id   = $_SESSION['user_id'];
$locations = mysqli_query($conn,
    "SELECT l.*, COUNT(s.id) AS available_slots
     FROM locations l
     INNER JOIN slots s ON s.location_id = l.id AND s.status = 'available'
     GROUP BY l.id
     HAVING available_slots > 0
     ORDER BY l.location_name");
$vehicles  = mysqli_query($conn, "SELECT * FROM vehicles WHERE user_id=$user_id ORDER BY id DESC");
$has_vehicles = mysqli_num_rows($vehicles) > 0;
// Re-fetch for the loop
$vehicles = mysqli_query($conn, "SELECT * FROM vehicles WHERE user_id=$user_id ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Book Slot - Smart Parking</title>
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
    <div class="topbar">
        <h1>🅿️ Book a Parking Slot</h1>
    </div>

    <!-- Step Indicator -->
    <div class="flow-steps">
        <div class="flow-step active"><div class="step-num">1</div><div class="step-label">Select Location</div></div>
        <div class="flow-connector"></div>
        <div class="flow-step"><div class="step-num">2</div><div class="step-label">Choose Slot</div></div>
        <div class="flow-connector"></div>
        <div class="flow-step"><div class="step-num">3</div><div class="step-label">Set Duration</div></div>
        <div class="flow-connector"></div>
        <div class="flow-step"><div class="step-num">4</div><div class="step-label">Payment</div></div>
    </div>

    <div class="card" style="max-width:560px;margin:0 auto;">
        <div class="card-header"><h2>Step 1: Select Location & Vehicle</h2></div>

        <?php if (!$has_vehicles): ?>
            <div class="alert alert-info">
                &#8505;&#65039; You have no vehicles registered. <a href="add_vehicle.php" style="font-weight:700;">Add a vehicle first</a> to book a slot.
            </div>
        <?php elseif (mysqli_num_rows($locations) === 0): ?>
            <div class="alert alert-info">
                &#8505;&#65039; No parking locations with available slots right now. Please check back later.
            </div>
        <?php else: ?>
        <?php
        // Build location data for JS map preview
        $loc_data = [];
        $loc_res2 = mysqli_query($conn,
            "SELECT l.id, l.location_name, l.rate_per_hour,
                    COUNT(s.id) AS available_slots
             FROM locations l
             INNER JOIN slots s ON s.location_id=l.id AND s.status='available'
             GROUP BY l.id HAVING available_slots > 0 ORDER BY l.location_name");
        while ($ld = mysqli_fetch_assoc($loc_res2)) $loc_data[$ld['id']] = $ld;
        ?>
        <form action="select_time.php" method="GET">
            <div class="form-group">
                <label>Parking Location</label>
                <select name="location_id" id="locationSelect" class="form-control" required onchange="showMap(this)">
                    <option value="">— Select a location —</option>
                    <?php while ($l = mysqli_fetch_assoc($locations)): ?>
                        <option value="<?= $l['id'] ?>" data-name="<?= htmlspecialchars($l['location_name']) ?>"
                                data-rate="<?= $l['rate_per_hour'] ?>" data-slots="<?= $l['available_slots'] ?>">
                            <?= htmlspecialchars($l['location_name']) ?> (<?= $l['available_slots'] ?> slot<?= $l['available_slots'] != 1 ? 's' : '' ?> free)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Map Preview -->
            <div id="map-preview" style="display:none;margin-bottom:18px;">
                <div class="map-info-bar" id="mapInfoBar"></div>
                <div style="border-radius:10px;overflow:hidden;border:2px solid var(--border);">
                    <iframe id="mapFrame" width="100%" height="220" frameborder="0"
                            style="border:0;display:block;"
                            allowfullscreen loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            src=""></iframe>
                </div>
            </div>

            <div class="form-group">
                <label>Your Vehicle</label>
                <select name="vehicle_id" class="form-control" required>
                    <option value="">— Select a vehicle —</option>
                    <?php while ($v = mysqli_fetch_assoc($vehicles)): ?>
                        <option value="<?= $v['id'] ?>">
                            <?= htmlspecialchars($v['vehicle_number']) ?> (<?= htmlspecialchars($v['type'] ?? $v['vehicle_type'] ?? '') ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Continue &rarr;</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</div>
<script>
function showMap(sel) {
    const opt = sel.options[sel.selectedIndex];
    const preview = document.getElementById('map-preview');
    if (!opt.value) { preview.style.display = 'none'; return; }
    const name  = opt.dataset.name;
    const rate  = opt.dataset.rate;
    const slots = opt.dataset.slots;
    document.getElementById('mapInfoBar').innerHTML =
        '<span>&#128205; <strong>' + name + '</strong></span>' +
        '<span>&#8377;' + rate + '/hr</span>' +
        '<span style="color:#2e7d32;">&#128994; ' + slots + ' slot(s) free</span>';
    document.getElementById('mapFrame').src =
        'https://maps.google.com/maps?q=' + encodeURIComponent(name) + '&output=embed';
    preview.style.display = 'block';
}
</script>
</body>
</html>
