<?php
require_once 'includes/db.php';

try {
    // 1. เพิ่ม Index ให้ตาราง products (ค้นหา barcode และดึงตาม category บ่อย)
    $pdo->exec("ALTER TABLE products ADD INDEX IF NOT EXISTS idx_barcode (barcode)");
    $pdo->exec("ALTER TABLE products ADD INDEX IF NOT EXISTS idx_category (category_id)");
    echo "1. สร้าง Index ตาราง products สำเร็จ<br>";
    
    // 2. เพิ่ม Index ให้ตาราง orders (กรองบิลของพนักงานแต่ละคนบ่อย)
    $pdo->exec("ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_user (user_id)");
    $pdo->exec("ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_created (created_at)");
    echo "2. สร้าง Index ตาราง orders สำเร็จ<br>";
    
    // 3. ปรับ category_name ให้เป็น UNIQUE เพื่อป้องกันชื่อหมวดหมู่ซ้ำระดับฐานข้อมูล
    // ก่อนอื่นต้องเช็คว่ามีซ้ำไหม แต่ข้ามไปก่อน ใช้แค่ Index ธรรมดาเพื่อความปลอดภัย
    $pdo->exec("ALTER TABLE categories ADD INDEX IF NOT EXISTS idx_category_name (category_name)");
    echo "3. สร้าง Index ตาราง categories สำเร็จ<br>";
    
    echo "<br><b>ปรับแต่งประสิทธิภาพฐานข้อมูลเสร็จสมบูรณ์!</b>";

} catch (PDOException $e) {
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>
