<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
require_once 'includes/db.php';

if (!isset($_GET['order_id'])) {
    die("ไม่พบรหัสบิล");
}
$order_id = $_GET['order_id'];

// ดึงข้อมูลหัวบิล
$stmt = $pdo->prepare("SELECT o.*, u.fullname FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("ไม่พบข้อมูลบิลนี้ในระบบ");
}

// ดึงข้อมูลรายการสินค้า
$stmt = $pdo->prepare("SELECT oi.*, p.product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// ดึงข้อมูลการตั้งค่าร้านค้า (Store Settings)
$stmt_setting = $pdo->query("SELECT * FROM settings ORDER BY id ASC LIMIT 1");
$store_settings = $stmt_setting->fetch();

// กำหนดค่าเริ่มต้นถ้ายังไม่มีในฐานข้อมูล
$store_name = $store_settings['store_name'] ?? 'Smart Inventory & Mini POS';
$store_address = $store_settings['address'] ?? '123 ถ.สุขุมวิท กรุงเทพฯ 10110';
$store_phone = $store_settings['phone'] ?? '02-123-4567';
$store_tax_id = $store_settings['tax_id'] ?? '';
$store_footer = $store_settings['receipt_footer'] ?? "ขอบคุณที่ใช้บริการ\nโอกาสหน้าเชิญใหม่ครับ";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเสร็จรับเงิน - <?= htmlspecialchars($order['order_no']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap');
        
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .receipt-container {
            width: 80mm; /* ขนาดมาตรฐานกระดาษความร้อน 80mm */
            background: white;
            padding: 10px 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        
        h3 { margin: 5px 0; font-size: 18px; }
        p { margin: 2px 0; font-size: 12px; }
        
        .divider {
            border-bottom: 1px dashed #000;
            margin: 10px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 4px 0;
            vertical-align: top;
        }
        
        /* ซ่อนปุ่มตอนปริ้น */
        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; margin: 0; width: 100%; }
            .no-print { display: none !important; }
        }
        
        .btn-print {
            display: block;
            width: 100%;
            padding: 10px;
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            font-size: 16px;
            margin-top: 15px;
        }
        .btn-back {
            display: block;
            width: 100%;
            padding: 10px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Sarabun', sans-serif;
            font-size: 16px;
            margin-top: 5px;
            text-align: center;
            text-decoration: none;
            box-sizing: border-box;
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <div class="text-center">
        <h3><?= htmlspecialchars($store_name) ?></h3>
        <p><?= nl2br(htmlspecialchars($store_address)) ?></p>
        <p>โทร. <?= htmlspecialchars($store_phone) ?></p>
        <?php if(!empty($store_tax_id)): ?>
            <p>TAX ID: <?= htmlspecialchars($store_tax_id) ?></p>
        <?php endif; ?>
        <h4 style="margin-top: 10px; margin-bottom: 5px;">ใบเสร็จรับเงินอย่างย่อ</h4>
    </div>
    
    <div class="divider"></div>
    
    <p>เลขที่บิล: <?= htmlspecialchars($order['order_no']) ?></p>
    <p>วันที่: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
    <p>พนักงาน: <?= htmlspecialchars($order['fullname']) ?></p>
    
    <div class="divider"></div>
    
    <table>
        <thead>
            <tr>
                <th style="text-align: left;">รายการ</th>
                <th class="text-center" width="40">จำนวน</th>
                <th class="text-right" width="50">รวม</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['product_name']) ?><br><small>@<?= number_format($item['unit_price'], 2) ?></small></td>
                <td class="text-center"><?= $item['quantity'] ?></td>
                <td class="text-right"><?= number_format($item['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="divider"></div>
    
    <table>
        <tr>
            <td class="fw-bold" style="font-size: 14px;">ยอดรวมทั้งสิ้น</td>
            <td class="text-right fw-bold" style="font-size: 14px;">฿<?= number_format($order['total_amount'], 2) ?></td>
        </tr>
        <tr>
            <td>รับเงินมา</td>
            <td class="text-right">฿<?= number_format($order['payment_received'], 2) ?></td>
        </tr>
        <tr>
            <td>เงินทอน</td>
            <td class="text-right">฿<?= number_format($order['change_amount'], 2) ?></td>
        </tr>
    </table>
    
    <div class="divider"></div>
    
    <div class="text-center" style="margin-top: 15px;">
        <p><?= nl2br(htmlspecialchars($store_footer)) ?></p>
    </div>
    
    <!-- ปุ่มปริ้นจะถูกซ่อนไว้เมื่อตอนพิมพ์จริง -->
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ พิมพ์ใบเสร็จ (Print)</button>
        <a href="pos.php" class="btn-back">กลับไปหน้าขายสินค้า</a>
    </div>
</div>

<script>
// สั่งเปิดหน้าต่าง Print อัตโนมัติเมื่อโหลดหน้าเสร็จ
window.onload = function() {
    window.print();
}

// เมื่อหน้าต่าง Print ถูกปิด (ไม่ว่าจะกดปริ้น หรือกดยกเลิก) ให้เด้งกลับไปหน้าขายของอัตโนมัติ
window.onafterprint = function() {
    window.location.href = 'pos.php';
}
</script>

</body>
</html>
