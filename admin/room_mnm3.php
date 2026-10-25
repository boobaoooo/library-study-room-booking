<?php
// room_management.php - updated to support date picker (view any date) but allow edits only for TODAY

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$requireAdmin = true;
require "auth_protect.php";

require_once("db.php");


// basic auth
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo "Not logged in.";
    exit;
}


$role = 'student';
if ($stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID = ? LIMIT 1")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) $role = $r['Role'];
    $stmt->close();
}
$isAdmin = ($role === 'admin' || $role === 'super admin');

// Fetch rooms server-side (headers) to render quickly
$rooms = [];
$res = $conn->query("SELECT Room_ID, Room_Name, Capacity, Equipment, Room_Status FROM room ORDER BY Room_ID");
while ($row = $res->fetch_assoc()) $rooms[] = $row;

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Room Availability — Date View</title>
  <link rel="stylesheet" href="room_mnm3.css">
  
</head>
<body>
  <?php include 'navbar.php'; ?>

  <main class="content-only">
    <h1 class="page-title">Room Management</h1>

    <div class="date-row">
      <label for="date" class="date-label">Selected date:</label>
      <input type="date" id="date" class="date-picker">
    </div>

    <div class="table-wrapper-only">
      <div class="table-container" id="tableContainer">
        <div class="loading">Loading availability…</div>
      </div>
    </div>

    <div class="table-actions">
      <a href="add_room.php" class="btn add-room-btn">Add Room</a>
      <a href="#" id="deleteRoomBtn" class="btn delete-room-btn">Delete Room</a>
    </div>
  </main>

  <!-- Edit Modal (whole-day) -->
  <div id="editModal" class="modal" aria-hidden="true">
    <div class="modal-content">
      <button class="modal-close" onclick="closeModal()">✕</button>
      <h3>Edit Room Availability</h3>
      <p id="modalInfo"></p>

      <div style="margin-top:14px;">
        <label>
          <input type="radio" name="dayStatus" value="available" checked> Available
        </label>
        &nbsp;&nbsp;
        <label>
          <input type="radio" name="dayStatus" value="unavailable"> Unavailable
        </label>
      </div>

      <div class="modal-actions" style="margin-top:18px;">
        <button id="saveDayStatus" class="btn primary">Save</button>
        <button onclick="closeModal()" class="btn">Cancel</button>
      </div>
      <p id="modalMsg" class="modal-msg"></p>
    </div>
  </div>

  <!-- Delete Modal (keeps earlier delete behaviour) -->
  <div id="deleteModal" class="modal" aria-hidden="true">
    <div class="modal-content">
      <button class="modal-close" onclick="closeDeleteModal()">✕</button>
      <h3>Delete Room</h3>
      <p>Select room(s) to delete:</p>

      <div id="deleteRoomList" style="max-height:250px; overflow-y:auto; margin:10px 0;"></div>

      <div class="modal-actions">
        <button class="btn danger" onclick="confirmDeleteRooms()">Delete</button>
        <button class="btn" onclick="closeDeleteModal()">Cancel</button>
      </div>

      <p id="deleteMsg" class="modal-msg"></p>
    </div>
  </div>

