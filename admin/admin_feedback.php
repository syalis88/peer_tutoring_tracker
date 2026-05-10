<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../account/login.php");
    exit();
}

$first_name = $_SESSION['first_name'] ?? explode(" ", $_SESSION['user_name'])[0];

//Delete feedback
$success = "";
$error   = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'delete') {
    $fid = (int)$_POST['feedback_id'];
    $stmt = $conn->prepare("DELETE FROM feedback WHERE feedback_id = ?");
    $stmt->execute([$fid]);
    $success = "Feedback removed.";
}

//Filters
$filter_rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';

$per_page     = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset       = ($current_page - 1) * $per_page;

$where  = "WHERE 1=1";
$params = [];

if ($filter_rating > 0) {
    $where .= " AND f.rating = ?";
    $params[] = $filter_rating;
}
if ($filter_search != '') {
    $where .= " AND (CONCAT(t.first_name, ' ', t.last_name) LIKE ? OR CONCAT(st.first_name, ' ', st.last_name) LIKE ?)";
    $params[] = '%' . $filter_search . '%';
    $params[] = '%' . $filter_search . '%';
}

$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total FROM feedback f
    JOIN sessions s  ON f.session_id  = s.session_id
    JOIN users t     ON s.tutor_id    = t.user_id
    JOIN users st    ON s.student_id  = st.user_id
    $where
");
$count_stmt->execute($params);
$total_rows  = $count_stmt->fetch()['total'];
$total_pages = ceil($total_rows / $per_page);

$stmt = $conn->prepare("
    SELECT f.feedback_id, f.rating, f.comments, f.tutor_response, f.responded_at,
           CONCAT(t.first_name, ' ', t.last_name) as tutor_name,
          CONCAT(st.first_name, ' ', st.last_name) as student_name,
           s.session_date, s.duration
    FROM feedback f
    JOIN sessions s  ON f.session_id  = s.session_id
    JOIN users t     ON s.tutor_id    = t.user_id
    JOIN users st    ON s.student_id  = st.user_id
    $where
    ORDER BY s.session_date DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

// Overall platform rating
$avg_stmt = $conn->query("SELECT ROUND(AVG(rating),1) as avg, COUNT(*) as total FROM feedback WHERE rating IS NOT NULL");
$avg_row   = $avg_stmt->fetch();
$avg_rating    = $avg_row['avg'] ?? 0;
$total_reviews = $avg_row['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Feedback — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/admin.css"/>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class=""></div>
      <span>Peer Tutoring Tracker Admin</span>
    </div>
    <nav class="sidebar-nav">
      <a href="admin_dashboard.php" class="nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
      <a href="admin_users.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Users</span></a>
      <a href="admin_sessions.php" class="nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span>Sessions</span></a>
      <a href="admin_subjects.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Subjects</span></a>
      <a href="admin_feedback.php" class="nav-item active"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Feedback</span></a>
      <a href="admin_reports.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg><span>Reports</span></a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="avatar"><?php echo strtoupper(substr($first_name, 0, 1)); ?></div>
        <div class="user-details">
          <span class="user-name"><?php echo htmlspecialchars($first_name); ?></span>
          <span class="user-role">Administrator</span>
        </div>
      </div>
     <a href="#" onclick="document.getElementById('logoutModal').classList.add('open'); return false;" class="logout-btn" title="Sign out">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 class="page-title">Feedback</h1>
        <p class="page-sub">View and moderate all student feedback across the platform.</p>
      </div>
      <!-- Platform rating summary -->
      <div class="platform-rating">
        <span class="platform-rating-num"><?php echo $avg_rating > 0 ? number_format($avg_rating, 1) : '—'; ?> ★</span>
        <span class="platform-rating-label">Platform avg · <?php echo $total_reviews; ?> reviews</span>
      </div>
    </div>

    <div class="page-content">
    <?php if ($success != "") { ?><div class="page-alert success show"><?php echo $success; ?></div><?php } ?>
    <?php if ($error   != "") { ?><div class="page-alert error show"><?php echo $error; ?></div><?php } ?>

    <form method="GET" action="admin_feedback.php" id="filterForm">
      <div class="filter-bar">
        <input type="text" name="search" placeholder="Search by tutor or student name..."
          value="<?php echo htmlspecialchars($filter_search); ?>">
        <select name="rating" onchange="document.getElementById('filterForm').submit()">
          <option value="0" <?php echo $filter_rating == 0 ? 'selected' : ''; ?>>All Ratings</option>
          <?php for ($r = 5; $r >= 1; $r--) { ?>
            <option value="<?php echo $r; ?>" <?php echo $filter_rating == $r ? 'selected' : ''; ?>><?php echo $r; ?> Star<?php echo $r != 1 ? 's' : ''; ?></option>
          <?php } ?>
        </select>
        <button type="submit" class="action-btn">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Search
        </button>
        <?php if ($filter_search != '' || $filter_rating > 0) { ?>
          <a href="admin_feedback.php" class="action-btn">Clear</a>
        <?php } ?>
      </div>
    </form>

    <!-- Feedback cards -->
    <?php if (count($feedbacks) == 0) { ?>
      <div style="text-align:center; padding:60px 20px; color:var(--muted); font-size:14px;">No feedback found.</div>
    <?php } else { ?>
      <div class="feedback-grid">
        <?php foreach ($feedbacks as $fb) { ?>
          <div class="feedback-card">
            <div class="feedback-card-header">
              <div>
                <div class="fb-names">
                  <span class="fb-student"><?php echo htmlspecialchars($fb['student_name']); ?></span>
                  <span class="fb-arrow">→</span>
                  <span class="fb-tutor"><?php echo htmlspecialchars($fb['tutor_name']); ?></span>
                </div>
                <div class="fb-meta">
                  <?php echo date('M d, Y', strtotime($fb['session_date'])); ?> &middot; <?php echo $fb['duration']; ?> min
                </div>
              </div>
              <?php if ($fb['rating']) { ?>
                <div class="fb-stars">
                  <?php for ($i = 1; $i <= 5; $i++) { ?>
                    <svg viewBox="0 0 24 24" class="fb-star <?php echo $i <= $fb['rating'] ? 'filled' : 'empty'; ?>">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>

            <?php if ($fb['comments']) { ?>
              <p class="fb-comment">"<?php echo htmlspecialchars($fb['comments']); ?>"</p>
            <?php } ?>

            <?php if ($fb['tutor_response']) { ?>
              <div class="fb-response">
                <span class="fb-response-label">Tutor responded</span>
                <p><?php echo htmlspecialchars($fb['tutor_response']); ?></p>
              </div>
            <?php } ?>

            <div class="fb-footer">
              <form action="admin_feedback.php" method="POST" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="feedback_id" value="<?php echo $fb['feedback_id']; ?>">
                <button type="submit" class="action-btn danger"
                  onclick="return confirm('Remove this feedback? This cannot be undone.')">
                  Remove
                </button>
              </form>
            </div>
          </div>
        <?php } ?>
      </div>

      <?php if ($total_pages > 1) { ?>
        <div class="pagination" style="margin-top:20px;">
          <span>Showing <?php echo min($offset + 1, $total_rows); ?>–<?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?></span>
          <div class="page-btns">
            <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
              <a href="?page=<?php echo $i; ?>&rating=<?php echo $filter_rating; ?>&search=<?php echo urlencode($filter_search); ?>"
                class="page-btn <?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php } ?>
          </div>
        </div>
      <?php } ?>
    <?php } ?>
  </div>
  </main>
  <script>
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