<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
// ให้เฉพาะ admin เข้าถึงหน้านี้
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['alert'] = ['type' => 'error', 'msg' => 'หน้านี้สำหรับผู้ดูแลระบบเท่านั้น'];
    header("Location: pos.php");
    exit();
}
require_once 'includes/db.php';

// ดึงข้อมูลปัจจุบัน
$stmt = $pdo->query("SELECT * FROM settings ORDER BY id ASC LIMIT 1");
$setting = $stmt->fetch();

// กรณี Submit Form เพื่อบันทึกการตั้งค่า
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $store_name = trim($_POST['store_name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $tax_id = trim($_POST['tax_id']);
    $receipt_footer = trim($_POST['receipt_footer']);
    
    try {
        if ($setting) {
            // อัปเดตข้อมูลที่มีอยู่แล้ว
            $update = "UPDATE settings SET store_name=?, address=?, phone=?, tax_id=?, receipt_footer=? WHERE id=?";
            $stmt = $pdo->prepare($update);
            $stmt->execute([$store_name, $address, $phone, $tax_id, $receipt_footer, $setting['id']]);
        } else {
            // เผื่อกรณีตารางว่างเปล่า
            $insert = "INSERT INTO settings (store_name, address, phone, tax_id, receipt_footer) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($insert);
            $stmt->execute([$store_name, $address, $phone, $tax_id, $receipt_footer]);
        }
        
        $_SESSION['alert'] = ['type' => 'success', 'msg' => 'บันทึกการตั้งค่าร้านค้าเรียบร้อยแล้ว'];
        header("Location: settings.php");
        exit();
    } catch(PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-body py-3 border-0 d-flex align-items-center">
                <i class="fa-solid fa-store text-primary fs-4 me-2"></i>
                <h5 class="mb-0 fw-bold text-body">ตั้งค่าข้อมูลร้านค้า (Store Settings)</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php
                // แจ้งเตือนเมื่อสำเร็จ (ใช้ SweetAlert แบบเดียวกับหน้าอื่นๆ)
                if(isset($_SESSION['alert'])) {
                    $alert = $_SESSION['alert'];
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                icon: '{$alert['type']}',
                                title: 'สำเร็จ',
                                text: '{$alert['msg']}',
                                confirmButtonText: 'ตกลง',
                                confirmButtonColor: '#0d6efd'
                            });
                        });
                    </script>";
                    unset($_SESSION['alert']);
                }
                ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อร้าน (Store Name) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="store_name" value="<?= htmlspecialchars($setting['store_name'] ?? '') ?>" required>
                        <div class="form-text">ชื่อร้านนี้จะแสดงอยู่ที่บรรทัดบนสุดของใบเสร็จรับเงิน</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($setting['phone'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">เลขประจำตัวผู้เสียภาษี (ถ้ามี)</label>
                            <input type="text" class="form-control" name="tax_id" value="<?= htmlspecialchars($setting['tax_id'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">ที่อยู่ร้านค้า (Address) <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="address" rows="2" required><?= htmlspecialchars($setting['address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">ข้อความขอบคุณท้ายบิล (Receipt Footer)</label>
                        <textarea class="form-control" name="receipt_footer" rows="2"><?= htmlspecialchars($setting['receipt_footer'] ?? '') ?></textarea>
                    </div>
                    
                    <hr>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4 rounded-pill">
                            <i class="fa-solid fa-save me-1"></i> บันทึกการตั้งค่า
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php require_once 'includes/footer.php'; ?>