<script>
  const rooms = <?php echo json_encode($rooms, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
  const isAdmin = <?php echo json_encode($isAdmin); ?>;
  const tableContainer = document.getElementById('tableContainer');

  // today's date string (server time)
  const todayStr = '<?php echo date('Y-m-d'); ?>';

  // initialize date picker to today (1.A)
  const dateInput = document.getElementById('date');
  dateInput.value = todayStr;

  dateInput.addEventListener('change', () => {
    fetchData(dateInput.value);
  });

  // Delete modal handlers
  document.getElementById("deleteRoomBtn").addEventListener("click", openDeleteModal);
  function openDeleteModal(e){
    e.preventDefault();
    const box = document.getElementById("deleteRoomList");
    box.innerHTML = "";
    rooms.forEach(r => {
      box.innerHTML += `
        <label style="display:flex; align-items:center; gap:10px; margin:6px 0;">
          <input type="checkbox" class="room-delete-check" value="${r.Room_ID}">
          <span><strong>${escapeHtml(r.Room_Name)}</strong> (ID: ${r.Room_ID}) — Capacity: ${r.Capacity}</span>
        </label>`;
    });
    const modal = document.getElementById("deleteModal");
    modal.style.display = "flex";
    modal.setAttribute("aria-hidden","false");
  }
  function closeDeleteModal(){
    const modal = document.getElementById("deleteModal");
    modal.style.display = "none";
    modal.setAttribute("aria-hidden","true");
    document.getElementById("deleteMsg").textContent = "";
  }
  async function confirmDeleteRooms(){
    const checks = [...document.querySelectorAll(".room-delete-check:checked")];
    const ids = checks.map(c => parseInt(c.value));
    if (ids.length === 0) { document.getElementById("deleteMsg").textContent = "Please select a room."; return; }
    if (!confirm(`Delete ${ids.length} room(s)?`)) return;
    document.getElementById("deleteMsg").textContent = "Deleting...";
    try {
      const res = await fetch("room_process3.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({ action: "delete_rooms", room_ids: ids })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.message);
      document.getElementById("deleteMsg").textContent = "Deleted successfully!";
      setTimeout(()=>{ closeDeleteModal(); fetchData(dateInput.value); }, 600);
    } catch (err) {
      document.getElementById("deleteMsg").textContent = "Error: " + err.message;
    }
  }

  // Edit modal (whole-day)
  const modal = document.getElementById('editModal');
  const modalInfo = document.getElementById('modalInfo');
  const modalMsg = document.getElementById('modalMsg');
  let editContext = null;

  function openEditModal(roomId, roomName, selectedDate) {
    editContext = { roomId, selectedDate };
    modalInfo.textContent = `Edit availability for ${roomName} on ${selectedDate}`;

    // default radio selection depends on current status
    modal.querySelector(`input[name="dayStatus"][value="available"]`).checked = true;
    modal.querySelector(`input[name="dayStatus"][value="unavailable"]`).checked = false;
    modalMsg.textContent = '';
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden','false');
  }
  function closeModal() {
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
    editContext = null;
  }

  document.getElementById('saveDayStatus').addEventListener('click', async () => {
    if (!editContext) return;

    const status = modal.querySelector('input[name="dayStatus"]:checked').value;

    await setDayStatusBackend(
    editContext.roomId,
    dateInput.value,
    status
);

});


  async function setDayStatusBackend(roomId, date, status) {
    modalMsg.textContent = "Saving…";

    try {
        const res = await fetch("room_process3.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: "update_room_status_for_date",
                room_id: roomId,
                date: date,
                status: status
            })
        });

        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        modalMsg.textContent = "Saved.";

        // Refresh table
        setTimeout(() => {
            closeModal();
            fetchData(dateInput.value);
        }, 400);

    } catch (e) {
        modalMsg.textContent = "Error: " + e.message;
    }
}


  // fetch availability and bookings for a date (date parameter)
  async function fetchData(date = todayStr) {
    tableContainer.innerHTML = '<div class="loading">Loading availability…</div>';
    try {
      const res = await fetch('room_process3.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'fetch_for_date', date })
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'fetch failed');
      renderTable(json);
    } catch (e) {
      tableContainer.innerHTML = '<div class="error">Error loading data: ' + (e.message || e) + '</div>';
      console.error(e);
    }
  }

  function renderTable(data) {
    const rooms = data.rooms || [];
    const bookings = data.bookings || [];
    const selectedDate = data.date || dateInput.value;

    const table = document.createElement("table");
    table.className = "room-table-only";

    // Header: single row
    const thead = document.createElement("thead");
    thead.innerHTML = `
        <tr>
            <th>Room</th>
            <th>Capacity</th>
            <th>Facilities</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    `;
    table.appendChild(thead);

    // Body
    const tbody = document.createElement("tbody");

    rooms.forEach(room => {
        // Determine today's effective availability
        let status = room.Room_Status;

        

        const tr = document.createElement("tr");

        // Action button
        let actionHtml = "";
        if (isAdmin) {
            if (selectedDate === todayStr) {
                actionHtml = `<button class="edit-btn" data-room-id="${room.Room_ID}" data-room-name="${escapeHtml(room.Room_Name)}" data-status="${status}">Edit</button>`;
            } else {
                actionHtml = `<button class="edit-btn disabled" data-tooltip="You can only edit today's availability" disabled>Edit</button>`;
            }
        }

        tr.innerHTML = `
            <td>${escapeHtml(room.Room_Name)}</td>
            <td>${room.Capacity}</td>
            <td>
                <span class="equipment-link" data-equipment="${escapeHtml(room.Equipment)}">Show</span>
            </td>
            <td><span class="slot-status ${status}">${capitalize(status)}</span></td>
            <td>${actionHtml}</td>
        `;

        tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    tableContainer.innerHTML = "";
    tableContainer.appendChild(table);

    // Equipment popups
    document.querySelectorAll(".equipment-link").forEach(link => {
        link.addEventListener("click", () => {
            alert("Facilities: " + link.dataset.equipment);
        });
    });

    // Hook edit buttons
    document.querySelectorAll(".edit-btn").forEach(btn => {
        if (btn.disabled) return;
        btn.addEventListener("click", () => {
            openEditModal(
                btn.dataset.roomId,
                btn.dataset.roomName,
                selectedDate
            );
        });
    });
}


  // simple helpers
  function capitalize(s){ return s && s[0].toUpperCase() + s.slice(1); }
  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // initial load
  fetchData(todayStr);

  // expose fetchData to global (used when other actions complete)
  window.fetchData = fetchData;

</script>
</body>
</html>
