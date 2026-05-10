<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'student') {
    header("Location: ../account/login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? explode(" ", $_SESSION['user_name'])[0];

//Stats
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions WHERE student_id = ?");
$stmt->execute([$user_id]);
$total_sessions = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions WHERE student_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$completed_sessions = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions WHERE student_id = ? AND status = 'scheduled' AND session_date >= NOW()");
$stmt->execute([$user_id]);
$upcoming_count = $stmt->fetch()['total'];

// Total hours learned
$stmt = $conn->prepare("SELECT SUM(duration) as total FROM sessions WHERE student_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$total_minutes = $stmt->fetch()['total'] ?? 0;
$total_hours   = round($total_minutes / 60, 1);

// Attendance rate
$attendance_rate = $total_sessions > 0 ? round(($completed_sessions / $total_sessions) * 100) : 0;

//Sessions per month (last 6 months) for bar chart
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(session_date, '%b') as month,
           DATE_FORMAT(session_date, '%Y-%m') as month_key,
           COUNT(*) as total,
           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM sessions
    WHERE student_id = ?
      AND session_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_key, month
    ORDER BY month_key ASC
");
$stmt->execute([$user_id]);
$monthly_data = $stmt->fetchAll();

$chart_labels    = [];
$chart_total     = [];
$chart_completed = [];
foreach ($monthly_data as $row) {
    $chart_labels[]    = $row['month'];
    $chart_total[]     = (int)$row['total'];
    $chart_completed[] = (int)$row['completed'];
}

//Tutors seen (unique)
$stmt = $conn->prepare("SELECT COUNT(DISTINCT tutor_id) as total FROM sessions WHERE student_id = ?");
$stmt->execute([$user_id]);
$unique_tutors = $stmt->fetch()['total'];

//Upcoming sessions
$stmt = $conn->prepare("
    SELECT s.session_date, s.duration, CONCAT(u.first_name, ' ', u.last_name) as tutor_name
    FROM sessions s JOIN users u ON s.tutor_id = u.user_id
    WHERE s.student_id = ? AND s.status = 'scheduled' AND s.session_date >= NOW()
    ORDER BY s.session_date ASC LIMIT 3
");
$stmt->execute([$user_id]);
$upcoming_sessions = $stmt->fetchAll();

//Recent sessions
$stmt = $conn->prepare("
    SELECT s.session_date, s.duration, s.status, CONCAT(u.first_name, ' ', u.last_name) as tutor_name
    FROM sessions s JOIN users u ON s.tutor_id = u.user_id
    WHERE s.student_id = ?
    ORDER BY s.session_date DESC LIMIT 5
");
$stmt->execute([$user_id]);
$recent_sessions = $stmt->fetchAll();

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
  <title>Student Dashboard — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/student_dashboard.css"/>
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="">
      </div>
      <span>Peer Tutoring Tracker</span>
    </div>
    <nav class="sidebar-nav">
      <a href="student_dashboard.php" class="nav-item active">
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
    <div class="hero-banner">
      <div class="hero-text">
        <p class="hero-greeting">Good <?php $h=(int)date('H'); echo $h<12?'morning':($h<18?'afternoon':'evening'); ?>, <?php echo htmlspecialchars($first_name); ?> </p>
        <h1 class="hero-title">Your Learning Journey</h1>
        <p class="hero-sub">Here’s your learning journey at a glance.</p>
      </div>
      <a href="find_tutors.php" class="hero-btn">Book a Session</a>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card teal">
        <div class="stat-icon">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Total Sessions</span>
          <span class="stat-value"><?php echo $total_sessions; ?></span>
        </div>
      </div>
      <div class="stat-card blue">
        <div class="stat-icon">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Hours Learned</span>
          <span class="stat-value"><?php echo $total_hours; ?>h</span>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">
          <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Attendance Rate</span>
          <span class="stat-value"><?php echo $total_sessions > 0 ? $attendance_rate . '%' : 'N/A'; ?></span>
        </div>
      </div>
      <div class="stat-card purple">
        <div class="stat-icon">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Tutors Worked With</span>
          <span class="stat-value"><?php echo $unique_tutors; ?></span>
        </div>
      </div>
    </div>

    <div class="dashboard-grid">

      <div class="card chart-card">
        <div class="card-header">
          <h2 class="card-title">Session Activity</h2>
          <span class="card-sub">Last 6 months</span>
        </div>
        <?php if (count($chart_labels) == 0) { ?>
          <div class="chart-empty">No session data yet.</div>
        <?php } else { ?>
          <div class="chart-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#2A9D8F;"></span>Total</span>
            <span class="legend-item"><span class="legend-dot" style="background:#1A3A5C;"></span>Completed</span>
          </div>
          <div style="position:relative; width:100%; height:220px;">
            <canvas id="sessionChart"
              role="img"
              aria-label="Bar chart showing monthly tutoring sessions over the last 6 months">
              Monthly sessions: <?php echo implode(', ', array_map(fn($l,$t) => "$l: $t", $chart_labels, $chart_total)); ?>
            </canvas>
          </div>
        <?php } ?>
      </div>

      <div class="card worth-card">
        <div class="card-header">
          <h2 class="card-title">Your Stats</h2>
          <span class="card-sub">Your tutoring impact</span>
        </div>

        <div class="worth-metric">
          <div class="worth-label">Attendance Rate</div>
          <div class="worth-bar-wrap">
            <div class="worth-bar" style="width:<?php echo $attendance_rate; ?>%; background:#2A9D8F;"></div>
          </div>
          <div class="worth-value"><?php echo $attendance_rate; ?>%</div>
        </div>

        <div class="worth-metric">
          <div class="worth-label">Sessions Completed</div>
          <div class="worth-bar-wrap">
            <div class="worth-bar" style="width:<?php echo $total_sessions > 0 ? round(($completed_sessions/$total_sessions)*100) : 0; ?>%; background:#1A3A5C;"></div>
          </div>
          <div class="worth-value"><?php echo $completed_sessions; ?>/<?php echo $total_sessions; ?></div>
        </div>

        <div class="worth-metric">
          <div class="worth-label">Upcoming Booked</div>
          <div class="worth-bar-wrap">
            <div class="worth-bar" style="width:<?php echo min($upcoming_count * 20, 100); ?>%; background:#6C63FF;"></div>
          </div>
          <div class="worth-value"><?php echo $upcoming_count; ?> session<?php echo $upcoming_count != 1 ? 's' : ''; ?></div>
        </div>

        <div class="verdict <?php
          if ($attendance_rate >= 80) echo 'verdict-great';
          else if ($attendance_rate >= 50) echo 'verdict-ok';
          else echo 'verdict-low';
        ?>">
          <?php
            if ($total_sessions == 0) {
              echo 'Start your first session to track progress!';
            } else if ($attendance_rate >= 80) {
              echo 'Excellent consistency! Tutoring is clearly paying off.';
            } else if ($attendance_rate >= 50) {
              echo 'Good progress — try to attend more sessions for better results.';
            } else {
              echo 'Consider booking more sessions to see real improvement.';
            }
          ?>
        </div>
      </div>

      <!-- Upcoming sessions -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Upcoming Sessions</h2>
          <a href="sessions.php" class="card-link">View all</a>
        </div>
        <?php if (count($upcoming_sessions) == 0) { ?>
          <div class="empty-state">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <p>No upcoming sessions.</p>
            <a href="find_tutors.php" class="btn-secondary">Book a session</a>
          </div>
        <?php } else { ?>
          <div class="session-list">
            <?php foreach ($upcoming_sessions as $s) { ?>
              <div class="session-item">
                <div class="session-date-block">
                  <span class="session-day"><?php echo date('d', strtotime($s['session_date'])); ?></span>
                  <span class="session-month"><?php echo date('M', strtotime($s['session_date'])); ?></span>
                </div>
                <div class="session-details">
                  <span class="session-person"><?php echo htmlspecialchars($s['tutor_name']); ?></span>
                  <span class="session-meta"><?php echo date('g:i A', strtotime($s['session_date'])); ?> &middot; <?php echo $s['duration']; ?> min</span>
                </div>
                <span class="badge badge-info">Scheduled</span>
              </div>
            <?php } ?>
          </div>
        <?php } ?>
      </div>

      <!-- Recent activity -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Recent Activity</h2>
          <a href="sessions.php" class="card-link">View all</a>
        </div>
        <?php if (count($recent_sessions) == 0) { ?>
          <div class="empty-state">
            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <p>No session history yet.</p>
          </div>
        <?php } else { ?>
          <div class="session-list">
            <?php foreach ($recent_sessions as $s) { ?>
              <div class="session-item">
                <div class="session-date-block">
                  <span class="session-day"><?php echo date('d', strtotime($s['session_date'])); ?></span>
                  <span class="session-month"><?php echo date('M', strtotime($s['session_date'])); ?></span>
                </div>
                <div class="session-details">
                  <span class="session-person"><?php echo htmlspecialchars($s['tutor_name']); ?></span>
                  <span class="session-meta"><?php echo date('g:i A', strtotime($s['session_date'])); ?> &middot; <?php echo $s['duration']; ?> min</span>
                </div>
                <?php echo status_badge($s['status']); ?>
              </div>
            <?php } ?>
          </div>
        <?php } ?>
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
              backgroundColor: 'rgba(42,157,143,0.2)',
              borderColor: '#2A9D8F',
              borderWidth: 2,
              borderRadius: 6,
              borderSkipped: false,
            },
            {
              label: 'Completed',
              data: <?php echo json_encode($chart_completed); ?>,
              backgroundColor: 'rgba(26,58,92,0.85)',
              borderColor: '#1A3A5C',
              borderWidth: 0,
              borderRadius: 6,
              borderSkipped: false,
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { font: { family: 'DM Sans', size: 12 }, color: '#6B7A90' }
            },
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(0,0,0,0.05)' },
              ticks: {
                stepSize: 1,
                font: { family: 'DM Sans', size: 12 },
                color: '#6B7A90'
              }
            }
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