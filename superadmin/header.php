<?php
// call session_start in pages before including this file
$username = $_SESSION['username'] ?? 'Guest';
?>
<header class="topbar">
  
  <div class="topbar-left">
    <!-- Replace LOGO text with an image -->
    <a href="staff.php" class="logo">
      <img src="logo.PNG" alt="Logo" style="height:45px; width:auto; border-radius:6px;">
    </a>
  </div>

  <nav class="tabs">
    <a href="staff.php" class="tab">HOME</a>
    <a href="system.php" class="tab">SYSTEM</a>
    <a href="user_activity.php" class="tab">USER ACTIVITY</a>
    <a href="/librarystudyroombooking/superadmin/report.php" class="tab">REPORT</a>
    <a href="/librarystudyroombooking/superadmin/logout.php" class="tab">LOGOUT</a>
  </nav>

  <div class="topbar-right">
    <a href="profile.php" class="profile">
      <img src="image2.png" alt="profile" class="avatar">
    </a>
  </div>

</header>


