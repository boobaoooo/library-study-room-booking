<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";

// must login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: /librarystudyroombooking/login.php");
    exit;
}

$role = $_SESSION['role'];

// allow BOTH admin and super admin
if ($role !== "admin" && $role !== "super admin") {
    exit("Permission denied: Only admin and super admin may view this page.");
}

/* -------------------------------------------
   DATE FILTER PREPARATION
-------------------------------------------- */
$start = $_GET['start_date'] ?? '';
$end   = $_GET['end_date'] ?? '';

$where = "";
$bind_types = "";
$bind_values = [];

// Build WHERE conditions
if ($start && $end) {
    $where = "WHERE DATE(h.action_timestamp) BETWEEN ? AND ?";
    $bind_types = "ss";
    $bind_values = [$start, $end];

} else if ($start) {
    $where = "WHERE DATE(h.action_timestamp) >= ?";
    $bind_types = "s";
    $bind_values = [$start];

} else if ($end) {
    $where = "WHERE DATE(h.action_timestamp) <= ?";
    $bind_types = "s";
    $bind_values = [$end];
}

/* -------------------------------------------
   Load activity log
-------------------------------------------- */
$sql = "
    SELECT 
        h.history_id,
        h.user_id,
        COALESCE(u.Name, CONCAT('User #', h.user_id)) AS user_name,
        h.action_type,
        h.description,
        h.action_timestamp
    FROM admin_history h
    LEFT JOIN user u ON h.user_id = u.User_ID
    $where
    ORDER BY h.action_timestamp DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $activities = [];
    $error = "SQL Error: " . $conn->error;
} else {

    // bind parameters dynamically
    if (!empty($bind_values)) {
        $stmt->bind_param($bind_types, ...$bind_values);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $activities = [];
    while ($row = $res->fetch_assoc()) {
        $activities[] = $row;
    }
    $stmt->close();
    $error = "";
}



?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>User Activity</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="c7.css">
  <style>
    .btn.download { background:#6d8596; color:white; }
    .btn.blacklist { background:#c36e4b; color:white; }
    .btn.reservation { background:#4d6b3c; color:white; }
    .data-table td.left { text-align:left; }

    .filter-box label { 
        font-size: 15px; 
        margin-right: 15px;
    }
    .filter-box input[type="date"] {
        padding: 6px;
        border-radius: 6px;
        border: 1px solid #ccc;
        margin-left: 5px;
    }
  </style>
</head>
<body>

<?php 
    include "header.php"; 
    if(isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success">
        Delete history successfully!
    </div>
<?php endif; ?>


<main class="container">

    <h1 class="login-heading">USER ACTIVITY</h1>

    <!-- ⭐ DATE FILTER -->
    <form method="GET" class="filter-box" style="text-align:center; margin-bottom:20px;">
        <label>
            From:
            <input type="date" name="start_date" 
                   value="<?= htmlspecialchars($start) ?>">
        </label>

        <label>
            To:
            <input type="date" name="end_date" 
                   value="<?= htmlspecialchars($end) ?>">
        </label>

        <button class="btn" style="background:#4d6b3c; color:white;">
            FILTER
        </button>
    </form>

    <div class="page-box">

      <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <table class="data-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Action Type</th>
            <th class="left">Description</th>
            <th>Timestamp</th>
          </tr>
        </thead>
        <tbody>

        <?php if (empty($activities)): ?>
            <tr><td colspan="4">No activity records found.</td></tr>
        <?php else: ?>
            <?php foreach($activities as $a): ?>
              <tr>
                <td><?= htmlspecialchars($a['user_name']) ?></td>
                <td><?= htmlspecialchars($a['action_type']) ?></td>
                <td class="left"><?= htmlspecialchars($a['description']) ?></td>
                <td><?= htmlspecialchars($a['action_timestamp']) ?></td>
              </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        </tbody>
      </table>

    </div>

    <div class="controls">
        <a href="download_activity.php" class="btn download">DOWNLOAD</a>
        <a href="delete_admin_history.php" 
            class="btn btn-danger" 
            onclick="return confirm('Are you sure you want to delete all the history?');">
            Delete History
            </a>

        <a href="blacklist.php" class="btn blacklist">VIEW BLACKLIST</a>
        <a href="reservation.php" class="btn reservation">VIEW RESERVATION</a>
    </div>

</main>

</body>
</html>


