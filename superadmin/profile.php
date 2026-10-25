<?php
/*
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['logged_in'])) { 
    header('Location: index.php'); 
    exit; 
}*/

require "db.php";
require_once "auth_superadmin.php";
require_once __DIR__ . '/../security_helpers.php';

// Logged in user
$user_id = $_SESSION['user_id'];

// Load real user info from DB
$sql = "SELECT Name, Email, PasswordHash, Role, Status FROM user WHERE User_ID=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Default picture (keep same)
$user_picture = "image2.png";

$msg = '';
$show_form = false;

// WHEN CLICK "CHANGE PASSWORD"
if (isset($_POST['show_change_form'])) {
    $show_form = true;
}

// WHEN SUBMIT NEW PASSWORD
if (isset($_POST['apply_change'])) {

    $new = trim($_POST['new_password']);
    $confirm = trim($_POST['confirm_password']);

    if ($new === '' || $confirm === '') {
        $msg = "Please fill all fields.";
        $show_form = true;
    } 
    elseif ($new !== $confirm) {
        $msg = "New password and confirmation do not match.";
        $show_form = true;
    } 
    else {
        // Hash password (same method as signup / login)
        $hash = make_password_hash($new);

        // Update DB
        $update = $conn->prepare("UPDATE user SET PasswordHash=? WHERE User_ID=?");
        $update->bind_param("si", $hash, $user_id);

        if ($update->execute()) {
            $msg = "Password successfully changed.";
        } else {
            $msg = "Error updating password: " . $conn->error;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Profile</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="c11.css">
</head>
<body>

<?php include 'header.php'; ?>

<main class="container">

  <h1 class="login-heading">PROFILE</h1>

  <div class="page-box profile-box">

    <?php if($msg): ?>
      <div class="alert"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="profile-card profile-center">
      <img src="<?= $user_picture ?>" class="profile-large" alt="profile">

      <div class="profile-info">
        <p><strong>Name:</strong> <?= htmlspecialchars($user['Name']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['Email']) ?></p>
        <p><strong>Password:</strong> ********</p>
        <p><strong>Role:</strong> <?= htmlspecialchars($user['Role']) ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($user['Status']) ?></p>
      </div>
    </div>

    <!-- SHOW PASSWORD CHANGE FORM -->
    <?php if ($show_form): ?>
    <form method="post" class="password-form">
        <label>New Password</label>
        <input type="password" name="new_password" required>

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit" name="apply_change" class="btn primary">CONFIRM PASSWORD</button>
    </form>
    <?php endif; ?>

  </div>

  <!-- BUTTONS -->
  <div class="controls">

    <?php if (!$show_form): ?>
      <form method="post">
        <button name="show_change_form" class="btn pnk">CHANGE PASSWORD</button>
      </form>
    <?php endif; ?>

  </div>

</main>
</body>
</html>


