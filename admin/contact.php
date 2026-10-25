<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /librarystudyroombooking/student/login.php?error=please_login");
    exit;
}

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


    <?php include 'navbar.php'; ?>

   



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
