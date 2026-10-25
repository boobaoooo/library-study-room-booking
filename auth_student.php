<?php
// auth_student.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php?error=Please+login");
    exit;
}
require_once __DIR__ . '/db.php';

$uid = $_SESSION['user_id'];
// fetch role to ensure correct access
$stmt = $conn->prepare("SELECT Role, Status FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    session_destroy();
    header("Location: /librarystudyroombooking/student/login.php?error=User+not+found");
    exit;
}
$user = $res->fetch_assoc();
if ($user['Role'] !== 'student') {
    header("Location: /librarystudyroombooking/student/login.php?error=Access+denied");
    exit;
}
if ($user['Status'] === 'blacklisted') {
    // optional: send to a page informing blacklist
    header("Location: /librarystudyroombooking/student/login.php?error=Your+account+is+blacklisted");
    exit;
}
