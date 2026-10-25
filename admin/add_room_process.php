<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once("db.php");

$requireAdmin = true;
require "auth_protect.php";

// log admin action
require_once "admin_log.php";
log_admin_action($conn, $_SESSION['user_id'], 'ADD_ROOM', 'room', $room_id, "Added room: $room_name");

// Check if form submitted properly
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $room_id = $_POST['room_id'];
    $room_name = $_POST['room_name'];
    $capacity = $_POST['capacity'];
    $equipment = $_POST['equipment'];
    $status = $_POST['status']; // available / unavailable

    // INSERT QUERY
    $stmt = $conn->prepare("
        INSERT INTO room (Room_ID, Room_Name, Capacity, Equipment, Room_status)
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("isiss", $room_id, $room_name, $capacity, $equipment, $status);

    if ($stmt->execute()) {
        // Redirect back to room page
        header("Location: room_mnm3.php?success=room_added");
        exit;
    } else {
        echo "Error adding room: " . $stmt->error;
    }

    $stmt->close();
}
$conn->close();
?>
