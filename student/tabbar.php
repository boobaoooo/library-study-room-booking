<?php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php'; // db.php อยู่ใน student/

$user_id = $_SESSION['user_id'] ?? null;

// default path
$profile_img = "/librarystudyroombooking/uploads/default.jpg";

if ($user_id) {
    $stmt = $conn->prepare("SELECT Profile_Image FROM user WHERE User_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($img);
    $stmt->fetch();
    $stmt->close();

    if (!empty($img)) {
        $profile_img = "/librarystudyroombooking/uploads/" . $img;
    }
}
?>

<link rel="stylesheet" href="tabbar.css">

<header class="topbar">
    <div class="logo-area">
        <img src="Logo.png" class="logo-img" alt="Logo">
    </div>

    <nav class="nav-right">
        <a href="/librarystudyroombooking/student/home.php">HOME</a>
        <div class="divider"></div>
        <a href="/librarystudyroombooking/student/history.php">HISTORY</a>
        <div class="divider"></div>
        <a href="/librarystudyroombooking/student/contact.php">CONTACT</a>
        <div class="divider"></div>
        <a href="/librarystudyroombooking/student/logout.php">LOGOUT</a>

        <a href="profile.php">
            <img class="profile-icon" src="<?= htmlspecialchars($profile_img) ?>" alt="profile">
        </a>
    </nav>
</header>
