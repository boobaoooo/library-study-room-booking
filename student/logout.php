<?php
// logout.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ลบ session ทั้งหมด
session_unset();
session_destroy();

// ป้องกัน cache กลับไปหน้าก่อนหน้า หลัง logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// redirect ไปหน้า login
header("Location: /librarystudyroombooking/login.php?logout=success");
exit;
?>
