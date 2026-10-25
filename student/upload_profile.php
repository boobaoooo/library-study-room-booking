<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db.php';
require_once __DIR__ . '/../security_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /librarystudyroombooking/login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if (!isset($_FILES['profile_image'])) {
    die("No file uploaded.");
}

// ตรวจชนิดไฟล์จากเนื้อไฟล์จริง + ตั้งชื่อ/นามสกุลเอง
$up = save_uploaded_image($_FILES['profile_image'], 'profile_' . $user_id, __DIR__ . '/../uploads');
if (!$up['ok']) {
    die(htmlspecialchars($up['error']));
}
$newName = $up['filename'];

// Update DB
$stmt = $conn->prepare("UPDATE user SET Profile_Image = ? WHERE User_ID = ?");
$stmt->bind_param("si", $newName, $user_id);
$stmt->execute();
$stmt->close();

// Redirect back
header("Location: profile.php?upload=success");
exit;
