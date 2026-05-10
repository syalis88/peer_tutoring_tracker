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

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

//Save availability
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'save_availability') {

    // Delete existing availability for this tutor
    $del = $conn->prepare("DELETE FROM schedules WHERE tutor_id = ?");
    $del->execute([$user_id]);

    // Insert new availability
    $active_days = isset($_POST['active_days']) ? $_POST['active_days'] : [];

    if (!empty($active_days)) {
        $ins = $conn->prepare("INSERT INTO schedules (tutor_id, available_day, start_time, end_time) VALUES (?, ?, ?, ?)");
        foreach ($active_days as $day) {
            $start = $_POST['start_' . $day] ?? '09:00';
            $end   = $_POST['end_' . $day]   ?? '17:00';
            if ($start < $end) {
                $ins->execute([$user_id, $day, $start, $end]);
            } else {
                $error = "End time must be after start time for $day.";
                break;
            }
        }
    }

    if ($error == '') $success = "Availability saved successfully.";
}

// Fetch current availability
$sched_stmt = $conn->prepare("SELECT available_day, start_time, end_time FROM schedules WHERE tutor_id = ?");
$sched_stmt->execute([$user_id]);
$schedules = [];
foreach ($sched_stmt->fetchAll() as $row) {
    $schedules[$row['available_day']] = $row;
}

//Fetch upcoming session dates for calendar dots
$cal_stmt = $conn->prepare("SELECT session_date FROM sessions WHERE tutor_id = ? AND status = 'scheduled' AND session_date >= NOW()");
$cal_stmt->execute([$user_id]);
$session_dates = [];
foreach ($cal_stmt->fetchAll() as $row) {
    $session_dates[] = date('Y-m-d', strtotime($row['session_date']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Schedule — Peer Tutoring Tracker</title>
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
      <a href="schedule.php" class="nav-item active"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>My Schedule</span></a>
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
        <h1 class="page-title">My Schedule</h1>
        <p class="page-sub">Manage your availability and upcoming sessions</p>
      </div>
    </div>

    <div class="page-content">
    <?php if ($success != "") { ?><div class="page-alert success show"><?php echo $success; ?></div><?php } ?>
    <?php if ($error   != "") { ?><div class="page-alert error show"><?php echo $error; ?></div><?php } ?>

    <div class="schedule-layout">

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
          <div class="legend-item"><div class="legend-dot session"></div> Session</div>
        </div>
      </div>

      <div class="avail-card">
        <div class="avail-title">Weekly Availability</div>
        <form action="schedule.php" method="POST">
          <input type="hidden" name="action" value="save_availability">
          <?php foreach ($days as $day) {
            $is_active  = isset($schedules[$day]);
            $start_time = $is_active ? $schedules[$day]['start_time'] : '09:00';
            $end_time   = $is_active ? $schedules[$day]['end_time']   : '17:00';
          ?>
            <div class="day-row">
              <label class="day-check">
                <input type="checkbox" name="active_days[]" value="<?php echo $day; ?>"
                  <?php echo $is_active ? 'checked' : ''; ?>
                  onchange="toggleDay('<?php echo $day; ?>', this.checked)">
                <?php echo $day; ?>
              </label>
              <div class="time-inputs <?php echo $is_active ? 'active' : ''; ?>" id="times-<?php echo $day; ?>">
                <input type="time" name="start_<?php echo $day; ?>" value="<?php echo $start_time; ?>">
                <span>to</span>
                <input type="time" name="end_<?php echo $day; ?>"   value="<?php echo $end_time; ?>">
              </div>
            </div>
          <?php } ?>
          <div style="margin-top:16px; text-align:right;">
            <button type="submit" class="btn-confirm">Save Availability</button>
          </div>
        </form>
      </div>

    </div>
  </div>
  </main>

  <script>
    function toggleDay(day, active) {
      var wrap = document.getElementById('times-' + day);
      if (active) wrap.classList.add('active');
      else        wrap.classList.remove('active');
    }

    var sessionDates = <?php echo json_encode(array_unique($session_dates)); ?>;
    var today = new Date();
    var curYear = today.getFullYear();
    var curMonth = today.getMonth();
    var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function renderCalendar(year, month) {
      document.getElementById('calTitle').textContent = monthNames[month] + ' ' + year;
      var grid = document.getElementById('calGrid');
      grid.innerHTML = '';

      ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(function(d) {
        var el = document.createElement('div');
        el.className = 'cal-day-label';
        el.textContent = d;
        grid.appendChild(el);
      });

      var firstDay  = new Date(year, month, 1).getDay();
      var daysInMon = new Date(year, month + 1, 0).getDate();

      for (var i = 0; i < firstDay; i++) {
        var blank = document.createElement('div');
        blank.className = 'cal-day';
        grid.appendChild(blank);
      }

      for (var d = 1; d <= daysInMon; d++) {
        var el = document.createElement('div');
        el.className = 'cal-day';
        el.textContent = d;

        var mm = String(month + 1).padStart(2, '0');
        var dd = String(d).padStart(2, '0');
        var dateStr = year + '-' + mm + '-' + dd;

        var isToday = (year === today.getFullYear() && month === today.getMonth() && d === today.getDate());
        if (isToday) el.classList.add('today');
        if (sessionDates.indexOf(dateStr) !== -1) el.classList.add('has-session');

        grid.appendChild(el);
      }
    }

    function changeMonth(dir) {
      curMonth += dir;
      if (curMonth > 11) { curMonth = 0; curYear++; }
      if (curMonth < 0)  { curMonth = 11; curYear--; }
      renderCalendar(curYear, curMonth);
    }

    renderCalendar(curYear, curMonth);

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