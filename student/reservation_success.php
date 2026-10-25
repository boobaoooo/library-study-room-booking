<?php
require_once __DIR__ . '/../auth_protect.php'; // path จาก student/ ไปยัง root
protect('student');

$reservation_id = $_GET['id'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Successful Reservation</title>

    <!-- CSS ของหน้านี้ -->
    <link rel="stylesheet" href="reservation_success.css">
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="logo-area">
        <img src="logo.png" alt="Logo">
    </div>

    <div class="nav-right">
        <a href="/librarystudyroombooking/student/home.php">HOME</a>
        <div class="divider"></div>

        <a href="/librarystudyroombooking/student/history.php">HISTORY</a>
        <div class="divider"></div>

        <a href="/librarystudyroombooking/student/contact.php">CONTACT</a>
        <div class="divider"></div>

        <a href="/librarystudyroombooking/student/logout.php">LOGOUT</a>

        <img src="profile.png" class="profile-icon" alt="Profile">
    </div>
</div>

<!-- MAIN PAGE CONTENT -->
<div class="page-wrapper">
    <div class="success-container">
        <h1>SUCCESSFUL RESERVATION!</h1>

        <div class="qr-box">
            <img src="qrcode.png" alt="QR Code">
        </div>

        <a class="cancel-btn" href="history.php">
            CANCEL RESERVATION
        </a>

        <p class="warning">
            *Warning: If you cancel the reservation 3 times, you might be in blacklist
        </p>
    </div>
</div>

</body>
</html>
