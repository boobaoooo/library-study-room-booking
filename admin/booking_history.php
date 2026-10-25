<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once "db.php";

$requireAdmin = true;
require "auth_protect.php";

// Role check
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { http_response_code(401); echo "Not logged in."; exit; }

$role = '';
$stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
$role = $r['Role'] ?? '';

if (!in_array($role, ['admin', 'super admin'])) {
    http_response_code(403); echo "Forbidden."; exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Booking History</title>
  <link rel="stylesheet" href="booking_history.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<main class="content">
    <h1 class="page-title">Booking History</h1>

    <div class="date-section">
        <label class="date-label">Select Date:</label>
        <input type="date" id="date" class="date-picker">
        <button id="loadBtn" class="btn primary">Load</button>
        <button id="downloadBtn" class="btn danger">Download Report</button>
    </div>

    <div id="tableContainer" class="table-container">
        <div class="loading">Select a date to view booking history...</div>
    </div>
</main>

<script>
const loadBtn = document.getElementById('loadBtn');
const dlBtn = document.getElementById('downloadBtn');

loadBtn.onclick = loadHistory;
dlBtn.onclick = downloadReport;

async function loadHistory() {
    let date = document.getElementById('date').value;
    if (!date) return alert("Please select a date.");

    const container = document.getElementById('tableContainer');
    container.innerHTML = `<div class="loading">Loading...</div>`;

    const res = await fetch("booking_history_process.php", {
        method: "POST",
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action:"fetch", date })
    });
    const data = await res.json();

    if (!data.success) {
        container.innerHTML = `<div class="error">${data.message}</div>`;
        return;
    }

    renderTable(data.records);
}

function renderTable(records) {
    const container = document.getElementById('tableContainer');
    if (!records.length) {
        container.innerHTML = `<div class="error">No bookings found for this date.</div>`;
        return;
    }

    let html = `
        <table class="history-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Room</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Booker</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
    `;

    records.forEach(r => {
        html += `
            <tr>
                <td>${r.Booking_Date}</td>
                <td>Room ${r.Room_Name}</td>
                <td>${r.BStart_Time}</td>
                <td>${r.BEnd_Time}</td>
                <td>${r.Name}</td>
                <td>${r.R_Status}</td>
            </tr>
        `;
    });

    html += `</tbody></table>`;

    container.innerHTML = html;
}

function downloadReport() {
    let date = document.getElementById('date').value;
    if (!date) return alert("Select a date first.");

    window.location.href = "booking_history_process.php?action=download&date=" + date;
}
</script>

</body>
</html>
