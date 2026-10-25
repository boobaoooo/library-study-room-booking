<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";

// ต้องเป็นแอดมินเท่านั้น
$requireAdmin = true;
require "auth_protect.php";

// ตรวจสอบ role อีกชั้น
if ($_SESSION['role'] !== 'super admin') {
    die("Not authorized");
}

// ลบข้อมูลใน admin_history ทั้งหมด
$stmt = $conn->prepare("DELETE FROM admin_history");

if ($stmt->execute()) {
    // สำเร็จ
    header("Location: user_activity.php?msg=deleted");
    exit;
} else {
    echo "Failed to delete history";
}
?>
