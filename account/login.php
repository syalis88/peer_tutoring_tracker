<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

// Already logged in — send to correct dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header("Location: ../admin/dashboard.php");
    } else if ($_SESSION['user_role'] == 'tutor') {
        header("Location: ../tutor/tutor_dashboard.php");
    } else {
        header("Location: ../student/student_dashboard.php");
    }
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {

        $stmt = $conn->prepare("SELECT user_id, first_name, last_name, role, password, is_verified, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $error = "Incorrect email or password.";
    } else if ($user['is_verified'] == 0 && $user['role'] != 'admin') {
    $error = "Please verify your email before logging in. Check your inbox.";
    } else if ($user['status'] === 'suspended') {
    $error = "Your account has been suspended. Please contact an administrator.";
    } else { 
        if (!$user || !password_verify($password, $user['password'])) {
            $error = "Incorrect email or password.";
        } else {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] == 'admin') {
                header("Location: ../admin/admin_dashboard.php");
            } else if ($user['role'] == 'tutor') {
                header("Location: ../tutor/tutor_dashboard.php");
            } else {
                header("Location: ../student/student_dashboard.php");
            }
            exit();
        }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/login.css"/>
</head>
<body>

  <aside class="panel-left" style="background-image: url('../assets/images/image.jpeg');">
  <div class="brand">
    <div class="brand-badge">
      <div class="brand-badge-dot"></div>
    </div>
    <h1>Peer Tutoring<br><span>Tracker</span></h1>
    <p>A smarter way to manage sessions, track progress, and connect with peers.</p>
    <div class="stats-row">
      <div class="stat-chip">
        <span class="stat-val">98%</span>
        <span class="stat-label">Satisfaction</span>
      </div>
      <div class="stat-chip">
        <span class="stat-val">24/7</span>
        <span class="stat-label">Available</span>
      </div>
      <div class="stat-chip">
        <span class="stat-val">100+</span>
        <span class="stat-label">Sessions</span>
      </div>
    </div>
  </div>
  <div class="features">
    <div class="feature">
  </div>
  <div class="panel-left-footer">© <?php echo date('Y'); ?> Peer Tutoring Tracker. All rights reserved.</div>
</aside>

  <main class="panel-right">
    <div class="form-card">

      <div class="form-header">
        <h2>Welcome back</h2>
        <p>Don't have an account? <a href="register.php">Sign up here</a></p>
      </div>

      <?php if ($error != "") { ?>
          <div class="alert error show"><?php echo $error; ?></div>
      <?php } ?>

      <?php if (($_GET['status'] ?? '') === 'reset_success') { ?>
        <div class="alert success show">Password reset successful! You can now sign in.</div>
      <?php } ?>

      <form action="login.php" method="POST" id="loginForm">

        <div class="field">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="you@email.com"
            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
          <div class="error-msg" id="err-email">Enter a valid email.</div>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="pw-wrap">
            <input type="password" id="password" name="password" placeholder="Your password">
            <button type="button" class="pw-toggle" id="togglePw">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="error-msg" id="err-password">Password is required.</div>
        </div>

        <div class="form-row">
          <label class="remember">
            <input type="checkbox" name="remember"> Remember me
          </label>
          <a href="forgot_password.php" class="forgot">Forgot password?</a>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span id="btnText">Sign In</span>
          <div class="spinner" id="spinner"></div>
        </button>

        <div class="divider">Your information is kept private and secure.</div>
      </form>
    </div>
  </main>

  <script>
    var btn = document.getElementById('togglePw');
    var inp = document.getElementById('password');
    btn.addEventListener('click', function() {
      if (inp.type === 'password') {
        inp.type = 'text';
        btn.querySelector('svg').innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
      } else {
        inp.type = 'password';
        btn.querySelector('svg').innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
      }
    });

    document.getElementById('loginForm').addEventListener('submit', function(e) {
      var hasError = false;

      var email = document.getElementById('email');
      var emailErr = document.getElementById('err-email');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        email.classList.add('error'); emailErr.style.display = 'block'; hasError = true;
      } else {
        email.classList.remove('error'); emailErr.style.display = 'none';
      }

      var pw = document.getElementById('password');
      var pwErr = document.getElementById('err-password');
      if (pw.value.trim() == '') {
        pw.classList.add('error'); pwErr.style.display = 'block'; hasError = true;
      } else {
        pw.classList.remove('error'); pwErr.style.display = 'none';
      }

      if (hasError) { e.preventDefault(); return; }

      document.getElementById('btnText').style.display = 'none';
      document.getElementById('spinner').style.display = 'block';
      document.getElementById('submitBtn').disabled = true;
    });
  </script>
</body>
</html>