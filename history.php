<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
require_once 'includes/db.php';

// --- จัดการยกเลิกบิล (Void Order) ---
if (isset($_GET['void_id']) && $_SESSION['role'] === 'admin') {
    $void_id = $_GET['void_id'];
    try {
        $pdo->beginTransaction();
        
        // เช็คก่อนว่าบิลนี้ยังไม่ถูกยกเลิก
        $stmt_chk = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt_chk->execute([$void_id]);
        if ($stmt_chk->fetchColumn() === 'completed') {
            // 1. เปลี่ยนสถานะบิลเป็น voided
            $stmt_upd = $pdo->prepare("UPDATE orders SET status = 'voided' WHERE id = ?");
            $stmt_upd->execute([$void_id]);
            
            // 2. ดึงรายการสินค้าเพื่อคืนสต็อก
            $stmt_items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmt_items->execute([$void_id]);
            $items = $stmt_items->fetchAll();
            
            // 3. คืนสต็อก
            $stmt_stock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            foreach ($items as $item) {
                $stmt_stock->execute([$item['quantity'], $item['product_id']]);
            }
            
            $pdo->commit();
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'ยกเลิกบิลและคืนสต็อกเรียบร้อยแล้ว'];
        } else {
            $pdo->rollBack();
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'บิลนี้ถูกยกเลิกไปแล้ว!'];
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['alert'] = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    header("Location: history.php");
    exit();
}

// กำหนดเงื่อนไขการค้นหา
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';

$sql = "SELECT o.*, u.fullname FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1";
$params = [];

if (!empty($start_date)) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $end_date;
}
if (!empty($filter_user)) {
    $sql .= " AND o.user_id = ?";
    $params[] = $filter_user;
}
$sql .= " ORDER BY o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// คำนวณสรุปยอดรวมเฉพาะบิลที่สมบูรณ์
$sum_total = 0;
$sum_bills = 0;
foreach ($orders as $o) {
    if ($o['status'] === 'completed' || $o['status'] === null) {
        $sum_total += $o['total_amount'];
        $sum_bills++;
    }
}

// ดึงรายชื่อพนักงานสำหรับ Dropdown ตัวกรอง (เฉพาะ Admin)
$users_list = [];
if ($_SESSION['role'] === 'admin') {
    $stmt_users = $pdo->query("SELECT id, fullname FROM users ORDER BY fullname ASC");
    $users_list = $stmt_users->fetchAll();
}

// หากต้องการ Export เป็น CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sales_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // รองรับภาษาไทยใน Excel (BOM)
    fputcsv($output, ['เลขที่บิล', 'วันที่-เวลา', 'พนักงานที่ขาย', 'ยอดรวม', 'รับเงินมา', 'เงินทอน']);
    foreach ($orders as $row) {
        fputcsv($output, [
            $row['order_no'],
            $row['created_at'],
            $row['fullname'],
            $row['total_amount'],
            $row['payment_received'],
            $row['change_amount']
        ]);
    }
    fclose($output);
    exit();
}

