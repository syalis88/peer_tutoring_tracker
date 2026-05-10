<?php
require '../classes/peer_tutoring_trackerDB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../account/login.php');
    exit;
}

$token       = trim($_POST['token'] ?? '');
$password    = $_POST['password'] ?? '';
$confirm     = $_POST['confirm_password'] ?? '';

if (empty($token) || empty($password) || empty($confirm)) {
    header("Location: ../account/reset_password.php?token=$token&error=empty");
    exit;
}

if ($password !== $confirm) {
    header("Location: ../account/reset_password.php?token=$token&error=mismatch");
    exit;
}

if (strlen($password) < 8) {
    header("Location: ../account/reset_password.php?token=$token&error=short");
    exit;
}

// Validate token
$stmt = $conn->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ../account/reset_password.php?error=invalid');
    exit;
}

// Update password and clear token
$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
$stmt->execute([$hashed, $user['user_id']]);

header('Location: ../account/login.php?status=reset_success');
exit;
