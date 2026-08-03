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

// --- จัดการเพิ่มผู้ใช้ (Create) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $password, $fullname, $role]);
        $_SESSION['alert'] = ['type' => 'success', 'msg' => 'เพิ่มพนักงานเรียบร้อยแล้ว'];
    } catch(PDOException $e) {
        // เช็คว่า username ซ้ำหรือไม่ (Unique key)
        if ($e->getCode() == 23000) {
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'ชื่อผู้ใช้นี้ (Username) ถูกใช้งานแล้ว'];
        } else {
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }
    header("Location: users.php");
    exit();
}

// --- จัดการแก้ไขผู้ใช้ (Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $role = $_POST['role'];
    
    // ป้องกันแอดมินเปลี่ยนสิทธิ์ตัวเองเป็น staff (เดี๋ยวจะเข้าหน้านี้ไม่ได้อีก)
    if ($id == $_SESSION['user_id'] && $role == 'staff') {
        $_SESSION['alert'] = ['type' => 'error', 'msg' => 'คุณไม่สามารถเปลี่ยนสิทธิ์ตัวเองเป็น Staff ได้'];
        header("Location: users.php");
        exit();
    }
    
    try {
        // ถ้าระบุรหัสผ่านใหม่ ให้แก้รหัสผ่านด้วย
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET username=?, fullname=?, role=?, password=? WHERE id=?");
            $stmt->execute([$username, $fullname, $role, $password, $id]);
        } else {
            // ถ้าปล่อยว่างรหัสผ่าน แปลว่าไม่เปลี่ยนรหัสผ่าน
            $stmt = $pdo->prepare("UPDATE users SET username=?, fullname=?, role=? WHERE id=?");
            $stmt->execute([$username, $fullname, $role, $id]);
        }
        $_SESSION['alert'] = ['type' => 'success', 'msg' => 'แก้ไขข้อมูลพนักงานเรียบร้อยแล้ว'];
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) {
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'ชื่อผู้ใช้นี้ (Username) ถูกใช้งานแล้ว'];
        } else {
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }
    header("Location: users.php");
    exit();
}

// --- จัดการลบผู้ใช้ (Delete) ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // ห้ามลบตัวเอง
    if ($id == $_SESSION['user_id']) {
        $_SESSION['alert'] = ['type' => 'error', 'msg' => 'คุณไม่สามารถลบบัญชีตัวเองที่กำลังล็อกอินอยู่ได้'];
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'ลบพนักงานเรียบร้อยแล้ว'];
        } catch(PDOException $e) {
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'ไม่สามารถลบได้ (อาจมีการอ้างอิงถึงประวัติการขาย)'];
        }
    }
    header("Location: users.php");
    exit();
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$stmt = $pdo->query("SELECT * FROM users ORDER BY role ASC, id DESC");
$users = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<!-- เรียกใช้ DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fa-solid fa-users-gear me-2 text-primary"></i> จัดการผู้ใช้งานระบบ</h2>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fa-solid fa-user-plus me-1"></i> เพิ่มพนักงานใหม่
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0 w-100" id="userTable">
            <thead class="table-dark">
                <tr>
                    <th class="ps-4">ชื่อ-สกุล</th>
                    <th>ชื่อผู้ใช้ (Username)</th>
                    <th class="text-center">สิทธิ์การใช้งาน</th>
                    <th class="text-center">วันที่เพิ่มเข้าระบบ</th>
                    <th class="text-center pe-4" width="150">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td class="ps-4 fw-bold">
                        <i class="fa-solid fa-circle-user text-muted fs-4 me-2 align-middle"></i>
                        <?= htmlspecialchars($u['fullname']) ?>
                        <?php if($u['id'] == $_SESSION['user_id']): ?>
                            <span class="badge bg-success ms-2">คุณ (กำลังล็อกอิน)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-primary"><?= htmlspecialchars($u['username']) ?></td>
                    <td class="text-center">
                        <?php if($u['role'] == 'admin'): ?>
                            <span class="badge bg-danger"><i class="fa-solid fa-crown me-1"></i> ผู้ดูแลระบบ (Admin)</span>
                        <?php else: ?>
                            <span class="badge bg-info text-body"><i class="fa-solid fa-user-tie me-1"></i> พนักงานขาย (Staff)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center text-muted"><small><?= date('d/m/Y', strtotime($u['created_at'])) ?></small></td>
                    <td class="text-center pe-4">
                        <button class="btn btn-sm btn-warning text-dark shadow-sm" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <?php if($u['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-sm btn-danger shadow-sm" onclick="confirmDelete(<?= $u['id'] ?>)">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary shadow-sm" style="opacity: 0.5; cursor: not-allowed;" title="ไม่สามารถลบตัวเองได้">
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

<!-- Modal เพิ่มพนักงาน -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>เพิ่มพนักงานใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="users.php" method="POST">
          <div class="modal-body">
              <input type="hidden" name="action" value="add">
              
              <div class="mb-3">
                  <label class="form-label fw-bold">ชื่อ-สกุล <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="fullname" required>
              </div>
              
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="username" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold">รหัสผ่าน <span class="text-danger">*</span></label>
                      <input type="password" class="form-control" name="password" required minlength="4">
                  </div>
              </div>
              
              <div class="mb-3">
                  <label class="form-label fw-bold">สิทธิ์การใช้งาน <span class="text-danger">*</span></label>
                  <select class="form-select" name="role" required>
                      <option value="staff">พนักงานขาย (Staff)</option>
                      <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                  </select>
                  <div class="form-text">แอดมินสามารถจัดการสินค้าและดูยอดขายได้</div>
              </div>
          </div>
          <div class="modal-footer bg-body-tertiary border-0">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
              <button type="submit" class="btn btn-primary">บันทึกเพิ่มพนักงาน</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal แก้ไขพนักงาน -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-warning border-0">
        <h5 class="modal-title text-body"><i class="fa-solid fa-pen-to-square me-2"></i>แก้ไขข้อมูลพนักงาน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="users.php" method="POST">
          <div class="modal-body">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" id="edit_id">
              
              <div class="mb-3">
                  <label class="form-label fw-bold">ชื่อ-สกุล <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="fullname" id="edit_fullname" required>
              </div>
              
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="username" id="edit_username" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold">รหัสผ่านใหม่</label>
                      <input type="password" class="form-control" name="password" placeholder="(ปล่อยว่างถ้าไม่เปลี่ยน)">
                  </div>
              </div>
              
              <div class="mb-3">
                  <label class="form-label fw-bold">สิทธิ์การใช้งาน <span class="text-danger">*</span></label>
                  <select class="form-select" name="role" id="edit_role" required>
                      <option value="staff">พนักงานขาย (Staff)</option>
                      <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                  </select>
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
function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_fullname').value = user.fullname;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_role').value = user.role;
    var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function confirmDelete(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "คุณแน่ใจหรือไม่ที่จะลบบัญชีผู้ใช้นี้ออกจากระบบ?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'users.php?delete=' + id;
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
    $('#userTable').DataTable({
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
