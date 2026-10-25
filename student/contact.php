<?php
require_once __DIR__ . '/../auth_protect.php'; // path จาก student/ ไปยัง root
protect('student');

require_once 'db.php';

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact</title>

    <link rel="stylesheet" href="contact.css">
</head>

<body>

<!-- ---------- TOP BAR ---------- -->
<!--<header class="topbar">
    <div class="logo-area">
        <img src="Logo.png" class="logo-img" alt="Logo">
    </div>

    <nav class="nav-right">
        <a href="/librarystudyroombooking/student/home.php">HOME</a>
        <div class="divider"></div>
        <a href="/librarystudyroombooking/student/history.php">HISTORY</a>
        <div class="divider"></div>
        <a href="/librarystudyroombooking/student/contact.php" class="active">CONTACT</a>
        <div class="divider"></div>
        <a href="/librarystudyroombooking/student/logout.php">LOGOUT</a>
        <div class="profile-icon"></div>
    </nav>
</header>-->

<?php include 'tabbar.php'; ?>

<!-- ---------- CONTACT CONTENT ---------- -->
<div class="page-bg">

    <div class="contact-card">
        <h1>CONTACT</h1>

        <div class="icons">
            <a href="#"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a5/Instagram_icon.png/600px-Instagram_icon.png"></a>
            <a href="#"><img src="https://upload.wikimedia.org/wikipedia/commons/0/05/Facebook_Logo_%282019%29.png"></a>
            <img src="phone.png" alt="phone">
        </div>
    </div>

</div>

</body>
</html>
