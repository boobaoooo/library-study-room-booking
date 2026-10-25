<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once "db.php";

$requireAdmin = true;
require "auth_protect.php";

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { echo json_encode(["success"=>false,"message"=>"not_logged_in"]); exit; }

$role = '';
$stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID=?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!in_array($r['Role'], ['admin','super admin'])) {
    echo json_encode(["success"=>false,"message"=>"forbidden"]); exit;
}

$raw = file_get_contents("php://input");
$input = json_decode($raw,true);
$action = $_GET['action'] ?? ($input['action'] ?? null);

if ($action === "fetch") {
    $date = $input["date"];

    $sql = "
        SELECT 
            b.Booking_Date,
            r.Room_Name,
            b.BStart_Time,
            b.BEnd_Time,
            u.Name,
            rs.R_Status
        FROM book b
        JOIN room r ON b.Room_ID = r.Room_ID
        JOIN reservation rs ON b.Reservation_ID = rs.Reservation_ID
        JOIN user u ON rs.User_ID = u.User_ID
        WHERE b.Booking_Date = ?
        ORDER BY b.BStart_Time
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s",$date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(["success"=>true,"records"=>$result]);
    exit;
}

if ($action === "download") {
    $date = $_GET['date'];

    $sql = "
        SELECT 
            b.Booking_Date,
            r.Room_Name,
            b.BStart_Time,
            b.BEnd_Time,
            u.Name,
            rs.R_Status
        FROM book b
        JOIN room r ON b.Room_ID = r.Room_ID
        JOIN reservation rs ON b.Reservation_ID = rs.Reservation_ID
        JOIN user u ON rs.User_ID = u.User_ID
        WHERE b.Booking_Date = ?
        ORDER BY b.BStart_Time
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s",$date);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=booking_history_$date.xls");

    echo "DATE\tROOM\tSTART\tEND\tBOOKER\tSTATUS\n";
    foreach ($records as $r) {
        echo "{$r['Booking_Date']}\t{$r['Room_Name']}\t{$r['BStart_Time']}\t{$r['BEnd_Time']}\t{$r['Name']}\t{$r['R_Status']}\n";
    }
    exit;
}

echo json_encode(["success"=>false,"message"=>"invalid_action"]);
exit;
?>
