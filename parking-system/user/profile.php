<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";

$user_id = $_SESSION['user_id'];
$message = $error = '';

// Update profile
if (isset($_POST['update_profile'])) {
    $name  = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    if (empty($name)) {
        $error = "Name cannot be empty.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, phone=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $phone, $user_id);
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name;
            $message = "Profile updated successfully!";
        } else {
            $error = "Error updating profile.";
        }
    }
}

// Change password
if (isset($_POST['change_password'])) {
    $current  = $_POST['current_password'];
    $new      = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];
    $user_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE id=$user_id"));
    if (!password_verify($current, $user_row['password'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute() ? $message = "Password changed successfully!" : $error = "Error changing password.";
    }
}

$user   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
$bcount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE user_id=$user_id"))['c'];
$vcount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM vehicles WHERE user_id=$user_id"))['c'];
$spent  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE user_id=$user_id"))['s'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Profile - Smart Parking</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">
<?php $active_page = 'profile'; include "../includes/user_sidebar.php"; ?>
<div class="main-content">
    <div class="topbar"><h1>&#128100; My Profile</h1></div>

    <?php if ($message): ?><div class="alert alert-success">&#9989; <?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">&#9888; <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header" style="margin-bottom:24px;">
        <div class="profile-avatar">&#128100;</div>
        <div class="profile-info">
            <h2><?= htmlspecialchars($user['name']) ?></h2>
            <p>&#9993; <?= htmlspecialchars($user['email']) ?></p>
            <p style="margin-top:4px;opacity:.7;font-size:13px;">Member since <?= date('M Y', strtotime($user['created_at'] ?? 'now')) ?></p>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon">&#128203;</div>
            <div class="stat-info"><h3>Total Bookings</h3><h2><?= $bcount ?></h2></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">&#128663;</div>
            <div class="stat-info"><h3>Vehicles</h3><h2><?= $vcount ?></h2></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">&#128176;</div>
            <div class="stat-info"><h3>Total Spent</h3><h2>&#8377;<?= number_format($spent) ?></h2></div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

        <!-- Edit Profile -->
        <div class="card">
            <div class="card-header"><h2>&#9999; Edit Profile</h2></div>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 9876543210">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:#f5f5f5;color:#999;">
                    <small style="color:#999;font-size:12px;">Email cannot be changed.</small>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary btn-block">Save Changes</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header"><h2>&#128274; Change Password</h2></div>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <div class="pass-wrap">
                        <input type="password" name="current_password" id="cp" class="form-control" required>
                        <button type="button" class="pass-toggle" onclick="togglePass('cp')">&#128065;</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <div class="pass-wrap">
                        <input type="password" name="new_password" id="np" class="form-control" required minlength="6">
                        <button type="button" class="pass-toggle" onclick="togglePass('np')">&#128065;</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="pass-wrap">
                        <input type="password" name="confirm_password" id="cnp" class="form-control" required minlength="6">
                        <button type="button" class="pass-toggle" onclick="togglePass('cnp')">&#128065;</button>
                    </div>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary btn-block">Change Password</button>
            </form>
        </div>
    </div>

    <div style="margin-top:16px;">
        <a href="../logout.php" class="btn btn-danger btn-sm" onclick="return confirm('Logout?')">&#128682; Logout</a>
    </div>
</div>
</div>
<script>
function togglePass(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
