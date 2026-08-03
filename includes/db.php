<?php
// กำหนดข้อมูลสำหรับการเชื่อมต่อฐานข้อมูล
$host = 'localhost';
$dbname = 'smart_inventory_pos';
$username = 'root'; // ค่าเริ่มต้นของ XAMPP มักจะเป็น root
$password = '';     // ค่าเริ่มต้นของ XAMPP มักจะไม่มีรหัสผ่าน (เว้นว่างไว้)

try {
    // เชื่อมต่อฐานข้อมูลด้วย PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // ตั้งค่า Error Mode เพื่อให้แจ้งเตือนเมื่อมีข้อผิดพลาด
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // ให้ดึงข้อมูลมาเป็น Array แบบคีย์ชื่อฟิลด์

} catch (PDOException $e) {
    // ถ้าเชื่อมต่อไม่สำเร็จ จะแสดงข้อความ Error
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}
?>
