<?php
session_start();
require_once '../classes/peer_tutoring_trackerDB.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'student') {
    header("Location: ../account/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $student_id  = $_SESSION['user_id'];
    $tutor_id    = (int)$_POST['tutor_id'];
    $date        = $_POST['session_date'];       // e.g. "2026-05-02 08:00:00"
    $duration    = (int)$_POST['duration'];      // in minutes

    // Calculate the end time of the requested session
    $start = new DateTime($date);
    $end   = (clone $start)->modify("+{$duration} minutes");

    $start_str = $start->format('Y-m-d H:i:s');
    $end_str   = $end->format('Y-m-d H:i:s');

    // ── Overlap check ─────────────────────────────────────────
    // A conflict exists if any existing scheduled session for this tutor
    // overlaps the requested time window.
    //
    // Overlap condition (two intervals [A,B] and [C,D] overlap when A < D AND C < B):
    //   existing_start < requested_end  AND  requested_start < existing_end
    //
    $overlap = $conn->prepare("
        SELECT session_id
        FROM sessions
        WHERE tutor_id = ?
          AND status = 'scheduled'
          AND session_date < ?
          AND DATE_ADD(session_date, INTERVAL duration MINUTE) > ?
        LIMIT 1
    ");
    $overlap->execute([$tutor_id, $end_str, $start_str]);

    if ($overlap->fetch()) {
        // Conflict found — redirect back with error
        header("Location: student_book_session.php?tutor_id={$tutor_id}&error=overlap");
        exit();
    }

    // ── Also check if the student themselves already has a conflict ───
    $student_overlap = $conn->prepare("
        SELECT session_id
        FROM sessions
        WHERE student_id = ?
          AND status = 'scheduled'
          AND session_date < ?
          AND DATE_ADD(session_date, INTERVAL duration MINUTE) > ?
        LIMIT 1
    ");
    $student_overlap->execute([$student_id, $end_str, $start_str]);

    if ($student_overlap->fetch()) {
        header("Location: student_book_session.php?tutor_id={$tutor_id}&error=student_overlap");
        exit();
    }

    // ── No conflict — safe to insert ──────────────────────────
    $stmt = $conn->prepare("
        INSERT INTO sessions (student_id, tutor_id, session_date, duration, status)
        VALUES (?, ?, ?, ?, 'scheduled')
    ");
    $stmt->execute([$student_id, $tutor_id, $date, $duration]);

    header("Location: ../student/student_dashboard.php?status=booked");
    exit();
}