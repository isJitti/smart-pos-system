<?php
session_start();

// ทำลาย Session ทั้งหมด
session_destroy();

// ล้างค่าตัวแปร Session (เผื่อไว้)
$_SESSION = array();

// เปลี่ยนเส้นทางกลับไปหน้า Login
header("Location: index.php");
exit();
?>
