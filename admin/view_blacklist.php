<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }


require_once "db.php";

$requireAdmin = true;
require "auth_protect.php";

// --- Role Check ---
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { http_response_code(401); exit("Not logged in"); }

$stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID=?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!in_array($r['Role'], ['admin','super admin'])) {
    http_response_code(403); exit("Forbidden");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>View Blacklist</title>
    <link rel="stylesheet" href="view_blacklist.css">
</head>
<body>

<?php include "navbar.php"; ?>

<main class="smain">
    <h2 class="page-title">View Blacklist</h2>

    <div class="back-section">
        <a href="student_management.php" class="back-btn">← Back</a>
    </div>

    <div class="sm-actions">
        <input type="date" id="datePicker">
        <button class="btn primary" id="loadBtn">Load</button>
        <button class="btn danger" id="downloadBtn">Download</button>
    </div>

    <div class="sm-table-container" id="tableContainer">
        <div class="loading">Select a date to load blacklist data…</div>
    </div>
</main>

<!-- Modal to confirm update -->
<div id="confirmModal" class="sm-modal" aria-hidden="true">
    <div class="sm-modal-content">
        <button class="sm-modal-close" onclick="closeModal()">✕</button>
        <h3>Confirm Update</h3>
        <p id="modalText"></p>

        <div class="sm-modal-actions">
            <button class="btn primary" id="confirmUpdateBtn">Confirm</button>
            <button onclick="closeModal()" class="btn">Cancel</button>
        </div>

        <div id="modalMessage" class="modal-message"></div>
    </div>
</div>

<script>
let pendingUpdate = null;

document.getElementById("loadBtn").onclick = loadBlacklist;
document.getElementById("downloadBtn").onclick = () => {
    let date = document.getElementById("datePicker").value;
    if (!date) return alert("Select a date!");
    window.location.href = "view_blacklist_process.php?action=download&date=" + date;
};

async function loadBlacklist() {
    let date = document.getElementById("datePicker").value;
    if (!date) return alert("Select a date");

    const container = document.getElementById("tableContainer");
    container.innerHTML = "<div class='loading'>Loading...</div>";

    const res = await fetch("view_blacklist_process.php", {
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({ action:"fetch", date })
    });

    const data = await res.json();
    if (!data.success) {
        container.innerHTML = `<div class='error'>${data.message}</div>`;
        return;
    }

    renderTable(data.records);
}

function renderTable(rows) {
    let html = `
        <table class="sm-table">
            <thead>
                <tr>
                    <th>Blacklist ID</th>
                    <th>User ID</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Date Added</th>
                    <th>Date Removed</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
    `;

    rows.forEach(r => {
        html += `
            <tr data-id="${r.Blacklist_ID}">
                <td>${r.Blacklist_ID}</td>
                <td>${r.User_ID}</td>
                <td><input class="editable" type="text" value="${r.Reason}"></td>

                <td>
                    <select class="editable status-dropdown">
                        <option value="active" ${r.Blacklist_Status==='active'?'selected':''}>active</option>
                        <option value="inactive" ${r.Blacklist_Status==='inactive'?'selected':''}>inactive</option>
                    </select>
                </td>

                <td>${r.Date_Added ?? '-'}</td>
                <td>${r.Date_Removed ?? '-'}</td>

                <td><button class="btn primary saveRowBtn">Save</button></td>
            </tr>
        `;
    });

    html += "</tbody></table>";
    document.getElementById("tableContainer").innerHTML = html;

    document.querySelectorAll(".saveRowBtn").forEach(btn => {
        btn.onclick = (e) => prepareUpdate(e.target.closest("tr"));
    });
}

function prepareUpdate(row) {
    let id = row.dataset.id;
    let reason = row.querySelector("input").value;
    let status = row.querySelector(".status-dropdown").value;

    pendingUpdate = { id, reason, status };

    document.getElementById("modalText").innerHTML = `
        Update blacklist entry <b>ID ${id}</b>?
    `;

    openModal();
}

document.getElementById("confirmUpdateBtn").onclick = async () => {
    const res = await fetch("view_blacklist_process.php", {
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({
            action:"update",
            id: pendingUpdate.id,
            reason: pendingUpdate.reason,
            status: pendingUpdate.status
        })
    });

    const data = await res.json();
    const msg = document.getElementById("modalMessage");

    if (!data.success) {
        msg.innerHTML = `<div class='error'>${data.message}</div>`;
    } else {
        msg.innerHTML = `<div class='success'>${data.message}</div>`;
        setTimeout(()=>{ closeModal(); loadBlacklist(); }, 900);
    }
};

function openModal() {
    let m = document.getElementById("confirmModal");
    m.style.display="flex";
    m.setAttribute("aria-hidden","false");
}

function closeModal() {
    document.getElementById("confirmModal").style.display="none";
    document.getElementById("confirmModal").setAttribute("aria-hidden","true");
    document.getElementById("modalMessage").innerHTML = "";
}
</script>

</body>
</html>
