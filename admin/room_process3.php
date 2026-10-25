<?php
// room_process.php - handle AJAX actions for room management with date view

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');



require_once("db.php");
$requireAdmin = true;
require "auth_protect.php";

require_once "admin_log.php";

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'not_logged_in']);
    exit;
}

// check role for editing
$isAdmin = false;
if ($stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID = ? LIMIT 1")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rr = $stmt->get_result()->fetch_assoc();
    if ($rr && ($rr['Role'] === 'admin' || $rr['Role'] === 'super admin')) $isAdmin = true;
    $stmt->close();
    
}


// parse input
$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = $input['action'] ?? ($_GET['action'] ?? null);

// server's today
$today = date('Y-m-d');

try {
    if ($action === 'fetch_for_date') {
        $date = $input['date'] ?? $today;
        // rooms (global Room_Status)
        $rooms = [];
        $rres = $conn->query("SELECT Room_ID, Room_Name, Capacity, Equipment, Room_Status FROM room ORDER BY Room_ID");
        while ($r = $rres->fetch_assoc()) $rooms[] = $r;

        // bookings for the requested date
        $stmt = $conn->prepare("SELECT Book_ID, Reservation_ID, Room_ID, Booking_Date, BStart_Time, BEnd_Time FROM book WHERE Booking_Date = ?");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $bres = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success'=>true, 'rooms'=>$rooms, 'bookings'=>$bres, 'date'=>$date]);
        exit;
    }

    if ($action === 'update_room_status_for_date') {
    if (!$isAdmin) {
        echo json_encode(['success'=>false,'message'=>'not_authorized']);
        
        exit;
    }

    $room_id = intval($input['room_id'] ?? 0);
    $date = $input['date'] ?? null;
    $status = $input['status'] ?? null;

    if (!$room_id || !$date) {
        echo json_encode(['success'=>false,'message'=>'missing_params']);
        exit;
    }

    if (!in_array($status, ['available','unavailable'])) {
        echo json_encode(['success'=>false,'message'=>'invalid_status']);
        exit;
    }

    // only allow edits for TODAY
    if ($date !== date('Y-m-d')) {
        echo json_encode(['success'=>false,'message'=>'edit_today_only']);
        exit;
    }

    // if setting unavailable → notify students who booked today
    if ($status === 'unavailable') {
        $sql = "SELECT DISTINCT Reservation_ID FROM book WHERE Room_ID=? AND Booking_Date=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $room_id, $date);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $rid = $row['Reservation_ID'];

            // call your existing notification procedure
            $conn->query("CALL sp_SendNotification('RESERVE_CANCEL', $rid)");
        }
        $stmt->close();
    }

    // update room status (whole-day)
    $stmt2 = $conn->prepare("UPDATE room SET Room_Status=? WHERE Room_ID=?");
    
    $stmt2->bind_param("si", $status, $room_id);
    $stmt2->execute();
    $stmt2->close();
    
    echo json_encode(['success'=>true]);
    log_admin_action($conn, $_SESSION['user_id'], 'EDIT_ROOM', 'room', $room_id, "Changed availability to $status");


    exit;


}


    if ($action === 'delete_rooms') {
        if (!$isAdmin) { echo json_encode(['success'=>false,'message'=>'not_authorized']); exit; }
        $ids = $input['room_ids'] ?? [];
        if (!is_array($ids) || count($ids) === 0) { echo json_encode(['success'=>false,'message'=>'no_rooms_selected']); exit; }
        $idList = implode(",", array_map("intval", $ids));
        $sql = "DELETE FROM room WHERE Room_ID IN ($idList)";
        $ok = $conn->query($sql);
        if (!$ok) { echo json_encode(['success'=>false,'message'=>'db_error: '.$conn->error]); exit; }
        echo json_encode(['success'=>true]);
        foreach ($ids as $id) {
    log_admin_action(
        $conn,
        $_SESSION['user_id'],
        'DELETE_ROOM',
        'room',
        $id,
        "Room deleted"
    );
        exit;
        
}


        
    }

    echo json_encode(['success'=>false,'message'=>'unknown_action']);
    exit;

} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    exit;
}
?>
