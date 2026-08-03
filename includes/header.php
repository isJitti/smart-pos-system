<!DOCTYPE html>
<html lang="th" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Inventory & Mini POS</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: var(--bs-body-bg); }
        /* สำหรับ Light Mode จะถูก override ด้วย theme colors */
        html[data-bs-theme="light"] body { background-color: #f4f7f6; }
        .navbar { box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        /* Day/Night Custom Toggle Switch */
        .theme-switch-wrapper { display: flex; align-items: center; }
        .theme-switch { display: inline-block; height: 28px; position: relative; width: 56px; margin: 0; }
        .theme-switch input { display: none; }
        .slider { background-color: #87CEEB; bottom: 0; cursor: pointer; left: 0; position: absolute; right: 0; top: 0; transition: .4s; border-radius: 34px; box-shadow: inset 0 0 5px rgba(0,0,0,0.2); overflow: hidden; }
        .slider:before { background-color: #FFD700; bottom: 3px; content: ""; height: 22px; left: 4px; position: absolute; transition: .4s; width: 22px; border-radius: 50%; z-index: 2; box-shadow: 0 0 10px rgba(255, 215, 0, 0.8); }
        .slider:after { content: '\f0c2'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; top: 5px; right: 6px; color: rgba(255, 255, 255, 0.8); font-size: 14px; transition: 0.4s; }
        input:checked + .slider { background-color: #1A1A2E; }
        input:checked + .slider:before { transform: translateX(26px); background-color: #F4F4F4; box-shadow: inset -4px -2px 0 0px #d4d4d4, 0 0 10px rgba(255,255,255,0.5); }
        input:checked + .slider:after { content: '\f005'; left: 8px; right: auto; color: #FFF; font-size: 10px; top: 8px; }
    </style>
    <!-- Script สำหรับเช็ค Dark Mode ด่วนก่อนเรนเดอร์จอ (ป้องกันจอกระพริบ) -->
    <script>
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
</head>
<body>

<!-- Navbar ส่วนบน -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fa-solid fa-store me-2"></i>Mini POS</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active fw-bold' : '' ?>" href="dashboard.php">
            <i class="fa-solid fa-chart-pie me-1"></i> Dashboard
          </a>
        </li>
        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active fw-bold' : '' ?>" href="products.php">
            <i class="fa-solid fa-box-open me-1"></i> จัดการสินค้า
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['categories.php', 'users.php']) ? 'active fw-bold' : '' ?>" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-gear me-1"></i> ตั้งค่าระบบ
          </a>
          <ul class="dropdown-menu shadow border-0" aria-labelledby="navbarDropdown">
            <li><a class="dropdown-item" href="settings.php"><i class="fa-solid fa-store me-2 text-success"></i> ตั้งค่าร้านค้า</a></li>
            <li><a class="dropdown-item" href="categories.php"><i class="fa-solid fa-list me-2 text-primary"></i> จัดการหมวดหมู่</a></li>
            <li><a class="dropdown-item" href="users.php"><i class="fa-solid fa-users-gear me-2 text-warning"></i> จัดการพนักงาน</a></li>
          </ul>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'active fw-bold' : '' ?>" href="pos.php">
            <i class="fa-solid fa-cash-register me-1"></i> ขายสินค้า (POS)
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active fw-bold' : '' ?>" href="history.php">
            <i class="fa-solid fa-clock-rotate-left me-1"></i> ประวัติการขาย
          </a>
        </li>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center">
        <!-- ปุ่ม Dark Mode Toggle (Switch) -->
        <li class="nav-item me-3 theme-switch-wrapper">
            <label class="theme-switch" title="สลับโหมดมืด/สว่าง">
                <input type="checkbox" id="checkboxDarkMode">
                <div class="slider"></div>
            </label>
        </li>
        <!-- เมนูโปรไฟล์ -->
        <li class="nav-item dropdown me-3">
          <a class="nav-link dropdown-toggle text-light fw-bold" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-circle-user me-1 fs-5 align-middle"></i> 
            <?= htmlspecialchars($_SESSION['fullname'] ?? '') ?>
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                <span class="badge bg-danger ms-1">Admin</span>
            <?php else: ?>
                <span class="badge bg-info text-dark ms-1">Staff</span>
            <?php endif; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="profileDropdown">
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal"><i class="fa-solid fa-key me-2 text-secondary"></i> เปลี่ยนรหัสผ่าน</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> ออกจากระบบ</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Modal เปลี่ยนรหัสผ่าน (ซ่อนไว้ในทุกหน้าที่มี header) -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-dark text-white border-0">
        <h5 class="modal-title"><i class="fa-solid fa-key me-2"></i>เปลี่ยนรหัสผ่านส่วนตัว</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="change_password.php" method="POST">
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label fw-bold">รหัสผ่านใหม่ <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" name="new_password" required minlength="4">
              </div>
              <div class="mb-2">
                  <label class="form-label fw-bold">ยืนยันรหัสผ่านใหม่ <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" name="confirm_password" required minlength="4">
              </div>
          </div>
          <div class="modal-footer bg-light border-0">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
              <button type="submit" class="btn btn-dark">บันทึกรหัสผ่าน</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- เปิด Container หลัก (จะไปปิดที่ footer.php) -->
<div class="container-fluid px-4">
