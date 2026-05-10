<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'tutor') {
    header("Location: ../account/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $tutor_id   = $_SESSION['user_id'];
    $student_id = (int)$_POST['student_id'];
    $date       = trim($_POST['session_date']);
    $duration   = (int)$_POST['duration'];

if (empty($student_id) || empty($date) || $duration <= 0) {
    header("Location: tutor_sessions.php?error=invalid");
    exit();
}

    $start     = new DateTime($date);
    $end       = (clone $start)->modify("+{$duration} minutes");
    $start_str = $start->format('Y-m-d H:i:s');
    $end_str   = $end->format('Y-m-d H:i:s');

    $conflict = $conn->prepare("
    SELECT session_id FROM sessions
    WHERE tutor_id = ? AND status = 'scheduled'
    AND session_date < ? AND DATE_ADD(session_date, INTERVAL duration MINUTE) > ?
    LIMIT 1
");

    $conflict->execute([$tutor_id, $end_str, $start_str]);
if ($conflict->fetch()) {
    header("Location: tutor_sessions.php?error=overlap");
    exit();
}

$stmt = $conn->prepare("INSERT INTO sessions (student_id, tutor_id, session_date, duration, status) VALUES (?, ?, ?, ?, 'scheduled')");
$stmt->execute([$student_id, $tutor_id, $date, $duration]);

header("Location: tutor_dashboard.php");
exit();
}