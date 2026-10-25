<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db.php';

// 1) ตรวจสอบการเข้าระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: /librarystudyroombooking/login.php?error=not_logged_in");
    exit;
}

// 2) ตรวจสอบค่าที่ส่งมา
if (!isset($_POST['book_id'])) {
    header("Location: history.php?error=no_book_id");
    exit;
}

$book_id = intval($_POST['book_id']);
$user_id = $_SESSION['user_id'];

// 3) ตรวจสอบว่า book นี้เป็นของ user ที่ login จริงหรือไม่
$stmt = $conn->prepare("
    SELECT r.User_ID, r.Reservation_ID
    FROM book b
    JOIN reservation r ON b.Reservation_ID = r.Reservation_ID
    WHERE b.Book_ID = ?
");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$stmt->bind_result($owner_id, $reservation_id);
if (!$stmt->fetch()) {
    $stmt->close();
    header("Location: history.php?error=book_not_found");
    exit;
}
$stmt->close();

if ($owner_id != $user_id) {
    header("Location: history.php?error=not_owner");
    exit;
}

// 4) เรียกใช้ SP sp_CancelReservation (แบบใหม่)
//    ลบ slot เฉพาะ timeslot นี้ + เช็คว่ามี slot เหลือไหม
$stmt2 = $conn->prepare("CALL sp_CancelReservation(?)");
$stmt2->bind_param("i", $book_id);
$stmt2->execute();
$result = $stmt2->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt2->close();
$conn->next_result();

if (!$row || $row['status'] !== 'success') {
    header("Location: history.php?error=cancel_failed");
    exit;
}

$reservation_id = intval($row['reservation_id']);
$remaining = intval($row['remaining_slots']);

// 4.5) ถ้ายังมี slot เหลือ -> recompute min/max time จาก book ที่เหลือ
if ($remaining > 0) {
    $q = $conn->prepare("
        SELECT Booking_Date, MIN(BStart_Time) AS minStart, MAX(BEnd_Time) AS maxEnd
        FROM book
        WHERE Reservation_ID = ?
        GROUP BY Booking_Date
        LIMIT 1
    ");
    $q->bind_param("i", $reservation_id);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();

    if ($res) {
        $booking_date = $res['Booking_Date'];
        $minStart = $res['minStart'];
        $maxEnd = $res['maxEnd'];

        $up = $conn->prepare("
            UPDATE reservation
            SET Booking_Date = ?, BStart_Time = ?, BEnd_Time = ?
            WHERE Reservation_ID = ?
        ");
        $up->bind_param("sssi", $booking_date, $minStart, $maxEnd, $reservation_id);
        $up->execute();
        $up->close();
    }
} else {
    // remaining == 0 -> reservation fully cancelled
    // keep Booking_Date/BStart_Time/BEnd_Time as-is (history preserved)
    $up = $conn->prepare("UPDATE reservation SET R_Status = 'cancelled' WHERE Reservation_ID = ?");
    $up->bind_param("i", $reservation_id);
    $up->execute();
    $up->close();
}

// 5) ส่ง Notification ว่า user ได้ยกเลิก timeslot แล้ว (เรียก SP เหมือนเดิม)
$event_type = 'RESERVE_CANCEL';

// sp_SendNotification รับ 2 พารามิเตอร์ (event_type, reservation_id) และสร้างข้อความเอง
$stmtN = $conn->prepare("CALL sp_SendNotification(?, ?)");
$stmtN->bind_param("si", $event_type, $reservation_id);
$stmtN->execute();
$stmtN->close();


// 6) Redirect กลับไปหน้า History พร้อมข้อความ
if ($remaining === 0) {
    header("Location: history.php?success=reservation_fully_cancelled");
} else {
    header("Location: history.php?success=slot_cancelled");
}
exit;
