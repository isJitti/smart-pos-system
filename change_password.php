<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $_SESSION['alert'] = ['type' => 'error', 'msg' => 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน'];
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'เปลี่ยนรหัสผ่านส่วนตัวเรียบร้อยแล้ว'];
        } catch(PDOException $e) {
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }
    
    // เด้งกลับไปหน้าที่เรียกมา
    $referer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
    header("Location: $referer");
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}
?>
