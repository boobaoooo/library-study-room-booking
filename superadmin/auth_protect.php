<?php
// auth_protect.php
// วางไฟล์นี้ไว้ที่ root ของโปรเจกต์ (ข้างๆ login.php, db.php)
// ใช้งาน: require_once __DIR__ . '/auth_protect.php'; protect(); หรือ protect('admin');

function protect($required_role = null, $login_path = '/librarystudyroombooking/login.php') {
    // เริ่ม session ถ้ายังไม่เริ่ม
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // ป้องกัน browser cache (สำคัญสำหรับ Back หลัง logout)
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");

    // ตรวจว่ามีการล็อกอินหรือไม่
    if (!isset($_SESSION['user_id'])) {
        // ถ้าเป็น AJAX อาจจะส่ง 401 แทน redirect แต่ที่นี่ใช้ redirect เข้า login
        header("Location: {$login_path}");
        exit;
    }

    // ถ้ามีการกำหนด role ที่ต้องการให้เช็ก
    if ($required_role !== null) {
        // คาดว่า role เก็บใน $_SESSION['role'] เช่น 'student', 'admin', 'superadmin'
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
            // ถ้าไม่มีสิทธิ์ ให้ไปหน้าที่เหมาะสม (login หรือ error)
            header("Location: {$login_path}?error=forbidden");
            exit;
        }
    }

    // ถ้าผ่าน เงื่อนไขทั้งหมด จะ return true เพื่อให้ไฟล์เรียกใช้ต่อได้
    return true;
}
?>
