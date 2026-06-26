<?php
require_once "../includes/auth_user.php";
require_once "../includes/db_connect.php";

$user_id = $_SESSION['user_id'];
$user    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, email, phone FROM users WHERE id=$user_id"));

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = htmlspecialchars(trim($_POST['subject'] ?? ''));
    $msg     = htmlspecialchars(trim($_POST['message'] ?? ''));

    if (!$subject)           $errors[] = 'Please select a subject.';
    if (strlen($msg) < 10)   $errors[] = 'Message must be at least 10 characters.';

    if (empty($errors)) {
        $name  = $user['name'];
        $email = $user['email'];
        $phone = $user['phone'] ?? '';
        $stmt  = mysqli_prepare($conn, "INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $phone, $subject, $msg);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// User's previous messages
$my_messages = mysqli_query($conn, "SELECT * FROM contact_messages WHERE email='" . mysqli_real_escape_string($conn, $user['email']) . "' ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Support - Smart Parking</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="dash-wrapper">
<?php $active_page = 'support'; include "../includes/user_sidebar.php"; ?>

<div class="main-content">
    <div class="topbar">
        <h1>🎧 Support</h1>
        <div class="topbar-right">📅 <?= date('D, d M Y') ?></div>
    </div>

    <div class="support-grid">

        <!-- FORM -->
        <div>
            <div class="card" style="padding:0; overflow:hidden;">
                <div style="background:linear-gradient(135deg,var(--primary),var(--primary-light)); padding:24px 28px;">
                    <h2 style="color:#fff; font-size:18px; font-weight:700; margin-bottom:4px;">📝 Send a Message to Admin</h2>
                    <p style="color:rgba(255,255,255,.78); font-size:13px;">Describe your issue and we'll get back to you shortly.</p>
                </div>

                <div style="padding:24px 28px;">

                    <?php if ($success): ?>
                        <div class="alert alert-success" style="display:flex; gap:12px; align-items:flex-start;">
                            <span style="font-size:22px;">✅</span>
                            <div>
                                <strong>Message sent successfully!</strong><br>
                                <span style="font-size:13px;">Our admin team will review your message and respond soon.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $e): ?>
                                <div>⚠️ <?= $e ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Pre-filled user info (read-only) -->
                    <div class="support-user-info">
                        <div class="sui-item">
                            <span class="sui-label">Sending as</span>
                            <span class="sui-value">👤 <?= htmlspecialchars($user['name']) ?></span>
                        </div>
                        <div class="sui-item">
                            <span class="sui-label">Email</span>
                            <span class="sui-value">✉️ <?= htmlspecialchars($user['email']) ?></span>
                        </div>
                    </div>

                    <?php if (!$success): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>Subject <span class="req-star">*</span></label>
                            <select name="subject" class="form-control" required>
                                <option value="">— Select a subject —</option>
                                <option value="Booking Issue"    <?= (($_POST['subject']??'')==='Booking Issue')    ?'selected':'' ?>>Booking Issue</option>
                                <option value="Payment Problem"  <?= (($_POST['subject']??'')==='Payment Problem')  ?'selected':'' ?>>Payment Problem</option>
                                <option value="Account Help"     <?= (($_POST['subject']??'')==='Account Help')     ?'selected':'' ?>>Account Help</option>
                                <option value="Slot Not Available" <?= (($_POST['subject']??'')==='Slot Not Available')?'selected':'' ?>>Slot Not Available</option>
                                <option value="Vehicle Issue"    <?= (($_POST['subject']??'')==='Vehicle Issue')    ?'selected':'' ?>>Vehicle Issue</option>
                                <option value="Other"            <?= (($_POST['subject']??'')==='Other')            ?'selected':'' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Message <span class="req-star">*</span></label>
                            <textarea name="message" class="form-control" rows="6"
                                placeholder="Describe your problem in detail..."
                                required style="resize:vertical;"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            <div class="char-counter"><span id="charCount">0</span> / 1000 characters</div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" style="padding:13px; font-size:15px; border-radius:10px;">
                            📤 Send Message
                        </button>
                    </form>
                    <?php else: ?>
                        <a href="support.php" class="btn btn-outline btn-block" style="margin-top:8px;">Send Another Message</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SIDEBAR -->
        <div style="display:flex; flex-direction:column; gap:20px;">

            <!-- Tips -->
            <div class="card">
                <h3 style="font-size:15px; font-weight:700; color:var(--primary); margin-bottom:14px; padding-bottom:10px; border-bottom:1.5px solid var(--border);">💡 Before You Write</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div class="support-tip">
                        <span>🅿️</span>
                        <div><strong>Booking issue?</strong><br><small>Check your booking history first.</small></div>
                    </div>
                    <div class="support-tip">
                        <span>💳</span>
                        <div><strong>Payment problem?</strong><br><small>Include your booking ID in the message.</small></div>
                    </div>
                    <div class="support-tip">
                        <span>🔑</span>
                        <div><strong>Account issue?</strong><br><small>Mention your registered email.</small></div>
                    </div>
                </div>
            </div>

            <!-- Response time -->
            <div class="card" style="text-align:center; background:linear-gradient(135deg,#e8eaf6,#f0f2ff); border-color:#c5cae9;">
                <div style="font-size:36px; margin-bottom:8px;">⏱️</div>
                <h4 style="font-size:14px; font-weight:700; color:var(--primary); margin-bottom:4px;">Average Response Time</h4>
                <div style="font-size:26px; font-weight:900; color:var(--primary); margin-bottom:6px;">Under 24 hrs</div>
                <p style="font-size:13px; color:var(--text-muted); line-height:1.6;">Our admin team reviews all messages and responds as quickly as possible.</p>
            </div>

        </div>
    </div>

    <!-- Previous Messages -->
    <?php if (mysqli_num_rows($my_messages) > 0): ?>
    <div class="card" style="margin-top:8px;">
        <div class="card-header">
            <h2>📋 My Previous Messages</h2>
        </div>
        <div style="display:flex; flex-direction:column; gap:12px;">
        <?php while ($m = mysqli_fetch_assoc($my_messages)): ?>
            <div style="background:var(--bg); border:1px solid var(--border); border-radius:10px; padding:14px 18px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="badge badge-info">📌 <?= htmlspecialchars($m['subject']) ?></span>
                        <?php if ($m['status'] === 'read'): ?>
                            <span class="badge badge-success">✅ Seen by Admin</span>
                        <?php else: ?>
                            <span class="badge badge-warning">🕐 Pending</span>
                        <?php endif; ?>
                    </div>
                    <span style="font-size:12px; color:var(--text-muted);">🕐 <?= date('d M Y, h:i A', strtotime($m['created_at'])) ?></span>
                </div>
                <p style="font-size:13px; color:var(--text-muted); line-height:1.7; margin:0;"><?= nl2br(htmlspecialchars($m['message'])) ?></p>
            </div>
        <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<style>
.support-grid {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 24px;
    align-items: start;
    margin-bottom: 24px;
}
.support-user-info {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 20px;
}
.sui-item { display: flex; flex-direction: column; gap: 2px; }
.sui-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .4px; }
.sui-value { font-size: 13px; font-weight: 600; color: var(--text); }
.support-tip {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 13px;
}
.support-tip span { font-size: 20px; flex-shrink: 0; }
.support-tip strong { display: block; font-size: 13px; color: var(--text); font-weight: 600; }
.support-tip small  { color: var(--text-muted); }
body.dark .support-user-info { background: #252525; border-color: #333; }
body.dark .sui-value { color: #e0e0e0; }
@media (max-width: 768px) {
    .support-grid { grid-template-columns: 1fr; }
}
</style>

<script>
const textarea = document.querySelector('textarea[name="message"]');
const counter  = document.getElementById('charCount');
if (textarea && counter) {
    textarea.addEventListener('input', () => {
        const len = textarea.value.length;
        counter.textContent = len;
        counter.style.color = len > 900 ? '#c62828' : len > 700 ? '#e65100' : '';
        if (len > 1000) textarea.value = textarea.value.slice(0, 1000);
    });
}
</script>
</body>
</html>
