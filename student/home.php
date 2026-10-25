<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../auth_protect.php';
protect('student');
require_once 'db.php';

$user_id = $_SESSION['user_id'];

// นับจำนวน slot ของ user ในวันนี้ (ดูจาก table book)
$countStmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM book b
    JOIN reservation r ON b.Reservation_ID = r.Reservation_ID
    WHERE r.User_ID = ?
      AND b.Booking_Date = CURDATE()
");
$countStmt->bind_param("i", $user_id);
$countStmt->execute();
$countStmt->bind_result($todayCount);
$countStmt->fetch();
$countStmt->close();

$canBookToday = $todayCount < 2;



// ดึงชื่อและ role
$stmt = $conn->prepare("SELECT Role, Name FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user || $user['Role'] !== 'student') {
    header("Location: /librarystudyroombooking/login.php?error=access_denied");
    exit;
}

// วันที่ปัจจุบัน
date_default_timezone_set('Asia/Bangkok');
$today_date = date("Y-m-d");
$now_time = date("H:i:s");

// timeslot 08:00–18:00
$slots = [];
for ($h = 8; $h < 18; $h++) {
    $slots[] = [
        'start' => sprintf("%02d:00:00", $h),
        'end'   => sprintf("%02d:00:00", $h + 1),
        'label' => sprintf("%02d:00 - %02d:00", $h, $h + 1)
    ];
}

// ดึงห้องทั้งหมด
$rooms = [];
$res = $conn->query("SELECT * FROM room ORDER BY Room_ID ASC");
while ($r = $res->fetch_assoc()) $rooms[] = $r;

