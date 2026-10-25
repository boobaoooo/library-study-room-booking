<?php
// student_management.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }


require_once("db.php"); // <-- your DB connection (mysqli $conn)

$requireAdmin = true;
require "auth_protect.php";

// basic role gate (admin / super admin only)
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { http_response_code(401); echo "Not logged in."; exit; }

$role = '';
if ($stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID = ? LIMIT 1")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rr = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $role = $rr['Role'] ?? '';
}
if (!in_array($role, ['admin', 'super admin'])) {
    http_response_code(403); echo "Forbidden"; exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Student Management</title>
<link rel="stylesheet" href="student_mgmt.css">
</head>
<body>
  <?php include 'navbar.php'; ?>
<main class="smain">
  <div class="sm-header">
    <h2>Student Management</h2>
  </div>

  <div class="sm-actions">
    <input id="searchInput" placeholder="Search name or email..." />
    <button id="refreshBtn" class="btn">Refresh</button>
    <a href="view_blacklist.php" class="btn btn-danger">View Blacklist</a>
  </div>

  <div class="sm-table-container" id="tableContainer">
    <div class="loading">Loading students…</div>
  </div>
</main>

<!-- Edit modal -->
<div id="editModal" class="sm-modal" aria-hidden="true">
  <div class="sm-modal-content">
    <button class="sm-modal-close" onclick="closeModal()">✕</button>
    <h3>Edit Student</h3>

    <form id="editForm">
      <input type="hidden" name="user_id" id="user_id" />

      <label>Name</label>
      <input type="text" id="name" name="name" readonly />

      <label>Email</label>
      <input type="email" id="email" name="email" readonly />

      <label>Role</label>
      <select id="role" name="role">
        <option value="student">student</option>
        <option value="admin">admin</option>
        
      </select>

      <label>Status</label>
      <select id="status" name="status">
        <option value="active">active</option>
        <option value="blacklisted">blacklisted</option>
      </select>

      <label>Cancellation Count</label>
      <input type="number" id="cancellation_count" name="cancellation_count" min="0"  readonly/>

      <div class="sm-info">
        <h4>Booking summary (today & upcoming)</h4>
        <div id="bookingList">—</div>
      </div>

      <div class="sm-modal-actions">
        <button type="button" id="saveBtn" class="btn primary">Save</button>
        <button type="button" onclick="closeModal()" class="btn">Cancel</button>
      </div>

      <div id="modalMessage" class="modal-message"></div>
    </form>
  </div>
</div>

<script>
// helper
function escapeHtml(s){ if(!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

const tableContainer = document.getElementById('tableContainer');
const searchInput = document.getElementById('searchInput');
const refreshBtn = document.getElementById('refreshBtn');

async function fetchStudents(q='') {
  tableContainer.innerHTML = '<div class="loading">Loading students…</div>';
  try {
    const res = await fetch('student_mgmt_process.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'list', q })
    });
    const j = await res.json();
    if (!j.success) throw new Error(j.message || 'fetch failed');
    renderTable(j.students);
  } catch (e) {
    tableContainer.innerHTML = '<div class="error">Error: '+ e.message +'</div>';
    console.error(e);
  }
}

function renderTable(students) {
  const table = document.createElement('table');
  table.className = 'sm-table';
  const thead = document.createElement('thead');
  thead.innerHTML = `<tr>
    <th>User ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Cancel Count</th><th>Actions</th>
  </tr>`;
  table.appendChild(thead);

  const tbody = document.createElement('tbody');
  students.forEach(s => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${escapeHtml(s.User_ID)}</td>
      <td>${escapeHtml(s.Name)}</td>
      <td>${escapeHtml(s.Email)}</td>
      <td>${escapeHtml(s.Role)}</td>
      <td>${escapeHtml(s.Status)}</td>
      <td>${escapeHtml(s.Cancellation_count ?? 0)}</td>
      <td><button class="btn edit-btn" data-id="${s.User_ID}">Edit</button></td>`;
    tbody.appendChild(tr);
  });

  table.appendChild(tbody);
  tableContainer.innerHTML = '';
  tableContainer.appendChild(table);

  // wire edit buttons
  document.querySelectorAll('.edit-btn').forEach(b => {
    b.addEventListener('click', () => openEdit(b.dataset.id));
  });
}

async function openEdit(userId) {
  // fetch details
  try {
    const res = await fetch('student_mgmt_process.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'get', user_id: userId })
    });
    const j = await res.json();
    if (!j.success) { alert('Error: ' + (j.message||'failed')); return; }
    const u = j.user;
    document.getElementById('user_id').value = u.User_ID;
    document.getElementById('name').value = u.Name || '';
    document.getElementById('email').value = u.Email || '';
    document.getElementById('role').value = u.Role || 'student';
    document.getElementById('status').value = u.Status || 'active';
    document.getElementById('cancellation_count').value = u.Cancellation_count ?? 0;

    // booking list
    const bl = document.getElementById('bookingList');
    bl.innerHTML = '';
    if (j.bookings && j.bookings.length) {
      j.bookings.forEach(b => {
        const div = document.createElement('div');
        div.className = 'booking-row';
        div.innerHTML = `${escapeHtml(b.Booking_Date)} ${escapeHtml(b.BStart_Time)}-${escapeHtml(b.BEnd_Time)} — Room ${escapeHtml(b.Room_ID)} — Status: ${escapeHtml(b.R_Status)} (Res#${b.Reservation_ID})`;
        bl.appendChild(div);
      });
    } else {
      bl.textContent = 'No current/upcoming bookings.';
    }

    // show modal
    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('editModal').setAttribute('aria-hidden','false');
    document.getElementById('modalMessage').textContent = '';

  } catch (e) {
    alert('Failed to load user: ' + e.message);
    console.error(e);
  }
}

function closeModal() {
  document.getElementById('editModal').style.display = 'none';
  document.getElementById('editModal').setAttribute('aria-hidden','true');
}

// save handler
document.getElementById('saveBtn').addEventListener('click', async () => {
  const payload = {
    action: 'update',
    user_id: document.getElementById('user_id').value,
    role: document.getElementById('role').value,
    status: document.getElementById('status').value,
    
  };
  const msgEl = document.getElementById('modalMessage');
  msgEl.textContent = 'Saving...';
  try {
    const res = await fetch('student_mgmt_process.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const j = await res.json();
    if (!j.success) {
      msgEl.textContent = 'Error: ' + (j.message || 'update failed');
    } else {
      msgEl.textContent = 'Saved.';
      // refresh list and close
      await fetchStudents(searchInput.value.trim());
      setTimeout(closeModal, 600);
    }
  } catch (e) {
    msgEl.textContent = 'Request failed: ' + e.message;
    console.error(e);
  }
});

refreshBtn.addEventListener('click', ()=> fetchStudents(searchInput.value.trim()));
searchInput.addEventListener('keyup', (e) => {
  if (e.key === 'Enter') fetchStudents(searchInput.value.trim());
});

// initial load
fetchStudents();
</script>
</body>
</html>