// Fetch order details via AJAX (if requested)
if (isset($_GET['ajax_order_id'])) {
    $order_id = $_GET['ajax_order_id'];
    $stmt = $pdo->prepare("SELECT oi.*, p.product_name, p.barcode FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    // Return HTML rows
    if (empty($items)) {
        echo '<tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูลสินค้าในบิลนี้</td></tr>';
        exit();
    }
    
    foreach ($items as $index => $item) {
        $price = number_format($item['unit_price'], 2);
        $total = number_format($item['total_price'], 2);
        $num = $index + 1;
        echo "
        <tr>
            <td class='text-center'>{$num}</td>
            <td>{$item['barcode']}<br><small class='text-muted'>{$item['product_name']}</small></td>
            <td class='text-center'>{$item['quantity']}</td>
            <td class='text-end text-primary fw-bold'>฿{$total}</td>
        </tr>";
    }
    exit(); // Stop execution for AJAX request
}

require_once 'includes/header.php';
?>

<!-- เรียกใช้ DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>ประวัติการขาย (Order History)</h2>
</div>

<!-- กล่องสรุปยอดรวม (Summary Box) -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="text-xs fw-bold text-success text-uppercase mb-1">ยอดขายรวม (ตามเงื่อนไขที่ค้นหา)</div>
                <div class="h3 mb-0 fw-bold text-body">฿<?= number_format($sum_total, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="text-xs fw-bold text-info text-uppercase mb-1">จำนวนบิลที่สมบูรณ์ (ตามเงื่อนไขที่ค้นหา)</div>
                <div class="h3 mb-0 fw-bold text-body"><?= number_format($sum_bills) ?> บิล</div>
            </div>
        </div>
    </div>
</div>

<!-- กล่องค้นหาตามวันที่ และตัวกรอง -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="history.php" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-bold">เริ่มวันที่</label>
                <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">ถึงวันที่</label>
                <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            
            <?php if($_SESSION['role'] === 'admin'): ?>
            <div class="col-md-3">
                <label class="form-label fw-bold">พนักงานขาย</label>
                <select name="filter_user" class="form-select">
                    <option value="">-- ทุกคน --</option>
                    <?php foreach($users_list as $ul): ?>
                        <option value="<?= $ul['id'] ?>" <?= $filter_user == $ul['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ul['fullname']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-3"><i class="fa-solid fa-magnifying-glass me-1"></i> ค้นหา</button>
                <a href="history.php" class="btn btn-secondary">รีเซ็ต</a>
                
                <a href="history.php?export=csv&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&filter_user=<?= urlencode($filter_user) ?>" class="btn btn-success ms-auto">
                    <i class="fa-solid fa-file-excel me-1"></i> Excel
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle w-100" id="historyTable">
            <thead class="table-dark">
                <tr>
                    <th class="ps-4">สถานะ/เลขบิล</th>
                    <th>วันที่-เวลา</th>
                    <th>พนักงานที่ขาย</th>
                    <th class="text-end">ยอดรวม</th>
                    <th class="text-end">รับเงินมา</th>
                    <th class="text-end">เงินทอน</th>
                    <th class="text-center pe-4" width="200">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($orders)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">ยังไม่มีประวัติการขาย</td></tr>
                <?php endif; ?>
                
                <?php foreach($orders as $o): 
                    $isVoid = ($o['status'] === 'voided');
                ?>
                <tr class="<?= $isVoid ? 'text-decoration-line-through text-muted' : '' ?>">
                    <td class="ps-4 fw-bold <?= $isVoid ? 'text-danger' : 'text-primary' ?>">
                        <?php if($isVoid): ?>
                            <span class="badge bg-danger me-1">ยกเลิก</span><br>
                        <?php endif; ?>
                        <?= htmlspecialchars($o['order_no']) ?>
                    </td>
                    <td><i class="fa-regular fa-calendar text-muted me-1"></i><?= date('d/m/y H:i', strtotime($o['created_at'])) ?></td>
                    <td><span class="badge bg-secondary"><i class="fa-solid fa-user me-1"></i><?= htmlspecialchars($o['fullname']) ?></span></td>
                    <td class="text-end fw-bold <?= $isVoid ? 'text-muted' : 'text-success' ?>">฿<?= number_format($o['total_amount'], 2) ?></td>
                    <td class="text-end">฿<?= number_format($o['payment_received'], 2) ?></td>
                    <td class="text-end">฿<?= number_format($o['change_amount'], 2) ?></td>
                    <td class="text-center pe-4">
                        <button class="btn btn-sm btn-info text-white shadow-sm" onclick="viewOrder(<?= $o['id'] ?>, '<?= $o['order_no'] ?>')">
                            <i class="fa-solid fa-eye me-1"></i>รายละเอียด
                        </button>
                        
                        <?php if(!$isVoid): ?>
                            <!-- ปุ่มสำหรับปริ้นใบเสร็จย้อนหลัง -->
                            <a href="receipt.php?order_id=<?= $o['id'] ?>" target="_blank" class="btn btn-sm btn-secondary shadow-sm">
                                <i class="fa-solid fa-print"></i>
                            </a>
                            <!-- ปุ่มยกเลิกบิล (เฉพาะ Admin) -->
                            <?php if($_SESSION['role'] === 'admin'): ?>
                            <button class="btn btn-sm btn-danger shadow-sm ms-1" onclick="confirmVoid(<?= $o['id'] ?>, '<?= $o['order_no'] ?>')">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal สำหรับดูรายละเอียดบิล -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-dark text-white border-0">
        <h5 class="modal-title" id="modalTitle"><i class="fa-solid fa-receipt me-2"></i>รายละเอียดบิล: <span id="modalOrderNo" class="text-warning"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
          <table class="table table-striped mb-0">
              <thead class="table-group-divider">
                  <tr>
                      <th class="text-center">#</th>
                      <th>สินค้า</th>
                      <th class="text-center">จำนวน</th>
                      <th class="text-end pe-3">รวม (฿)</th>
                  </tr>
              </thead>
              <tbody id="orderDetailsBody">
                  <!-- โหลดข้อมูลจาก AJAX -->
                  <tr><td colspan="4" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div> กำลังโหลด...</td></tr>
              </tbody>
          </table>
      </div>
      <div class="modal-footer bg-body-tertiary border-0">
        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
      </div>
    </div>
  </div>
</div>

<script>
function viewOrder(orderId, orderNo) {
    document.getElementById('modalOrderNo').innerText = orderNo;
    document.getElementById('orderDetailsBody').innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div> กำลังโหลด...</td></tr>';
    
    var modal = new bootstrap.Modal(document.getElementById('orderModal'));
    modal.show();
    
    // ดึงข้อมูลด้วย AJAX 
    fetch('history.php?ajax_order_id=' + orderId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('orderDetailsBody').innerHTML = html;
        });
}

function confirmVoid(orderId, orderNo) {
    Swal.fire({
        title: 'ยืนยันการยกเลิกบิล?',
        html: `คุณกำลังจะยกเลิกบิลหมายเลข <b>${orderNo}</b><br>ระบบจะคืนสต็อกสินค้าอัตโนมัติ และไม่สามารถย้อนกลับได้`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ยกเลิกบิล!',
        cancelButtonText: 'ปิด'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'history.php?void_id=' + orderId;
        }
    });
}
</script>

<!-- เรียกใช้ DataTables JS และ jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#historyTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json"
        },
        "pageLength": 10,
        "ordering": true,
        "info": true,
        "order": [[1, "desc"]] // เรียงวันที่-เวลา ล่าสุดขึ้นก่อน
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
