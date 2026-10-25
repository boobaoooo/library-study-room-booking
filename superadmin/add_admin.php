<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db.php';
require_once 'auth_superadmin.php';   // super admin only
require_once __DIR__ . '/../security_helpers.php';

// Must be logged in
if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
    header("Location: /librarystudyroombooking/login.php");
    exit;
}

$current_role = $_SESSION['role'];
$current_user_id = $_SESSION['user_id'];

$msg = "";
$err = "";

/* -----------------------------------------------------
   INSERT NEW ADMIN (NO MORE sp_ManageAdmin)
------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {

    $name     = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role     = trim($_POST['role']);

    if (!$name || !$email || !$password) {
        $err = "Please fill all fields.";
    } elseif (!in_array($role, ["admin", "super admin"], true)) {
        $err = "Invalid role.";
    } else {

        // Only super admin can create super admin
        if ($role === "super admin" && $current_role !== "super admin") {
            $err = "Only super admin can create another super admin.";
        } else {

            // Hash password (same method as signup / login)
            $passwordHash = make_password_hash($password);

            // Insert new admin
            $stmt = $conn->prepare("
                INSERT INTO user (Name, Email, PasswordHash, Role, Status, Cancellation_Count, Verify_Status)
                VALUES (?, ?, ?, ?, 'active', 0, 'verified')
            ");

            if (!$stmt) {
                $err = "Prepare failed: " . $conn->error;
            } else {
                $stmt->bind_param("ssss", $name, $email, $passwordHash, $role);

                if ($stmt->execute()) {

    $desc = "Created new admin account (Name: $name, Email: $email, Role: $role)";
    logActivity($conn, $_SESSION['user_id'], "ADD_ADMIN", $desc);

    header("Location: staff.php?msg=added");
    exit;


                } else {
                    $err = "Failed to add admin: " . $stmt->error;
                }

                $stmt->close();
            }
        }
    }
}

/* -----------------------------------------------------
   LOAD EXISTING ADMINS FOR DISPLAY
------------------------------------------------------*/
$result = $conn->query("
    SELECT User_ID, Name, Email, PasswordHash, Role, Status
    FROM user 
    WHERE Role='admin'
    ORDER BY User_ID ASC
");
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="c2.css">

  <style>
    #addForm { display:none; margin-top:20px; }
    .table-container { overflow-x:auto; width:100%; }
    .data-table th, .data-table td { white-space:nowrap; }
  </style>
</head>

<body>

<?php include 'header.php'; ?>

<main class="container">

    <h1 class="login-heading">ADD ADMIN</h1>
 

    <div class="page-box">

        <?php if($msg): ?><div class="alert" style="background:#e6ffed;border:1px solid #c7f0d0;"><?=$msg?></div><?php endif; ?>
        <?php if($err): ?><div class="alert" style="background:#fff1f3;border:1px solid rgba(255,107,154,0.2);"><?=$err?></div><?php endif; ?>

        <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>User_ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>PasswordHash</th>
                  <th>Role</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php while($a = $result->fetch_assoc()): ?>
                  <tr>
                    <td><?= $a['User_ID'] ?></td>
                    <td><?= htmlspecialchars($a['Name']) ?></td>
                    <td><?= htmlspecialchars($a['Email']) ?></td>
                    <td><?= htmlspecialchars($a['PasswordHash']) ?></td>
                    <td><?= htmlspecialchars($a['Role']) ?></td>
                    <td><?= htmlspecialchars($a['Status']) ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
        </div>

        <!-- Hidden form for adding admin -->
        <form id="addForm" class="card" method="POST">
            <input type="hidden" name="add_admin" value="1">

            <label>Username:
                <input type="text" name="username" required>
            </label>

            <label>Email:
                <input type="email" name="email" required>
            </label>

            <label>Password:
                <input type="password" name="password" required>
            </label>

            <label>Role:
                <select name="role">
                    <option value="admin">Admin</option>

                    <?php if($current_role === "super admin"): ?>
                        <option value="super admin">Super Admin</option>
                    <?php endif; ?>
                </select>
            </label>

            <div class="controls">
                <button type="submit" class="btn primary">CONFIRM</button>
                <a href="staff.php" class="btn danger">CANCEL</a>
            </div>
        </form>

    </div>

    <div class="controls">
        <button id="showFormBtn" class="btn edit">ADD ADMIN</button>
        <a href="staff.php" class="btn danger">BACK</a>
    </div>

</main>

<script>
document.getElementById("showFormBtn").onclick = function () {
    document.getElementById("addForm").style.display = "block";
    this.style.display = "none"; 
};
</script>

</body>
</html>

