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

//Submit a response to feedback
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'respond') {
    $feedback_id = (int)$_POST['feedback_id'];
    $response    = trim($_POST['response']);

    if (empty($response)) {
        $error = "Please write a response before submitting.";
    } else {
        // Make sure feedback belongs to a session of this tutor
        $check = $conn->prepare("
            SELECT f.feedback_id FROM feedback f
            JOIN sessions s ON f.session_id = s.session_id
            WHERE f.feedback_id = ? AND s.tutor_id = ?
        ");
        $check->execute([$feedback_id, $user_id]);

        if ($check->rowCount() == 0) {
            $error = "Invalid feedback.";
        } else {
            // We store the response in the comments column prefixed — or
            // add a tutor_response column if you prefer. Here we use a
            // separate update on a tutor_response column.
            $stmt = $conn->prepare("UPDATE feedback SET tutor_response = ? WHERE feedback_id = ?");
            $stmt->execute([$response, $feedback_id]);
            $success = "Response submitted.";
        }
    }
}

//Overall average rating
$stmt = $conn->prepare("
    SELECT ROUND(AVG(f.rating), 1) as avg_rating, COUNT(f.feedback_id) as total_reviews
    FROM feedback f
    JOIN sessions s ON f.session_id = s.session_id
    WHERE s.tutor_id = ?
");
$stmt->execute([$user_id]);
$summary = $stmt->fetch();
$avg_rating    = $summary['avg_rating'] ?? 0;
$total_reviews = $summary['total_reviews'] ?? 0;

//Rating breakdown (count per star)
$breakdown = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
if ($total_reviews > 0) {
    $stmt = $conn->prepare("
        SELECT f.rating, COUNT(*) as cnt
        FROM feedback f
        JOIN sessions s ON f.session_id = s.session_id
        WHERE s.tutor_id = ? AND f.rating IS NOT NULL
        GROUP BY f.rating
    ");
    $stmt->execute([$user_id]);
    foreach ($stmt->fetchAll() as $row) {
        $breakdown[(int)$row['rating']] = (int)$row['cnt'];
    }
}

//Fetch all feedback
$stmt = $conn->prepare("
    SELECT f.feedback_id, f.rating, f.comments, f.tutor_response,
          CONCAT(u.first_name, ' ', u.last_name) as student_name, s.session_date, s.duration
    FROM feedback f
    JOIN sessions s ON f.session_id = s.session_id
    JOIN users u ON f.student_id = u.user_id
    WHERE s.tutor_id = ?
    ORDER BY s.session_date DESC
");
$stmt->execute([$user_id]);
$feedbacks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Feedback — Peer Tutoring Tracker</title>
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
      <a href="tutor_feedback.php" class="nav-item active"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Feedback</span></a>
      <a href="tutor_profile.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span></a>
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
        <h1 class="page-title">Feedback</h1>
        <p class="page-sub">View and respond to student feedback.</p>
      </div>
    </div>
    
    <?php if ($success != "") { ?><div class="page-alert success show"><?php echo $success; ?></div><?php } ?>
    <?php if ($error   != "") { ?><div class="page-alert error show"><?php echo $error; ?></div><?php } ?>

    <?php if ($total_reviews == 0) { ?>
      <div class="empty-page">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <p>No feedback received yet. Complete sessions to start collecting reviews.</p>
      </div>
    <?php } else { ?>

      <!-- Summary card -->
      <div class="feedback-summary">
        <div class="rating-big">
          <div class="rating-number"><?php echo number_format($avg_rating, 1); ?></div>
          <div class="rating-stars">
            <?php for ($i = 1; $i <= 5; $i++) { ?>
              <svg viewBox="0 0 24 24" class="rating-star <?php echo $i <= round($avg_rating) ? 'filled' : 'empty'; ?>">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
              </svg>
            <?php } ?>
          </div>
          <div class="rating-count"><?php echo $total_reviews; ?> review<?php echo $total_reviews != 1 ? 's' : ''; ?></div>
        </div>

        <div class="rating-bars">
          <?php foreach ([5,4,3,2,1] as $star) {
            $count = $breakdown[$star];
            $pct   = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
          ?>
            <div class="rating-bar-row">
              <span class="rating-bar-label"><?php echo $star; ?> ★</span>
              <div class="rating-bar-track">
                <div class="rating-bar-fill" style="width:<?php echo $pct; ?>%"></div>
              </div>
              <span class="rating-bar-count"><?php echo $count; ?></span>
            </div>
          <?php } ?>
        </div>
      </div>

      <!-- Individual feedback items -->
      <div class="feedback-list">
        <?php foreach ($feedbacks as $fb) { ?>
          <div class="feedback-item">
            <div class="feedback-item-header">
              <div>
                <div class="feedback-student"><?php echo htmlspecialchars($fb['student_name']); ?></div>
                <div class="feedback-date">
                  <?php echo date('M d, Y', strtotime($fb['session_date'])); ?> &middot;
                  <?php echo $fb['duration']; ?> min session
                </div>
              </div>
              <?php if ($fb['rating']) { ?>
                <div class="feedback-stars">
                  <?php for ($i = 1; $i <= 5; $i++) { ?>
                    <svg viewBox="0 0 24 24" class="feedback-star <?php echo $i <= $fb['rating'] ? 'filled' : 'empty'; ?>">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>

            <?php if ($fb['comments']) { ?>
              <p class="feedback-comment">"<?php echo htmlspecialchars($fb['comments']); ?>"</p>
            <?php } ?>

            <!-- Tutor response -->
            <?php if ($fb['tutor_response']) { ?>
              <div class="tutor-response">
                <div class="tutor-response-label">Your response</div>
                <p class="tutor-response-text"><?php echo htmlspecialchars($fb['tutor_response']); ?></p>
              </div>
            <?php } else { ?>
              <form action="tutor_feedback.php" method="POST"
                class="response-form" id="resp-<?php echo $fb['feedback_id']; ?>">
                <input type="hidden" name="action" value="respond">
                <input type="hidden" name="feedback_id" value="<?php echo $fb['feedback_id']; ?>">
                <textarea name="response" placeholder="Write a response to this review..." rows="2" required></textarea>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:8px;">
                  <button type="button" class="btn-cancel"
                    onclick="toggleResponse(<?php echo $fb['feedback_id']; ?>)">Cancel</button>
                  <button type="submit" class="btn-confirm">Post Response</button>
                </div>
              </form>
              <button type="button" class="btn-respond" id="btn-resp-<?php echo $fb['feedback_id']; ?>"
                onclick="toggleResponse(<?php echo $fb['feedback_id']; ?>)">
                Respond
              </button>
            <?php } ?>
          </div>
        <?php } ?>
      </div>
    <?php } ?>
  </main>

  <script>
    function toggleResponse(id) {
      var form = document.getElementById('resp-' + id);
      var btn  = document.getElementById('btn-resp-' + id);
      var open = form.classList.contains('open');
      form.classList.toggle('open');
      if (btn) btn.style.display = open ? 'inline-block' : 'none';
    }

    document.getElementById('logoutModal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
  </script>
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