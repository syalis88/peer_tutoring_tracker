<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../account/login.php");
    exit();
}

$first_name = $_SESSION['first_name'] 
    ?? (isset($_SESSION['user_name']) ? explode(" ", $_SESSION['user_name'])[0] : 'Admin');

$success = "";
$error   = "";

//Cancel a session
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'cancel') {
    $sid = (int)$_POST['session_id'];
    $stmt = $conn->prepare("UPDATE sessions SET status = 'cancelled' WHERE session_id = ? AND status = 'scheduled'");
    $stmt->execute([$sid]);
    if ($stmt->rowCount() > 0) {
        $success = "Session cancelled.";
    } else {
        $error = "Session could not be cancelled — it may already be completed or cancelled.";
    }
}

//Uncancel a session
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'uncancel') {
    $sid = (int)$_POST['session_id'];
    $stmt = $conn->prepare("UPDATE sessions SET status = 'scheduled' WHERE session_id = ? AND status = 'cancelled'");
    $stmt->execute([$sid]);
    if ($stmt->rowCount() > 0) {
        $success = "Session restored to scheduled.";
    } else {
        $error = "Could not restore that session.";
    }
}

//Filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';

$per_page     = 12;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset       = ($current_page - 1) * $per_page;

$where  = "WHERE 1=1";
$params = [];

if ($filter_status != 'all') {
    $where .= " AND s.status = ?";
    $params[] = $filter_status;
}
if ($filter_search != '') {
    $where .= " AND (CONCAT(t.first_name, ' ', t.last_name) LIKE ? OR CONCAT(st.first_name, ' ', st.last_name) LIKE ?)";
    $params[] = '%' . $filter_search . '%';
    $params[] = '%' . $filter_search . '%';
}

$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total FROM sessions s
    JOIN users t  ON s.tutor_id   = t.user_id
    JOIN users st ON s.student_id = st.user_id
    $where
");
$count_stmt->execute($params);
$total_rows  = $count_stmt->fetch()['total'];
$total_pages = ceil($total_rows / $per_page);

