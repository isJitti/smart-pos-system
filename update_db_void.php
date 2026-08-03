<?php
require_once 'includes/db.php';

try {
    // 1. เพิ่มคอลัมน์ status
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS status ENUM('completed', 'voided') DEFAULT 'completed' AFTER change_amount");
    echo "1. เพิ่มคอลัมน์ status สำเร็จ<br>";
    
} catch (PDOException $e) {
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>
