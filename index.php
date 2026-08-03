<?php
session_start();

// ถ้ามีการเข้าสู่ระบบอยู่แล้ว ให้ข้ามไปหน้า Dashboard เลย
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'includes/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        // เตรียมคำสั่ง SQL ค้นหา user จาก username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // ตรวจสอบว่าพบ user และรหัสผ่านตรงกันหรือไม่ (ใช้ password_verify แกะ Hash)
        if ($user && password_verify($password, $user['password'])) {
            // รหัสผ่านถูกต้อง เก็บข้อมูลลง Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];

            // เปลี่ยนเส้นทางไปหน้า Dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - Smart Inventory & Mini POS</title>
    <!-- เรียกใช้ Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- เรียกใช้ Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- เรียกใช้ FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            margin: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            /* Animated Gradient Background */
            background: linear-gradient(-45deg, #4e73df, #1cc88a, #36b9cc, #6610f2);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            perspective: 1000px;
        }

        .login-card {
            /* Glassmorphism Effect */
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 40px 30px;
            transform-style: preserve-3d;
            transition: transform 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .brand-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #4e73df, #36b9cc);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(78, 115, 223, 0.3);
        }

        .login-card h3 {
            font-weight: 700;
            color: #2c3e50;
            letter-spacing: -0.5px;
        }

        .input-group-text {
            background-color: transparent;
            border-right: none;
            color: #4e73df;
        }

        .form-control {
            border-left: none;
            padding-left: 0;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #dee2e6;
        }
        
        .input-group:focus-within {
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
            border-radius: 0.375rem;
        }
        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control {
            border-color: #4e73df;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4e73df, #2e59d9);
            border: none;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            box-shadow: 0 8px 15px rgba(78, 115, 223, 0.3);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(78, 115, 223, 0.4);
            background: linear-gradient(135deg, #2e59d9, #1a40b3);
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="brand-icon">
                <i class="fa-solid fa-cube"></i>
            </div>
            <h3 class="mb-1">Smart POS</h3>
            <p class="text-muted small">ระบบจัดการสต็อกและจุดขายสินค้า</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger text-center shadow-sm rounded-3 py-2" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label fw-bold text-secondary small">ชื่อผู้ใช้ (Username)</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="กรอกชื่อผู้ใช้..." required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label fw-bold text-secondary small">รหัสผ่าน (Password)</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="กรอกรหัสผ่าน..." required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                เข้าสู่ระบบ <i class="fa-solid fa-arrow-right-to-bracket ms-1"></i>
            </button>
        </form>
    </div>
</div>

</body>
</html>
