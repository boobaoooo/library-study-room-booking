<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);


ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db.php';

// Must login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: /librarystudyroombooking/login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_role    = $_SESSION['role'];

// Only super admin can edit admins
if ($current_role !== "super admin") {
    exit("Permission denied: Only super admin may edit admin accounts.");
}

if (!isset($_GET['id'])) {
    exit("No admin selected.");
}

$admin_id = (int)$_GET['id'];
$err = "";

/* ---------------------------------------------------
   LOAD admin details
------------------------------------------------------ */
$sql = "SELECT User_ID, Name, Email, Role, Status 
        FROM user 
        WHERE User_ID = ? AND Role IN ('admin','super admin')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("Admin not found.");
}

$admin = $result->fetch_assoc();
$stmt->close();

/* ---------------------------------------------------
   UPDATE admin (NO PASSWORD LOGIC ANYMORE)
------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin'])) {

    $name   = trim($_POST['username']);
    $email  = trim($_POST['email']);
    $role   = trim($_POST['role']);
    $status = trim($_POST['status']);

    // Validate role
    if (!in_array($role, ["admin", "super admin"])) {
        $err = "Invalid role selected.";
    }
    // Prevent self downgrade
    else if ($admin['User_ID'] == $current_user_id && $role !== "super admin") {
        $err = "You cannot change your own role.";
    }

    if (!$err) {

        $sql = "UPDATE user 
                SET Name = ?, Email = ?, Role = ?, Status = ?
                WHERE User_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $name, $email, $role, $status, $admin_id);

        if (!$stmt) {
            $err = "Prepare failed: " . $conn->error;
        } 
        else if ($stmt->execute()) {

            if ($status === "blacklisted") {

    // Check if already blacklisted
    $check = $conn->prepare("SELECT * FROM blacklist WHERE User_ID = ? AND Date_removed IS NULL");
    $check->bind_param("i", $admin_id);
    $check->execute();
    $exists = $check->get_result()->num_rows;
    $check->close();

    // If not already blacklisted → insert new blacklist row
    if ($exists == 0) {
        $bl = $conn->prepare("
            INSERT INTO blacklist (User_ID, Date_added, Reason)
            VALUES (?, NOW(), 'Manual admin blacklist')
        ");
        $bl->bind_param("i", $admin_id);
        $bl->execute();
        $bl->close();
    }
}

if ($status === "active") {
    $reset = $conn->prepare("
        UPDATE blacklist 
        SET Date_removed = NOW() 
        WHERE User_ID = ? AND Date_removed IS NULL
    ");
    $reset->bind_param("i", $admin_id);
    $reset->execute();
    $reset->close();
}


            

            // Log activity
            $desc = "Edited admin ID $admin_id (name: $name, role: $role)";
            logActivity($conn, $_SESSION['user_id'], "EDIT_ADMIN", $desc);

            header("Location: staff.php?msg=updated");
            echo "<script>window.location.href='staff.php?msg=updated';</script>";
            exit();

        } else {
            $err = "Update failed: " . $stmt->error;
        }

        $stmt->close();
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="c4.css">
  <style>
    .edit-box { max-width: 480px; margin: 0 auto; }
  </style>
</head>

<body>

<?php include 'header.php'; ?>

<main class="container">

    <h1 class="login-heading">EDIT ADMIN</h1>
    <h1 class="login-heading2">@<?= htmlspecialchars($admin['Name']) ?></h1>

    <div class="page-box edit-box">

        <?php if($err): ?>
            <div class="alert" style="background:#fff1f3;border:1px solid rgba(255,107,154,0.2);">
                <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <form class="card" method="POST">
            <input type="hidden" name="edit_admin" value="1">

            <label>Name:
                <input type="text" name="username" value="<?= htmlspecialchars($admin['Name']) ?>" required>
            </label>

            <label>Email:
                <input type="email" name="email" value="<?= htmlspecialchars($admin['Email']) ?>" required>
            </label>

            <label>Status:
                <select name="status">
                    <option value="active" <?= $admin['Status']=="active"?"selected":"" ?>>Active</option>
                    <option value="blacklisted" <?= $admin['Status']=="blacklisted"?"selected":"" ?>>Blacklisted</option>
                </select>
            </label>

            <label>Role:
                <select name="role">
                    <option value="admin" <?= $admin['Role']=="admin"?"selected":"" ?>>Admin</option>
                    <option value="super admin" <?= $admin['Role']=="super admin"?"selected":"" ?>>Super Admin</option>
                </select>
            </label>

            <div class="controls">
                <button type="submit" class="btn primary">SAVE</button>
                <a href="staff.php" class="btn danger">CANCEL</a>
            </div>

        </form>
    </div>

</main>

<?php ob_end_flush(); ?>
</body>
</html>
