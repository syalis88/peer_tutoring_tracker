<?php
require '../classes/peer_tutoring_trackerDB.php';

$token = trim($_GET['token'] ?? '');
$error = $_GET['error'] ?? '';

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT user_id, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $valid = ($row && strtotime($row['reset_token_expiry'] . ' UTC') > time()) ? $row : false;
} else {
    $valid = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | Peer Tutoring Tracker</title>
    <link rel="stylesheet" href="/PEER_TUTORING_TRACKER/assets/login.css">
</head>
<body>
<div class="panel-right" style="min-height:100vh;">
  <div class="form-card">
    <div class="form-header">
      <h2>Reset Password</h2>
      <p>Enter your new password below.</p>
    </div>

    <?php if (!$valid): ?>
      <div class="alert error show">
        This reset link is <strong>invalid or has expired</strong>.
        <a href="forgot_password.php">Request a new one</a>.
      </div>
    <?php else: ?>

      <?php if ($error === 'mismatch'): ?>
        <div class="alert error show">Passwords do not match.</div>
      <?php elseif ($error === 'short'): ?>
        <div class="alert error show">Password must be at least 8 characters.</div>
      <?php elseif ($error === 'empty'): ?>
        <div class="alert error show">All fields are required.</div>
      <?php endif; ?>

      <form action="../actions/process_reset.php" method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="field">
  <label for="password">New Password</label>
  <div class="pw-wrap">
    <input type="password" name="password" id="password" placeholder="At least 8 characters" required>
    <button type="button" class="pw-toggle" onclick="togglePw('password', this)">
      <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    </button>
  </div>
</div>
<div class="field">
  <label for="confirm_password">Confirm Password</label>
  <div class="pw-wrap">
    <input type="password" name="confirm_password" id="confirm_password" placeholder="Repeat new password" required>
    <button type="button" class="pw-toggle" onclick="togglePw('confirm_password', this)">
      <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
    </button>
  </div>
</div>
        <button type="submit" class="btn-submit">Reset Password</button>
      </form>

    <?php endif; ?>

    <div class="divider">
      <a href="login.php">← Back to Login</a>
    </div>
  </div>
</div>
<script>
function togglePw(fieldId, btn) {
  var input = document.getElementById(fieldId);
  var isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  btn.innerHTML = isPassword
    ? '<svg viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
    : '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
}
</script>
</body>
</html>