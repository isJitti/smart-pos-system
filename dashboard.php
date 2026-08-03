<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
// ตรวจสอบสิทธิ์การเข้าถึง (ให้เฉพาะ admin)
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['alert'] = ['type' => 'error', 'msg' => 'หน้านี้สำหรับผู้ดูแลระบบเท่านั้น'];
    header("Location: pos.php");
    exit();
}
require_once 'includes/db.php';

// 1. ดึงข้อมูลยอดขายวันนี้ (เฉพาะบิลที่สมบูรณ์)
$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
$today_sales = $stmt->fetch()['total'] ?? 0;

// 2. ดึงข้อมูลยอดขายเดือนนี้
$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'completed'");
$month_sales = $stmt->fetch()['total'] ?? 0;

// 3. ดึงจำนวนบิลทั้งหมดที่เคยขาย
$stmt = $pdo->query("SELECT COUNT(id) as count FROM orders WHERE status = 'completed'");
$total_orders = $stmt->fetch()['count'] ?? 0;

// 4. ดึงจำนวนรายการสินค้าที่ใกล้หมดสต็อก
$stmt = $pdo->query("SELECT COUNT(id) as count FROM products WHERE stock_quantity <= min_stock");
$low_stock = $stmt->fetch()['count'] ?? 0;

