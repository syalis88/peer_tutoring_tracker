<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'student') {
    header("Location: ../account/login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? explode(" ", $_SESSION['user_name'])[0];

$success = "";
$error   = "";

//Submit feedback
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'submit') {
    $session_id = (int)$_POST['session_id'];
    $rating     = (int)$_POST['rating'];
    $comments   = trim($_POST['comments']);

    if ($rating < 1 || $rating > 5) {
        $error = "Please select a star rating.";
    } else if (empty($comments)) {
        $error = "Please write a comment before submitting.";
    } else {
        // Make sure session belongs to this student and is completed
        $check = $conn->prepare("
            SELECT session_id FROM sessions
            WHERE session_id = ? AND student_id = ? AND status = 'completed'
        ");
        $check->execute([$session_id, $user_id]);

        if ($check->rowCount() == 0) {
            $error = "Invalid session.";
        } else {
            // Check if feedback already submitted
            $dup = $conn->prepare("SELECT feedback_id FROM feedback WHERE session_id = ? AND student_id = ?");
            $dup->execute([$session_id, $user_id]);

            if ($dup->rowCount() > 0) {
                $error = "You have already submitted feedback for this session.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO feedback (session_id, student_id, rating, comments)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$session_id, $user_id, $rating, $comments]);
                $success = "Feedback submitted. Thank you!";
            }
        }
    }
}

//Fetch completed sessions with/without feedback
$stmt = $conn->prepare("
    SELECT s.session_id, s.session_date, s.duration,
           CONCAT(u.first_name, ' ', u.last_name) as tutor_name,
           f.feedback_id, f.rating, f.comments
    FROM sessions s
    JOIN users u ON s.tutor_id = u.user_id
    LEFT JOIN feedback f ON f.session_id = s.session_id AND f.student_id = ?
    WHERE s.student_id = ? AND s.status = 'completed'
    ORDER BY s.session_date DESC
");
$stmt->execute([$user_id, $user_id]);
$sessions = $stmt->fetchAll();

// Pre-open a specific session if coming from sessions page
$preselect = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Feedback — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/student_dashboard.css"/>
  <link rel="stylesheet" href="../assets/student.css"/>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="">
      </div>
      <span>Peer Tutoring Tracker</span>
    </div>
    <nav class="sidebar-nav">
      <a href="student_dashboard.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        <span>Dashboard</span>
      </a>
      <a href="student_sessions.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>My Sessions</span>
      </a>
      <a href="find_tutors.php" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <span>Find Tutors</span>
      </a>
      <a href="student_feedback.php" class="nav-item active">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span>Feedback</span>
      </a>
      <a href="student_profile.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>Profile</span>
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="avatar"><?php echo strtoupper(substr($first_name, 0, 1)); ?></div>
        <div class="user-details">
          <span class="user-name"><?php echo htmlspecialchars($first_name); ?></span>
          <span class="user-role">Student</span>
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
        <p class="page-sub">Rate and leave a comment on your completed tutoring sessions.</p>
      </div>
    </div>

    <?php if ($success != "") { ?>
      <div class="page-alert success show"><?php echo $success; ?></div>
    <?php } ?>
    <?php if ($error != "") { ?>
      <div class="page-alert error show"><?php echo $error; ?></div>
    <?php } ?>

    <?php if (count($sessions) == 0) { ?>
      <div class="empty-page">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <p>No completed sessions to review yet.</p>
        <a href="find_tutors.php" class="btn-secondary">Book a session</a>
      </div>
    <?php } else { ?>
      <div class="feedback-list">
        <?php foreach ($sessions as $s) {
          $already_done = $s['feedback_id'] != null;
          $is_preselect = ($preselect == $s['session_id'] && !$already_done);
        ?>
          <div class="feedback-card <?php echo $already_done ? 'done' : ''; ?>">

            <div class="feedback-card-header">
              <div>
                <div class="feedback-tutor"><?php echo htmlspecialchars($s['tutor_name']); ?></div>
                <div class="feedback-meta">
                  <?php echo date('M d, Y', strtotime($s['session_date'])); ?> &middot;
                  <?php echo date('g:i A', strtotime($s['session_date'])); ?> &middot;
                  <?php echo $s['duration']; ?> min
                </div>
              </div>
              <?php if ($already_done) { ?>
                <span class="badge badge-success">Reviewed</span>
              <?php } else { ?>
                <span class="badge badge-info">Pending Review</span>
              <?php } ?>
            </div>

            <?php if ($already_done) { ?>
              <!-- Show submitted rating + comment -->
              <div class="existing-feedback">
                <div class="star-display">
                  <?php for ($i = 1; $i <= 5; $i++) { ?>
                    <svg viewBox="0 0 24 24" class="star-icon <?php echo $i <= $s['rating'] ? 'filled' : 'empty'; ?>">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  <?php } ?>
                  <span class="rating-label"><?php echo $s['rating']; ?> out of 5</span>
                </div>
                <?php if ($s['comments'] != '') { ?>
                  <p class="existing-comment">"<?php echo htmlspecialchars($s['comments']); ?>"</p>
                <?php } ?>
              </div>

            <?php } else { ?>
              <!-- Rating + comment form -->
              <form action="submit_feedback.php" method="POST"
                class="feedback-form <?php echo $is_preselect ? 'open' : ''; ?>"
                id="form-<?php echo $s['session_id']; ?>">

                <input type="hidden" name="action" value="submit">
                <input type="hidden" name="session_id" value="<?php echo $s['session_id']; ?>">
                <input type="hidden" name="rating" id="rating-<?php echo $s['session_id']; ?>" value="0">

                <!-- Star picker -->
                <div class="star-picker-wrap">
                  <label class="picker-label">Your Rating</label>
                  <div class="star-picker" id="stars-<?php echo $s['session_id']; ?>">
                    <?php for ($i = 1; $i <= 5; $i++) { ?>
                      <svg viewBox="0 0 24 24" class="star-pick empty" data-value="<?php echo $i; ?>"
                        onclick="setRating(<?php echo $s['session_id']; ?>, <?php echo $i; ?>)"
                        onmouseover="hoverRating(<?php echo $s['session_id']; ?>, <?php echo $i; ?>)"
                        onmouseout="restoreRating(<?php echo $s['session_id']; ?>)">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                      </svg>
                    <?php } ?>
                  </div>
                  <span class="rating-hint" id="hint-<?php echo $s['session_id']; ?>">Click a star to rate</span>
                </div>

                <!-- Comment -->
                <div class="field" style="margin-top: 14px;">
                  <label>Your Comment</label>
                  <textarea name="comments" placeholder="Share your experience with this tutor..." rows="3" required></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px;">
                  <button type="button" class="btn-cancel"
                    onclick="closeForm(<?php echo $s['session_id']; ?>)">
                    Cancel
                  </button>
                  <button type="submit" class="btn-confirm"
                    onclick="return validateFeedback(<?php echo $s['session_id']; ?>)">
                    Submit Feedback
                  </button>
                </div>
              </form>

              <?php if (!$is_preselect) { ?>
                <button type="button" class="btn-leave-feedback" id="btn-<?php echo $s['session_id']; ?>"
                  onclick="openForm(<?php echo $s['session_id']; ?>)">
                  Leave Feedback
                </button>
              <?php } ?>
            <?php } ?>

          </div>
        <?php } ?>
      </div>
    <?php } ?>

  </main>

  <script>
    var selectedRatings = {};

    function setRating(sessionId, value) {
      selectedRatings[sessionId] = value;
      document.getElementById('rating-' + sessionId).value = value;

      var labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
      document.getElementById('hint-' + sessionId).textContent = labels[value];

      updateStars(sessionId, value);
    }

    function hoverRating(sessionId, value) {
      updateStars(sessionId, value);
    }

    function restoreRating(sessionId) {
      var current = selectedRatings[sessionId] || 0;
      updateStars(sessionId, current);
      var labels = ['Click a star to rate', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
      if (current == 0) {
        document.getElementById('hint-' + sessionId).textContent = 'Click a star to rate';
      }
    }

    function updateStars(sessionId, value) {
      var stars = document.querySelectorAll('#stars-' + sessionId + ' .star-pick');
      stars.forEach(function(star, index) {
        if (index < value) {
          star.classList.remove('empty');
          star.classList.add('filled');
        } else {
          star.classList.remove('filled');
          star.classList.add('empty');
        }
      });
    }

    function openForm(sessionId) {
      document.getElementById('form-' + sessionId).classList.add('open');
      document.getElementById('btn-' + sessionId).style.display = 'none';
    }

    function closeForm(sessionId) {
      document.getElementById('form-' + sessionId).classList.remove('open');
      var btn = document.getElementById('btn-' + sessionId);
      if (btn) btn.style.display = 'inline-block';
      // Reset stars
      selectedRatings[sessionId] = 0;
      document.getElementById('rating-' + sessionId).value = 0;
      updateStars(sessionId, 0);
      document.getElementById('hint-' + sessionId).textContent = 'Click a star to rate';
    }

    function validateFeedback(sessionId) {
      var rating = document.getElementById('rating-' + sessionId).value;
      if (rating == 0) {
        alert('Please select a star rating.');
        return false;
      }
      return true;
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