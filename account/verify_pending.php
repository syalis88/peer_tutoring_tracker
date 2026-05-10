<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Check Your Email — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/login.css"/>
</head>
<body>
  <aside class="panel-left">
    <div class="brand">
      <h1>Peer Tutoring Tracker</h1>
      <p>Almost there! Verify your email to activate your account.</p>
    </div>
    <div class="panel-left-footer">© <?php echo date('Y'); ?> Peer Tutoring Tracker. All rights reserved.</div>
  </aside>
  <main class="panel-right">
    <div class="form-card">
      <div class="form-header">
        <h2>Check your inbox</h2>
        <p>A verification link has been sent to your email address.</p>
      </div>
      <div class="alert success show">
        Click the link in the email to activate your account. Check your spam folder if you don't see it.
      </div>
      <div class="divider"><a href="login.php" class="forgot">← Back to Login</a></div>
    </div>
  </main>
</body>
</html>