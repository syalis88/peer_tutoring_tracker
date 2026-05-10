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

//Quick book
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'book') {
    $tutor_id     = (int)$_POST['tutor_id'];
    $session_date = trim($_POST['session_date']);
    $duration     = (int)$_POST['duration'];

    if (empty($tutor_id) || empty($session_date) || $duration <= 0) {
        $error = "Please fill in all fields.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'tutor'");
        $check->execute([$tutor_id]);
        if ($check->rowCount() == 0) {
            $error = "Invalid tutor.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO sessions (tutor_id, student_id, session_date, duration, status)
                VALUES (?, ?, ?, ?, 'scheduled')
            ");
            $stmt->execute([$tutor_id, $user_id, $session_date, $duration]);
            $success = "Session booked successfully!";
        }
    }
}

// Fetch subjects for filter dropdown
// Only show subjects that at least one tutor actually teaches
$subj_stmt = $conn->prepare("
    SELECT DISTINCT s.subject_id, s.subject_name
    FROM subjects s
    JOIN tutor_subjects ts ON ts.subject_id = s.subject_id
    ORDER BY s.subject_name ASC
");
$subj_stmt->execute();
$all_subjects = $subj_stmt->fetchAll();

//Filters
$search  = isset($_GET['search'])  ? trim($_GET['search']) : '';
$subject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

//Fetch tutors
$where  = "WHERE u.role = 'tutor' AND u.status = 'active'";
$params = [];

if ($search != '') {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = '%' . $search . '%'; 
    $params[] = '%' . $search . '%';
}

if ($subject > 0) {
    $where .= " AND EXISTS (
        SELECT 1 FROM tutor_subjects ts
        WHERE ts.tutor_id = u.user_id AND ts.subject_id = ?
    )";
    $params[] = $subject;
}

$stmt = $conn->prepare("
    SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) as name,
       COUNT(DISTINCT s.session_id) as total_sessions,
       ROUND(AVG(f.rating), 1) as avg_rating,
       COUNT(DISTINCT f.feedback_id) as review_count
    FROM users u
    LEFT JOIN sessions s ON s.tutor_id = u.user_id AND s.status = 'completed'
    LEFT JOIN feedback f ON f.session_id = s.session_id
    $where
    GROUP BY u.user_id, u.first_name, u.last_name
    ORDER BY avg_rating DESC, total_sessions DESC
");
$stmt->execute($params);
$tutors = $stmt->fetchAll();

