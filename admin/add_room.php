<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once("db.php");

$requireAdmin = true;
require "auth_protect.php";

if (isset($_GET['success']) && $_GET['success'] === 'room_added') {
    $success_message = "Room added successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Room - Room Booking System</title>
  <link rel="stylesheet" href="add_room.css">
</head>
<body>

  <?php include 'navbar.php'; ?>

  <main class="content">
    <h1 class="page-title">Add Room</h1>

    <div class="form-container">
      
      <?php if (isset($success_message)): ?>
        <div class="success-message"><?php echo $success_message; ?></div>
      <?php endif; ?>

      <form method="POST" action="add_room_process.php">

        <div class="form-group">
          <label for="room_id">Room ID</label>
          <input type="number" id="room_id" name="room_id" placeholder="Enter room ID (e.g., 101)" required>
          <small>Use a unique numeric ID</small>
        </div>

        <div class="form-group">
          <label for="room_name">Room Name</label>
          <input type="text" id="room_name" name="room_name" placeholder="Enter room name (e.g., Conference Room A)" required>
        </div>

        <div class="form-group">
          <label for="capacity">Capacity</label>
          <input type="number" id="capacity" name="capacity" placeholder="Enter room capacity" min="1" required>
        </div>

        <div class="form-group">
          <label for="equipment">Equipment</label>
          <input type="text" id="equipment" name="equipment" placeholder="Projector, Whiteboard, etc." required>
        </div>

        <div class="form-group">
          <label for="status">Room Status</label>
          <select id="status" name="status" required>
            <option value="available">Available</option>
            <option value="unavailable">Unavailable</option>
          </select>
        </div>

        <div class="button-group">
          <button type="submit" class="confirm-btn">Confirm</button>
          <a href="room_mnm3.php" class="cancel-btn">Cancel</a>
        </div>

      </form>
    </div>
  </main>

</body>
</html>