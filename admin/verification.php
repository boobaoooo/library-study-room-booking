<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once "db.php";

$requireAdmin = true;
require "auth_protect.php";

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header("Location: /librarystudyroombooking/login.php"); exit; }

$stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$roleRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!in_array($roleRow['Role'], ['admin','super admin'])) {
    http_response_code(403); exit("Forbidden");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Verification</title>
<link rel="stylesheet" href="verification.css">
</head>
<body>

<?php include "navbar.php"; ?>

<main class="verify-main">

    <div class="verify-header">
        <h2>User Verification</h2>
    </div>

    <div class="verify-actions">
        <input id="searchInput" type="text" placeholder="Search name or email...">
        <button id="refreshBtn" class="btn-refresh">Refresh</button>
    </div>

    <div class="verify-table-container" id="tableContainer">
        <div class="loading">Loading pending verification...</div>
    </div>

</main>

<script>
function escapeHtml(s){
  if (!s) return "";
  return s.replace(/&/g,"&amp;")
          .replace(/</g,"&lt;")
          .replace(/>/g,"&gt;")
          .replace(/"/g,"&quot;");
}

const tableContainer = document.getElementById("tableContainer");
const searchInput = document.getElementById("searchInput");
const refreshBtn = document.getElementById("refreshBtn");

async function loadPending(query="") {
    tableContainer.innerHTML = `<div class="loading">Loading...</div>`;

    const res = await fetch("verification_process.php", {
        method:"POST",
        headers:{ "Content-Type":"application/json" },
        body:JSON.stringify({ action:"fetch", q:query })
    });

    const data = await res.json();
    if (!data.success) {
        tableContainer.innerHTML = `<div class="error">${data.message}</div>`;
        return;
    }

    renderTable(data.users);
}

function renderTable(users){
    if (users.length === 0) {
        tableContainer.innerHTML = `<div class="empty">No users waiting for verification.</div>`;
        return;
    }

    let html = `
<table class="verify-table">
    <thead>
        <tr>
            <th class="col-avatar"></th>
            <th class="col-id">User ID</th>
            <th class="col-name">Name</th>
            <th class="col-email">Email</th>
            <th class="col-role">Role</th>
            <th class="col-status">Status</th>
            <th class="col-action">Action</th>
        </tr>
    </thead>
    <tbody>
`;


    users.forEach(u=>{
        html += `
        <tr>
            <td><img src="assets/avatar_icon.png" class="avatar-icon"></td>
            <td>${u.User_ID}</td>
            <td>${escapeHtml(u.Name)}</td>
            <td>${escapeHtml(u.Email)}</td>
            <td>${u.Role}</td>
            <td><span class="badge badge-pending">pending</span></td>
            <td><button class="btn-verify" onclick="verifyUser(${u.User_ID}, '${escapeHtml(u.Name)}', this)">Verify</button></td>
        </tr>

        `;
    });

    html += `</tbody></table>`;
    tableContainer.innerHTML = html;
}

async function verifyUser(uid, name, btn){
    if (!confirm(`Verify "${name}"?\nThis will activate their account.`)) return;

    btn.textContent = "Verifying...";
    btn.disabled = true;

    const res = await fetch("verification_process.php", {
        method:"POST",
        headers:{ "Content-Type":"application/json" },
        body:JSON.stringify({ action:"verify", user_id:uid })
    });

    const data = await res.json();
    if (!data.success) {
        alert(data.message);
        btn.textContent = "Verify";
        btn.disabled = false;
        return;
    }

    btn.textContent = "Verified ✓";
    btn.classList.add("verified");
    btn.disabled = true;

    setTimeout(()=> loadPending(searchInput.value.trim()), 800);
}

searchInput.addEventListener("keyup", e=>{
    if (e.key === "Enter") loadPending(searchInput.value.trim());
});

refreshBtn.addEventListener("click", ()=>{
    loadPending(searchInput.value.trim());
});

loadPending();
</script>

</body>
</html>
