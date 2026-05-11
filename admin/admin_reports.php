<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../account/login.php");
    exit();
}

$first_name = $_SESSION['first_name'] ?? explode(" ", $_SESSION['user_name'])[0];

// Active report
$report = isset($_GET['report']) ? $_GET['report'] : 'session_summary';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to   = isset($_GET['date_to'])   ? $_GET['date_to']   : '';

// REPORT 1: Session Summary Report
$session_summary = [];
if ($report == 'session_summary') {
    $date_where  = "";
    $date_params = [];

    if ($date_from != '') {
        $date_where   .= " AND s.session_date >= ?";
        $date_params[] = $date_from . ' 00:00:00';
    }
    if ($date_to != '') {
        $date_where   .= " AND s.session_date <= ?";
        $date_params[] = $date_to . ' 23:59:59';
    }

    $stmt = $conn->prepare("
        WITH tutor_sessions AS (
    SELECT
        u.user_id,
        CONCAT(u.first_name, ' ', u.last_name) AS tutor_name,

        COUNT(s.session_id) AS total_sessions,

        SUM(s.status = 'completed') AS completed_sessions,
        SUM(s.status = 'cancelled') AS cancelled_sessions,
        SUM(s.status = 'scheduled') AS scheduled_sessions,

        SUM(s.status = 'completed' * s.duration) / 60 AS hours_taught,

        COUNT(DISTINCT s.student_id) AS unique_students

    FROM users u
    LEFT JOIN sessions s ON s.tutor_id = u.user_id
    WHERE u.role = 'tutor'
    GROUP BY u.user_id
),

tutor_ratings AS (
    SELECT
        s.tutor_id,
        AVG(f.rating) AS avg_rating,
        COUNT(f.feedback_id) AS total_reviews
    FROM feedback f
    JOIN sessions s ON f.session_id = s.session_id
    GROUP BY s.tutor_id
)

SELECT
    ts.*,
    IFNULL(tr.avg_rating, 0) AS avg_rating,
    IFNULL(tr.total_reviews, 0) AS total_reviews,

    (ts.completed_sessions * 100) / ts.total_sessions AS completion_rate

FROM tutor_sessions ts
LEFT JOIN tutor_ratings tr ON tr.tutor_id = ts.user_id
ORDER BY ts.completed_sessions DESC, ts.total_sessions DESC;
    ");
    $stmt->execute($date_params);
    $session_summary = $stmt->fetchAll();
}

// REPORT 2: Student Progress Report
$student_progress = [];
if ($report == 'student_progress') {
    $date_where  = "";
    $date_params = [];

    if ($date_from != '') {
        $date_where   .= " AND s.session_date >= ?";
        $date_params[] = $date_from . ' 00:00:00';
    }
    if ($date_to != '') {
        $date_where   .= " AND s.session_date <= ?";
        $date_params[] = $date_to . ' 23:59:59';
    }

    $stmt = $conn->prepare("
        WITH student_sessions AS (
    SELECT
        u.user_id,
        CONCAT(u.first_name, ' ', u.last_name) AS student_name,
        u.year_level,

        COUNT(s.session_id) AS total_sessions,

        SUM(s.status = 'completed') AS completed_sessions,
        SUM(s.status = 'cancelled') AS cancelled_sessions,

        SUM(s.status = 'completed' * s.duration) / 60 AS hours_learned,

        COUNT(DISTINCT s.tutor_id) AS unique_tutors,

        MAX(s.session_date) AS last_session

    FROM users u
    LEFT JOIN sessions s ON s.student_id = u.user_id
    WHERE u.role = 'student'
    GROUP BY u.user_id
),

student_feedback AS (
    SELECT
        f.student_id,
        COUNT(f.feedback_id) AS reviews_given
    FROM feedback f
    GROUP BY f.student_id
)

SELECT
    ss.*,
    IFNULL(sf.reviews_given, 0) AS reviews_given,

    (ss.completed_sessions * 100) / ss.total_sessions AS attendance_rate

FROM student_sessions ss
LEFT JOIN student_feedback sf ON sf.student_id = ss.user_id
ORDER BY ss.completed_sessions DESC, ss.total_sessions DESC;
    ");
    $stmt->execute($date_params);
    $student_progress = $stmt->fetchAll();
}

// REPORT 3: Subject Popularity Report
$subject_popularity = [];
if ($report == 'subject_popularity') {
    $stmt = $conn->prepare("
       WITH subject_tutors AS (
    SELECT
        s.subject_id,
        s.subject_name,
        COUNT(DISTINCT ts.tutor_id) AS tutor_count
    FROM subjects s
    LEFT JOIN tutor_subjects ts ON ts.subject_id = s.subject_id
    GROUP BY s.subject_id
),

subject_sessions AS (
    SELECT
        s.subject_id,

        COUNT(DISTINCT se.session_id) AS total_sessions,

        SUM(se.status = 'completed') AS completed_sessions,

        COUNT(DISTINCT se.student_id) AS unique_students

    FROM subjects s
    JOIN tutor_subjects ts ON ts.subject_id = s.subject_id
    JOIN sessions se ON se.tutor_id = ts.tutor_id
    GROUP BY s.subject_id
),

subject_ranked AS (
    SELECT
        st.subject_id,
        st.subject_name,
        st.tutor_count,

        IFNULL(ss.total_sessions, 0) AS total_sessions,
        IFNULL(ss.completed_sessions, 0) AS completed_sessions,
        IFNULL(ss.unique_students, 0) AS unique_students,

        RANK() OVER (
            ORDER BY IFNULL(ss.total_sessions, 0) DESC,
                     st.tutor_count DESC
        ) AS popularity_rank

    FROM subject_tutors st
    LEFT JOIN subject_sessions ss ON ss.subject_id = st.subject_id
)

SELECT *
FROM subject_ranked
ORDER BY popularity_rank;
    ");
    $stmt->execute();
    $subject_popularity = $stmt->fetchAll();
}

// REPORT 4: Feedback & Ratings Report
$feedback_report = [];
if ($report == 'feedback_ratings') {
    $stmt = $conn->prepare("
        WITH tutor_feedback AS (
    SELECT
        u.user_id,
        CONCAT(u.first_name, ' ', u.last_name) AS tutor_name,

        COUNT(f.feedback_id) AS total_reviews,
        AVG(f.rating) AS avg_rating,

        SUM(f.rating = 5) AS rating_5,
        SUM(f.rating = 4) AS rating_4,
        SUM(f.rating = 3) AS rating_3,
        SUM(f.rating = 2) AS rating_2,
        SUM(f.rating = 1) AS rating_1,

        SUM(f.tutor_response IS NOT NULL) AS responses_given

    FROM users u
    JOIN sessions s ON s.tutor_id = u.user_id
    JOIN feedback f ON f.session_id = s.session_id
    WHERE u.role = 'tutor'
    GROUP BY u.user_id
),

tutor_feedback_ranked AS (
    SELECT
        *,

        RANK() OVER (
            ORDER BY avg_rating DESC, total_reviews DESC
        ) AS rating_rank,

        (responses_given * 100) / total_reviews AS response_rate

    FROM tutor_feedback
)

SELECT *
FROM tutor_feedback_ranked
ORDER BY rating_rank;
    ");
    $stmt->execute();
    $feedback_report = $stmt->fetchAll();
}

// Print mode
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

$report_titles = [
    'session_summary'    => 'Session Summary Report',
    'student_progress'   => 'Student Progress Report',
    'subject_popularity' => 'Subject Popularity Report',
    'feedback_ratings'   => 'Feedback & Ratings Report',
];
$current_title = $report_titles[$report] ?? 'Report';

// Helper: render star rating display
function render_stars($rating) {
    $full  = round($rating);
    $empty = 5 - $full;
    return str_repeat('★', $full) . str_repeat('☆', $empty);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo $current_title; ?> — PTT Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <?php if (!$print_mode) { ?>
    <link rel="stylesheet" href="../assets/admin.css"/>
  <?php } ?>
  <style>
    <?php if ($print_mode) { ?>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: #fff; color: #1A2A3A;
      padding: 32px 40px; font-size: 13px;
    }
    .print-header {
      display: flex; align-items: flex-start;
      justify-content: space-between; margin-bottom: 28px;
      padding-bottom: 18px; border-bottom: 2px solid #1A3A5C;
    }
    .print-logo { font-family: 'Lora', serif; font-size: 20px; font-weight: 600; color: #1A3A5C; }
    .print-logo span { display: block; font-family: 'DM Sans', sans-serif; font-size: 11px; font-weight: 400; color: #6B7A90; margin-top: 3px; }
    .print-meta { text-align: right; font-size: 11px; color: #6B7A90; line-height: 1.8; }
    .print-meta strong { color: #1A2A3A; }
    .print-title { font-family: 'Lora', serif; font-size: 17px; font-weight: 600; color: #1A3A5C; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    thead th {
      background: #1A3A5C; color: #fff;
      padding: 9px 12px; text-align: left;
      font-size: 10px; font-weight: 600;
      text-transform: uppercase; letter-spacing: 0.05em;
    }
    tbody tr { border-bottom: 1px solid #E8EDF4; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:nth-child(even) { background: #F7F9FC; }
    tbody td { padding: 9px 12px; }
    .badge {
      display: inline-block; font-size: 10px; font-weight: 600;
      padding: 2px 8px; border-radius: 20px;
    }
    .badge-teal   { background: #E8F6F4; color: #1F7A6E; }
    .badge-gold   { background: #FDF6E3; color: #B8860B; }
    .badge-green  { background: #EAFAF1; color: #1E8449; }
    .badge-red    { background: #FDEDEC; color: #C0392B; }
    .badge-navy   { background: #EAF0F7; color: #1A3A5C; }
    .stars { color: #E9C46A; letter-spacing: 1px; }
    .bar-wrap { width: 80px; height: 6px; background: #E8EDF4; border-radius: 3px; display: inline-block; vertical-align: middle; margin-right: 6px; }
    .bar-fill { height: 100%; border-radius: 3px; background: #2A9D8F; }
    .print-footer {
      margin-top: 28px; padding-top: 14px;
      border-top: 1px solid #E8EDF4;
      font-size: 10px; color: #6B7A90;
      display: flex; justify-content: space-between;
    }
    .summary-boxes {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 12px; margin-bottom: 22px;
    }
    .summary-box {
      border: 1px solid #D5DEE8; border-radius: 8px;
      padding: 12px 14px;
    }
    .summary-box-label { font-size: 10px; color: #6B7A90; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
    .summary-box-value { font-size: 20px; font-weight: 600; color: #1A3A5C; line-height: 1; }
    @media print {
      body { padding: 20px; }
      @page { margin: 1.5cm; }
    }
    <?php } else { ?>
    /* ── Admin page extras ── */
    .report-nav {
      display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;
    }
    .report-nav-btn {
      padding: 8px 16px; border-radius: 8px; font-size: 13px;
      font-family: 'DM Sans', sans-serif; font-weight: 500;
      border: 1.5px solid var(--border); background: var(--surface);
      color: var(--navy-mid); cursor: pointer; text-decoration: none;
      transition: all 0.15s;
    }
    .report-nav-btn:hover  { border-color: var(--teal); background: var(--teal-light); color: var(--teal-dark); }
    .report-nav-btn.active { background: var(--navy); color: #fff; border-color: var(--navy); }
    .report-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow);
    }
    .report-card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 20px; border-bottom: 1px solid var(--border);
      background: var(--surface-2);
    }
    .report-card-title { font-family: 'Lora', serif; font-size: 15px; font-weight: 600; color: var(--navy); }
    .report-card-sub   { font-size: 12px; color: var(--muted); margin-top: 2px; }
    .date-filter-bar {
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
      padding: 14px 20px; border-bottom: 1px solid var(--border);
      background: var(--bg);
    }
    .date-filter-bar label { font-size: 12px; color: var(--muted); font-weight: 500; }
    .date-filter-bar input[type="date"] {
      height: 34px; padding: 0 10px;
      border: 1.5px solid var(--border); border-radius: 8px;
      font-family: 'DM Sans', sans-serif; font-size: 13px;
      color: var(--text); background: var(--surface); outline: none;
    }
    .date-filter-bar input:focus { border-color: var(--teal); }
    .summary-strip {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 0; border-bottom: 1px solid var(--border);
    }
    .summary-strip-item {
      padding: 16px 20px; border-right: 1px solid var(--border);
      display: flex; flex-direction: column; gap: 4px;
    }
    .summary-strip-item:last-child { border-right: none; }
    .summary-strip-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; }
    .summary-strip-value { font-size: 22px; font-weight: 700; color: var(--navy); line-height: 1; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    thead th {
      padding: 11px 16px; text-align: left;
      font-size: 11px; font-weight: 600; color: var(--muted);
      text-transform: uppercase; letter-spacing: 0.05em;
      border-bottom: 1px solid var(--border); background: var(--surface-2);
      white-space: nowrap;
    }
    tbody tr { border-bottom: 1px solid var(--border-light); transition: background 0.1s; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: var(--bg); }
    tbody td { padding: 12px 16px; color: var(--text); vertical-align: middle; }
    .td-name { font-weight: 500; }
    .td-meta { font-size: 11px; color: var(--muted); margin-top: 2px; }
    .mini-bar-wrap { width: 70px; height: 6px; background: var(--border); border-radius: 3px; display: inline-block; vertical-align: middle; margin-right: 6px; }
    .mini-bar-fill { height: 100%; border-radius: 3px; }
    .rank-badge {
      width: 26px; height: 26px; border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 700; flex-shrink: 0;
    }
    .rank-1 { background: var(--gold-light);  color: var(--gold-dark); }
    .rank-2 { background: var(--navy-light);  color: var(--navy); }
    .rank-3 { background: var(--teal-light);  color: var(--teal-dark); }
    .rank-n { background: var(--surface-2);   color: var(--muted); }
    .stars-display { color: #E9C46A; font-size: 13px; letter-spacing: 1px; }
    .print-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 16px; height: 36px;
      background: var(--navy); color: #fff;
      border: none; border-radius: 8px;
      font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500;
      cursor: pointer; text-decoration: none;
      transition: background 0.15s;
    }
    .print-btn:hover { background: var(--navy-mid); }
    .print-btn svg { width: 14px; height: 14px; stroke: #fff; fill: none; stroke-width: 2; stroke-linecap: round; }
    .empty-report {
      padding: 48px 20px; text-align: center;
      font-size: 14px; color: var(--muted);
    }
    <?php } ?>
  </style>
</head>
<body>

<?php if ($print_mode) { ?>
  <!-- ══════════════════════════════════════════
       PRINT MODE
  ══════════════════════════════════════════ -->
  <div class="print-header">
    <div>
      <div class="print-logo">
        Peer Tutoring Tracker
        <span>Admin Report — <?php echo htmlspecialchars($current_title); ?></span>
      </div>
    </div>
    <div class="print-meta">
      <strong>Generated by:</strong> <?php echo htmlspecialchars($first_name); ?><br>
      <strong>Date:</strong> <?php echo date('F j, Y'); ?><br>
      <strong>Time:</strong> <?php echo date('g:i A'); ?><br>
      <?php if ($date_from != '' || $date_to != '') { ?>
        <strong>Period:</strong>
        <?php echo $date_from != '' ? date('M j, Y', strtotime($date_from)) : 'Start'; ?>
        – <?php echo $date_to != '' ? date('M j, Y', strtotime($date_to)) : 'Present'; ?>
      <?php } ?>
    </div>
  </div>

  <?php if ($report == 'session_summary' && count($session_summary) > 0) {
    $total_sessions   = array_sum(array_column($session_summary, 'total_sessions'));
    $total_completed  = array_sum(array_column($session_summary, 'completed_sessions'));
    $total_hours      = array_sum(array_column($session_summary, 'hours_taught'));
  ?>
    <div class="print-title">Session Summary Report</div>
    <div class="summary-boxes">
      <div class="summary-box">
        <div class="summary-box-label">Total Tutors</div>
        <div class="summary-box-value"><?php echo count($session_summary); ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Total Sessions</div>
        <div class="summary-box-value"><?php echo $total_sessions; ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Completed Sessions</div>
        <div class="summary-box-value"><?php echo $total_completed; ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Total Hours Taught</div>
        <div class="summary-box-value"><?php echo $total_hours; ?>h</div>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Tutor Name</th>
          <th>Total Sessions</th>
          <th>Completed</th>
          <th>Cancelled</th>
          <th>Completion Rate</th>
          <th>Hours Taught</th>
          <th>Students</th>
          <th>Avg Rating</th>
          <th>Reviews</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($session_summary as $i => $row) { ?>
          <tr>
            <td><?php echo $i + 1; ?></td>
            <td><?php echo htmlspecialchars($row['tutor_name']); ?></td>
            <td><?php echo $row['total_sessions']; ?></td>
            <td><?php echo $row['completed_sessions']; ?></td>
            <td><?php echo $row['cancelled_sessions']; ?></td>
            <td>
              <div class="bar-wrap">
                <div class="bar-fill" style="width:<?php echo $row['completion_rate']; ?>%"></div>
              </div>
              <?php echo $row['completion_rate']; ?>%
            </td>
            <td><?php echo $row['hours_taught']; ?>h</td>
            <td><?php echo $row['unique_students']; ?></td>
            <td><?php echo $row['avg_rating'] > 0 ? number_format($row['avg_rating'], 2) . ' ★' : '—'; ?></td>
            <td><?php echo $row['total_reviews']; ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>

  <?php } elseif ($report == 'student_progress' && count($student_progress) > 0) {
    $total_sessions  = array_sum(array_column($student_progress, 'total_sessions'));
    $total_completed = array_sum(array_column($student_progress, 'completed_sessions'));
    $total_hours     = array_sum(array_column($student_progress, 'hours_learned'));
  ?>
    <div class="print-title">Student Progress Report</div>
    <div class="summary-boxes">
      <div class="summary-box">
        <div class="summary-box-label">Total Students</div>
        <div class="summary-box-value"><?php echo count($student_progress); ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Total Sessions</div>
        <div class="summary-box-value"><?php echo $total_sessions; ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Completed</div>
        <div class="summary-box-value"><?php echo $total_completed; ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Total Hours Learned</div>
        <div class="summary-box-value"><?php echo $total_hours; ?>h</div>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Student Name</th>
          <th>Year Level</th>
          <th>Total Sessions</th>
          <th>Completed</th>
          <th>Attendance Rate</th>
          <th>Hours Learned</th>
          <th>Tutors</th>
          <th>Reviews Given</th>
          <th>Last Session</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($student_progress as $i => $row) { ?>
          <tr>
            <td><?php echo $i + 1; ?></td>
            <td><?php echo htmlspecialchars($row['student_name']); ?></td>
            <td><?php echo $row['year_level'] ? 'Year ' . $row['year_level'] : '—'; ?></td>
            <td><?php echo $row['total_sessions']; ?></td>
            <td><?php echo $row['completed_sessions']; ?></td>
            <td>
              <div class="bar-wrap">
                <div class="bar-fill" style="width:<?php echo $row['attendance_rate']; ?>%"></div>
              </div>
              <?php echo $row['attendance_rate']; ?>%
            </td>
            <td><?php echo $row['hours_learned']; ?>h</td>
            <td><?php echo $row['unique_tutors']; ?></td>
            <td><?php echo $row['reviews_given']; ?></td>
            <td><?php echo $row['last_session'] ? date('M d, Y', strtotime($row['last_session'])) : '—'; ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>

  <?php } elseif ($report == 'subject_popularity' && count($subject_popularity) > 0) { ?>
    <div class="print-title">Subject Popularity Report</div>
    <div class="summary-boxes">
      <div class="summary-box">
        <div class="summary-box-label">Total Subjects</div>
        <div class="summary-box-value"><?php echo count($subject_popularity); ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Total Sessions</div>
        <div class="summary-box-value"><?php echo array_sum(array_column($subject_popularity, 'total_sessions')); ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Total Tutors</div>
        <div class="summary-box-value"><?php echo array_sum(array_column($subject_popularity, 'tutor_count')); ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Students Reached</div>
        <div class="summary-box-value"><?php echo array_sum(array_column($subject_popularity, 'unique_students')); ?></div>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>Rank</th>
          <th>Subject</th>
          <th>Tutors Teaching</th>
          <th>Total Sessions</th>
          <th>Completed Sessions</th>
          <th>Students Reached</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subject_popularity as $row) { ?>
          <tr>
            <td>#<?php echo $row['popularity_rank']; ?></td>
            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
            <td><?php echo $row['tutor_count']; ?></td>
            <td><?php echo $row['total_sessions']; ?></td>
            <td><?php echo $row['completed_sessions']; ?></td>
            <td><?php echo $row['unique_students']; ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>

  <?php } elseif ($report == 'feedback_ratings' && count($feedback_report) > 0) {
    $total_reviews    = array_sum(array_column($feedback_report, 'total_reviews'));
    $total_responses  = array_sum(array_column($feedback_report, 'responses_given'));

    // Calculate platform average rating
    $rating_sum   = 0;
    $rating_count = 0;
    foreach ($feedback_report as $row) {
        if ($row['avg_rating'] > 0) {
            $rating_sum  += $row['avg_rating'];
            $rating_count++;
        }
    }
    $platform_avg = $rating_count > 0 ? number_format($rating_sum / $rating_count, 2) : '—';
  ?>
    <div class="print-title">Feedback & Ratings Report</div>
    <div class="summary-boxes">
      <div class="summary-box">
        <div class="summary-box-label">Tutors Reviewed</div>
        <div class="summary-box-value"><?php echo count($feedback_report); ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Total Reviews</div>
        <div class="summary-box-value"><?php echo $total_reviews; ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Platform Avg Rating</div>
        <div class="summary-box-value"><?php echo $platform_avg; ?></div>
      </div>
      <div class="summary-box">
        <div class="summary-box-label">Responses Given</div>
        <div class="summary-box-value"><?php echo $total_responses; ?></div>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>Rank</th>
          <th>Tutor Name</th>
          <th>Avg Rating</th>
          <th>Reviews</th>
          <th>5★</th><th>4★</th><th>3★</th><th>2★</th><th>1★</th>
          <th>Responses Given</th>
          <th>Pending</th>
          <th>Response Rate</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($feedback_report as $row) { ?>
          <tr>
            <td>#<?php echo $row['rating_rank']; ?></td>
            <td><?php echo htmlspecialchars($row['tutor_name']); ?></td>
            <td><?php echo number_format($row['avg_rating'], 2); ?> ★</td>
            <td><?php echo $row['total_reviews']; ?></td>
            <td><?php echo $row['rating_5']; ?></td>
            <td><?php echo $row['rating_4']; ?></td>
            <td><?php echo $row['rating_3']; ?></td>
            <td><?php echo $row['rating_2']; ?></td>
            <td><?php echo $row['rating_1']; ?></td>
            <td><?php echo $row['responses_given']; ?></td>
            <td><?php echo $row['responses_pending']; ?></td>
            <td><?php echo $row['response_rate']; ?>%</td>
          </tr>
        <?php } ?>
      </tbody>
    </table>

  <?php } else { ?>
    <p style="color:#6B7A90; font-size:14px;">No data available for this report.</p>
  <?php } ?>

  <div class="print-footer">
    <span>Peer Tutoring Tracker — Confidential Admin Report</span>
    <span>Printed on <?php echo date('F j, Y \a\t g:i A'); ?></span>
  </div>

  <script>window.onload = function() { window.print(); }</script>

<?php } else { ?>
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      </div>
      <span>Peer Tutoring Tracker Admin</span>
    </div>
    <nav class="sidebar-nav">
      <a href="admin_dashboard.php" class="nav-item">
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
      <a href="admin_reports.php" class="nav-item active">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        <span>Reports</span>
      </a>
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
        <h1 class="page-title">Reports</h1>
        <p class="page-sub">Generate reports and analyze platform data.</p>
      </div>
    </div>

    <div class="page-content">
      <!-- Report type selector -->
      <div class="report-nav">
        <?php
        // Build base date query string to keep filters when switching tabs
        $date_qs = '';
        if ($date_from != '') $date_qs .= '&date_from=' . $date_from;
        if ($date_to   != '') $date_qs .= '&date_to='   . $date_to;
        ?>
        <a href="?report=session_summary<?php echo $date_qs; ?>"
           class="report-nav-btn <?php echo $report == 'session_summary' ? 'active' : ''; ?>">
          Session Summary
        </a>
        <a href="?report=student_progress<?php echo $date_qs; ?>"
           class="report-nav-btn <?php echo $report == 'student_progress' ? 'active' : ''; ?>">
          Student Progress
        </a>
        <a href="?report=subject_popularity"
           class="report-nav-btn <?php echo $report == 'subject_popularity' ? 'active' : ''; ?>">
          Subject Popularity
        </a>
        <a href="?report=feedback_ratings"
           class="report-nav-btn <?php echo $report == 'feedback_ratings' ? 'active' : ''; ?>">
          Feedback & Ratings
        </a>
      </div>

      <!-- Report card -->
      <div class="report-card">

        <div class="report-card-header">
          <div>
            <div class="report-card-title"><?php echo htmlspecialchars($current_title); ?></div>
            <div class="report-card-sub">
              <?php
              $descriptions = [
                'session_summary'    => 'CTE: per-tutor session totals, completion rates, hours taught, and average ratings.',
                'student_progress'   => 'CTE: per-student attendance rate, hours learned, tutors worked with, and reviews given.',
                'subject_popularity' => 'CTE: subjects ranked by session count and tutor count using RANK() window function.',
                'feedback_ratings'   => 'CTE: rating distribution and response rates per tutor, ranked by average rating.',
              ];
              echo $descriptions[$report] ?? '';
              ?>
            </div>
          </div>
          <?php
          $print_url = '?report=' . $report . '&print=1';
          if ($date_from != '') $print_url .= '&date_from=' . urlencode($date_from);
          if ($date_to   != '') $print_url .= '&date_to='   . urlencode($date_to);
          ?>
          <a href="<?php echo $print_url; ?>" target="_blank" class="print-btn">
            <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Report
          </a>
        </div>

        <!-- Date filter (session & student reports only) -->
        <?php if (in_array($report, ['session_summary', 'student_progress'])) { ?>
        <form method="GET" action="admin_reports.php">
          <input type="hidden" name="report" value="<?php echo $report; ?>">
          <div class="date-filter-bar">
            <label>Filter by date:</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            <span style="font-size:13px; color:var(--muted);">to</span>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            <button type="submit" class="action-btn">Apply</button>
            <?php if ($date_from != '' || $date_to != '') { ?>
              <a href="?report=<?php echo $report; ?>" class="action-btn">Clear</a>
            <?php } ?>
          </div>
        </form>
        <?php } ?>

        <!-- ── SESSION SUMMARY ── -->
        <?php if ($report == 'session_summary') {
          if (count($session_summary) == 0) { ?>
            <div class="empty-report">No tutor session data found.</div>
          <?php } else {
            $total_sessions  = array_sum(array_column($session_summary, 'total_sessions'));
            $total_completed = array_sum(array_column($session_summary, 'completed_sessions'));
            $total_hours     = array_sum(array_column($session_summary, 'hours_taught'));
            $total_students  = array_sum(array_column($session_summary, 'unique_students'));
          ?>
            <div class="summary-strip">
              <div class="summary-strip-item">
                <span class="summary-strip-label">Tutors</span>
                <span class="summary-strip-value"><?php echo count($session_summary); ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Total Sessions</span>
                <span class="summary-strip-value"><?php echo $total_sessions; ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Completed</span>
                <span class="summary-strip-value"><?php echo $total_completed; ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Hours Taught</span>
                <span class="summary-strip-value"><?php echo $total_hours; ?>h</span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Students Reached</span>
                <span class="summary-strip-value"><?php echo $total_students; ?></span>
              </div>
            </div>
            <table>
              <thead>
                <tr>
                  <th>#</th><th>Tutor</th><th>Total</th><th>Completed</th>
                  <th>Cancelled</th><th>Completion Rate</th><th>Hours</th>
                  <th>Students</th><th>Avg Rating</th><th>Reviews</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($session_summary as $i => $row) { ?>
                  <tr>
                    <td>
                      <span class="rank-badge <?php echo $i < 3 ? 'rank-' . ($i + 1) : 'rank-n'; ?>">
                        <?php echo $i + 1; ?>
                      </span>
                    </td>
                    <td><div class="td-name"><?php echo htmlspecialchars($row['tutor_name']); ?></div></td>
                    <td><?php echo $row['total_sessions']; ?></td>
                    <td><span class="badge badge-success"><?php echo $row['completed_sessions']; ?></span></td>
                    <td><span class="badge badge-danger"><?php echo $row['cancelled_sessions']; ?></span></td>
                    <td>
                      <div class="mini-bar-wrap">
                        <div class="mini-bar-fill" style="width:<?php echo $row['completion_rate']; ?>%; background:#2A9D8F;"></div>
                      </div>
                      <?php echo $row['completion_rate']; ?>%
                    </td>
                    <td><?php echo $row['hours_taught']; ?>h</td>
                    <td><?php echo $row['unique_students']; ?></td>
                    <td>
                      <?php if ($row['avg_rating'] > 0) { ?>
                        <span class="stars-display"><?php echo render_stars($row['avg_rating']); ?></span>
                        <span style="font-size:11px; color:var(--muted); margin-left:4px;">
                          <?php echo number_format($row['avg_rating'], 1); ?>
                        </span>
                      <?php } else { echo '—'; } ?>
                    </td>
                    <td><?php echo $row['total_reviews']; ?></td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>

        <!-- ── STUDENT PROGRESS ── -->
        <?php } elseif ($report == 'student_progress') {
          if (count($student_progress) == 0) { ?>
            <div class="empty-report">No student session data found.</div>
          <?php } else {
            $total_sessions  = array_sum(array_column($student_progress, 'total_sessions'));
            $total_completed = array_sum(array_column($student_progress, 'completed_sessions'));
            $total_hours     = array_sum(array_column($student_progress, 'hours_learned'));
          ?>
            <div class="summary-strip">
              <div class="summary-strip-item">
                <span class="summary-strip-label">Students</span>
                <span class="summary-strip-value"><?php echo count($student_progress); ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Total Sessions</span>
                <span class="summary-strip-value"><?php echo $total_sessions; ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Completed</span>
                <span class="summary-strip-value"><?php echo $total_completed; ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Hours Learned</span>
                <span class="summary-strip-value"><?php echo $total_hours; ?>h</span>
              </div>
            </div>
            <table>
              <thead>
                <tr>
                  <th>#</th><th>Student</th><th>Year</th><th>Total</th>
                  <th>Completed</th><th>Attendance Rate</th><th>Hours</th>
                  <th>Tutors</th><th>Reviews</th><th>Last Session</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($student_progress as $i => $row) {
                  // Pick bar color based on attendance rate
                  if ($row['attendance_rate'] >= 80) {
                    $bar_color = '#27AE60';
                  } elseif ($row['attendance_rate'] >= 50) {
                    $bar_color = '#E9C46A';
                  } else {
                    $bar_color = '#C0392B';
                  }
                ?>
                  <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><div class="td-name"><?php echo htmlspecialchars($row['student_name']); ?></div></td>
                    <td><?php echo $row['year_level'] ? 'Year ' . $row['year_level'] : '—'; ?></td>
                    <td><?php echo $row['total_sessions']; ?></td>
                    <td><span class="badge badge-success"><?php echo $row['completed_sessions']; ?></span></td>
                    <td>
                      <div class="mini-bar-wrap">
                        <div class="mini-bar-fill" style="width:<?php echo $row['attendance_rate']; ?>%; background:<?php echo $bar_color; ?>;"></div>
                      </div>
                      <?php echo $row['attendance_rate']; ?>%
                    </td>
                    <td><?php echo $row['hours_learned']; ?>h</td>
                    <td><?php echo $row['unique_tutors']; ?></td>
                    <td><?php echo $row['reviews_given']; ?></td>
                    <td>
                      <div class="td-meta">
                        <?php echo $row['last_session'] ? date('M d, Y', strtotime($row['last_session'])) : '—'; ?>
                      </div>
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>

        <!-- ── SUBJECT POPULARITY ── -->
        <?php } elseif ($report == 'subject_popularity') {
          if (count($subject_popularity) == 0) { ?>
            <div class="empty-report">No subject data found.</div>
          <?php } else {
            $all_session_counts = array_column($subject_popularity, 'total_sessions');
            $max_sessions = max($all_session_counts) > 0 ? max($all_session_counts) : 1;
          ?>
            <div class="summary-strip">
              <div class="summary-strip-item">
                <span class="summary-strip-label">Total Subjects</span>
                <span class="summary-strip-value"><?php echo count($subject_popularity); ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Total Sessions</span>
                <span class="summary-strip-value"><?php echo array_sum(array_column($subject_popularity, 'total_sessions')); ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Total Tutors</span>
                <span class="summary-strip-value"><?php echo array_sum(array_column($subject_popularity, 'tutor_count')); ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Students Reached</span>
                <span class="summary-strip-value"><?php echo array_sum(array_column($subject_popularity, 'unique_students')); ?></span>
              </div>
            </div>
            <table>
              <thead>
                <tr>
                  <th>Rank</th><th>Subject</th><th>Tutors</th>
                  <th>Total Sessions</th><th>Completed</th><th>Students Reached</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($subject_popularity as $row) {
                  $bar_width = round(($row['total_sessions'] / $max_sessions) * 100);
                ?>
                  <tr>
                    <td>
                      <span class="rank-badge <?php echo $row['popularity_rank'] <= 3 ? 'rank-' . $row['popularity_rank'] : 'rank-n'; ?>">
                        #<?php echo $row['popularity_rank']; ?>
                      </span>
                    </td>
                    <td><div class="td-name"><?php echo htmlspecialchars($row['subject_name']); ?></div></td>
                    <td><?php echo $row['tutor_count']; ?></td>
                    <td>
                      <div class="mini-bar-wrap">
                        <div class="mini-bar-fill" style="width:<?php echo $bar_width; ?>%; background:#1A3A5C;"></div>
                      </div>
                      <?php echo $row['total_sessions']; ?>
                    </td>
                    <td><span class="badge badge-success"><?php echo $row['completed_sessions']; ?></span></td>
                    <td><?php echo $row['unique_students']; ?></td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>

        <!-- ── FEEDBACK & RATINGS ── -->
        <?php } elseif ($report == 'feedback_ratings') {
          if (count($feedback_report) == 0) { ?>
            <div class="empty-report">No feedback data found.</div>
          <?php } else {
            $total_reviews   = array_sum(array_column($feedback_report, 'total_reviews'));
            $total_responses = array_sum(array_column($feedback_report, 'responses_given'));

            // Calculate platform average rating
            $rating_sum   = 0;
            $rating_count = 0;
            foreach ($feedback_report as $row) {
                if ($row['avg_rating'] > 0) {
                    $rating_sum  += $row['avg_rating'];
                    $rating_count++;
                }
            }
            $platform_avg = $rating_count > 0 ? number_format($rating_sum / $rating_count, 2) : '—';
          ?>
            <div class="summary-strip">
              <div class="summary-strip-item">
                <span class="summary-strip-label">Tutors Reviewed</span>
                <span class="summary-strip-value"><?php echo count($feedback_report); ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Total Reviews</span>
                <span class="summary-strip-value"><?php echo $total_reviews; ?></span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Platform Avg</span>
                <span class="summary-strip-value"><?php echo $platform_avg; ?> ★</span>
              </div>
              <div class="summary-strip-item">
                <span class="summary-strip-label">Responses Given</span>
                <span class="summary-strip-value"><?php echo $total_responses; ?></span>
              </div>
            </div>
            <table>
              <thead>
                <tr>
                  <th>Rank</th><th>Tutor</th><th>Avg Rating</th><th>Reviews</th>
                  <th>5★</th><th>4★</th><th>3★</th><th>2★</th><th>1★</th>
                  <th>Responded</th><th>Pending</th><th>Response Rate</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($feedback_report as $row) { ?>
                  <tr>
                    <td>
                      <span class="rank-badge <?php echo $row['rating_rank'] <= 3 ? 'rank-' . $row['rating_rank'] : 'rank-n'; ?>">
                        #<?php echo $row['rating_rank']; ?>
                      </span>
                    </td>
                    <td><div class="td-name"><?php echo htmlspecialchars($row['tutor_name']); ?></div></td>
                    <td>
                      <span class="stars-display"><?php echo render_stars($row['avg_rating']); ?></span>
                      <span style="font-size:11px; color:var(--muted); margin-left:4px;">
                        <?php echo number_format($row['avg_rating'], 2); ?>
                      </span>
                    </td>
                    <td><?php echo $row['total_reviews']; ?></td>
                    <td><span style="color:#27AE60; font-weight:600;"><?php echo $row['rating_5']; ?></span></td>
                    <td><?php echo $row['rating_4']; ?></td>
                    <td><?php echo $row['rating_3']; ?></td>
                    <td><?php echo $row['rating_2']; ?></td>
                    <td><span style="color:#C0392B; font-weight:600;"><?php echo $row['rating_1']; ?></span></td>
                    <td><span class="badge badge-success"><?php echo $row['responses_given']; ?></span></td>
                    <td><span class="badge badge-danger"><?php echo $row['responses_pending']; ?></span></td>
                    <td>
                      <div class="mini-bar-wrap">
                        <div class="mini-bar-fill" style="width:<?php echo $row['response_rate']; ?>%; background:#2A9D8F;"></div>
                      </div>
                      <?php echo $row['response_rate']; ?>%
                    </td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>
        <?php } ?>

      </div><!-- end report-card -->
    </div><!-- end page-content -->
  </main>

  <!-- Logout modal -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Sign Out</h2>
        <button type="button" class="modal-close" onclick="document.getElementById('logoutModal').classList.remove('open')">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="font-size:14px; color:var(--muted); margin-bottom:20px;">Are you sure you want to sign out?</p>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="document.getElementById('logoutModal').classList.remove('open')">Cancel</button>
        <a href="../account/logout.php" class="btn-confirm danger">Yes, Sign Out</a>
      </div>
    </div>
  </div>

  <script>
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
      overlay.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
      });
    });
  </script>

<?php } ?>
</body>
</html>
