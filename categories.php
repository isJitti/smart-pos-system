<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
// ตรวจสอบสิทธิ์ (เฉพาะ admin)
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['alert'] = ['type' => 'error', 'msg' => 'หน้านี้สำหรับผู้ดูแลระบบเท่านั้น'];
    header("Location: pos.php");
    exit();
}
require_once 'includes/db.php';

// --- จัดการเพิ่มหมวดหมู่ (Create) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $category_name = trim($_POST['category_name']);
    try {
        // ตรวจสอบชื่อหมวดหมู่ซ้ำ
        $stmt_check = $pdo->prepare("SELECT id FROM categories WHERE category_name = ?");
        $stmt_check->execute([$category_name]);
        if ($stmt_check->rowCount() > 0) {
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'ชื่อหมวดหมู่นี้มีอยู่ในระบบแล้ว!'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
            $stmt->execute([$category_name]);
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'เพิ่มหมวดหมู่เรียบร้อยแล้ว'];
        }
    } catch(PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    header("Location: categories.php");
    exit();
}

// --- จัดการแก้ไขหมวดหมู่ (Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $category_name = trim($_POST['category_name']);
    try {
        // ตรวจสอบชื่อหมวดหมู่ซ้ำ ยกเว้นตัวมันเอง
        $stmt_check = $pdo->prepare("SELECT id FROM categories WHERE category_name = ? AND id != ?");
        $stmt_check->execute([$category_name, $id]);
        if ($stmt_check->rowCount() > 0) {
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'ชื่อหมวดหมู่นี้มีอยู่ในระบบแล้ว!'];
        } else {
            $stmt = $pdo->prepare("UPDATE categories SET category_name = ? WHERE id = ?");
            $stmt->execute([$category_name, $id]);
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'แก้ไขหมวดหมู่เรียบร้อยแล้ว'];
        }
    } catch(PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    header("Location: categories.php");
    exit();
}

// --- จัดการลบหมวดหมู่ (Delete) ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // เช็คก่อนว่ามีสินค้าผูกอยู่ไหม
        $stmt_check = $pdo->prepare("SELECT COUNT(id) FROM products WHERE category_id = ?");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'ไม่สามารถลบได้ เนื่องจากมีสินค้าอยู่ในหมวดหมู่นี้!'];
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'ลบหมวดหมู่เรียบร้อยแล้ว'];
        }
    } catch(PDOException $e) {
        $_SESSION['alert'] = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    header("Location: categories.php");
    exit();
}

// ดึงข้อมูลหมวดหมู่มาแสดงพร้อมนับจำนวนสินค้าในแต่ละหมวดหมู่
$stmt = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.category_name ASC
");
$categories = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<!-- เรียกใช้ DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fa-solid fa-list me-2 text-primary"></i> จัดการหมวดหมู่สินค้า</h2>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="fa-solid fa-plus me-1"></i> เพิ่มหมวดหมู่ใหม่
    </button>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 w-100" id="categoryTable">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4" width="80">รหัส</th>
                            <th>ชื่อหมวดหมู่</th>
                            <th class="text-center">จำนวนสินค้าที่มี</th>
                            <th class="text-center pe-4" width="150">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($categories)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">ยังไม่มีหมวดหมู่สินค้า</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach($categories as $c): ?>
                        <tr>
                            <td class="ps-4 text-muted">#<?= $c['id'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($c['category_name']) ?></td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-<?= $c['product_count'] > 0 ? 'info' : 'secondary' ?>">
                                    <?= $c['product_count'] ?> รายการ
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <button class="btn btn-sm btn-warning text-dark shadow-sm" onclick="editCategory(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['category_name'])) ?>')">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if($c['product_count'] == 0): ?>
                                    <button class="btn btn-sm btn-danger shadow-sm" onclick="confirmDelete(<?= $c['id'] ?>)">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary shadow-sm" style="opacity: 0.5; cursor: not-allowed;" title="ไม่สามารถลบได้เพราะมีสินค้าอยู่">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal เพิ่มหมวดหมู่ -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="modal-title"><i class="fa-solid fa-folder-plus me-2"></i>เพิ่มหมวดหมู่ใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="categories.php" method="POST">
          <div class="modal-body">
              <input type="hidden" name="action" value="add">
              <div class="mb-3">
                  <label class="form-label fw-bold">ชื่อหมวดหมู่ <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="category_name" required autofocus>
              </div>
          </div>
          <div class="modal-footer bg-body-tertiary border-0">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
              <button type="submit" class="btn btn-primary">บันทึก</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal แก้ไขหมวดหมู่ -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning border-0">
        <h5 class="modal-title text-body"><i class="fa-solid fa-pen-to-square me-2"></i>แก้ไขหมวดหมู่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="categories.php" method="POST">
          <div class="modal-body">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" id="edit_id">
              <div class="mb-3">
                  <label class="form-label fw-bold">ชื่อหมวดหมู่ <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="category_name" id="edit_category_name" required>
              </div>
          </div>
          <div class="modal-footer bg-body-tertiary border-0">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
              <button type="submit" class="btn btn-warning">บันทึกการเปลี่ยนแปลง</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function editCategory(id, name) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_category_name').value = name;
    var modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    modal.show();
}

function confirmDelete(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "คุณแน่ใจหรือไม่ที่จะลบหมวดหมู่นี้?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'categories.php?delete=' + id;
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
    $('#categoryTable').DataTable({
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
if(isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '{$alert['type']}',
                title: '{$alert['msg']}',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        });
    </script>";
    unset($_SESSION['alert']);
}
require_once 'includes/footer.php'; 
?>
