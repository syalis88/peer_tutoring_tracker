<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'student') {
    header("Location: ../account/login.php");
    exit();
}

$session_id = (int)$_POST['session_id'];
$rating     = (int)$_POST['rating'];
$comments   = trim($_POST['comments']);
$student_id = $_SESSION['user_id'];

if ($rating < 1 || $rating > 5 || empty($comments)) {
    header("Location: student_feedback.php?error=invalid");
    exit();
}

// Verify session belongs to this student and is completed
$check = $conn->prepare("SELECT session_id FROM sessions WHERE session_id = ? AND student_id = ? AND status = 'completed'");
$check->execute([$session_id, $student_id]);
if ($check->rowCount() == 0) {
    header("Location: student_feedback.php?error=invalid");
    exit();
}

// Prevent duplicate feedback
$dup = $conn->prepare("SELECT feedback_id FROM feedback WHERE session_id = ? AND student_id = ?");
$dup->execute([$session_id, $student_id]);
if ($dup->rowCount() > 0) {
    header("Location: student_feedback.php?error=duplicate");
    exit();
}

$stmt = $conn->prepare("INSERT INTO feedback (session_id, student_id, rating, comments) VALUES (?, ?, ?, ?)");
$stmt->execute([$session_id, $student_id, $rating, $comments]);

header("Location: student_feedback.php");
exit();