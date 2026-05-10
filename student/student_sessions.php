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

//Book a session
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'book') {

    $tutor_id     = (int)$_POST['tutor_id'];
    $session_date = trim($_POST['session_date']);
    $duration     = (int)$_POST['duration'];

    if (empty($tutor_id) || empty($session_date) || $duration <= 0) {
        $error = "Please fill in all fields.";
    } else {
        // Confirm tutor exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'tutor'");
        $check->execute([$tutor_id]);

        // Calculate end time of requested session
        $start     = new DateTime($session_date);
        $end       = (clone $start)->modify("+{$duration} minutes");
        $start_str = $start->format('Y-m-d H:i:s');
        $end_str   = $end->format('Y-m-d H:i:s');

        // Check if tutor is already booked during this time window
        $tutor_conflict = $conn->prepare("
            SELECT session_id FROM sessions
            WHERE tutor_id = ?
              AND status = 'scheduled'
              AND session_date < ?
              AND DATE_ADD(session_date, INTERVAL duration MINUTE) > ?
            LIMIT 1
        ");
        $tutor_conflict->execute([$tutor_id, $end_str, $start_str]);

        // Check if this student already has a session during this time window
        $student_conflict = $conn->prepare("
            SELECT session_id FROM sessions
            WHERE student_id = ?
              AND status = 'scheduled'
              AND session_date < ?
              AND DATE_ADD(session_date, INTERVAL duration MINUTE) > ?
            LIMIT 1
        ");
        $student_conflict->execute([$user_id, $end_str, $start_str]);

        if ($check->rowCount() == 0) {
            $error = "Invalid tutor selected.";
        } elseif ($tutor_conflict->fetch()) {
            $error = "This tutor is already booked during that time. Please choose a different time.";
        } elseif ($student_conflict->fetch()) {
            $error = "You already have a session scheduled during that time.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO sessions (tutor_id, student_id, session_date, duration, status)
                VALUES (?, ?, ?, ?, 'scheduled')
            ");
            $stmt->execute([$tutor_id, $user_id, $session_date, $duration]);

            // Get inserted session ID
            $session_id = $conn->lastInsertId();

            $att = $conn->prepare("
              INSERT INTO attendance (session_id, student_id, status)
              VALUES (?, ?, 'absent')
            ");
            $att->execute([$session_id, $user_id]);

            $success = "Session booked successfully!";
        }
    }
}

//Cancel a session
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'cancel') {

    $session_id = (int)$_POST['session_id'];

    $check = $conn->prepare("SELECT session_id FROM sessions WHERE session_id = ? AND student_id = ? AND status = 'scheduled'");
    $check->execute([$session_id, $user_id]);

    if ($check->rowCount() > 0) {
        $stmt = $conn->prepare("UPDATE sessions SET status = 'cancelled' WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $success = "Session cancelled.";
    } else {
        $error = "Could not cancel that session.";
    }
}

//Load tutors for booking modal
$tutors_stmt = $conn->prepare("
    SELECT user_id, CONCAT(first_name, ' ', last_name) as name
    FROM users WHERE role = 'tutor' AND status = 'active'
    ORDER BY first_name ASC
");
$tutors_stmt->execute();
$tutors = $tutors_stmt->fetchAll();

//Fetch all session dates for calendar dots
$cal_stmt = $conn->prepare("SELECT session_date FROM sessions WHERE student_id = ? AND status != 'cancelled'");
$cal_stmt->execute([$user_id]);
$cal_rows      = $cal_stmt->fetchAll();
$session_dates = [];
foreach ($cal_rows as $row) {
    $session_dates[] = date('Y-m-d', strtotime($row['session_date']));
}

//Filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date   = isset($_GET['date'])   ? $_GET['date']         : '';

//Pagination
$per_page     = 8;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset       = ($current_page - 1) * $per_page;

//Build query
$where  = "WHERE s.student_id = ?";
$params = [$user_id];

if ($filter_status != 'all') {
    $where .= " AND s.status = ?";
    $params[] = $filter_status;
}

if ($filter_search != '') {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = '%' . $filter_search . '%'; 
    $params[] = '%' . $filter_search . '%';
}

if ($filter_date != '') {
    $where .= " AND DATE(s.session_date) = ?";
    $params[] = $filter_date;
}

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions s JOIN users u ON s.tutor_id = u.user_id $where");
$count_stmt->execute($params);
$total_rows  = $count_stmt->fetch()['total'];
$total_pages = ceil($total_rows / $per_page);

$stmt = $conn->prepare("
    SELECT s.session_id, s.session_date, s.duration, s.status,
       CONCAT(u.first_name, ' ', u.last_name) as tutor_name
    FROM sessions s
    JOIN users u ON s.tutor_id = u.user_id
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
  <title>My Sessions — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/student_dashboard.css"/>
  <link rel="stylesheet" href="../assets/sessions.css"/>
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class=""></div>
      <span>Peer Tutoring Tracker</span>
    </div>
    <nav class="sidebar-nav">
      <a href="student_dashboard.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        <span>Dashboard</span>
      </a>
      <a href="student_sessions.php" class="nav-item active">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>My Sessions</span>
      </a>
      <a href="find_tutors.php" class="nav-item">
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
        <h1 class="page-title">My Sessions</h1>
        <p class="page-sub">View, book, and manage your tutoring sessions.</p>
      </div>
      <button type="button" class="btn-primary" onclick="document.getElementById('bookModal').classList.add('open')">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Book a Session
      </button>
    </div>

    <?php if ($success != "") { ?>
      <div class="page-alert success show"><?php echo $success; ?></div>
    <?php } ?>
    <?php if ($error != "") { ?>
      <div class="page-alert error show"><?php echo $error; ?></div>
    <?php } ?>

    <div class="sessions-layout">

      <!-- Mini calendar -->
      <div class="calendar-card">
        <div class="cal-header">
          <span class="cal-title" id="calTitle"></span>
          <div class="cal-nav">
            <button class="cal-nav-btn" onclick="changeMonth(-1)">
              <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <button class="cal-nav-btn" onclick="changeMonth(1)">
              <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
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
            <a href="student_sessions.php" class="action-btn" style="width:100%; justify-content:center;">Clear date filter</a>
          </div>
        <?php } ?>
      </div>

      <!-- Right: filter + table -->
      <div class="sessions-right">

        <form method="GET" action="student_sessions.php" id="filterForm">
          <?php if ($filter_date != '') { ?>
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
          <?php } ?>
          <div class="filter-bar">
            <input type="text" name="search" placeholder="Search by tutor name..."
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
            <?php if ($filter_search != '' || $filter_status != 'all' || $filter_date != '') { ?>
              <a href="student_sessions.php" class="action-btn">Clear</a>
            <?php } ?>
          </div>
        </form>

        <?php if ($filter_date != '') { ?>
          <p style="font-size:13px; color:var(--muted);">
            Showing sessions on <strong><?php echo date('F j, Y', strtotime($filter_date)); ?></strong>
          </p>
        <?php } ?>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Tutor</th>
                <th>Date & Time</th>
                <th>Duration</th>
                <th>Status</th>
                <th></th>
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
                    <div class="td-name"><?php echo htmlspecialchars($s['tutor_name']); ?></div>
                    <div class="td-meta">Tutor</div>
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
                        <button type="button" class="action-btn danger"
                          onclick="openCancel(<?php echo $s['session_id']; ?>, '<?php echo htmlspecialchars($s['tutor_name'], ENT_QUOTES); ?>', '<?php echo date('M d, Y g:i A', strtotime($s['session_date'])); ?>')">
                          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                          Cancel
                        </button>
                      <?php } ?>
                      <?php if ($s['status'] == 'completed') { ?>
                        <a href="student_feedback.php?session_id=<?php echo $s['session_id']; ?>" class="action-btn success">
                          <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                          Feedback
                        </a>
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
                    class="page-btn <?php echo $i == $current_page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                  </a>
                <?php } ?>
              </div>
            </div>
          <?php } ?>
        </div>

      </div>
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
      <form action="student_sessions.php" method="POST" id="bookForm">
        <input type="hidden" name="action" value="book">

        <div class="field">
          <label for="tutor_id">Tutor</label>
          <select id="tutor_id" name="tutor_id" required>
            <option value="" disabled selected>Select a tutor</option>
            <?php foreach ($tutors as $t) { ?>
              <option value="<?php echo $t['user_id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
            <?php } ?>
          </select>
          <div class="error-msg" id="err-tutor">Please select a tutor.</div>
        </div>

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
        Cancel your session with <strong id="cancelTutor"></strong> on <strong id="cancelDate"></strong>? This cannot be undone.
      </p>
      <form action="student_sessions.php" method="POST">
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
    // Session dates from PHP for calendar dots
    var sessionDates = <?php echo json_encode(array_unique($session_dates)); ?>;
    var selectedDate = '<?php echo $filter_date; ?>';

    var today    = new Date();
    var curYear  = today.getFullYear();
    var curMonth = today.getMonth();

    var monthNames = ['January','February','March','April','May','June',
                      'July','August','September','October','November','December'];

    function renderCalendar(year, month) {
      var title = document.getElementById('calTitle');
      var grid  = document.getElementById('calGrid');
      title.textContent = monthNames[month] + ' ' + year;
      grid.innerHTML = '';

      var days = ['Su','Mo','Tu','We','Th','Fr','Sa'];
      days.forEach(function(d) {
        var el = document.createElement('div');
        el.className = 'cal-day-label';
        el.textContent = d;
        grid.appendChild(el);
      });

      var firstDay  = new Date(year, month, 1).getDay();
      var daysInMon = new Date(year, month + 1, 0).getDate();

      for (var i = 0; i < firstDay; i++) {
        var blank = document.createElement('div');
        blank.className = 'cal-day empty';
        grid.appendChild(blank);
      }

      for (var d = 1; d <= daysInMon; d++) {
        var el = document.createElement('div');
        el.className = 'cal-day';
        el.textContent = d;

        var mm      = String(month + 1).padStart(2, '0');
        var dd      = String(d).padStart(2, '0');
        var dateStr = year + '-' + mm + '-' + dd;

        var isToday = (year === today.getFullYear() && month === today.getMonth() && d === today.getDate());
        if (isToday) el.classList.add('today');
        if (selectedDate === dateStr) el.classList.add('selected');
        if (sessionDates.indexOf(dateStr) !== -1) el.classList.add('has-session');

        el.setAttribute('data-date', dateStr);
        el.addEventListener('click', function() {
          window.location.href = 'student_sessions.php?date=' + this.getAttribute('data-date');
        });

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
      curYear  = parseInt(parts[0]);
      curMonth = parseInt(parts[1]) - 1;
    }

    renderCalendar(curYear, curMonth);

    <?php if ($error != "") { ?>
      document.getElementById('bookModal').classList.add('open');
    <?php } ?>

    function openCancel(id, tutor, date) {
      document.getElementById('cancelSessionId').value = id;
      document.getElementById('cancelTutor').textContent = tutor;
      document.getElementById('cancelDate').textContent  = date;
      document.getElementById('cancelModal').classList.add('open');
    }
    
    document.querySelectorAll('.modal-overlay').forEach(function(o) {
      o.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
      });
    });

    // Book form validation
    document.getElementById('bookForm').addEventListener('submit', function(e) {
      var hasError = false;

      var tutor    = document.getElementById('tutor_id');
      var tutorErr = document.getElementById('err-tutor');
      if (!tutor.value) { tutorErr.style.display = 'block'; hasError = true; }
      else               { tutorErr.style.display = 'none'; }

      var date    = document.getElementById('session_date');
      var dateErr = document.getElementById('err-date');
      if (!date.value) { dateErr.style.display = 'block'; hasError = true; }
      else              { dateErr.style.display = 'none'; }

      var dur    = document.getElementById('duration');
      var durErr = document.getElementById('err-duration');
      if (!dur.value) { durErr.style.display = 'block'; hasError = true; }
      else             { durErr.style.display = 'none'; }

      if (hasError) e.preventDefault();
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