<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$db = "library_room_booking";
$user = "super admin";   // FIXED
$pass = "superad123";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

function logActivity($conn, $user_id, $type, $description) {
    $sql = "INSERT INTO admin_history (user_id, action_type, description) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $type, $description);
    $stmt->execute();
    $stmt->close();
}
?>