// --- ข้อมูลสำหรับกราฟวงกลม/แท่ง: สินค้าขายดี 5 อันดับแรก ---
$stmt = $pdo->query("
    SELECT p.product_name, SUM(oi.quantity) as total_qty 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status = 'completed'
    GROUP BY oi.product_id 
    ORDER BY total_qty DESC 
    LIMIT 5
");
$best_sellers = $stmt->fetchAll();

$chart_labels = [];
$chart_data = [];
foreach($best_sellers as $b) {
    $chart_labels[] = (strlen($b['product_name']) > 15) ? mb_substr($b['product_name'],0,15).'..' : $b['product_name'];
    $chart_data[] = $b['total_qty'];
}

// --- ข้อมูลยอดขาย 7 วันย้อนหลัง (สำหรับ Line Chart) ---
$stmt = $pdo->query("
    SELECT DATE(created_at) as sale_date, SUM(total_amount) as total 
    FROM orders 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
");
$sales_trend = $stmt->fetchAll();

$trend_labels = [];
$trend_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $trend_labels[] = date('d/m', strtotime("-$i days")); // แสดงแค่ วว/ดด
    
    $found = false;
    foreach($sales_trend as $st) {
        if ($st['sale_date'] == $date) {
            $trend_data[] = $st['total'];
            $found = true;
            break;
        }
    }
    if (!$found) $trend_data[] = 0;
}

// --- ข้อมูลจัดอันดับพนักงานขายยอดเยี่ยม (เดือนนี้) ---
$stmt = $pdo->query("
    SELECT u.fullname, SUM(o.total_amount) as staff_total, COUNT(o.id) as bill_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE()) AND o.status = 'completed'
    GROUP BY u.id
    ORDER BY staff_total DESC
    LIMIT 5
");
$staff_performance = $stmt->fetchAll();

// --- ดึงรายการสินค้าที่ใกล้หมดสต็อก ---
$stmt = $pdo->query("
    SELECT product_name, stock_quantity, min_stock 
    FROM products 
    WHERE stock_quantity <= min_stock AND is_active = 1 
    ORDER BY stock_quantity ASC 
    LIMIT 5
");
$low_stock_items = $stmt->fetchAll();

// เรียกใช้ส่วนหัว
require_once 'includes/header.php'; 
?>

<style>
    /* ตั้งค่าหน้าตาเวลาสั่งปริ้นท์ (Print Media Query) */
    @media print {
        @page { size: A4 portrait; margin: 10mm; }
        body { background-color: #fff !important; }
        .navbar, .btn, #btnDarkMode { display: none !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; margin-bottom: 20px !important; break-inside: avoid; }
        /* ขยายขนาด Chart ให้พอดีกระดาษ */
        .chart-container { min-height: 250px !important; }
        /* บังคับให้สีพื้นหลังของการ์ดแสดงใน PDF */
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i> ภาพรวมระบบ (Dashboard)</h2>
        <span class="text-muted"><i class="fa-regular fa-clock"></i> ข้อมูล ณ วันที่ <?= date('d/m/Y') ?></span>
    </div>
    <button class="btn btn-outline-danger shadow-sm fw-bold" onclick="window.print()">
        <i class="fa-solid fa-file-pdf me-2"></i> ปริ้นท์รายงาน (PDF)
    </button>
</div>

<!-- 4 การ์ดสรุปข้อมูลด้านบน (Summary Cards) -->
<div class="row g-4 mb-4">
    <!-- การ์ดยอดขายวันนี้ -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm border-start border-primary border-4 h-100">
            <div class="card-body py-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">ยอดขายวันนี้</div>
                        <div class="h3 mb-0 fw-bold text-body">฿<?= number_format($today_sales, 2) ?></div>
                    </div>
                    <div class="fs-1 text-body-secondary opacity-25">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- การ์ดยอดขายเดือนนี้ -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
            <div class="card-body py-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">ยอดขายเดือนนี้</div>
                        <div class="h3 mb-0 fw-bold text-body">฿<?= number_format($month_sales, 2) ?></div>
                    </div>
                    <div class="fs-1 text-body-secondary opacity-25">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- การ์ดจำนวนบิลทั้งหมด -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm border-start border-info border-4 h-100">
            <div class="card-body py-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">จำนวนบิลทั้งหมด</div>
                        <div class="h3 mb-0 fw-bold text-body"><?= number_format($total_orders) ?> บิล</div>
                    </div>
                    <div class="fs-1 text-body-secondary opacity-25">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- การ์ดสินค้าต้องเติมสต็อก -->
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm border-start border-danger border-4 h-100">
            <div class="card-body py-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs fw-bold text-danger text-uppercase mb-1">สินค้าต้องเติมสต็อก</div>
                        <div class="h3 mb-0 fw-bold text-body"><?= number_format($low_stock) ?> รายการ</div>
                    </div>
                    <div class="fs-1 text-body-secondary opacity-25">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- กราฟเส้น: เทรนด์ยอดขาย 7 วันย้อนหลัง -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-body py-3 border-0">
                <h6 class="m-0 fw-bold text-primary"><i class="fa-solid fa-chart-line me-2"></i>แนวโน้มยอดขาย 7 วันย้อนหลัง (บาท)</h6>
            </div>
            <div class="card-body">
                <canvas id="trendChart" style="height: 300px; width: 100%;"></canvas>
            </div>
        </div>
    </div>
    
    <!-- กราฟโดนัท/แท่ง: สินค้าขายดี -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-body py-3 border-0">
                <h6 class="m-0 fw-bold text-success"><i class="fa-solid fa-ranking-star me-2"></i>5 อันดับสินค้าขายดี (ชิ้น)</h6>
            </div>
            <div class="card-body">
                <canvas id="bestSellerChart" style="height: 300px; width: 100%;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- ตาราง: พนักงานยอดเยี่ยม -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-body py-3 border-0">
                <h6 class="m-0 fw-bold text-info"><i class="fa-solid fa-award me-2"></i>ยอดขายพนักงาน (เดือนนี้)</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-group-divider">
                        <tr>
                            <th class="ps-3">ชื่อพนักงาน</th>
                            <th class="text-end pe-3">ยอดรวม (฿)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($staff_performance)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">ยังไม่มีข้อมูลยอดขายเดือนนี้</td></tr>
                        <?php endif; ?>
                        <?php foreach($staff_performance as $staff): ?>
                        <tr>
                            <td class="ps-3 fw-bold">
                                <i class="fa-solid fa-circle-user text-muted me-2"></i><?= htmlspecialchars($staff['fullname']) ?>
                                <div class="text-muted text-xs ms-4" style="font-size: 0.75rem;"><?= $staff['bill_count'] ?> บิล</div>
                            </td>
                            <td class="text-end pe-3 text-success fw-bold"><?= number_format($staff['staff_total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ตาราง: สินค้าใกล้หมดสต็อก -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-body py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>เตือน! สินค้าใกล้หมด</h6>
                <a href="products.php" class="btn btn-sm btn-outline-danger" style="font-size: 0.75rem;">จัดการสต็อก</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if(empty($low_stock_items)): ?>
                        <li class="list-group-item text-center text-muted py-4"><i class="fa-solid fa-check-circle text-success me-2"></i>สต็อกสินค้าปกติทั้งหมด</li>
                    <?php endif; ?>
                    <?php foreach($low_stock_items as $ls): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                        <span class="fw-bold text-truncate" style="max-width: 200px;"><?= htmlspecialchars($ls['product_name']) ?></span>
                        <span class="badge bg-danger rounded-pill fs-6 px-3">เหลือ <?= $ls['stock_quantity'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- วิดเจ็ต: รายการขายล่าสุด 5 บิล -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-body py-3 border-0">
                <h6 class="m-0 fw-bold text-secondary"><i class="fa-solid fa-clock-rotate-left me-2"></i>รายการขายล่าสุด (5 บิล)</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    $stmt_recent = $pdo->query("SELECT order_no, total_amount, created_at FROM orders ORDER BY id DESC LIMIT 5");
                    if($stmt_recent->rowCount() == 0) {
                        echo '<li class="list-group-item text-center py-4 text-muted">ยังไม่มีรายการขาย</li>';
                    }
                    while($row = $stmt_recent->fetch()):
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                        <div>
                            <div class="fw-bold"><?= $row['order_no'] ?></div>
                            <small class="text-muted"><i class="fa-regular fa-calendar me-1"></i><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small>
                        </div>
                        <span class="badge bg-secondary bg-gradient rounded-pill px-3 py-2 fs-6 shadow-sm">฿<?= number_format($row['total_amount']) ?></span>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- เรียกใช้งาน Chart.js จาก CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ข้อมูลสินค้าขายดี (Doughnut Chart)
const bestLabels = <?= json_encode($chart_labels) ?>;
const bestData = <?= json_encode($chart_data) ?>;

if(bestData.length > 0) {
    const ctx = document.getElementById('bestSellerChart');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: bestLabels,
            datasets: [{
                data: bestData,
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '65%' // ทำรูตรงกลางใหญ่ๆ ดูโมเดิร์น
        }
    });
} else {
    document.getElementById('bestSellerChart').parentElement.innerHTML = '<div class="h-100 d-flex justify-content-center align-items-center text-muted"><p>ยังไม่มีข้อมูลการขาย</p></div>';
}

// ข้อมูลแนวโน้มยอดขาย 7 วัน (Line Chart)
const trendLabels = <?= json_encode($trend_labels) ?>;
const trendData = <?= json_encode($trend_data) ?>;

const trendCtx = document.getElementById('trendChart');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'ยอดขาย (บาท)',
            data: trendData,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.1)',
            borderWidth: 3,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#4e73df',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: true,
            tension: 0.4 // ทำเส้นโค้งสมูท (Spline)
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    borderDash: [5, 5],
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                grid: { display: false }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index',
        },
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
