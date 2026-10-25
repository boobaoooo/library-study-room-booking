<?php

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super admin') {
    header("Location: /librarystudyroombooking/login.php");
    exit;
}
?>