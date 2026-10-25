<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: /librarystudyroombooking/login.php?error=please_login");
    exit;
}

// Require admin if page sets $requireAdmin = true
if (isset($requireAdmin) && $requireAdmin === true) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super admin'])) {
        header("Location: forbidden.php");
        exit;
    }
}
?>
