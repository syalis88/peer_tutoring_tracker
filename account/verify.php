<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    header('Location: login.php?error=invalid_token');
    exit;
}

$stmt = $conn->prepare("SELECT user_id, role, name FROM users WHERE verify_token = ? AND is_verified = 0");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php?error=invalid_token');
    exit;
}

// Mark as verified
$upd = $conn->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE user_id = ?");
$upd->execute([$user['user_id']]);

// Log them in automatically
$_SESSION['user_id']   = $user['user_id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];

if ($user['role'] == 'tutor') {
    header("Location: ../tutor/tutor_dashboard.php?status=verified");
} else {
    header("Location: ../student/student_dashboard.php?status=verified");
}
exit;