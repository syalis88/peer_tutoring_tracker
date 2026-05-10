<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'tutor') {
    header("Location: ../account/login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? explode(" ", $_SESSION['user_name'])[0];

$success = "";
$error   = "";

//Fetch user
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, email FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$current_first = $user['first_name'];
$current_last  = $user['last_name'];

//Fetch tutor's subjects
$subj_stmt = $conn->prepare("
    SELECT s.subject_id, s.subject_name
    FROM tutor_subjects ts
    JOIN subjects s ON ts.subject_id = s.subject_id
    WHERE ts.tutor_id = ?
    ORDER BY s.subject_name ASC
");
$subj_stmt->execute([$user_id]);
$my_subjects = $subj_stmt->fetchAll();

//Fetch all subjects for adding
$all_subj = $conn->prepare("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
$all_subj->execute();
$all_subjects = $all_subj->fetchAll();
$my_subject_ids = array_column($my_subjects, 'subject_id');

//Update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'update_profile') {

    $new_first  = trim($_POST['first_name']);
    $new_middle = trim($_POST['middle_name']);
    $new_last   = trim($_POST['last_name']);
    $new_email  = trim($_POST['email']);

    if (empty($new_first) || empty($new_middle) || empty($new_last)) {
        $error = "First, middle, and last name are required.";
    } else if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check->execute([$new_email, $user_id]);

        if ($check->rowCount() > 0) {
            $error = "That email is already used by another account.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ? WHERE user_id = ?");
            $stmt->execute([$new_first, $new_middle, $new_last, $new_email, $user_id]);
            $_SESSION['user_name']  = $new_first . ' ' . $new_last;
            $_SESSION['first_name'] = $new_first;
            $_SESSION['last_name']  = $new_last;
            $success = "Profile updated successfully.";

            $stmt = $conn->prepare("SELECT first_name, middle_name, last_name, email FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user          = $stmt->fetch();
            $current_first = $user['first_name'];
            $current_last  = $user['last_name'];
            $first_name    = $current_first;
        }
    }
}

//Change password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'change_password') {

    $current_pw = $_POST['current_password'];
    $new_pw     = $_POST['new_password'];
    $confirm_pw = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!password_verify($current_pw, $row['password'])) {
        $error = "Current password is incorrect.";
    } else if (strlen($new_pw) < 8) {
        $error = "New password must be at least 8 characters.";
    } else if ($new_pw != $confirm_pw) {
        $error = "New passwords do not match.";
    } else {
        $hashed = password_hash($new_pw, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$hashed, $user_id]);
        $success = "Password changed successfully.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'add_subject') {
    $subject_id = (int)$_POST['subject_id'];
    if ($subject_id > 0) {
        $stmt = $conn->prepare("INSERT IGNORE INTO tutor_subjects (tutor_id, subject_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $subject_id]);
        $success = "Subject added.";

        $subj_stmt->execute([$user_id]);
        $my_subjects    = $subj_stmt->fetchAll();
        $my_subject_ids = array_column($my_subjects, 'subject_id');
    }
}

//Remove a subject
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'remove_subject') {
    $subject_id = (int)$_POST['subject_id'];
    $stmt = $conn->prepare("DELETE FROM tutor_subjects WHERE tutor_id = ? AND subject_id = ?");
    $stmt->execute([$user_id, $subject_id]);
    $success = "Subject removed.";

    $subj_stmt->execute([$user_id]);
    $my_subjects    = $subj_stmt->fetchAll();
    $my_subject_ids = array_column($my_subjects, 'subject_id');
}

//Stats
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions WHERE tutor_id = ?");
$stmt->execute([$user_id]);
$total_sessions = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as total FROM sessions WHERE tutor_id = ?");
$stmt->execute([$user_id]);
$unique_students = $stmt->fetch()['total'];

