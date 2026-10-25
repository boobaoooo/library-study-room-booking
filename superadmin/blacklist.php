<?php
// superadmin/blacklist.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";
require_once "auth_superadmin.php";

$current_user_id = $_SESSION['user_id'];
$msg = "";

// load blacklist rows
$q = "SELECT 
        Blacklist_ID,
        User_ID,
        Reason,
        Blacklist_Status,
        Date_added,
        Date_removed
      FROM blacklist
      ORDER BY Blacklist_ID ASC";
$result = $conn->query($q);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>View Blacklist</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="blacklist.css">
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">

    <h1 class="login-heading">VIEW BLACKLIST</h1>

    <div class="page-box">

        <?php if($msg): ?>
            <div class="alert"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- POST to process file -->
        <form id="editForm" method="POST" action="blacklist_process_superadmin.php">

            <table class="data-table2">
                <thead>
                    <tr>
                        <th>Blacklist_ID</th>
                        <th>User_ID</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Date Added</th>
                        <th>Date Removed</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($r = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['Blacklist_ID']) ?></td>
                            <td><?= htmlspecialchars($r['User_ID']) ?></td>

                            <td>
                                <input type="text"
                                       name="reason[<?= $r['Blacklist_ID'] ?>]"
                                       value="<?= htmlspecialchars($r['Reason']) ?>">
                            </td>

                            <td>
                                <select name="status[<?= $r['Blacklist_ID'] ?>]">
                                    <option value="active"   <?= ($r['Blacklist_Status']=="active"?"selected":"") ?>>Active</option>
                                    <option value="inactive" <?= ($r['Blacklist_Status']=="inactive"?"selected":"") ?>>Inactive</option>
                                </select>
                            </td>

                            <td><?= htmlspecialchars($r['Date_added']) ?></td>

                            <td>
                                <?php
                                  // show date part for input (YYYY-MM-DD) if not null
                                  $dr_val = "";
                                  if (!empty($r['Date_removed']) && $r['Date_removed'] !== "0000-00-00 00:00:00") {
                                      $dr_val = htmlspecialchars(substr($r['Date_removed'],0,10));
                                  }
                                ?>
                                <input type="date"
                                       name="date_removed[<?= $r['Blacklist_ID'] ?>]"
                                       value="<?= $dr_val ?>">
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6">No blacklist records found.</td></tr>
                <?php endif; ?>
                </tbody>

            </table>

        </form>

    </div>

    <div class="controls">
        <!-- submit form normally -->
        <button form="editForm" class="btn primary" type="submit">SAVE</button>
        <a href="download_blacklist.php" class="btn pnk">DOWNLOAD</a>
    </div>

</main>
</body>
</html>