//Fetch subjects per tutor for display on cards
$tutor_subjects_map = [];
if (count($tutors) > 0) {
    $tutor_ids = implode(',', array_column($tutors, 'user_id'));
    $ts_stmt = $conn->prepare("
        SELECT ts.tutor_id, s.subject_name
        FROM tutor_subjects ts
        JOIN subjects s ON s.subject_id = ts.subject_id
        WHERE ts.tutor_id IN ($tutor_ids)
        ORDER BY s.subject_name ASC
    ");
    $ts_stmt->execute();
    foreach ($ts_stmt->fetchAll() as $row) {
        $tutor_subjects_map[$row['tutor_id']][] = $row['subject_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Find Tutors — Peer Tutoring Tracker</title>
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
      <a href="find_tutors.php" class="nav-item active">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <span>Find Tutors</span>
      </a>
      <a href="student_feedback.php" class="nav-item">
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
        <h1 class="page-title">Find Tutors</h1>
        <p class="page-sub">Browse available tutors and book a session.</p>
      </div>
    </div>

    <div class="page-content">
    <?php if ($success != "") { ?>
      <div class="page-alert success show"><?php echo $success; ?></div>
    <?php } ?>
    <?php if ($error != "") { ?>
      <div class="page-alert error show"><?php echo $error; ?></div>
    <?php } ?>

    <!-- Filter bar -->
    <form method="GET" action="find_tutors.php" id="filterForm">
      <div class="filter-bar">
        <input type="text" name="search" placeholder="Search by tutor name..."
          value="<?php echo htmlspecialchars($search); ?>">
        <select name="subject" onchange="document.getElementById('filterForm').submit()">
          <option value="0">All Subjects</option>
          <?php foreach ($all_subjects as $s) { ?>
            <option value="<?php echo $s['subject_id']; ?>"
              <?php echo $subject == $s['subject_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($s['subject_name']); ?>
            </option>
          <?php } ?>
        </select>
        <button type="submit" class="action-btn">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Search
        </button>
        <?php if ($search != '' || $subject > 0) { ?>
          <a href="find_tutors.php" class="action-btn">Clear</a>
        <?php } ?>
      </div>
    </form>

    <!-- Tutor cards -->
    <?php if (count($tutors) == 0) { ?>
      <div class="empty-page">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <p>No tutors found<?php echo $subject > 0 ? ' teaching that subject' : ''; ?>.</p>
      </div>
    <?php } else { ?>
      <div class="tutor-grid">
        <?php foreach ($tutors as $t) {
          $subjects_taught = isset($tutor_subjects_map[$t['user_id']]) ? $tutor_subjects_map[$t['user_id']] : [];
          $rating = $t['avg_rating'] ? $t['avg_rating'] : 0;
        ?>
          <div class="tutor-card">
            <div class="tutor-card-top">
              <div class="tutor-avatar-lg"><?php echo strtoupper(substr($t['name'], 0, 1)); ?></div>
              <div>
                <h3 class="tutor-name"><?php echo htmlspecialchars($t['name']); ?></h3>
                <div class="tutor-meta">
                  <?php echo $rating > 0 ? number_format($rating, 1) . ' ★ · ' . $t['review_count'] . ' reviews' : 'No reviews yet'; ?>
                </div>
              </div>
            </div>

            <!-- Subject tags -->
            <?php if (count($subjects_taught) > 0) { ?>
              <div class="subject-tags">
                <?php foreach ($subjects_taught as $subj_name) { ?>
                  <span class="subject-tag"><?php echo htmlspecialchars($subj_name); ?></span>
                <?php } ?>
              </div>
            <?php } ?>

            <div class="tutor-stats">
              <div class="tutor-stat">
                <span class="tutor-stat-value"><?php echo $t['total_sessions']; ?></span>
                <span class="tutor-stat-label">Sessions</span>
              </div>
              <div class="tutor-stat">
                <span class="tutor-stat-value"><?php echo $rating > 0 ? number_format($rating, 1) : '—'; ?></span>
                <span class="tutor-stat-label">Rating</span>
              </div>
              <div class="tutor-stat">
                <span class="tutor-stat-value"><?php echo $t['review_count']; ?></span>
                <span class="tutor-stat-label">Reviews</span>
              </div>
            </div>

            <button type="button" class="btn-book"
              onclick="openBook(<?php echo $t['user_id']; ?>, '<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>')">
              Book a Session
            </button>
          </div>
        <?php } ?>
      </div>
    <?php } ?>
    
    </div>
  </main>

  <!-- Book modal -->
  <div class="modal-overlay" id="bookModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Book a Session</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('bookModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">
        Booking a session with <strong id="bookTutorName"></strong>
      </p>
      <form action="find_tutors.php" method="POST" id="bookForm">
        <input type="hidden" name="action" value="book">
        <input type="hidden" name="tutor_id" id="bookTutorId">
        <div class="modal-field-row">
          <div class="field">
            <label for="session_date">Date & Time</label>
            <input type="datetime-local" id="session_date" name="session_date" required>
            <div class="error-msg" id="err-date">Required.</div>
          </div>
          <div class="field">
            <label for="duration">Duration</label>
            <select id="duration" name="duration" required>
              <option value="" disabled selected>Select</option>
              <option value="30">30 min</option>
              <option value="45">45 min</option>
              <option value="60">60 min</option>
              <option value="90">90 min</option>
              <option value="120">120 min</option>
            </select>
            <div class="error-msg" id="err-duration">Required.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="document.getElementById('bookModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn-confirm">Book Session</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openBook(id, name) {
      document.getElementById('bookTutorId').value = id;
      document.getElementById('bookTutorName').textContent = name;
      document.getElementById('bookModal').classList.add('open');
    }

    document.querySelectorAll('.modal-overlay').forEach(function(o) {
      o.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
      });
    });

    document.getElementById('bookForm').addEventListener('submit', function(e) {
      var hasError = false;
      var dt = document.getElementById('session_date');
      var dtErr = document.getElementById('err-date');
      if (!dt.value) { dtErr.style.display = 'block'; hasError = true; } else { dtErr.style.display = 'none'; }
      var dur = document.getElementById('duration');
      var durErr = document.getElementById('err-duration');
      if (!dur.value) { durErr.style.display = 'block'; hasError = true; } else { durErr.style.display = 'none'; }
      if (hasError) e.preventDefault();
    });

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