$stmt = $conn->prepare("
    SELECT ROUND(AVG(f.rating),1) as avg
    FROM feedback f JOIN sessions s ON f.session_id = s.session_id
    WHERE s.tutor_id = ?
");
$stmt->execute([$user_id]);
$avg_rating = $stmt->fetch()['avg'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/tutor_dashboard.css"/>
  <link rel="stylesheet" href="../assets/tutor.css"/>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class=""></div>
      <span>Peer Tutoring Tracker</span>
    </div>
    <nav class="sidebar-nav">
      <a href="tutor_dashboard.php" class="nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
      <a href="tutor_sessions.php" class="nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span>My Sessions</span></a>
      <a href="my_students.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>My Students</span></a>
      <a href="schedule.php" class="nav-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>My Schedule</span></a>
      <a href="tutor_feedback.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Feedback</span></a>
      <a href="tutor_profile.php" class="nav-item active"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span></a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="avatar tutor-avatar"><?php echo strtoupper(substr($first_name, 0, 1)); ?></div>
        <div class="user-details">
          <span class="user-name"><?php echo htmlspecialchars($first_name); ?></span>
          <span class="user-role">Tutor</span>
        </div>
      </div>
      <a href="#" onclick="document.getElementById('logoutModal').classList.add('open'); return false;" class="logout-btn" title="Sign out">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 class="page-title">My Profile</h1>
        <p class="page-sub">Manage your account settings and password.</p>
      </div>
    </div>

    <div class="page-content">
    <?php if ($success != "") { ?><div class="page-alert success show"><?php echo $success; ?></div><?php } ?>
    <?php if ($error   != "") { ?><div class="page-alert error show"><?php echo $error; ?></div><?php } ?>

    <div class="profile-layout">

      <!-- Sidebar summary -->
      <div class="profile-sidebar">
        <div class="profile-avatar"><?php echo strtoupper(substr($current_first, 0, 1)); ?></div>
        <div class="profile-display-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
        <span class="profile-role-badge">Tutor</span>

        <div class="profile-summary-stats">
          <div class="profile-stat">
            <span class="profile-stat-value"><?php echo $total_sessions; ?></span>
            <span class="profile-stat-label">Sessions</span>
          </div>
          <div class="profile-stat">
            <span class="profile-stat-value"><?php echo $unique_students; ?></span>
            <span class="profile-stat-label">Students</span>
          </div>
          <div class="profile-stat">
            <span class="profile-stat-value"><?php echo $avg_rating > 0 ? number_format($avg_rating, 1) : '—'; ?></span>
            <span class="profile-stat-label">Rating</span>
          </div>
        </div>
      </div>

      <div class="profile-forms">

        <!-- Personal info -->
        <div class="card">
          <div class="card-header"><h2 class="card-title">Personal Information</h2></div>
          <form action="tutor_profile.php" method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="field-row">
              <div class="field">
                <label>First Name</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($current_first); ?>" required>
              </div>
              <div class="field">
                <label>Middle Name</label>
                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name']); ?>" required>
              </div>
              <div class="field">
                <label>Last Name</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($current_last); ?>" required>
              </div>
            </div>
            <div class="field">
              <label>Email Address</label>
              <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div style="display:flex; justify-content:flex-end;">
              <button type="submit" class="btn-confirm">Save Changes</button>
            </div>
          </form>
        </div>

        <!-- Subjects -->
        <div class="card">
          <div class="card-header"><h2 class="card-title">Subjects I Teach</h2></div>

          <!-- Current subjects -->
          <div class="subject-tags" style="margin-bottom:16px;">
            <?php if (count($my_subjects) == 0) { ?>
              <p style="font-size:13px; color:var(--muted);">No subjects added yet.</p>
            <?php } else { ?>
              <?php foreach ($my_subjects as $subj) { ?>
                <form action="tutor_profile.php" method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="remove_subject">
                  <input type="hidden" name="subject_id" value="<?php echo $subj['subject_id']; ?>">
                  <button type="submit" class="subject-tag-remove">
                    <?php echo htmlspecialchars($subj['subject_name']); ?> &times;
                  </button>
                </form>
              <?php } ?>
            <?php } ?>
          </div>

          <!-- Add subject -->
          <form action="tutor_profile.php" method="POST" style="display:flex; gap:10px; align-items:flex-end;">
            <input type="hidden" name="action" value="add_subject">
            <div class="field" style="flex:1; margin-bottom:0;">
              <label>Add a Subject</label>
              <select name="subject_id" required>
                <option value="" disabled selected>Select subject to add</option>
                <?php foreach ($all_subjects as $subj) {
                  if (!in_array($subj['subject_id'], $my_subject_ids)) { ?>
                    <option value="<?php echo $subj['subject_id']; ?>"><?php echo htmlspecialchars($subj['subject_name']); ?></option>
                  <?php }
                } ?>
              </select>
            </div>
            <button type="submit" class="btn-confirm" style="flex-shrink:0;">Add</button>
          </form>
        </div>

        <!-- Change password -->
        <div class="card">
          <div class="card-header"><h2 class="card-title">Change Password</h2></div>
          <form action="tutor_profile.php" method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="field">
              <label>Current Password</label>
              <div class="pw-wrap">
                <input type="password" id="cur_pw" name="current_password" placeholder="Enter current password">
                <button type="button" class="pw-toggle" onclick="togglePw('cur_pw', this)">
                  <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="field">
              <label>New Password</label>
              <div class="pw-wrap">
                <input type="password" id="new_pw" name="new_password" placeholder="Min. 8 characters">
                <button type="button" class="pw-toggle" onclick="togglePw('new_pw', this)">
                  <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="field">
              <label>Confirm New Password</label>
              <div class="pw-wrap">
                <input type="password" id="con_pw" name="confirm_password" placeholder="Repeat new password">
                <button type="button" class="pw-toggle" onclick="togglePw('con_pw', this)">
                  <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div style="display:flex; justify-content:flex-end;">
              <button type="submit" class="btn-confirm">Update Password</button>
            </div>
          </form>
        </div>

      </div>
    </div>
</div>
  </main>

  <script>
    function togglePw(inputId, btn) {
      var inp = document.getElementById(inputId);
      if (inp.type === 'password') {
        inp.type = 'text';
        btn.querySelector('svg').innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
      } else {
        inp.type = 'password';
        btn.querySelector('svg').innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
      }
    }

    document.getElementById('logoutModal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
  </script>
   <!-- Logout confirm modal -->
<div class="modal-overlay" id="logoutModal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Sign Out</h2>
      <button type="button" class="modal-close" onclick="document.getElementById('logoutModal').classList.remove('open')">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">
      Are you sure you want to sign out?
    </p>
    <div class="modal-footer">
      <button type="button" class="btn-cancel" onclick="document.getElementById('logoutModal').classList.remove('open')">Cancel</button>
      <a href="../account/logout.php" class="btn-confirm danger">Yes, Sign Out</a>
    </div>
  </div>
</div>
</body>
</html>