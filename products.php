<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
// ตรวจสอบสิทธิ์การเข้าถึง (ให้เฉพาะ admin)
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['alert'] = ['type' => 'error', 'msg' => 'ไม่มีสิทธิ์เข้าถึงหน้าจัดการสินค้า'];
    header("Location: pos.php");
    exit();
}
require_once 'includes/db.php';

// --- จัดการการ ลบสินค้า (Delete) ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // แบบใหม่ (Soft Delete): เราจะไม่ลบไฟล์รูป และไม่ใช้คำสั่ง DELETE 
    // เพื่อป้องกันประวัติการขายพัง แต่จะเปลี่ยนสถานะ is_active ให้เป็น 0 แทน
    $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['alert'] = ['type' => 'success', 'msg' => 'ลบสินค้าเรียบร้อยแล้ว (ซ่อนจากระบบ)'];
    header("Location: products.php");
    exit();
}

// --- จัดการการ เพิ่มและแก้ไขสินค้า (Add / Edit) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $id = $_POST['id'] ?? '';
    
    $barcode = $_POST['barcode'];
    $product_name = $_POST['product_name'];
    $category_id = $_POST['category_id'];
    $cost_price = $_POST['cost_price'];
    $sale_price = $_POST['sale_price'];
    $stock_quantity = $_POST['stock_quantity'];
    $min_stock = $_POST['min_stock'];
    
    // 1. ดักจับค่าติดลบ (Negative Value Validation)
    if ($cost_price < 0 || $sale_price < 0 || $stock_quantity < 0 || $min_stock < 0) {
        $_SESSION['alert'] = ['type' => 'error', 'msg' => 'ราคาสินค้าหรือสต็อก ห้ามเป็นค่าติดลบ!'];
        header("Location: products.php");
        exit();
    }
    
    // 2. ดักจับบาร์โค้ดซ้ำ (Duplicate Barcode Validation)
    if ($action == 'add') {
        $stmt_check = $pdo->prepare("SELECT id FROM products WHERE barcode = ? AND is_active = 1");
        $stmt_check->execute([$barcode]);
    } else {
        $stmt_check = $pdo->prepare("SELECT id FROM products WHERE barcode = ? AND id != ? AND is_active = 1");
        $stmt_check->execute([$barcode, $id]);
    }
    
    if ($stmt_check->rowCount() > 0) {
        $_SESSION['alert'] = ['type' => 'error', 'msg' => 'บาร์โค้ดซ้ำ! มีสินค้านี้ในระบบแล้ว'];
        header("Location: products.php");
        exit();
    }
    
    // 3. จัดการอัปโหลดรูปภาพพร้อมระบบตรวจสอบไฟล์รูปจริง (Secure Image Upload)
    $image = $_POST['old_image'] ?? ''; 
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // ตรวจสอบนามสกุลไฟล์ และตรวจสอบว่าเป็นรูปภาพจริงๆ (ไม่ใช่ไฟล์แฝงตัว)
        $is_real_image = getimagesize($_FILES['image']['tmp_name']);
        
        if (in_array($ext, $allowed) && $is_real_image !== false) {
            $new_name = uniqid() . '.' . $ext;
            $upload_path = 'assets/images/';
            
            // สร้างโฟลเดอร์ถ้ายังไม่มี
            if (!is_dir($upload_path)) { mkdir($upload_path, 0777, true); }
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path . $new_name)) {
                $image = $new_name; // ใช้ชื่อใหม่
                // ลบรูปเก่าทิ้ง
                if (!empty($_POST['old_image']) && file_exists($upload_path . $_POST['old_image'])) {
                    unlink($upload_path . $_POST['old_image']);
                }
            }
        } else {
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'ไฟล์รูปภาพไม่ถูกต้อง หรือไม่รองรับ'];
            header("Location: products.php");
            exit();
        }
    }

    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO products (barcode, product_name, category_id, cost_price, sale_price, stock_quantity, min_stock, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$barcode, $product_name, $category_id, $cost_price, $sale_price, $stock_quantity, $min_stock, $image]);
        $_SESSION['alert'] = ['type' => 'success', 'msg' => 'เพิ่มสินค้าสำเร็จ'];
    } elseif ($action == 'edit') {
        $stmt = $pdo->prepare("UPDATE products SET barcode=?, product_name=?, category_id=?, cost_price=?, sale_price=?, stock_quantity=?, min_stock=?, image=? WHERE id=?");
        $stmt->execute([$barcode, $product_name, $category_id, $cost_price, $sale_price, $stock_quantity, $min_stock, $image, $id]);
        $_SESSION['alert'] = ['type' => 'success', 'msg' => 'อัปเดตข้อมูลสำเร็จ'];
    }
    
    header("Location: products.php");
    exit();
}

