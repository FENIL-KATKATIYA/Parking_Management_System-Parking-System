<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";

$user_id = $_SESSION['user_id'];
$message = $error = '';

if (isset($_POST['add_vehicle'])) {
    $number = strtoupper(trim($_POST['vehicle_number']));
    $type   = $_POST['vehicle_type'];
    if (empty($number)) {
        $error = "Vehicle number is required.";
    } else {
        $chk = $conn->prepare("SELECT id FROM vehicles WHERE vehicle_number=? AND user_id=?");
        $chk->bind_param("si", $number, $user_id);
        $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = "This vehicle is already registered.";
        } else {
            $stmt = $conn->prepare("INSERT INTO vehicles (user_id, vehicle_number, type) VALUES (?,?,?)");
            $stmt->bind_param("iss", $user_id, $number, $type);
            $stmt->execute() ? $message = "Vehicle added successfully!" : $error = "Error: " . $conn->error;
        }
    }
}

if (isset($_GET['delete_vehicle'])) {
    $vid = (int)$_GET['delete_vehicle'];
    // Block if vehicle has an active booking
    $active = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM bookings WHERE vehicle_id=$vid AND user_id=$user_id AND status='booked' LIMIT 1"));
    if ($active) {
        $error = "Cannot delete — this vehicle has an active booking.";
    } else {
        mysqli_query($conn, "DELETE FROM vehicles WHERE id=$vid AND user_id=$user_id");
        header("Location: add_vehicle.php?msg=Vehicle+deleted."); exit;
    }
}
if (isset($_GET['msg'])) $message = $_GET['msg'];

$my_vehicles = mysqli_query($conn, "SELECT * FROM vehicles WHERE user_id=$user_id ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Vehicles - Smart Parking</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">
<?php $active_page = 'vehicles'; include "../includes/user_sidebar.php"; ?>
<div class="main-content">
    <div class="topbar"><h1>&#128663; My Vehicles</h1></div>

    <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:24px;align-items:start;">

        <div class="card">
            <div class="card-header"><h2>Add New Vehicle</h2></div>
            <?php if ($message): ?><div class="alert alert-success">&#9989; <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-danger">&#9888; <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Vehicle Number</label>
                    <input type="text" name="vehicle_number" class="form-control"
                           placeholder="e.g. MH12AB1234" required style="text-transform:uppercase;">
                </div>
                <div class="form-group">
                    <label>Vehicle Type</label>
                    <select name="vehicle_type" class="form-control" required>
                        <optgroup label="Two Wheelers">
                            <option value="Bike">&#127949; Bike / Motorcycle</option>
                            <option value="Scooter">&#128756; Scooter</option>
                            <option value="Electric Bike">&#9889; Electric Bike</option>
                        </optgroup>
                        <optgroup label="Four Wheelers">
                            <option value="Car" selected>&#128663; Car</option>
                            <option value="SUV">&#128665; SUV</option>
                            <option value="Sedan">&#128664; Sedan</option>
                            <option value="Hatchback">&#128663; Hatchback</option>
                            <option value="Electric Car">&#9889; Electric Car</option>
                        </optgroup>
                        <optgroup label="Heavy Vehicles">
                            <option value="Truck">&#128667; Truck</option>
                            <option value="Bus">&#128652; Bus</option>
                            <option value="Van">&#128656; Van</option>
                            <option value="Auto Rickshaw">&#128661; Auto Rickshaw</option>
                        </optgroup>
                        <optgroup label="Others">
                            <option value="Bicycle">&#128690; Bicycle</option>
                            <option value="Other">&#128663; Other</option>
                        </optgroup>
                    </select>
                </div>
                <button type="submit" name="add_vehicle" class="btn btn-primary btn-block">Add Vehicle</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h2>Registered Vehicles</h2></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Vehicle Number</th><th>Type</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if (mysqli_num_rows($my_vehicles) === 0): ?>
                        <tr><td colspan="4" style="text-align:center;color:#999;padding:24px;">No vehicles added yet.</td></tr>
                    <?php else: ?>
                        <?php $i=1; while ($v = mysqli_fetch_assoc($my_vehicles)):
                            $has_active = mysqli_fetch_assoc(mysqli_query($conn,
                                "SELECT id FROM bookings WHERE vehicle_id={$v['id']} AND status='booked' LIMIT 1"));
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($v['vehicle_number']) ?></strong></td>
                            <td><?= htmlspecialchars($v['type'] ?? '') ?></td>
                            <td>
                                <?php if ($has_active): ?>
                                    <span class="badge badge-warning" title="Has active booking">&#128274; In Use</span>
                                <?php else: ?>
                                    <a href="?delete_vehicle=<?= $v['id'] ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this vehicle?')">&#128465;</a>
                                <?php endif; ?>
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
</div>
</body>
</html>
