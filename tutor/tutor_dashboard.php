<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'tutor') {
    header("Location: ../account/login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? explode(" ", $_SESSION['user_name'])[0];

//Stats
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions WHERE tutor_id = ?");
$stmt->execute([$user_id]);
$total_sessions = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions WHERE tutor_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$completed_sessions = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions WHERE tutor_id = ? AND status = 'scheduled' AND session_date >= NOW()");
$stmt->execute([$user_id]);
$upcoming_count = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as total FROM sessions WHERE tutor_id = ?");
$stmt->execute([$user_id]);
$unique_students = $stmt->fetch()['total'];

// Total hours taught
$stmt = $conn->prepare("SELECT SUM(duration) as total FROM sessions WHERE tutor_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$total_minutes = $stmt->fetch()['total'] ?? 0;
$total_hours   = round($total_minutes / 60, 1);

// Average rating
$stmt = $conn->prepare("
    SELECT 
    AVG(f.rating) AS avg_rating,
    COUNT(f.feedback_id) AS total_reviews
    FROM feedback f
    JOIN sessions s ON f.session_id = s.session_id
    WHERE s.tutor_id = ?
");
$stmt->execute([$user_id]);
$rating_row    = $stmt->fetch();
$avg_rating    = $rating_row['avg_rating'] ?? 0;
$total_reviews = $rating_row['total_reviews'] ?? 0;

// Pending feedback (unresponded)
$stmt = $conn->prepare("
    SELECT COUNT(*) as total FROM feedback f
    JOIN sessions s ON f.session_id = s.session_id
    WHERE s.tutor_id = ? AND f.tutor_response IS NULL
");
$stmt->execute([$user_id]);
$pending_feedback = $stmt->fetch()['total'];

//Sessions per month chart (last 6 months)
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(session_date, '%b') as month,
           DATE_FORMAT(session_date, '%Y-%m') as month_key,
           COUNT(*) as total,
           COUNT(DISTINCT student_id) as unique_students
    FROM sessions
    WHERE tutor_id = ?
      AND session_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_key, month
    ORDER BY month_key ASC
");
$stmt->execute([$user_id]);
$monthly_data = $stmt->fetchAll();

$chart_labels   = [];
$chart_sessions = [];
$chart_students = [];
foreach ($monthly_data as $row) {
    $chart_labels[]   = $row['month'];
    $chart_sessions[] = (int)$row['total'];
    $chart_students[] = (int)$row['unique_students'];
}

//Rating breakdown for mini chart
$breakdown = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];
if ($total_reviews > 0) {
    $stmt = $conn->prepare("
        SELECT f.rating, COUNT(*) as cnt
        FROM feedback f JOIN sessions s ON f.session_id = s.session_id
        WHERE s.tutor_id = ? AND f.rating IS NOT NULL
        GROUP BY f.rating
    ");
    $stmt->execute([$user_id]);
    foreach ($stmt->fetchAll() as $row) {
        $breakdown[(int)$row['rating']] = (int)$row['cnt'];
    }
}

//Upcoming sessions (next 3)
$stmt = $conn->prepare("
    SELECT s.session_date, s.duration, CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM sessions s 
    JOIN users u ON s.student_id = u.user_id
    WHERE s.tutor_id = ? AND s.status = 'scheduled' AND s.session_date >= NOW()
    ORDER BY s.session_date ASC LIMIT 3
");
$stmt->execute([$user_id]);
$upcoming_sessions = $stmt->fetchAll();

//Recent activity (last 5)
$stmt = $conn->prepare("
    SELECT s.session_date, s.duration, s.status, CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM sessions s 
    JOIN users u ON s.student_id = u.user_id
    WHERE s.tutor_id = ?
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
  <title>Tutor Dashboard — Peer Tutoring Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/tutor_dashboard.css"/>
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      </div>
      <span>Peer Tutoring Tracker</span>
    </div>
    <nav class="sidebar-nav">
      <a href="tutor_dashboard.php" class="nav-item active">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        <span>Dashboard</span>
      </a>
      <a href="tutor_sessions.php" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>My Sessions</span>
      </a>
      <a href="my_students.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>My Students</span>
      </a>
      <a href="schedule.php" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span>My Schedule</span>
      </a>
      <a href="tutor_feedback.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span>Feedback</span>
        <?php if ($pending_feedback > 0) { ?>
          <span class="nav-badge"><?php echo $pending_feedback; ?></span>
        <?php } ?>
      </a>
      <a href="tutor_profile.php" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>Profile</span>
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="avatar"><?php echo strtoupper(substr($first_name, 0, 1)); ?></div>
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

    <!-- Hero banner -->
    <div class="hero-banner">
      <div class="hero-left">
        <p class="hero-greeting">Good <?php $h=(int)date('H'); echo $h<12?'morning':($h<18?'afternoon':'evening'); ?>, <?php echo htmlspecialchars($first_name); ?> !</p>
        <h1 class="hero-title">Your Teaching Dashboard</h1>
        <p class="hero-sub">Manage your sessions and support your students.</p>
      </div>
      <!-- Rating badge -->
      <div class="hero-rating">
        <div class="rating-circle">
          <span class="rating-num"><?php echo $avg_rating > 0 ? number_format($avg_rating, 1) : '—'; ?></span>
          <span class="rating-sub">/ 5.0</span>
        </div>
        <div class="rating-label">Avg. Rating<br><span><?php echo $total_reviews; ?> review<?php echo $total_reviews != 1 ? 's' : ''; ?></span></div>
      </div>
      <a href="tutor_sessions.php?action=new" class="hero-btn">+ New Session</a>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card gold">
        <div class="stat-icon">
          <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Total Sessions</span>
          <span class="stat-value"><?php echo $total_sessions; ?></span>
        </div>
      </div>
      <div class="stat-card navy">
        <div class="stat-icon">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Hours Taught</span>
          <span class="stat-value"><?php echo $total_hours; ?>h</span>
        </div>
      </div>
      <div class="stat-card teal">
        <div class="stat-icon">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Students Tutored</span>
          <span class="stat-value"><?php echo $unique_students; ?></span>
        </div>
      </div>
      <div class="stat-card orange">
        <div class="stat-icon">
          <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-label">Upcoming Sessions</span>
          <span class="stat-value"><?php echo $upcoming_count; ?></span>
        </div>
      </div>
    </div>

    <!-- Charts + panels -->
    <div class="dashboard-grid">

      <!-- Teaching activity line chart -->
      <div class="card chart-card">
        <div class="card-header">
          <h2 class="card-title">Teaching Activity</h2>
          <span class="card-sub">Last 6 months</span>
        </div>
        <?php if (count($chart_labels) == 0) { ?>
          <div class="chart-empty">No session data yet.</div>
        <?php } else { ?>
          <div class="chart-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#E9C46A;"></span>Sessions</span>
            <span class="legend-item"><span class="legend-dot" style="background:#2A9D8F;"></span>Unique Students</span>
          </div>
          <div style="position:relative; width:100%; height:220px;">
            <canvas id="activityChart"></canvas>
          </div>
        <?php } ?>
      </div>

      <!-- Rating breakdown -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Rating Breakdown</h2>
          <span class="card-sub"><?php echo $total_reviews; ?> total reviews</span>
        </div>
        <?php if ($total_reviews == 0) { ?>
          <div class="chart-empty">No reviews yet.</div>
        <?php } else { ?>
          <div class="rating-breakdown">
            <?php foreach ([5,4,3,2,1] as $star) {
              $count = $breakdown[$star];
              $pct   = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
            ?>
              <div class="rb-row">
                <span class="rb-label"><?php echo $star; ?> ★</span>
                <div class="rb-track">
                  <div class="rb-fill" style="width:<?php echo $pct; ?>%"></div>
                </div>
                <span class="rb-count"><?php echo $count; ?></span>
              </div>
            <?php } ?>
          </div>
          <div class="rating-big-display">
            <span class="rbd-num"><?php echo number_format($avg_rating, 1); ?></span>
            <div class="rbd-stars">
              <?php for ($i = 1; $i <= 5; $i++) { ?>
                <svg viewBox="0 0 24 24" class="rbd-star <?php echo $i <= round($avg_rating) ? 'filled' : 'empty'; ?>">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
              <?php } ?>
            </div>
            <span class="rbd-label">Average Rating</span>
          </div>
        <?php } ?>
      </div>

      <!-- Upcoming sessions -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Upcoming Sessions</h2>
          <a href="tutor_sessions.php" class="card-link">View all</a>
        </div>
        <?php if (count($upcoming_sessions) == 0) { ?>
          <div class="empty-state">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <p>No upcoming sessions.</p>
            <a href="tutor_sessions.php?action=new" class="btn-secondary">Create a session</a>
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
                  <span class="session-person"><?php echo htmlspecialchars($s['student_name']); ?></span>
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
          <a href="tutor_sessions.php" class="card-link">View all</a>
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
                  <span class="session-person"><?php echo htmlspecialchars($s['student_name']); ?></span>
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
    var ctx = document.getElementById('activityChart');
    if (ctx) {
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: <?php echo json_encode($chart_labels); ?>,
          datasets: [
            {
              label: 'Sessions',
              data: <?php echo json_encode($chart_sessions); ?>,
              borderColor: '#E9C46A',
              backgroundColor: 'rgba(233,196,106,0.12)',
              borderWidth: 2.5,
              pointBackgroundColor: '#E9C46A',
              pointRadius: 4,
              tension: 0.4,
              fill: true,
            },
            {
              label: 'Unique Students',
              data: <?php echo json_encode($chart_students); ?>,
              borderColor: '#2A9D8F',
              backgroundColor: 'rgba(42,157,143,0.08)',
              borderWidth: 2.5,
              pointBackgroundColor: '#2A9D8F',
              pointRadius: 4,
              tension: 0.4,
              fill: true,
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: {
              grid: { display: false },
              ticks: { font: { family: 'DM Sans', size: 12 }, color: '#6B7A90' }
            },
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(0,0,0,0.05)' },
              ticks: { stepSize: 1, font: { family: 'DM Sans', size: 12 }, color: '#6B7A90' }
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
</body>
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
</html>
