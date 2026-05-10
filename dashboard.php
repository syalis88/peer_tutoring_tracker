<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../account/login.php");
    exit();
}

if ($_SESSION['user_role'] == 'admin') {
    header("Location: admin/admin_dashboard.php");
} else if ($_SESSION['user_role'] == 'tutor') {
    header("Location: tutor/tutor_dashboard.php");
} else if ($_SESSION['user_role'] == 'student') {
    header("Location: student/student_dashboard.php");
} else {
    header("Location: account/login.php");
}
exit();