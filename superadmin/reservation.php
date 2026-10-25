<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require "db.php";
require_once "auth_superadmin.php";  // ensures only superadmin enters

$msg = "";

/* --------------------------------------------------
   DATE FILTER INPUT
--------------------------------------------------*/
$start = $_GET['start_date'] ?? "";
$end   = $_GET['end_date'] ?? "";

$where = "";
$bind_types = "";
$bind_values = [];

// Build dynamic conditions for filtering by Booking_Date
if ($start && $end) {
    $where = "WHERE DATE(b.Booking_Date) BETWEEN ? AND ?";
    $bind_types = "ss";
    $bind_values = [$start, $end];

} elseif ($start) {
    $where = "WHERE DATE(b.Booking_Date) >= ?";
    $bind_types = "s";
    $bind_values = [$start];

} elseif ($end) {
    $where = "WHERE DATE(b.Booking_Date) <= ?";
    $bind_types = "s";
    $bind_values = [$end];
}

/* --------------------------------------------------
   LOAD RESERVATION DATA (with date filter)
--------------------------------------------------*/
$sql = "
SELECT 
    r.Reservation_ID,
    r.User_ID,
    u.Name AS StudentName,
    u.Email,
    r.Room_ID,
    rm.Room_Name,
    r.Reservation_Date,
    r.R_Status,
    b.Booking_Date,
    b.BStart_Time,
    b.BEnd_Time
FROM reservation r
LEFT JOIN user u ON r.User_ID = u.User_ID
LEFT JOIN room rm ON r.Room_ID = rm.Room_ID
LEFT JOIN book b ON r.Reservation_ID = b.Reservation_ID
$where
ORDER BY r.Reservation_ID ASC
";

$stmt = $conn->prepare($sql);

if (!empty($bind_values)) {
    $stmt->bind_param($bind_types, ...$bind_values);
}

$stmt->execute();
$result = $stmt->get_result();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>View Reservations</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="c9.css">
</head>
<body>

<?php include "header.php"; ?>

<main class="container">

    <h1 class="login-heading">VIEW RESERVATION</h1>

    <!-- ⭐ DATE FILTER -->
    <form method="GET" class="filter-box" style="text-align:center; margin-bottom:20px;">
        <label>
            From:
            <input type="date" name="start_date" 
                   value="<?= htmlspecialchars($start) ?>">
        </label>

        <label style="margin-left:20px;">
            To:
            <input type="date" name="end_date" 
                   value="<?= htmlspecialchars($end) ?>">
        </label>

        <button class="btn" style="margin-left:20px; background:#4d6b3c; color:white;">
            FILTER
        </button>
    </form>

    <div class="page-box">

        <?php if($msg): ?>
            <div class="alert"><?= $msg ?></div>
        <?php endif; ?>

        <table class="data-table2">
            <thead>
                <tr>
                    <th>Reservation_ID</th>
                    <th>User_ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Room</th>
                    <th>Booking Date</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php while($r = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $r['Reservation_ID'] ?></td>
                        <td><?= $r['User_ID'] ?></td>
                        <td><?= htmlspecialchars($r['StudentName']) ?></td>
                        <td><?= htmlspecialchars($r['Email']) ?></td>
                        <td><?= htmlspecialchars($r['Room_Name']) ?></td>
                        <td><?= $r['Booking_Date'] ?></td>
                        <td><?= $r['BStart_Time'] ?></td>
                        <td><?= $r['BEnd_Time'] ?></td>
                        <td><?= $r['R_Status'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    </div>

    <div class="controls">
        <a href="download_reservation.php" class="btn pnk">DOWNLOAD</a>
    </div>

</main>

</body>
</html>



