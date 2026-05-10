<?php
$status = $_GET['status'] ?? '';
$error  = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/login.css"/>
</head>
<body>

  <aside class="panel-left">
    <div class="brand">
      <h1>Peer Tutoring Tracker</h1>
      <p>Enter your registered email and we'll send you a link to reset your password.</p>
    </div>
    <div class="panel-left-footer">© <?php echo date('Y'); ?> Peer Tutoring Tracker. All rights reserved.</div>
  </aside>

  <main class="panel-right">
    <div class="form-card">

      <div class="form-header">
        <h2>Forgot Password</h2>
        <p>Remembered it? <a href="login.php">Sign in here</a></p>
      </div>

      <?php if ($status === 'sent'): ?>
        <div class="alert success show">If that email is registered, a reset link has been sent. Check your inbox.</div>
      <?php elseif ($error === 'empty'): ?>
        <div class="alert error show">Please enter your email address.</div>
      <?php elseif ($error === 'mail'): ?>
        <div class="alert error show">Failed to send email. Please try again later.</div>
      <?php endif; ?>

      <form action="../actions/send_reset.php" method="POST">
        <div class="field">
          <label for="email">Email Address</label>
          <input type="email" name="email" id="email" placeholder="you@email.com" required>
        </div>

        <button type="submit" class="btn-submit" style="color:#fff;">
          Send Reset Link
        </button>

        <div class="divider"><a href="login.php" class="forgot">← Back to Login</a></div>
      </form>

    </div>
  </main>

</body>
</html>
