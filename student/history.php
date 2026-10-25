<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../auth_protect.php';
protect('student');

require 'db.php';

$user_id = $_SESSION['user_id'];

/* =======================
   Count cancelled
======================= */
$stmtCancel = $conn->prepare("
    SELECT COUNT(*) 
    FROM reservation 
    WHERE User_ID = ? AND R_Status = 'cancelled'
");
$stmtCancel->bind_param("i", $user_id);
$stmtCancel->execute();
$stmtCancel->bind_result($cancelCount);
$stmtCancel->fetch();
$stmtCancel->close();

$cancelLimit = 3;

/* =======================
   RECENT (active + pending + cancelled)
   We show reservation-level times (from reservation)
   but keep book.Book_ID so user can cancel specific slot when available
======================= */
$recent = [];

$stmt = $conn->prepare("
    SELECT 
        r.Reservation_ID,
        rm.Room_Name,
        r.R_Status,
        r.Booking_Date,
        r.BStart_Time,
        r.BEnd_Time,
        b.Book_ID
    FROM reservation r
    JOIN room rm ON rm.Room_ID = r.Room_ID
    LEFT JOIN book b ON b.Reservation_ID = r.Reservation_ID
    WHERE r.User_ID = ?
      AND r.R_Status IN ('active', 'pending', 'cancelled')
    ORDER BY r.Reservation_ID DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($rid, $room, $status, $date, $start, $end, $book_id);

while ($stmt->fetch()) {
    $recent[] = [
        "Reservation_ID" => $rid,
        "Room_Name" => $room,
        "Status" => $status,
        "Date" => $date,
        "StartTime" => $start,
        "EndTime" => $end,
        "Book_ID" => $book_id
    ];
}
$stmt->close();

/* =======================
   PREVIOUS (cancelled + completed)
======================= */
$previous = [];

$stmt2 = $conn->prepare("
    SELECT 
        r.Reservation_ID,
        rm.Room_Name,
        r.R_Status,
        r.Booking_Date,
        r.BStart_Time,
        r.BEnd_Time
    FROM reservation r
    JOIN room rm ON rm.Room_ID = r.Room_ID
    WHERE r.User_ID = ?
      AND (r.R_Status = 'cancelled' OR r.R_Status = 'completed')
    ORDER BY r.Reservation_ID DESC
");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$stmt2->bind_result($rid, $room, $status, $date, $start, $end);

while ($stmt2->fetch()) {
    $previous[] = [
        "Reservation_ID" => $rid,
        "Room_Name" => $room,
        "Status" => $status,
        "Date" => $date,
        "StartTime" => $start,
        "EndTime" => $end
    ];
}
$stmt2->close();

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>History</title>
<link rel="stylesheet" href="history.css">
</head>

<body>

<?php include 'tabbar.php'; ?>

<h1>HISTORY</h1>

<?php if ($cancelCount > 0): ?>
<div class="warning-box <?= ($cancelCount >= $cancelLimit) ? 'danger' : '' ?>">
    ⚠ You have cancelled <?= $cancelCount ?> / <?= $cancelLimit ?>
</div>
<?php endif; ?>

<!-- RECENT -->
<div class="section-title">RECENT BOOKING</div>
<table>
<tr><th>ROOM</th><th>DATE</th><th>TIME</th><th>STATUS</th></tr>

<?php foreach ($recent as $r): ?>
<tr>
<td><?= htmlspecialchars($r["Room_Name"]) ?></td>
<td><?= $r["Date"] ? htmlspecialchars($r["Date"]) : "-" ?></td>
<td>
    <?php if (!empty($r["StartTime"])): ?>
        <?= substr(htmlspecialchars($r["StartTime"]), 0, 5) ?> - <?= substr(htmlspecialchars($r["EndTime"]), 0, 5) ?>
    <?php else: ?>
        -
    <?php endif; ?>
</td>
<td>

<?php
$statusClass = strtolower($r["Status"]);
?>

<span class="status <?= $statusClass ?>">
    <?= strtoupper(htmlspecialchars($r["Status"])) ?>
</span>

<?php
// show cancel button only if reservation is active/pending AND there is a specific book slot id available
if (($statusClass === "active" || $statusClass === "pending") && !empty($r["Book_ID"])): ?>
    <form action="cancel_reservation.php" method="POST" style="display:inline;"
          onsubmit="return confirm('Warning: max cancellation is 3!');">
        <input type="hidden" name="book_id" value="<?= intval($r["Book_ID"]) ?>">
        <button type="submit" class="btn-cancel">CANCEL</button>
    </form>
<?php endif; ?>


</td>
</tr>
<?php endforeach; ?>
</table>

<!-- PREVIOUS -->
<div class="section-title">PREVIOUS BOOKING</div>
<table>
<tr><th>ROOM</th><th>DATE</th><th>TIME</th><th>STATUS</th></tr>

<?php foreach ($previous as $r): ?>
<tr>
<td><?= htmlspecialchars($r["Room_Name"]) ?></td>
<td><?= $r["Date"] ? htmlspecialchars($r["Date"]) : "-" ?></td>
<td>
    <?php if (!empty($r["StartTime"])): ?>
        <?= substr(htmlspecialchars($r["StartTime"]),0,5) ?> - <?= substr(htmlspecialchars($r["EndTime"]),0,5) ?>
    <?php else: ?>
        - 
    <?php endif; ?>
</td>
<td>
    <span class="status <?= htmlspecialchars(strtolower($r["Status"])) ?>"><?= strtoupper(htmlspecialchars($r["Status"])) ?></span>
</td>
</tr>
<?php endforeach; ?>
</table>

</body>
</html>
