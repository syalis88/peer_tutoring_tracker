<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../classes/PHPMailer/src/PHPMailer.php';
require '../classes/PHPMailer/src/SMTP.php';
require '../classes/PHPMailer/src/Exception.php';
require '../classes/peer_tutoring_trackerDB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../account/forgot_password.php');
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    header('Location: ../account/forgot_password.php?error=empty');
    exit;
}

// Check if email exists
$stmt = $conn->prepare("SELECT user_id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ../account/forgot_password.php?status=sent');
    exit;
}

// Generate token
$token = bin2hex(random_bytes(32));
$expiry = gmdate('Y-m-d H:i:s', time() + 3600);

$stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
$stmt->execute([$token, $expiry, $user['user_id']]);

$reset_link = "http://localhost/PEER_TUTORING_TRACKER/account/reset_password.php?token=" . $token;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'peerturoring@gmail.com'; 
    $mail->Password   = 'pcdslgglcturvuwj';   
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('peerturoring@gmail.com', 'Peer Tutoring Tracker');
    $mail->addAddress($email, $user['name']);
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset Request';
    $mail->Body    = "
        <p>Hi <strong>{$user['name']}</strong>,</p>
        <p>We received a request to reset your password. Click the link below to set a new one:</p>
        <p><a href='{$reset_link}'>Reset My Password</a></p>
        <p>This link will expire in <strong>1 hour</strong>.</p>
        <p>If you did not request this, you can safely ignore this email.</p>
        <br>
        <p>— Peer Tutoring Tracker</p>
    ";
    $mail->AltBody = "Reset your password here: $reset_link (expires in 1 hour)";

    $mail->send();
    header('Location: ../account/forgot_password.php?status=sent');
} catch (Exception $e) {
    header('Location: ../account/forgot_password.php?error=mail');
}
exit;