$stmt = $conn->prepare("
    SELECT s.session_id, s.session_date, s.duration, s.status,
           CONCAT(t.first_name, ' ', t.last_name) as tutor_name,
           CONCAT(st.first_name, ' ', st.last_name) as student_name
    FROM sessions s
    JOIN users t  ON s.tutor_id   = t.user_id
    JOIN users st ON s.student_id = st.user_id
    $where
    ORDER BY s.session_date DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$sessions = $stmt->fetchAll();

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
  <title>Sessions — Admin</title>
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
      <a href="admin_sessions.php" class="nav-item active"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span>Sessions</span></a>
      <a href="admin_subjects.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span>Subjects</span></a>
      <a href="admin_feedback.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Feedback</span></a>
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
        <h1 class="page-title">Sessions</h1>
        <p class="page-sub">View and manage all tutoring sessions on the platform.</p>
      </div>
    </div>

    <div class="page-content">

      <?php if ($success != "") { ?><div class="page-alert success show"><?php echo $success; ?></div><?php } ?>
      <?php if ($error   != "") { ?><div class="page-alert error show"><?php echo $error; ?></div><?php } ?>

      <form method="GET" action="admin_sessions.php" id="filterForm">
        <div class="filter-bar">
          <input type="text" name="search" placeholder="Search by tutor or student name..."
            value="<?php echo htmlspecialchars($filter_search); ?>">
          <select name="status" onchange="document.getElementById('filterForm').submit()">
            <option value="all"       <?php echo $filter_status == 'all'       ? 'selected' : ''; ?>>All Statuses</option>
            <option value="scheduled" <?php echo $filter_status == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
            <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
          </select>
          <button type="submit" class="action-btn">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Search
          </button>
          <?php if ($filter_search != '' || $filter_status != 'all') { ?>
            <a href="admin_sessions.php" class="action-btn">Clear</a>
          <?php } ?>
          <div style="flex:1;"></div>
          <span style="font-size:13px; color:var(--muted);"><?php echo $total_rows; ?> session<?php echo $total_rows != 1 ? 's' : ''; ?></span>
        </div>
      </form>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tutor</th>
              <th>Student</th>
              <th>Date & Time</th>
              <th>Duration</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($sessions) == 0) { ?>
              <tr><td colspan="6" style="text-align:center; padding:32px; color:var(--muted); font-size:14px;">No sessions found.</td></tr>
            <?php } else { ?>
              <?php foreach ($sessions as $s) { ?>
                <tr>
                  <td><div class="td-name"><?php echo htmlspecialchars($s['tutor_name']); ?></div></td>
                  <td><div class="td-name"><?php echo htmlspecialchars($s['student_name']); ?></div></td>
                  <td>
                    <div class="td-name"><?php echo date('M d, Y', strtotime($s['session_date'])); ?></div>
                    <div class="td-meta"><?php echo date('g:i A', strtotime($s['session_date'])); ?></div>
                  </td>
                  <td><?php echo $s['duration']; ?> min</td>
                  <td><?php echo status_badge($s['status']); ?></td>
                  <td>
                    <div class="actions-cell">
                      <?php if ($s['status'] == 'scheduled') { ?>
                        <button type="button" class="action-btn danger"
                          onclick="openCancel(
                            <?php echo $s['session_id']; ?>,
                            '<?php echo htmlspecialchars($s['tutor_name'], ENT_QUOTES); ?>',
                            '<?php echo htmlspecialchars($s['student_name'], ENT_QUOTES); ?>',
                            '<?php echo date('M d, Y g:i A', strtotime($s['session_date'])); ?>'
                          )">
                          Cancel
                        </button>
                      <?php } else if ($s['status'] == 'cancelled') { ?>
                        <form action="admin_sessions.php" method="POST" style="display:inline;">
                          <input type="hidden" name="action" value="uncancel">
                          <input type="hidden" name="session_id" value="<?php echo $s['session_id']; ?>">
                          <button type="submit" class="action-btn success">Uncancel</button>
                        </form>
                      <?php } else { ?>
                        <span style="font-size:12px; color:var(--muted);">—</span>
                      <?php } ?>
                    </div>
                  </td>
                </tr>
              <?php } ?>
            <?php } ?>
          </tbody>
        </table>

        <?php if ($total_pages > 1) { ?>
          <div class="pagination">
            <span>Showing <?php echo min($offset + 1, $total_rows); ?>–<?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?></span>
            <div class="page-btns">
              <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($filter_search); ?>"
                  class="page-btn <?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
              <?php } ?>
            </div>
          </div>
        <?php } ?>
      </div>

    </div>
  </main>

  <!-- Cancel confirm modal -->
  <div class="modal-overlay" id="cancelModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Cancel Session</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('cancelModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">
        Cancel the session between <strong id="cancelTutor"></strong> and
        <strong id="cancelStudent"></strong> on <strong id="cancelDate"></strong>?
        You can undo this afterwards.
      </p>
      <form action="admin_sessions.php" method="POST">
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="session_id" id="cancelSessionId">
        <div class="modal-footer">
          <button type="button" class="btn-cancel"
            onclick="document.getElementById('cancelModal').classList.remove('open')">
            Go Back
          </button>
          <button type="submit" class="btn-confirm danger">Yes, Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openCancel(id, tutor, student, date) {
      document.getElementById('cancelSessionId').value = id;
      document.getElementById('cancelTutor').textContent = tutor;
      document.getElementById('cancelStudent').textContent = student;
      document.getElementById('cancelDate').textContent = date;
      document.getElementById('cancelModal').classList.add('open');
    }

    document.querySelectorAll('.modal-overlay').forEach(function(o) {
      o.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
      });
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