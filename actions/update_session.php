<?php
require_once '../classes/peer_tutoring_trackerDB.php';

$session_id = $_POST['session_id'];
$status     = $_POST['status'];

$stmt = $conn->prepare("UPDATE sessions SET status = ? WHERE session_id = ?");
$stmt->execute([$status, $session_id]);

header("Location: " . $_SERVER['HTTP_REFERER']);