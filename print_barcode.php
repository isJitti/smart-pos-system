<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("ไม่มีสิทธิ์เข้าถึง");
}
require_once 'includes/db.php';

if (!isset($_GET['id'])) {
    die("ไม่พบรหัสสินค้า");
}
$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT barcode, product_name, sale_price FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    die("ไม่พบข้อมูลสินค้า");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พิมพ์บาร์โค้ด - <?= htmlspecialchars($product['product_name']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap');
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f0f0f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .barcode-container {
            background: white;
            padding: 15px;
            border: 1px solid #ccc;
            text-align: center;
            width: 50mm; /* ขนาดสติ๊กเกอร์มาตรฐาน */
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .product-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-price {
            font-size: 16px;
            font-weight: bold;
            margin-top: 5px;
        }
        .btn-print {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #0d6efd;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        @media print {
            body { background: white; padding: 0; }
            .barcode-container { border: none; box-shadow: none; margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="barcode-container">
    <div class="product-name"><?= htmlspecialchars($product['product_name']) ?></div>
    <!-- Element สำหรับแสดงบาร์โค้ด -->
    <svg id="barcode"></svg>
    <div class="product-price">฿<?= number_format($product['sale_price'], 2) ?></div>
</div>

<button class="btn-print no-print" onclick="window.print()">
    <i class="fa-solid fa-print"></i> สั่งพิมพ์บาร์โค้ด
</button>

<!-- ใช้ JsBarcode สำหรับสร้างบาร์โค้ด -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
    JsBarcode("#barcode", "<?= htmlspecialchars($product['barcode']) ?>", {
        format: "CODE128",
        lineColor: "#000",
        width: 1.5,
        height: 40,
        displayValue: true,
        fontSize: 14,
        margin: 0
    });
</script>

</body>
</html>
