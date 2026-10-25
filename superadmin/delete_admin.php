<?php
// delete.php - safe delete admin with proper logActivity
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ALWAYS load the correct superadmin database with logActivity()
require_once __DIR__ . "/db.php";

// Must be superadmin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== "super admin") {
    header('Location: /librarystudyroombooking/login.php');
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$current_role    = $_SESSION['role'];

$msg = "";

/* --------------------------------------------------
   HANDLE DELETE REQUEST
--------------------------------------------------*/
if (isset($_GET['delete_id'])) {

    $adminId = (int)$_GET['delete_id'];

    if ($adminId <= 0) {
        $msg = "Invalid admin ID.";
    }
    else if ($adminId === $current_user_id) {
        $msg = "You cannot delete your own account.";
    }
    else {

        // Check if the account exists
        $chk = $conn->prepare("SELECT User_ID, Role, Name FROM user WHERE User_ID = ?");
        $chk->bind_param("i", $adminId);
        $chk->execute();
        $res = $chk->get_result();

        if ($res->num_rows === 0) {
            $msg = "Account not found.";
        } else {
            $target = $res->fetch_assoc();
            $chk->close();

            // Prevent deleting another super admin
            if ($target['Role'] === "super admin") {
                $msg = "You cannot delete another super admin.";
            } 
            else if ($target['Role'] !== "admin") {
                $msg = "Only admin accounts can be deleted.";
            }
            else {

                // DELETE ADMIN ACCOUNT
                $del = $conn->prepare("DELETE FROM user WHERE User_ID = ? AND Role = 'admin'");
                $del->bind_param("i", $adminId);

                if ($del->execute() && $del->affected_rows > 0) {

                    // LOG ACTIVITY (Guaranteed to work)
                    $desc = "Deleted admin ID $adminId";
                    logActivity($conn, $current_user_id, "DELETE_ADMIN", $desc);

                    $msg = "Admin deleted successfully.";

                } else {
                    $msg = "Delete failed or no admin deleted.";
                }

                $del->close();
            }
        }
    }
}

/* --------------------------------------------------
   LOAD ADMIN LIST
--------------------------------------------------*/
$result = $conn->query("
    SELECT User_ID, Name, Email, Role, Status 
    FROM user 
    WHERE Role = 'admin'
    ORDER BY User_ID ASC
");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Delete Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="c3.css">
  <style>
    .page-box { overflow-x:auto; }
  </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">

    <h1 class="login-heading">DELETE ADMIN</h1>
    <h1 class="login-heading2">ADMIN LIST</h1>

    <div class="page-box">

      <?php if ($msg): ?>
        <div class="alert"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <table class="data-table">
        <thead>
          <tr>
            <th>User_ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th style="background:#cb7e5b; color:white;">Action</th>
          </tr>
        </thead>

        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $row['User_ID'] ?></td>
                <td><?= htmlspecialchars($row['Name']) ?></td>
                <td><?= htmlspecialchars($row['Email']) ?></td>
                <td><?= htmlspecialchars($row['Role']) ?></td>
                <td><?= htmlspecialchars($row['Status']) ?></td>
                <td>
                  <?php if ((int)$row['User_ID'] !== $current_user_id): ?>
                    <a href="delete.php?delete_id=<?= urlencode($row['User_ID']) ?>"
                       class="btn danger"
                       onclick="return confirm('Are you sure you want to delete this admin?');">
                       DELETE
                    </a>
                  <?php else: ?>
                    <span style="opacity:.6">SELF</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No admin accounts found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>

    </div>

    <div class="controls">
        <a href="staff.php" class="btn ghost">← BACK</a>
    </div>

</main>

</body>
</html>