// --- ดึงข้อมูล หมวดหมู่ (สำหรับใส่ใน Dropdown) ---
$stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
$categories = $stmt->fetchAll();

// --- ดึงข้อมูล สินค้าทั้งหมด ---
$sql = "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.id DESC";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll();

// เรียกใช้ส่วนหัว
require_once 'includes/header.php';
?>

<!-- เรียกใช้ DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="fa-solid fa-box-open me-2 text-primary"></i>จัดการสินค้า (CRUD)</h2>
    <!-- ปุ่มเรียก Modal (Popup) สำหรับเพิ่มสินค้า -->
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openAddModal()">
        <i class="fa-solid fa-plus me-1"></i> เพิ่มสินค้าใหม่
    </button>
</div>

<!-- ตารางแสดงรายการสินค้า -->
<div class="card shadow-sm border-0">
    <div class="card-body p-3 table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle w-100" id="productTable">
            <thead class="table-dark">
                <tr>
                    <th class="text-center" width="80">รูปภาพ</th>
                    <th>บาร์โค้ด</th>
                    <th>ชื่อสินค้า</th>
                    <th>หมวดหมู่</th>
                    <th class="text-end">ต้นทุน</th>
                    <th class="text-end">ราคาขาย</th>
                    <th class="text-center">สต็อก</th>
                    <th class="text-center" width="120">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($products)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">ไม่มีข้อมูลสินค้าในระบบ</td></tr>
                <?php endif; ?>
                
                <?php foreach($products as $p): ?>
                <tr>
                    <td class="text-center">
                        <?php 
                        // ตรวจสอบรูปภาพ ถ้าไม่มีให้ใช้ Placeholder
                        $img = (!empty($p['image']) && file_exists('assets/images/'.$p['image'])) 
                                ? 'assets/images/'.$p['image'] 
                                : 'https://placehold.co/100x100?text=No+Img'; 
                        ?>
                        <img src="<?= $img ?>" alt="product" class="rounded border" width="50" height="50" style="object-fit: cover;">
                    </td>
                    <td><?= htmlspecialchars($p['barcode']) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($p['product_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['category_name']) ?></span></td>
                    <td class="text-end"><?= number_format($p['cost_price'], 2) ?></td>
                    <td class="text-end text-success fw-bold"><?= number_format($p['sale_price'], 2) ?></td>
                    <td class="text-center">
                        <?php 
                        // ขั้นตอนที่ 6: แจ้งเตือนสินค้าใกล้หมด (Low Stock Alert) แทรกรวมอยู่ที่นี่เลย
                        if($p['stock_quantity'] <= $p['min_stock']): ?>
                            <span class="badge bg-danger rounded-pill px-2 py-1"><i class="fa-solid fa-triangle-exclamation"></i> <?= $p['stock_quantity'] ?></span>
                        <?php else: ?>
                            <span class="badge bg-success rounded-pill px-3 py-1"><?= $p['stock_quantity'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <!-- ปุ่มปริ้นท์บาร์โค้ด -->
                        <a href="print_barcode.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-info shadow-sm text-white" title="พิมพ์บาร์โค้ด">
                            <i class="fa-solid fa-barcode"></i>
                        </a>
                        <!-- ปุ่มแก้ไข จะเรียกฟังก์ชัน JavaScript เพื่อส่งข้อมูลเข้า Modal -->
                        <button class="btn btn-sm btn-warning shadow-sm" onclick='openEditModal(<?= json_encode($p) ?>)' title="แก้ไข">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <!-- ปุ่มลบ -->
                        <a href="products.php?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger shadow-sm btn-delete" onclick="return confirm('ยืนยันการลบสินค้าชิ้นนี้ ใช่หรือไม่?');" title="ลบ">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal สำหรับ เพิ่ม / แก้ไขสินค้า -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <!-- ฟอร์มส่งไปที่ products.php หน้าเดิมนี่แหละ สังเกต enctype สำคัญมากสำหรับการอัปโหลดไฟล์ -->
      <form action="products.php" method="POST" enctype="multipart/form-data">
          <div class="modal-header bg-primary text-white border-0">
            <h5 class="modal-title fw-bold" id="modalTitle"><i class="fa-solid fa-box-open me-2"></i> เพิ่มสินค้า</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body bg-body-tertiary">
              <!-- ตัวแปรซ่อน เอาไว้แยกว่ากำลัง Add หรือ Edit -->
              <input type="hidden" name="action" id="formAction" value="add">
              <input type="hidden" name="id" id="productId" value="">
              <input type="hidden" name="old_image" id="oldImage" value="">

              <div class="row g-3">
                  <div class="col-md-6">
                      <label class="form-label fw-bold">บาร์โค้ด</label>
                      <input type="text" name="barcode" id="barcode" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">ชื่อสินค้า</label>
                      <input type="text" name="product_name" id="product_name" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">หมวดหมู่</label>
                      <select name="category_id" id="category_id" class="form-select" required>
                          <option value="">-- เลือกหมวดหมู่ --</option>
                          <?php foreach($categories as $c): ?>
                              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">อัปโหลดรูปภาพสินค้าใหม่</label>
                      <input type="file" name="image" class="form-control" accept="image/png, image/jpeg, image/webp">
                      <small class="text-muted"><i class="fa-solid fa-circle-info"></i> ปล่อยว่างไว้หากไม่ต้องการเปลี่ยนรูป</small>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">ต้นทุน (บาท)</label>
                      <input type="number" step="0.01" name="cost_price" id="cost_price" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">ราคาขาย (บาท)</label>
                      <input type="number" step="0.01" name="sale_price" id="sale_price" class="form-control text-success fw-bold" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold">จำนวนสต็อกตั้งต้น</label>
                      <input type="number" name="stock_quantity" id="stock_quantity" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label fw-bold text-danger">จุดสั่งซื้อ (แจ้งเตือนเมื่อเหลือน้อย)</label>
                      <input type="number" name="min_stock" id="min_stock" class="form-control border-danger" value="5" required>
                  </div>
              </div>
          </div>
          <div class="modal-footer border-0 bg-body">
            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-save me-1"></i> บันทึกข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
// ฟังก์ชันล้างค่าใน Modal เวลาจะกด "เพิ่มสินค้าใหม่"
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-box-open me-2"></i> เพิ่มสินค้าใหม่';
    document.getElementById('formAction').value = 'add';
    document.getElementById('productId').value = '';
    document.getElementById('oldImage').value = '';
    
    document.getElementById('barcode').value = '';
    document.getElementById('product_name').value = '';
    document.getElementById('category_id').value = '';
    document.getElementById('cost_price').value = '';
    document.getElementById('sale_price').value = '';
    document.getElementById('stock_quantity').value = '';
    document.getElementById('min_stock').value = '5';
}

// ฟังก์ชันเติมค่าเดิมลงใน Modal เวลาจะกด "แก้ไข"
function openEditModal(p) {
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2"></i> แก้ไขสินค้า: <span class="text-warning">' + p.product_name + '</span>';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('productId').value = p.id;
    document.getElementById('oldImage').value = p.image;
    
    document.getElementById('barcode').value = p.barcode;
    document.getElementById('product_name').value = p.product_name;
    document.getElementById('category_id').value = p.category_id;
    document.getElementById('cost_price').value = p.cost_price;
    document.getElementById('sale_price').value = p.sale_price;
    document.getElementById('stock_quantity').value = p.stock_quantity;
    document.getElementById('min_stock').value = p.min_stock;
    
// สั่งเปิด Modal
    var modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}
</script>

<!-- เรียกใช้ DataTables JS และ jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#productTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/th.json"
        },
        "pageLength": 10,
        "ordering": true,
        "info": true
    });
});
</script>

<?php 
// ส่วนแสดงผลแจ้งเตือนด้วย SweetAlert2 (ถ้าระบบตั้ง Session alert ไว้)
if(isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '{$alert['type']}',
                title: '{$alert['msg']}',
                showConfirmButton: false,
                timer: 2000,
                toast: true,
                position: 'top-end'
            });
        });
    </script>";
    unset($_SESSION['alert']);
}

// เรียกใช้ส่วนท้าย
require_once 'includes/footer.php'; 
?>
