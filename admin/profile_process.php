<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }


require_once "db.php";
require_once __DIR__ . '/../security_helpers.php';

$requireAdmin = true;
require "auth_protect.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success"=>false, "message"=>"Not logged in"]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data || !isset($data['action'])) {
    echo json_encode(["success"=>false, "message"=>"Invalid request"]);
    exit;
}

$action = $data['action'];

// Update Username
if ($action === "update_name") {
    // ใช้ id จาก session เท่านั้น (ห้ามเชื่อค่าที่ส่งมาจาก browser)
    $uid = (int)$_SESSION['user_id'];
    $name = trim($data["name"]);

    if (empty($name)) {
        echo json_encode(["success"=>false, "message"=>"Name cannot be empty"]);
        exit;
    }

    // Check if name already exists (duplicate check)
    $stmt = $conn->prepare("SELECT User_ID FROM user WHERE Name=? AND User_ID<>?");
    $stmt->bind_param("si", $name, $uid);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(["success"=>false, "message"=>"Username already exists"]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Update name
    $stmt = $conn->prepare("UPDATE user SET Name=? WHERE User_ID=?");
    $stmt->bind_param("si", $name, $uid);
    if ($stmt->execute()) {
        echo json_encode(["success"=>true, "message"=>"Username updated successfully"]);
    } else {
        echo json_encode(["success"=>false, "message"=>"Database error"]);
    }
    $stmt->close();
    exit;
}

// Update Password
if ($action === "update_password") {
    // ใช้ id จาก session เท่านั้น (ห้ามเชื่อค่าที่ส่งมาจาก browser)
    $uid = (int)$_SESSION['user_id'];
    $password = $data["password"];

    if (empty($password)) {
        echo json_encode(["success"=>false, "message"=>"Password cannot be empty"]);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(["success"=>false, "message"=>"Password must be at least 6 characters"]);
        exit;
    }

    // Hash password
    $hashed = make_password_hash($password);
    // Update password
    $stmt = $conn->prepare("UPDATE user SET PasswordHash=? WHERE User_ID=?");
    $stmt->bind_param("si", $hashed, $uid);
    if ($stmt->execute()) {
        echo json_encode(["success"=>true, "message"=>"Password updated successfully"]);
    } else {
        echo json_encode(["success"=>false, "message"=>"Database error"]);
    }
    $stmt->close();
    exit;
}

echo json_encode(["success"=>false, "message"=>"Invalid action"]);
exit;
?>
