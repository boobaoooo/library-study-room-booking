<?php
// booking_management.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$requireAdmin = true;
require "auth_protect.php";

require_once("db.php"); // <-- your DB connection (mysqli $conn) // must set $conn (mysqli)


// only admin / super admin allowed
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401); echo "Not logged in."; exit;
}
$role = '';
$stmt = $conn->prepare("SELECT Role FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
$role = $r['Role'] ?? '';
if (!in_array($role, ['admin','super admin'])) {
    http_response_code(403); echo "Forbidden"; exit;
}

// we'll render the page and the JS will fetch today's data
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Booking Management</title>
<link rel="stylesheet" href="booking_management.css">
</head>
<body>
  <?php include 'navbar.php'; ?>
  
<main class="bm-content">
  <!-- header omitted (page lives inside your app) -->
  <div class="bm-wrapper">
    <div class="bm-top">
      <h2>Booking Management — Today</h2>
    </div>

    <div class="bm-table-container" id="bmTableContainer">
      <div class="loading">Loading today's bookings…</div>
    </div>
  </div>
</main>



<script>
// config
const isAdmin = <?php echo json_encode(in_array($role, ['admin','super admin'])); ?>;
const HOURS_START = 8;
const HOURS_END = 18; // exclusive
const today = (new Date()).toISOString().slice(0,10);

async function fetchData() {
  const container = document.getElementById('bmTableContainer');
  container.innerHTML = '<div class="loading">Loading today\'s bookings…</div>';
  try {
    const res = await fetch('booking_management_process.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'fetch_today' })
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Fetch failed');
    renderTable(json.rooms, json.bookings, json.reservations);
  } catch (e) {
    document.getElementById('bmTableContainer').innerHTML = '<div class="error">Error: ' + e.message + '</div>';
    console.error(e);
  }
}

function renderTable(rooms, bookings, reservations) {
  // bookings: array of book rows with Reservation_ID and Room_ID and times
  // reservations: reservation rows keyed by Reservation_ID with Name and R_Status and User_ID
  const container = document.getElementById('bmTableContainer');
  container.innerHTML = '';

  // table element
  const table = document.createElement('table');
  table.className = 'bm-table';

  // THEAD - top: selection header + rooms header row with capacity and facilities
  const thead = document.createElement('thead');
  // header row: left empty then each room
  const hr1 = document.createElement('tr');
  const thEmpty = document.createElement('th'); thEmpty.className='bm-timehead'; thEmpty.textContent = 'TIME SLOT';
  hr1.appendChild(thEmpty);

  rooms.forEach(r => {
    const th = document.createElement('th');
    th.className = 'room-header-cell';
      // Room box (name, capacity, equipment link)
      th.innerHTML = `
        <div class="room-box" data-room-id="${r.Room_ID}">
          <div class="room-main">
            <span class="room-name">${escapeHtml(r.Room_Name)}</span>
            <br><span class="room-cap">Capacity: ${r.Capacity}</span>
          </div>
          <div class="room-equipment">
            <a href="#" class="equipment-link" data-room-id="${r.Room_ID}" data-equipment="${escapeHtml(r.Equipment)}">Facilities</a>
            <div class="equipment-popup" id="equip-${r.Room_ID}" aria-hidden="true">${escapeHtml(r.Equipment)}</div>
            

          </div>
        </div>
      `;
    hr1.appendChild(th);
  });
  thead.appendChild(hr1);
  table.appendChild(thead);

  // TBODY - timeslots
  const tbody = document.createElement('tbody');
  for (let h = HOURS_START; h < HOURS_END; h++) {
    const tr = document.createElement('tr');
    // time cell
    const tdTime = document.createElement('td');
    tdTime.className = 'bm-slotcell';
    tdTime.textContent = `${String(h).padStart(2,'0')}:00 - ${String(h+1).padStart(2,'0')}:00`;
    tr.appendChild(tdTime);

    // room cells
    rooms.forEach(r => {
      const td = document.createElement('td');
      td.className = 'bm-roomcell';

      // find booking(s) that overlap this hour for this room (bookings array)
      const slotStart = h;
      const slotEnd = h + 1;

      const matchBooks = bookings.filter(b => (parseInt(b.Room_ID)===parseInt(r.Room_ID)) && (timeOverlap(slotStart, slotEnd, b.BStart_Time, b.BEnd_Time)));

      if (matchBooks.length === 0) {
        // no reservation -> show default
        td.innerHTML = `
          <div class="status-row"><select disabled class="status-select empty"><option>Available</option></select></div>
          <div class="student-row"><input type="text" disabled placeholder=""></div>
          <div class="equip-row"><input type="text" disabled value=""></div>
          <div class="btn-row">
            
           
          </div>
        `;
      } else {
        // choose first matching booking (if multiple, show list of names)
        // gather unique reservation ids for this slot+room
        const resIds = [...new Set(matchBooks.map(b=>b.Reservation_ID))];
        // build UI showing each reservation (for simplicity show first, allow selecting others later)
        const firstResId = resIds[0];
        const resObj = reservations[firstResId] || null;
        const rStatus = resObj ? resObj.R_Status : 'pending';
        const studentName = resObj ? resObj.Name : '';
        const equipmentVal = r.Equipment ?? '';

        td.innerHTML = `
          <div class="status-row">
            <select class="status-select" data-reservation-id="${firstResId}">
              <option value="pending" ${rStatus==='pending'?'selected':''}>Pending</option>
              <option value="active" ${rStatus==='active'?'selected':''}>Active</option>
              <option value="completed" ${rStatus==='completed'?'selected':''}>Completed</option>
              <option value="cancelled" ${rStatus==='cancelled'?'selected':''}>Cancelled</option>
            </select>
          </div>
          <div class="student-row">
            <input type="text" class="student-field" value="${escapeHtml(studentName)}" readonly>
          </div>
          <div class="equip-row">
    <input 
        type="text" 
        class="equip-field" 
        data-room-id="${r.Room_ID}"
        value="${escapeHtml(equipmentVal)}"
        ${isAdmin ? "" : "readonly"}
    >
</div>

          <div class="btn-row">
            <button class="btn confirm" data-reservation-id="${firstResId}">CONFIRM</button>
           
          </div>
        `;
      }

      tr.appendChild(td);
    });

    tbody.appendChild(tr);
  }

  table.appendChild(tbody);
  container.appendChild(table);

  // hook events
  document.querySelectorAll('.equip-link').forEach(link => {
    const popup = link.parentElement.querySelector('.equip-popup');
    link.addEventListener('mouseenter', ()=> popup.style.display='block');
    link.addEventListener('mouseleave', ()=> popup.style.display='none');
    link.addEventListener('click', (e)=> { e.preventDefault(); popup.style.display = (popup.style.display==='block') ? 'none' : 'block'; });
  });

  // status change via dropdown
  document.querySelectorAll('.status-select').forEach(sel => {
    sel.addEventListener('change', async (e) => {
      const resId = sel.dataset.reservationId;
      const newStatus = sel.value;
      await updateStatus(resId, newStatus);
    });
  });

  // confirm / cancel buttons
 // confirm button - confirms the selected status
document.querySelectorAll('.btn.confirm:not([disabled])').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    const resId = btn.dataset.reservationId;
    if (!resId) return;
    
    // Find the corresponding status dropdown
    const sel = document.querySelector(`.status-select[data-reservation-id="${resId}"]`);
    if (!sel) return;
    
    const newStatus = sel.value;
    const originalStatus = sel.dataset.originalValue;
    
    // If no change, do nothing
    if (newStatus === originalStatus) {
      alert('No changes to confirm.');
      return;
    }
    
    // If changing to cancelled, confirm with user
    if (newStatus === 'cancelled') {
      if (!confirm('Are you sure you want to cancel this reservation? This will permanently remove all associated bookings from the database.')) {
        // Revert dropdown to original value
        sel.value = originalStatus;
        sel.style.borderColor = '';
        sel.style.borderWidth = '';
        return;
      }
    }
    
    // Confirm the status change
    await updateStatus(resId, newStatus, sel);
  });
});

