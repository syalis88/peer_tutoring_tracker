<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'tutor') {
    header("Location: ../account/login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? explode(" ", $_SESSION['user_name'])[0];

// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch unique students with stats
$where  = "WHERE s.tutor_id = ?";
$params = [$user_id];

if ($search != '') {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$stmt = $conn->prepare("
    SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) as name, u.year_level,
           COUNT(s.session_id) as total_sessions,
           SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed,
           MAX(s.session_date) as last_session
    FROM sessions s
    JOIN users u ON s.student_id = u.user_id
    $where
    GROUP BY u.user_id, u.first_name, u.last_name, u.year_level
    ORDER BY last_session DESC
");

$stmt->execute($params);
$students = $stmt->fetchAll();

// Fetch session history per student for the drawer
$history_map = [];
if (count($students) > 0) {
    $student_ids = implode(',', array_column($students, 'user_id'));
    $h_stmt = $conn->prepare("
        SELECT session_id, student_id, session_date, duration, status
        FROM sessions
        WHERE tutor_id = ? AND student_id IN ($student_ids)
        ORDER BY session_date DESC
    ");
    $h_stmt->execute([$user_id]);
    foreach ($h_stmt->fetchAll() as $row) {
        $history_map[$row['student_id']][] = $row;
    }
}

function status_badge($status) {
    if ($status == 'completed') return '<span class="badge badge-success">Completed</span>';
    if ($status == 'cancelled') return '<span class="badge badge-danger">Cancelled</span>';
    return '<span class="badge badge-info">Scheduled</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Students — Peer Tutoring Tracker</title>
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
      <a href="tutor_dashboard.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Dashboard</span>
      </a>
      <a href="tutor_sessions.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span>My Sessions</span>
      </a>
      <a href="my_students.php" class="nav-item active">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>My Students</span>
      </a>
      <a href="schedule.php" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>My Schedule</span>
      </a>
      <a href="tutor_feedback.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Feedback</span>
      </a>
      <a href="tutor_profile.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span>
      </a>
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
        <h1 class="page-title">My Students</h1>
        <p class="page-sub">View your students and their session history.</p>
      </div>
    </div>

    <div class="page-content">
    <!-- Search -->
    <form method="GET" action="my_students.php" id="filterForm">
      <div class="filter-bar">
        <input type="text" name="search" placeholder="Search by student name..."
          value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="action-btn">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Search
        </button>
        <?php if ($search != '') { ?><a href="my_students.php" class="action-btn">Clear</a><?php } ?>
      </div>
    </form>

    <?php if (count($students) == 0) { ?>
      <div class="empty-page">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <p>No students found<?php echo $search != '' ? ' matching your search.' : ' yet. Create a session to get started.'; ?></p>
      </div>
    <?php } else { ?>
      <div class="student-grid">
        <?php foreach ($students as $st) { ?>
          <div class="student-card">
            <div class="student-card-top">
              <div class="student-avatar"><?php echo strtoupper(substr($st['name'], 0, 1)); ?></div>
              <div>
                <div class="student-name"><?php echo htmlspecialchars($st['name']); ?></div>
                <div class="student-meta">
                  <?php echo $st['year_level'] ? 'Year ' . $st['year_level'] : 'Tutor'; ?> &middot;
                  Last session: <?php echo $st['last_session'] ? date('M d, Y', strtotime($st['last_session'])) : 'N/A'; ?>
                </div>
              </div>
            </div>

            <div class="student-stats">
              <div class="student-stat">
                <span class="student-stat-value"><?php echo $st['total_sessions']; ?></span>
                <span class="student-stat-label">Total</span>
              </div>
              <div class="student-stat">
                <span class="student-stat-value"><?php echo $st['completed']; ?></span>
                <span class="student-stat-label">Completed</span>
              </div>
              <div class="student-stat">
                <span class="student-stat-value"><?php echo $st['total_sessions'] - $st['completed']; ?></span>
                <span class="student-stat-label">Remaining</span>
              </div>
            </div>

            <button type="button" class="btn-view-history"
              onclick="toggleHistory(<?php echo $st['user_id']; ?>, this)">
              View Session History
            </button>

            <!-- Session history drawer -->
            <div class="session-history" id="history-<?php echo $st['user_id']; ?>">
              <?php
              $history = isset($history_map[$st['user_id']]) ? $history_map[$st['user_id']] : [];
              if (count($history) == 0) { ?>
                <p style="font-size:13px; color:var(--muted);">No sessions found.</p>
              <?php } else {
                foreach ($history as $h) { ?>
                  <div class="history-item">
                    <div class="history-date"><?php echo date('M d, Y', strtotime($h['session_date'])); ?></div>
                    <div class="history-meta"><?php echo date('g:i A', strtotime($h['session_date'])); ?> &middot; <?php echo $h['duration']; ?> min</div>
                    <?php echo status_badge($h['status']); ?>
                  </div>
                <?php }
              } ?>
            </div>
          </div>
        <?php } ?>
      </div>
    <?php } ?>
  </div>
  </main>

  <script>
    function toggleHistory(id, btn) {
      var drawer = document.getElementById('history-' + id);
      drawer.classList.toggle('open');
      btn.textContent = drawer.classList.contains('open') ? 'Hide Session History' : 'View Session History';
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
