<?php
// booking_management_process.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$requireAdmin = true;
require "auth_protect.php";
require_once("db.php");

require_once "admin_log.php";
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { echo json_encode(['success'=>false,'message'=>'not_logged_in']); exit; }

// role check
$role = '';
$stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$role = $row['Role'] ?? '';

if (!in_array($role, ['admin','super admin'])) {
    echo json_encode(['success'=>false,'message'=>'forbidden']);
    exit;
}

// read JSON input
$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? $_POST;
$action = $input['action'] ?? null;


// ======================================================
// FETCH TODAY'S BOOKINGS
// ======================================================
if ($action === 'fetch_today') {

    $today = date('Y-m-d');

    // 1) fetch rooms
    $rooms = [];
    $roomQuery = $conn->query("SELECT Room_ID, Room_Name, Capacity, Equipment, Room_status FROM room ORDER BY Room_ID");
    while ($r = $roomQuery->fetch_assoc()) {
        $rooms[] = $r;
    }

    // 2) bookings today
    $stmt = $conn->prepare("
        SELECT b.Book_ID, b.Reservation_ID, b.Room_ID, b.Booking_Date,
               b.BStart_Time, b.BEnd_Time,
               r.R_Status, r.User_ID, u.Name
        FROM book b
        JOIN reservation r ON b.Reservation_ID = r.Reservation_ID
        JOIN user u ON r.User_ID = u.User_ID
        WHERE b.Booking_Date = ?
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // build reservation map
    $reservations = [];
    foreach ($bookings as $bk) {
        $rid = $bk['Reservation_ID'];
        $reservations[$rid] = [
            'Reservation_ID' => $rid,
            'User_ID'        => $bk['User_ID'],
            'Name'           => $bk['Name'],
            'R_Status'       => $bk['R_Status']
        ];
    }

    echo json_encode([
        'success' => true,
        'rooms' => $rooms,
        'bookings' => $bookings,
        'reservations' => $reservations
    ]);
    exit;
}


// ======================================================
// UPDATE EQUIPMENT
// ======================================================
if ($action === 'update_equipment') {

    $room_id = $input['room_id'] ?? null;
    $equipment = $input['equipment'] ?? '';

    if (!$room_id) {
        echo json_encode(['success'=>false,'message'=>'Missing room ID']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE room SET Equipment = ? WHERE Room_ID = ?");
    if (!$stmt) {
        echo json_encode(['success'=>false,'message'=>'SQL error: '.$conn->error]);
        exit;
    }

    $stmt->bind_param("si", $equipment, $room_id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        echo json_encode(['success'=>false,'message'=>'Update failed']);
        exit;
    }

    echo json_encode(['success'=>true,'message'=>'Facilities updated']);
    exit;
}



// ======================================================
// UPDATE RESERVATION STATUS
// ======================================================
if ($action === 'update_status') {

    $reservation_id = intval($input['reservation_id'] ?? 0);
    $newStatus = $input['status'] ?? null;

    if (!$reservation_id || !$newStatus) {
        echo json_encode(['success'=>false,'message'=>'missing_params']);
        exit;
    }

    // Fetch reservation
    $stmt = $conn->prepare("SELECT Reservation_ID, User_ID, R_Status FROM reservation WHERE Reservation_ID = ? LIMIT 1");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $resv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$resv) {
        echo json_encode(['success'=>false,'message'=>'reservation_not_found']);
        exit;
    }


    // ========== CANCEL ========== 
    if ($newStatus === 'cancelled') {

        $conn->begin_transaction();

        // delete book slots
        $d = $conn->prepare("DELETE FROM book WHERE Reservation_ID = ?");
        $d->bind_param("i", $reservation_id);
        $d->execute();
        $d->close();

        // update reservation
        $u = $conn->prepare("UPDATE reservation SET R_Status = ? WHERE Reservation_ID = ?");
        $u->bind_param("si", $newStatus, $reservation_id);
        $u->execute();
        $u->close();

        $conn->commit();

        echo json_encode(['success'=>true, 'message'=>'cancelled']);
        log_admin_action($conn, $_SESSION['user_id'], 'CANCEL_RESERVATION', 'reservation', $reservation_id, "Admin cancelled reservation");

        exit;
    }

    // ========== ACTIVATE ==========
    if ($newStatus === 'active') {

        // 1) check user not blacklisted
        $stmt = $conn->prepare("SELECT Status FROM user WHERE User_ID = ?");
        $stmt->bind_param("i", $resv['User_ID']);
        $stmt->execute();
        $ux = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($ux && $ux['Status'] !== 'active') {
            echo json_encode(['success'=>false,'message'=>'user_blacklisted']);
            exit;
        }

        // 2) check booking conflicts
        $bq = $conn->prepare("
            SELECT Room_ID, Booking_Date, BStart_Time, BEnd_Time
            FROM book
            WHERE Reservation_ID = ?
        ");
        $bq->bind_param("i", $reservation_id);
        $bq->execute();
        $slots = $bq->get_result()->fetch_all(MYSQLI_ASSOC);
        $bq->close();

        foreach ($slots as $s) {
            $chk = $conn->prepare("
                SELECT b.Book_ID
                FROM book b
                JOIN reservation r ON b.Reservation_ID = r.Reservation_ID
                WHERE b.Room_ID = ?
                AND b.Booking_Date = ?
                AND NOT (b.BEnd_Time <= ? OR b.BStart_Time >= ?)
                AND r.Reservation_ID <> ?
                AND r.R_Status = 'active'
                LIMIT 1
            ");
            $chk->bind_param(
                "isssi",
                $s['Room_ID'],
                $s['Booking_Date'],
                $s['BEnd_Time'],
                $s['BStart_Time'],
                $reservation_id
            );
            $chk->execute();
            $found = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($found) {
                echo json_encode(['success'=>false,'message'=>'conflict_with_active_booking']);
                exit;
            }
        }

        // 3) passed → activate
        $up = $conn->prepare("UPDATE reservation SET R_Status = ? WHERE Reservation_ID = ?");
        $up->bind_param("si", $newStatus, $reservation_id);
        $ok = $up->execute();
        $up->close();

        if ($ok) {
            echo json_encode(['success'=>true,'message'=>'activated']);
            exit;
        } else {
            echo json_encode(['success'=>false,'message'=>'db_error']);
            exit;
        }
    }

    // ========== OTHER STATUS (completed, pending) ==========
    $up = $conn->prepare("UPDATE reservation SET R_Status = ? WHERE Reservation_ID = ?");
    $up->bind_param("si", $newStatus, $reservation_id);
    $ok = $up->execute();
    $up->close();

    echo json_encode(['success'=>$ok]);
    exit;
}



// ======================================================
// FALLBACK — action NOT MATCHED
// ======================================================
echo json_encode(['success'=>false,'message'=>'unknown_action']);
exit;

?>
