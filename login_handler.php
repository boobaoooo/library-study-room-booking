<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db.php';
require_once __DIR__ . '/security_helpers.php';

$email = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;

if (!$email || !$password) {
    die("Missing login fields");
}

// หา user ตาม email
$sql = "SELECT * FROM user WHERE Email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: login.php?error=Account not found");
    exit;
}

$user = $result->fetch_assoc();

// 🔐 ตรวจรหัสผ่าน (รองรับ hash แบบใหม่ และแบบเก่า SHA-256/MD5)
$needsRehash = false;
if (!check_password($password, $user['PasswordHash'], $needsRehash)) {
    header("Location: login.php?error=Incorrect password");
    exit;
}

// ถ้าเป็น hash แบบเก่า → อัปเกรดเป็น password_hash อัตโนมัติ
if ($needsRehash) {
    $newHash = make_password_hash($password);
    $rh = $conn->prepare("UPDATE user SET PasswordHash = ? WHERE User_ID = ?");
    $rh->bind_param("si", $newHash, $user['User_ID']);
    $rh->execute();
    $rh->close();
}

// บล็อก user ที่โดน blacklist
if (strtolower($user['Status']) === 'blacklisted') {
    header("Location: login.php?error=This account has been blacklisted");
    exit;
}

// ✔️ เพิ่มเงื่อนไข: Student ต้อง Verified ก่อน
$role = strtolower(trim($user['Role']));
$verified = strtolower(trim($user['Verify_Status'] ?? ''));

if ($role === 'student') {
    if ($verified !== 'verified' && $verified !== 'yes' && $verified !== '1') {
        header("Location: login.php?error=Your account is not verified");
        exit;
    }
}

// ตั้ง session (สร้าง session id ใหม่ กัน session fixation)
session_regenerate_id(true);
$_SESSION['user_id'] = $user['User_ID'];
$_SESSION['role']    = $user['Role'];

// normalize role
$role = strtolower(trim($user['Role']));

// redirect ตาม role
switch ($role) {
    case 'student':
        header("Location: /librarystudyroombooking/student/home.php");
        exit;

    case 'admin':
        header("Location: /librarystudyroombooking/admin/room_mnm3.php");
        exit;

    case 'superadmin':
    case 'super admin':
        header("Location: /librarystudyroombooking/superadmin/staff.php");
        exit;

    default:
        die("Unknown role: " . $role);
}

?>
