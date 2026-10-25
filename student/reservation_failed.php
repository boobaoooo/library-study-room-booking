<?php
require_once __DIR__ . '/../auth_protect.php'; // path จาก student/ ไปยัง root
protect('student');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Failed Reservation</title>
    <link rel="stylesheet" href="reservation_failed.css">
</head>
<body>

<!-- ---------- TOP BAR (เหมือน home.php) ---------- -->
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
        <div class="profile-icon"></div>
    </nav>
</header>

<!-- ------- MAIN CONTENT ------- -->
<div class="failed-container">
    <div class="failed-card">
        <h1 class="failed-title">FAILED RESERVATION!</h1>

        <div class="failed-box">
            <p><strong>The user might</strong></p>
            <ul>
                <li>Double booking!</li>
            </ul>
        </div>

        <div class="btn-group">
            <a href="home.php" class="btn-home">Back to Home</a>
            <a href="contact.php" class="btn-contact">Contact</a>
        </div>
    </div>
</div>

</body>
</html>