// ดึง booking วันนี้ที่ active
$bookings = [];
$stmt = $conn->prepare("
    SELECT b.Room_ID, b.BStart_Time, b.BEnd_Time
    FROM book b
    JOIN reservation r ON b.Reservation_ID = r.Reservation_ID
    WHERE b.Booking_Date = ?
       AND r.R_Status IN ('active', 'pending')
");
$stmt->bind_param("s", $today_date);
$stmt->execute();
$q = $stmt->get_result();
while ($row = $q->fetch_assoc()) {
    $bookings[$row['Room_ID']][] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Available Rooms</title>
    <link rel="stylesheet" href="home.css">
</head>
<body>

<?php include 'tabbar.php'; ?>

<div class="page-bg">
    <h1 class="title">AVAILABLE ROOMS</h1>

    <div class="table-bg-box">
        <div class="table-wrapper">

            <table class="modern-table">
                <thead>
                    <tr>
                        <th class="ts-col">TIME SLOT</th>
                        <?php foreach ($rooms as $room): ?>
                            <th class="room-col">
                                <div class="room-header-card">
                                    <span class="r-name"><?= htmlspecialchars($room["Room_Name"]) ?></span>
                                    <span class="r-cap">Capacity: <?= $room["Capacity"] ?></span>
                                    <span class="r-facility" onclick="showFac('<?= addslashes($room['Equipment']) ?>')">
                                        Facilities
                                    </span>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($slots as $slot): ?>
                        <tr>
                            <td class="ts-cell"><?= $slot["label"] ?></td>

                            <?php foreach ($rooms as $room):

                                $roomID = $room['Room_ID'];

                                // ห้องปิดทั้งวัน
                                if ($room['Room_Status'] === 'unavailable') {
                                    echo '<td class="slot-cell"><div class="pill pill-unavail">Closed</div></td>';
                                    continue;
                                }

                                // เช็ค Past (สำคัญที่สุด!)
                                $isPast = ($slot['end'] <= $now_time);

                                // เช็ค booking
                                $isBooked = false;

                                if (!$isPast && isset($bookings[$roomID])) {
                                    foreach ($bookings[$roomID] as $b) {
                                        if (
                                            $b['BStart_Time'] < $slot['end'] &&
                                            $b['BEnd_Time'] > $slot['start']
                                        ) {
                                            $isBooked = true;
                                        }
                                    }
                                }

                                // ตัดสินสถานะ
                                $available = (!$isPast && !$isBooked);
                                $class = $available ? "pill-avail" : "pill-unavail";
                            ?>

                            <td class="slot-cell">
                                <?php if ($available): ?>
                                    <div class="pill <?= $class ?> clickable"
                                        data-room="<?= $roomID ?>"
                                        data-roomname="<?= htmlspecialchars($room['Room_Name']) ?>"
                                        data-capacity="<?= $room['Capacity'] ?>"
                                        data-equipment="<?= htmlspecialchars($room['Equipment']) ?>"
                                        data-status="<?= htmlspecialchars($room['Room_Status']) ?>"
                                        data-start="<?= $slot['start'] ?>"
                                        data-end="<?= $slot['end'] ?>"
                                        onclick="selectSlot(this)">
                                        Available
                                    </div>
                                <?php else: ?>
                                    <div class="pill <?= $class ?>">Unavailable</div>
                                <?php endif; ?>
                            </td>

                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>

        </div>
    </div>

    <div class="selected-box">
        <h3>Selected Time Slots</h3>
        <ul id="selectedList"></ul>

        <button type="button" class="reserve-btn" onclick="openPopup()">Confirm Reservation</button>
    </div>

</div>

<!-- POPUP -->
<div id="popupOverlay" class="popup-overlay">
    <div class="popup-container">
        <h1 class="popup-title">ROOM RESERVATION</h1>

        <div class="reservation-box">
            <h2>ROOM DETAILS</h2>
            <ul class="details">
                <li><strong>Booker Name:</strong> <?= htmlspecialchars($user['Name']) ?></li>
                <li><strong>Room:</strong> <span id="p_roomname"></span></li>
                <li><strong>Availability:</strong> <span id="p_status"></span></li>
                <li><strong>Capacity:</strong> <span id="p_capacity"></span> persons</li>
                <li><strong>Facilities:</strong> <span id="p_equipment"></span></li>
                <li><strong>Booking Date:</strong> <span id="p_date"></span></li>

                <div class="timeslot-bg">
                    <h3 class="timeslot-title">Booking Timeslot</h3>
                    <div id="timeslotContainer"></div>
                </div>
            </ul>

            <form id="popupForm" action="create_reservation.php" method="POST">
                <input type="hidden" name="room_id" id="f_room">
                <input type="hidden" name="booking_date" id="f_date">
                <div id="slotHiddenContainer"></div>
            </form>

            <div class="buttons">
                <button type="submit" form="popupForm" class="confirm-btn">CONFIRM</button>
                <button type="button" class="cancel-btn" onclick="closePopup()">CANCEL</button>
            </div>

        </div>
    </div>
</div>

<input type="hidden" id="php_today" value="<?= $today_date ?>">

<script>
let selected = [];
const MAX = 2;

function showFac(text) { alert("Facilities:\n" + text); }

function selectSlot(box) {
    const key = box.dataset.room + "|" + box.dataset.start + "|" + box.dataset.end;
    const index = selected.findIndex(s => s.key === key);

    if (index !== -1) {
        selected.splice(index, 1);
        box.classList.remove("selected-pill");
        updateList();
        return;
    }

    if (selected.length >= MAX) return alert("You can select up to 2 slots.");
    if (selected.length > 0 && selected[0].room !== box.dataset.room)
        return alert("Please select slots from the same room.");

    selected.push({
        key,
        room: box.dataset.room,
        roomname: box.dataset.roomname,
        capacity: box.dataset.capacity,
        equipment: box.dataset.equipment,
        status: box.dataset.status,
        start: box.dataset.start,
        end: box.dataset.end
    });

    box.classList.add("selected-pill");
    updateList();
}

function updateList() {
    const ul = document.getElementById("selectedList");
    ul.innerHTML = "";
    selected.sort((a,b) => a.start.localeCompare(b.start));

    selected.forEach(s => {
        const li = document.createElement("li");
        li.textContent = s.start.substring(0,5) + " - " + s.end.substring(0,5);
        ul.appendChild(li);
    });
}

function openPopup() {
    if (selected.length === 0) {
        alert("Please select a time slot first.");
        return;
    }

    let todayCount = <?= $todayCount ?>;

    if (todayCount >= 2) {
        alert("You have reached your daily limit of 2 slots.");
        return;
    }

    const today = document.getElementById("php_today").value;

    document.getElementById("p_roomname").innerText = selected[0].roomname;
    document.getElementById("p_status").innerText = selected[0].status;
    document.getElementById("p_capacity").innerText = selected[0].capacity;
    document.getElementById("p_equipment").innerText = selected[0].equipment;
    document.getElementById("p_date").innerText = today;

    const container = document.getElementById("timeslotContainer");
    container.innerHTML = "";
    selected.forEach(slot => {
        container.innerHTML += `
            <div class="timeslot-card">
                <div class="ts-block">
                    <p><span class="ts-label">Start Time:</span> ${slot.start.substring(0,5)}</p>
                    <p><span class="ts-label">End Time:</span> ${slot.end.substring(0,5)}</p>
                </div>
            </div>
        `;
    });

    document.getElementById("f_room").value = selected[0].room;
    document.getElementById("f_date").value = today;

    const hiddenDiv = document.getElementById("slotHiddenContainer");
    hiddenDiv.innerHTML = "";
    selected.forEach(slot => {
        hiddenDiv.innerHTML += `
            <input type="hidden" name="slot_start[]" value="${slot.start}">
            <input type="hidden" name="slot_end[]" value="${slot.end}">
        `;
    });

    document.getElementById("popupOverlay").style.display = "flex";
}

function closePopup() {
    document.getElementById("popupOverlay").style.display = "none";
}
</script>

</body>
</html>
