<?php
// STAFF MANAGEMENT PAGE — ONE-FILE VERSION (2025 FIXED)

error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once 'db.php';
require_once 'auth_superadmin.php';   // super admin only

// ------------------- ACCESS CONTROL -------------------

if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: /librarystudyroombooking/login.php');
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$current_role = $_SESSION['role'];

$msg = '';
$err = '';

/* -----------------------------------------------------
   DELETE ADMIN — NO MORE OLD PROCEDURES
------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'delete_selected') {

    if ($current_role !== 'super admin') {
        $err = 'Permission denied: only super admin may delete admin accounts.';
    } else {

        $ids = $_POST['ids'] ?? [];

        if (!is_array($ids) || count($ids) === 0) {
            $err = 'No admins selected for deletion.';
        } else {

            $deleted = 0;

            $stmt = $conn->prepare("DELETE FROM user WHERE User_ID = ? AND Role = 'admin'");

            foreach ($ids as $rawid) {
                $aid = (int)$rawid;
                if ($aid <= 0) continue;

                $stmt->bind_param("i", $aid);

                if ($stmt->execute()) {
                    $deleted++;
                } else {
                    $err .= "Failed to delete ID {$aid}: " . $stmt->error . " ";
                }
            }

            $stmt->close();

            if ($deleted > 0 && !$err) {
                $msg = "Deleted {$deleted} admin(s).";
            } else if ($deleted > 0) {
                $msg = "Deleted {$deleted} admin(s). Some errors: {$err}";
            }
        }
    }
}

/* -----------------------------------------------------
   LOAD ADMINS
------------------------------------------------------*/
$admins = [];
$q = "SELECT User_ID, Name, Email, PasswordHash, Role, Status 
      FROM user 
      WHERE Role = 'admin'
      ORDER BY User_ID ASC";

$res = $conn->query($q);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $admins[] = $row;
    }
    $res->free();
} else {
    $err = "Failed to load admins: " . $conn->error;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Management</title>
<link rel="stylesheet" href="c1.css">

<style>
/* Fix table overflow */
.table-container {
    width: 100%;
    overflow-x: auto;
}

/* Prevent breaking the layout */
.data-table th, .data-table td {
    white-space: nowrap;
}

/* Prevent box overflow */
.page-box {
    max-width: 100%;
    overflow-x: auto;
}

/* Center main box */
.container {
    max-width: 1100px;
    margin: 28px auto;
}

/* Header spacing fix */
.login-heading {
    margin-top: 10px;
}
</style>

</head>

<body>

<?php include 'header.php'; ?>

<main class="container">

    <h1 class="login-heading">STAFF MANAGEMENT</h1>
  


    <div class="page-box">

        <?php if ($msg): ?>
            <div class="alert" style="background:#e6ffed;border:1px solid #c7f0d0;">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <?php if ($err): ?>
            <div class="alert" style="background:#fff1f3;border:1px solid rgba(255,107,154,0.2);">
                <?= $err ?>
            </div>
        <?php endif; ?>

        <form id="adminsForm" method="post">
            <input type="hidden" name="action" value="delete_selected">

            <!-- NEW SCROLLABLE WRAPPER -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th id="p01">Select</th>
                            <th>User_ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>PasswordHash</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Edit</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($admins) === 0): ?>
                            <tr><td colspan="8">No admin accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($admins as $a): ?>
                            <tr>
                                <td class="center" id="p02">
                                    <input type="checkbox" name="ids[]" value="<?= $a['User_ID'] ?>">
                                </td>
                                <td><?= htmlspecialchars($a['User_ID']) ?></td>
                                <td><?= htmlspecialchars($a['Name']) ?></td>
                                <td><?= htmlspecialchars($a['Email']) ?></td>
                                <td><?= htmlspecialchars($a['PasswordHash']) ?></td>
                                <td><?= htmlspecialchars($a['Role']) ?></td>
                                <td><?= htmlspecialchars($a['Status']) ?></td>
                                <td><a class="btn edit" href="edit_admin.php?id=<?= urlencode($a['User_ID']) ?>">Edit</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </form>

    </div>

    <!-- Buttons under table -->
    <div class="controls top-actions">

        <a href="add_admin.php" class="btn primary">ADD ADMIN</a>

        <?php if ($current_role === 'super admin'): ?>
            <button id="deleteBtn" class="btn danger" type="button">DELETE ADMIN</button>
        <?php else: ?>
            <button type="button" class="btn danger" onclick="alert('Only super admin can delete admins.')">DELETE ADMIN</button>
        <?php endif; ?>

    </div>

</main>

<script>
document.getElementById('deleteBtn')?.addEventListener('click', function() {
    const form = document.getElementById('adminsForm');
    const checks = form.querySelectorAll('input[name="ids[]"]:checked');

    if (checks.length === 0) {
        alert('Please select at least one admin to delete.');
        return;
    }

    if (confirm('Are you sure you want to delete the selected admin(s)?')) {
        form.submit();
    }
});
</script>

</body>
</html>




