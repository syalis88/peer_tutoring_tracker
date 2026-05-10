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

//Create session
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'create') {
    $student_id   = (int)$_POST['student_id'];
    $session_date = trim($_POST['session_date']);
    $duration     = (int)$_POST['duration'];

    if (empty($student_id) || empty($session_date) || $duration <= 0) {
        $error = "Please fill in all fields.";
    } else {
       $students_stmt = $conn->prepare("
        SELECT user_id, CONCAT(first_name, ' ', last_name) as name
        FROM users WHERE role = 'student' AND status = 'active'
        ORDER BY first_name ASC
        ");

        $check->execute([$student_id]);
        if ($check->rowCount() == 0) {
            $error = "Invalid student selected.";
        } else {
            $stmt = $conn->prepare("INSERT INTO sessions (tutor_id, student_id, session_date, duration, status) VALUES (?, ?, ?, ?, 'scheduled')");
            $stmt->execute([$user_id, $student_id, $session_date, $duration]);
            $success = "Session created successfully!";
        }
    }
}

//Mark complete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'complete') {
    $session_id = (int)$_POST['session_id'];
    $check = $conn->prepare("SELECT session_id FROM sessions WHERE session_id = ? AND tutor_id = ? AND status = 'scheduled'");
    $check->execute([$session_id, $user_id]);
    if ($check->rowCount() > 0) {
        $stmt = $conn->prepare("UPDATE sessions SET status = 'completed' WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $success = "Session marked as completed.";
    } else {
        $error = "Could not update that session.";
    }
}

//Cancel session
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'cancel') {
    $session_id = (int)$_POST['session_id'];
    $check = $conn->prepare("SELECT session_id FROM sessions WHERE session_id = ? AND tutor_id = ? AND status = 'scheduled'");
    $check->execute([$session_id, $user_id]);
    if ($check->rowCount() > 0) {
        $stmt = $conn->prepare("UPDATE sessions SET status = 'cancelled' WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $success = "Session cancelled.";
    } else {
        $error = "Could not cancel that session.";
    }
}

//Students list for create modal
$students_stmt = $conn->prepare("
    SELECT user_id, CONCAT(first_name, ' ', last_name) as name
    FROM users WHERE role = 'student' AND status = 'active'
    ORDER BY first_name ASC
");
$students_stmt->execute();
$students = $students_stmt->fetchAll();

//Calendar dots
$cal_stmt = $conn->prepare("SELECT session_date FROM sessions WHERE tutor_id = ? AND status != 'cancelled'");
$cal_stmt->execute([$user_id]);
$session_dates = [];
foreach ($cal_stmt->fetchAll() as $row) {
    $session_dates[] = date('Y-m-d', strtotime($row['session_date']));
}

//Filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date   = isset($_GET['date'])   ? $_GET['date'] : '';

//Pagination
$per_page     = 8;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset       = ($current_page - 1) * $per_page;

//Build query
$where  = "WHERE s.tutor_id = ?";
$params = [$user_id];

if ($filter_status != 'all') { $where .= " AND s.status = ?"; $params[] = $filter_status; }

if ($filter_search != '') {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = '%' . $filter_search . '%';
    $params[] = '%' . $filter_search . '%';
}

if ($filter_date   != '')    { $where .= " AND DATE(s.session_date) = ?"; $params[] = $filter_date; }

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions s JOIN users u ON s.student_id = u.user_id $where");
$count_stmt->execute($params);
$total_rows  = $count_stmt->fetch()['total'];
$total_pages = ceil($total_rows / $per_page);

$stmt = $conn->prepare("
    SELECT s.session_id, s.session_date, s.duration, s.status,
       CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM sessions s JOIN users u ON s.student_id = u.user_id
    $where ORDER BY s.session_date DESC LIMIT $per_page OFFSET $offset
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
  <title>My Sessions — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/tutor_dashboard.css"/>
  <link rel="stylesheet" href="../assets/sessions.css"/>
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
      <a href="tutor_sessions.php" class="nav-item active"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span>My Sessions</span></a>
      <a href="my_students.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>My Students</span></a>
      <a href="schedule.php" class="nav-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>My Schedule</span></a>
      <a href="tutor_feedback.php" class="nav-item"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Feedback</span></a>
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
        <h1 class="page-title">My Sessions</h1>
        <p class="page-sub">Manage your availability and upcoming sessions.</p>
      </div>
      <button type="button" class="btn-primary" onclick="document.getElementById('createModal').classList.add('open')">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Session
      </button>
    </div>

    <?php if ($success != "") { ?><div class="page-alert success show"><?php echo $success; ?></div><?php } ?>
    <?php if ($error   != "") { ?><div class="page-alert error show"><?php echo $error; ?></div><?php } ?>

    <div class="sessions-layout">

      <!-- Mini calendar -->
      <div class="calendar-card">
        <div class="cal-header">
          <span class="cal-title" id="calTitle"></span>
          <div class="cal-nav">
            <button class="cal-nav-btn" onclick="changeMonth(-1)"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
            <button class="cal-nav-btn" onclick="changeMonth(1)"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
          </div>
        </div>
        <div class="cal-grid" id="calGrid"></div>
        <div class="cal-legend">
          <div class="legend-item"><div class="legend-dot today"></div> Today</div>
          <div class="legend-item"><div class="legend-dot session"></div> Has session</div>
          <div class="legend-item"><div class="legend-dot selected"></div> Selected</div>
        </div>
        <?php if ($filter_date != '') { ?>
          <div style="margin-top:12px; text-align:center;">
            <a href="tutor_sessions.php" class="action-btn" style="width:100%; justify-content:center;">Clear date filter</a>
          </div>
        <?php } ?>
      </div>

      <div class="sessions-right">
        <form method="GET" action="tutor_sessions.php" id="filterForm">
          <?php if ($filter_date != '') { ?><input type="hidden" name="date" value="<?php echo htmlspecialchars($filter_date); ?>"><?php } ?>
          <div class="filter-bar">
            <input type="text" name="search" placeholder="Search by student name..." value="<?php echo htmlspecialchars($filter_search); ?>">
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
            <?php if ($filter_search != '' || $filter_status != 'all' || $filter_date != '') { ?>
              <a href="tutor_sessions.php" class="action-btn">Clear</a>
            <?php } ?>
          </div>
        </form>

        <?php if ($filter_date != '') { ?>
          <p style="font-size:13px; color:var(--muted); margin-bottom:12px;">
            Showing sessions on <strong><?php echo date('F j, Y', strtotime($filter_date)); ?></strong>
          </p>
        <?php } ?>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Date & Time</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($sessions) == 0) { ?>
                <tr><td colspan="5">
                  <div class="table-empty">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    No sessions found.
                  </div>
                </td></tr>
              <?php } else { ?>
                <?php foreach ($sessions as $s) {
                  $row_date    = date('Y-m-d', strtotime($s['session_date']));
                  $highlighted = ($filter_date != '' && $row_date == $filter_date) ? 'highlighted' : '';
                ?>
                <tr class="<?php echo $highlighted; ?>">
                  <td>
                    <div class="td-name"><?php echo htmlspecialchars($s['student_name']); ?></div>
                    <div class="td-meta">Student</div>
                  </td>
                  <td>
                    <div class="td-name"><?php echo date('M d, Y', strtotime($s['session_date'])); ?></div>
                    <div class="td-meta"><?php echo date('g:i A', strtotime($s['session_date'])); ?></div>
                  </td>
                  <td><?php echo $s['duration']; ?> min</td>
                  <td><?php echo status_badge($s['status']); ?></td>
                  <td>
                    <div class="actions-cell">
                      <?php if ($s['status'] == 'scheduled') { ?>
                        <button type="button" class="action-btn success"
                          onclick="openComplete(<?php echo $s['session_id']; ?>, '<?php echo htmlspecialchars($s['student_name'], ENT_QUOTES); ?>')">
                          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Done
                        </button>
                        <button type="button" class="action-btn danger"
                          onclick="openCancel(<?php echo $s['session_id']; ?>, '<?php echo htmlspecialchars($s['student_name'], ENT_QUOTES); ?>', '<?php echo date('M d, Y g:i A', strtotime($s['session_date'])); ?>')">
                          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Cancel
                        </button>
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
                  <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($filter_search); ?><?php echo $filter_date ? '&date=' . urlencode($filter_date) : ''; ?>"
                    class="page-btn <?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php } ?>
              </div>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </main>

  <!-- Create modal -->
  <div class="modal-overlay" id="createModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">New Session</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('createModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form action="tutor_sessions.php" method="POST" id="createForm">
        <input type="hidden" name="action" value="create">
        <div class="field">
          <label>Student</label>
          <select name="student_id" id="student_id" required>
            <option value="" disabled selected>Select a student</option>
            <?php foreach ($students as $st) { ?>
              <option value="<?php echo $st['user_id']; ?>"><?php echo htmlspecialchars($st['name']); ?></option>
            <?php } ?>
          </select>
          <div class="error-msg" id="err-student">Please select a student.</div>
        </div>
        <div class="modal-field-row">
          <div class="field">
            <label>Date & Time</label>
            <input type="datetime-local" name="session_date" id="session_date" required>
            <div class="error-msg" id="err-date">Required.</div>
          </div>
          <div class="field">
            <label>Duration</label>
            <select name="duration" id="duration" required>
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
          <button type="button" class="btn-cancel" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn-confirm">Create Session</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Mark complete modal -->
  <div class="modal-overlay" id="completeModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Mark as Completed</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('completeModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">
        Mark the session with <strong id="completeStudent"></strong> as completed?
      </p>
      <form action="tutor_sessions.php" method="POST">
        <input type="hidden" name="action" value="complete">
        <input type="hidden" name="session_id" id="completeSessionId">
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="document.getElementById('completeModal').classList.remove('open')">Go Back</button>
          <button type="submit" class="btn-confirm">Yes, Mark Completed</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Cancel modal -->
  <div class="modal-overlay" id="cancelModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Cancel Session</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('cancelModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">
        Cancel the session with <strong id="cancelStudent"></strong> on <strong id="cancelDate"></strong>?
      </p>
      <form action="tutor_sessions.php" method="POST">
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="session_id" id="cancelSessionId">
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="document.getElementById('cancelModal').classList.remove('open')">Go Back</button>
          <button type="submit" class="btn-confirm danger">Yes, Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    var sessionDates = <?php echo json_encode(array_unique($session_dates)); ?>;
    var selectedDate = '<?php echo $filter_date; ?>';
    var today = new Date();
    var curYear = today.getFullYear();
    var curMonth = today.getMonth();
    var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function renderCalendar(year, month) {
      document.getElementById('calTitle').textContent = monthNames[month] + ' ' + year;
      var grid = document.getElementById('calGrid');
      grid.innerHTML = '';
      ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(function(d) {
        var el = document.createElement('div'); el.className = 'cal-day-label'; el.textContent = d; grid.appendChild(el);
      });
      var firstDay = new Date(year, month, 1).getDay();
      var daysInMon = new Date(year, month + 1, 0).getDate();
      for (var i = 0; i < firstDay; i++) { var b = document.createElement('div'); b.className = 'cal-day empty'; grid.appendChild(b); }
      for (var d = 1; d <= daysInMon; d++) {
        var el = document.createElement('div'); el.className = 'cal-day'; el.textContent = d;
        var mm = String(month + 1).padStart(2, '0');
        var dd = String(d).padStart(2, '0');
        var dateStr = year + '-' + mm + '-' + dd;
        if (year === today.getFullYear() && month === today.getMonth() && d === today.getDate()) el.classList.add('today');
        if (selectedDate === dateStr) el.classList.add('selected');
        if (sessionDates.indexOf(dateStr) !== -1) el.classList.add('has-session');
        el.setAttribute('data-date', dateStr);
        el.addEventListener('click', function() { window.location.href = 'tutor_sessions.php?date=' + this.getAttribute('data-date'); });
        grid.appendChild(el);
      }
    }

    function changeMonth(dir) {
      curMonth += dir;
      if (curMonth > 11) { curMonth = 0; curYear++; }
      if (curMonth < 0)  { curMonth = 11; curYear--; }
      renderCalendar(curYear, curMonth);
    }

    if (selectedDate !== '') {
      var parts = selectedDate.split('-');
      curYear = parseInt(parts[0]); curMonth = parseInt(parts[1]) - 1;
    }
    renderCalendar(curYear, curMonth);

    function openComplete(id, student) {
      document.getElementById('completeSessionId').value = id;
      document.getElementById('completeStudent').textContent = student;
      document.getElementById('completeModal').classList.add('open');
    }
    function openCancel(id, student, date) {
      document.getElementById('cancelSessionId').value = id;
      document.getElementById('cancelStudent').textContent = student;
      document.getElementById('cancelDate').textContent   = date;
      document.getElementById('cancelModal').classList.add('open');
    }

    document.querySelectorAll('.modal-overlay').forEach(function(o) {
      o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
    });

    if (new URLSearchParams(window.location.search).get('action') === 'new') {
      document.getElementById('createModal').classList.add('open');
    }

    document.getElementById('createForm').addEventListener('submit', function(e) {
      var hasError = false;
      var s = document.getElementById('student_id');
      var sErr = document.getElementById('err-student');
      if (!s.value) { sErr.style.display = 'block'; hasError = true; } else { sErr.style.display = 'none'; }
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