//  EQUIPMENT EDIT HANDLER — save on Enter or focus-out
document.querySelectorAll('.equip-field').forEach(input => {
    input.addEventListener('change', async () => {
        const roomId = input.dataset.roomId;
        const newEquip = input.value;

        if (!roomId) return;

        const res = await fetch('booking_management_process.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'update_equipment',
                room_id: roomId,
                equipment: newEquip
            })
        });

        const json = await res.json();
        if (!json.success) {
            alert("Failed to update facilities: " + json.message);
            return;
        }

        alert("Facilities updated successfully!");
    });
});
}



// helpers
function timeOverlap(slotStart, slotEnd, bStartTimeStr, bEndTimeStr) {
  // bStartTimeStr like "08:00:00"
  const bStart = parseInt(bStartTimeStr.split(':')[0],10) + parseInt(bStartTimeStr.split(':')[1],10)/60;
  const bEnd = parseInt(bEndTimeStr.split(':')[0],10) + parseInt(bEndTimeStr.split(':')[1],10)/60;
  return (bStart < slotEnd && bEnd > slotStart);
}

function escapeHtml(s){ if(!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function updateStatus(reservationId, status, selectElement) {
  try {
    const res = await fetch('booking_management_process.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'update_status', reservation_id: reservationId, status })
    });
    const j = await res.json();
    if (!j.success) {
      alert('Update failed: ' + (j.message || 'unknown'));
      
      // Revert dropdown on error
      if (selectElement) {
        selectElement.value = selectElement.dataset.originalValue;
        selectElement.style.borderColor = '';
        selectElement.style.borderWidth = '';
      }
      return;
    }
    
    alert('Status updated successfully!');
    
    // Reset visual indicators
    if (selectElement) {
      selectElement.dataset.originalValue = status;
      selectElement.style.borderColor = '';
      selectElement.style.borderWidth = '';
    }
    
    // refresh UI
    fetchData();
  } catch (e) {
    alert('Request failed: '+e.message);
    console.error(e);
    
    // Revert on error
    if (selectElement) {
      selectElement.value = selectElement.dataset.originalValue;
      selectElement.style.borderColor = '';
      selectElement.style.borderWidth = '';
    }
  }
}

// initial load
fetchData();
</script>
</body>
</html>
