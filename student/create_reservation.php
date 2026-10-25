<?php
require_once __DIR__ . '/../auth_protect.php';
protect('student');

require 'db.php';

$user_id      = $_SESSION['user_id'];
$room_id      = $_POST['room_id'] ?? null;
$booking_date = $_POST['booking_date'] ?? null;

$slot_starts = $_POST['slot_start'] ?? [];
$slot_ends   = $_POST['slot_end'] ?? [];

if (!$room_id || !$booking_date || empty($slot_starts)) {
    die("Missing booking data");
}

// 1) CHECK QUOTA
$check = $conn->prepare("
    SELECT COUNT(*) 
    FROM book b
    JOIN reservation r ON b.Reservation_ID = r.Reservation_ID
    WHERE r.User_ID = ?
      AND b.Booking_Date = ?
");
$check->bind_param("is", $user_id, $booking_date);
$check->execute();
$check->bind_result($currentSlots);
$check->fetch();
$check->close();

$newSlots = count($slot_starts);
if ($currentSlots + $newSlots > 2) {
    header("Location: reservation_failed.php?error=Exceeded_daily_quota");
    exit;
}

// 2) CHECK SLOT OVERLAP
$conflict = $conn->prepare("
    SELECT 1 FROM book
    WHERE Room_ID = ?
      AND Booking_Date = ?
      AND NOT (BEnd_Time <= ? OR BStart_Time >= ?)
    LIMIT 1
");
for ($i = 0; $i < count($slot_starts); $i++) {
    $s = $slot_starts[$i];
    $e = $slot_ends[$i];
    $conflict->bind_param("isss", $room_id, $booking_date, $s, $e);
    $conflict->execute();
    $conflict->store_result();
    if ($conflict->num_rows > 0) {
        header("Location: reservation_failed.php?error=Slot_overlap");
        exit;
    }
}
$conflict->close();

// 3) CREATE RESERVATION USING PROCEDURE
$stmt = $conn->prepare("CALL sp_CreateReservation(?, ?)");
$stmt->bind_param("ii", $user_id, $room_id);
$stmt->execute();

$result = $stmt->get_result();
if (!$result) {
    die("Procedure error");
}

$row = $result->fetch_assoc();
$reservation_id = $row['reservation_id'];
$stmt->close();
$conn->next_result();

// 4) INSERT EACH SLOT INTO book
$insertSlot = $conn->prepare("
    INSERT INTO book(Reservation_ID, Room_ID, Booking_Date, BStart_Time, BEnd_Time)
    VALUES(?, ?, ?, ?, ?)
");

for ($i = 0; $i < count($slot_starts); $i++) {
    $s = $slot_starts[$i];
    $e = $slot_ends[$i];
    $insertSlot->bind_param("iisss", $reservation_id, $room_id, $booking_date, $s, $e);
    $insertSlot->execute();
}
$insertSlot->close();

// 4.5) Update reservation with aggregated times (min start, max end) and booking_date
// compute in PHP from arrays (robust and avoids extra JOIN)
$minStart = null;
$maxEnd = null;
for ($i = 0; $i < count($slot_starts); $i++) {
    $s = $slot_starts[$i];
    $e = $slot_ends[$i];
    if ($minStart === null || strcmp($s, $minStart) < 0) $minStart = $s;
    if ($maxEnd === null || strcmp($e, $maxEnd) > 0) $maxEnd = $e;
}

if ($minStart !== null && $maxEnd !== null) {
    $updateR = $conn->prepare("
        UPDATE reservation
        SET Booking_Date = ?, BStart_Time = ?, BEnd_Time = ?
        WHERE Reservation_ID = ?
    ");
    $updateR->bind_param("sssi", $booking_date, $minStart, $maxEnd, $reservation_id);
    $updateR->execute();
    $updateR->close();
}

// 5) REDIRECT SUCCESS
header("Location: reservation_success.php?id=$reservation_id");
exit;
