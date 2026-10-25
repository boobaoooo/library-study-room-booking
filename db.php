<?php


$host = "localhost";
$db = "library_room_booking";

// DEFAULT: ใช้ root จนกว่าจะ login เสร็จ
$user = "root";            // หรือ user ที่คุณใช้ใน MAMP จริง ๆ เช่น "root"
$pass = "root";   
// AFTER LOGIN → เปลี่ยน role แล้วค่อยใช้ user database ตาม role
if (isset($_SESSION["role"])) {
    switch ($_SESSION["role"]) {
        case "student":
            $user = "student";
            $pass = "stu123";
            break;
        case "admin":
            $user = "admin";
            $pass = "ad123";
            break;
        case "super admin":
            $user = "super admin";
            $pass = "superad123";
            break;
    }
}

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>
