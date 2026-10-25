<?php
require 'db.php';
require_once __DIR__ . '/security_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

// รับข้อมูลจากฟอร์ม
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// สมัครผ่านหน้านี้ได้เฉพาะนักศึกษาเท่านั้น
// (บัญชี admin / super admin สร้างได้จากหน้า superadmin เท่านั้น)
$role = 'student';

// ตรวจสอบค่าว่าง
if ($username === '' || $email === '' || $password === '') {
    header("Location: signup.php?error=" . urlencode("All fields are required"));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: signup.php?error=" . urlencode("Invalid email"));
    exit;
}

if (strlen($password) < 6) {
    header("Location: signup.php?error=" . urlencode("Password must be at least 6 characters"));
    exit;
}

// อีเมลซ้ำ
$chk = $conn->prepare("SELECT User_ID FROM user WHERE Email = ?");
$chk->bind_param("s", $email);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    header("Location: signup.php?error=" . urlencode("Email already registered"));
    exit;
}
$chk->close();

// ---------------------------
// Hash Password
// ---------------------------
$hashedPassword = make_password_hash($password);

// ---------------------------
// Upload profile image (optional)
// ---------------------------
$profileImage = null;

if (!empty($_FILES['profileImage']['name'])) {
    $up = save_uploaded_image($_FILES['profileImage'], 'profile_new', __DIR__ . '/uploads');
    if (!$up['ok']) {
        header("Location: signup.php?error=" . urlencode($up['error']));
        exit;
    }
    // เก็บแค่ชื่อไฟล์ (หน้าอื่นจะต่อ path /uploads/ ให้เอง)
    $profileImage = $up['filename'];
}

// ---------------------------
// Insert user
// ---------------------------
$sql = "INSERT INTO user (Name, Email, PasswordHash, Role, Status, Verify_Status, Cancellation_Count, Profile_Image)
        VALUES (?, ?, ?, ?, 'active', 'pending', 0, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $username, $email, $hashedPassword, $role, $profileImage);

if ($stmt->execute()) {
    header("Location: login.php?error=" . urlencode("Signup successful. Please wait for admin verification."));
    exit;
} else {
    header("Location: signup.php?error=" . urlencode("Signup failed"));
    exit;
}
