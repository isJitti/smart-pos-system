<?php
require_once 'includes/db.php';

try {
    // 1. สร้างตาราง settings
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_name VARCHAR(100) NOT NULL,
        address TEXT NOT NULL,
        phone VARCHAR(50) NOT NULL,
        tax_id VARCHAR(50) NULL,
        receipt_footer TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "1. สร้างตาราง settings สำเร็จ<br>";

    // 2. เพิ่มข้อมูลเริ่มต้น ถ้ายังไม่มี
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $insert = "INSERT INTO settings (store_name, address, phone, tax_id, receipt_footer) 
                   VALUES ('Smart Inventory & Mini POS', '123 ถ.สุขุมวิท กรุงเทพฯ 10110', '02-123-4567', '1234567890123', 'ขอบคุณที่ใช้บริการ\nโอกาสหน้าเชิญใหม่ครับ')";
        $pdo->exec($insert);
        echo "2. เพิ่มข้อมูลเริ่มต้นสำเร็จ<br>";
    } else {
        echo "2. มีข้อมูล settings อยู่แล้ว ข้ามการเพิ่มข้อมูล<br>";
    }

    echo "<br><b>อัปเดตฐานข้อมูลสำหรับ Phase 7 สำเร็จ!</b>";

} catch (PDOException $e) {
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>
