<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../account/login.php");
    exit();
}

$first_name = $_SESSION['first_name'] ?? explode(" ", $_SESSION['user_name'])[0];

$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$total_students = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'tutor'");
$total_tutors = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM sessions");
$total_sessions = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM sessions WHERE status = 'completed'");
$completed_sessions = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM sessions WHERE status = 'scheduled' AND session_date >= NOW()");
$upcoming_sessions = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT ROUND(AVG(rating), 1) as avg FROM feedback WHERE rating IS NOT NULL");
$platform_rating = $stmt->fetch()['avg'] ?? 0;

$stmt = $conn->query("SELECT COUNT(*) as total FROM feedback");
$total_feedback = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM subjects");
$total_subjects = $stmt->fetch()['total'];

//Sessions per month (last 6)
$stmt = $conn->query("
    SELECT DATE_FORMAT(session_date, '%b') as month,
           DATE_FORMAT(session_date, '%Y-%m') as month_key,
           COUNT(*) as total,
           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM sessions
    WHERE session_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_key, month
    ORDER BY month_key ASC
");
$monthly = $stmt->fetchAll();
$chart_labels    = [];
$chart_total     = [];
$chart_completed = [];
foreach ($monthly as $row) {
    $chart_labels[]    = $row['month'];
    $chart_total[]     = (int)$row['total'];
    $chart_completed[] = (int)$row['completed'];
}

//Recent registrations
$stmt = $conn->query("
    SELECT user_id, CONCAT(first_name, ' ', last_name) as name, email, role, created_at
    FROM users WHERE role != 'admin'
    ORDER BY created_at DESC LIMIT 6
");
$recent_users = $stmt->fetchAll();

//Recent sessions
$stmt = $conn->query("
    SELECT s.session_id, s.session_date, s.status, s.duration,
           CONCAT(t.first_name, ' ', t.last_name) as tutor_name,
            CONCAT(st.first_name, ' ', st.last_name) as student_name
    FROM sessions s
    JOIN users t  ON s.tutor_id   = t.user_id
    JOIN users st ON s.student_id = st.user_id
    ORDER BY s.session_date DESC LIMIT 6
");
$recent_sessions = $stmt->fetchAll();

function status_badge($status) {
    if ($status == 'completed') return '<span class="badge badge-success">Completed</span>';
    if ($status == 'cancelled') return '<span class="badge badge-danger">Cancelled</span>';
    return '<span class="badge badge-info">Scheduled</span>';
}

function role_badge($role) {
    if ($role == 'tutor')   return '<span class="badge badge-gold">Tutor</span>';
    return '<span class="badge badge-teal">Student</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/admin.css"/>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="">
      </div>
      <span>Peer Tutoring Tracker Admin</span>
    </div>
    <nav class="sidebar-nav">
      <a href="admin_dashboard.php" class="nav-item active">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        <span>Dashboard</span>
      </a>
      <a href="admin_users.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Users</span>
      </a>
      <a href="admin_sessions.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Sessions</span>
      </a>
      <a href="admin_subjects.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <span>Subjects</span>
      </a>
      <a href="admin_feedback.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span>Feedback</span>
      </a>
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
  </aside>

  <main class="main">

    <div class="topbar">
      <div>
        <h1 class="page-title">Welcome back, Admin</h1>
        <p class="page-sub">Good <?php $h=(int)date('H'); echo $h<12?'morning':($h<18?'afternoon':'evening'); ?>, <?php echo htmlspecialchars($first_name); ?>. Here’s what’s happening today.</p>
      </div>
      <div class="topbar-date"><?php echo date('F j, Y'); ?></div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon teal">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Total Students</span>
          <span class="stat-value"><?php echo $total_students; ?></span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon gold">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Total Tutors</span>
          <span class="stat-value"><?php echo $total_tutors; ?></span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon navy">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Total Sessions</span>
          <span class="stat-value"><?php echo $total_sessions; ?></span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Completed</span>
          <span class="stat-value"><?php echo $completed_sessions; ?></span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Upcoming</span>
          <span class="stat-value"><?php echo $upcoming_sessions; ?></span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple">
          <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Avg. Platform Rating</span>
          <span class="stat-value"><?php echo $platform_rating > 0 ? number_format($platform_rating, 1) . ' ★' : 'N/A'; ?></span>
        </div>
      </div>
    </div>

    <div class="dashboard-grid">

      <!-- Session activity chart -->
      <div class="card chart-card">
        <div class="card-header">
          <h2 class="card-title">Session Activity</h2>
          <span class="card-sub">Last 6 months</span>
        </div>
        <?php if (count($chart_labels) == 0) { ?>
          <div class="chart-empty">No session data yet.</div>
        <?php } else { ?>
          <div class="chart-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#1A3A5C;"></span>Total</span>
            <span class="legend-item"><span class="legend-dot" style="background:#2A9D8F;"></span>Completed</span>
          </div>
          <div style="position:relative; width:100%; height:220px;">
            <canvas id="sessionChart"></canvas>
          </div>
        <?php } ?>
      </div>

      <!-- Recent registrations -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Recent Registrations</h2>
          <a href="admin_users.php" class="card-link">View all</a>
        </div>
        <div class="user-list">
          <?php foreach ($recent_users as $u) { ?>
            <div class="user-list-item">
              <div class="user-avatar"><?php echo strtoupper(substr($u['name'], 0, 1)); ?></div>
              <div class="user-list-info">
                <span class="user-list-name"><?php echo htmlspecialchars($u['name']); ?></span>
                <span class="user-list-email"><?php echo htmlspecialchars($u['email']); ?></span>
              </div>
              <?php echo role_badge($u['role']); ?>
            </div>
          <?php } ?>
        </div>
      </div>

      <!-- Recent sessions -->
      <div class="card full-width">
        <div class="card-header">
          <h2 class="card-title">Recent Sessions</h2>
          <a href="admin_sessions.php" class="card-link">View all</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Tutor</th>
                <th>Student</th>
                <th>Date & Time</th>
                <th>Duration</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($recent_sessions) == 0) { ?>
                <tr><td colspan="5" style="text-align:center; padding:24px; color:var(--muted); font-size:14px;">No sessions yet.</td></tr>
              <?php } else { ?>
                <?php foreach ($recent_sessions as $s) { ?>
                  <tr>
                    <td><div class="td-name"><?php echo htmlspecialchars($s['tutor_name']); ?></div></td>
                    <td><div class="td-name"><?php echo htmlspecialchars($s['student_name']); ?></div></td>
                    <td>
                      <div class="td-name"><?php echo date('M d, Y', strtotime($s['session_date'])); ?></div>
                      <div class="td-meta"><?php echo date('g:i A', strtotime($s['session_date'])); ?></div>
                    </td>
                    <td><?php echo $s['duration']; ?> min</td>
                    <td><?php echo status_badge($s['status']); ?></td>
                  </tr>
                <?php } ?>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
  <script>
    <?php if (count($chart_labels) > 0) { ?>
    var ctx = document.getElementById('sessionChart');
    if (ctx) {
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: <?php echo json_encode($chart_labels); ?>,
          datasets: [
            {
              label: 'Total',
              data: <?php echo json_encode($chart_total); ?>,
              backgroundColor: 'rgba(26,58,92,0.15)',
              borderColor: '#1A3A5C',
              borderWidth: 2, borderRadius: 6, borderSkipped: false,
            },
            {
              label: 'Completed',
              data: <?php echo json_encode($chart_completed); ?>,
              backgroundColor: 'rgba(42,157,143,0.85)',
              borderColor: '#2A9D8F',
              borderWidth: 0, borderRadius: 6, borderSkipped: false,
            }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false }, ticks: { font: { family: 'DM Sans', size: 12 }, color: '#6B7A90' } },
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { stepSize: 1, font: { family: 'DM Sans', size: 12 }, color: '#6B7A90' } }
          }
        }
      });
    }
    <?php } ?>

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
