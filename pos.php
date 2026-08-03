<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
require_once 'includes/db.php';

// --- จัดการเมื่อกดปุ่ม "ชำระเงิน" (Checkout) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    // รับค่า JSON จากตะกร้าสินค้า (ที่ส่งมาจาก JavaScript)
    $cart_data = json_decode($_POST['cart_data'], true);
    $payment_received = floatval($_POST['payment_received']);
    
    // ตรวจสอบความถูกต้องเบื้องต้น
    if (!empty($cart_data) && $payment_received > 0) {
        try {
            // เริ่ม Transaction (ถ้ามีอะไรพลาดกลางคิว จะยกเลิกทั้งหมดอัตโนมัติ ป้องกันเงินหายแต่สต็อกไม่ตัด)
            $pdo->beginTransaction();
            
            // 1. ตรวจสอบราคาที่แท้จริงจากฐานข้อมูล (Backend Validation) ป้องกันการแฮ็กเปลี่ยนราคาจากหน้าบ้าน
            $total_amount_real = 0;
            $validated_items = [];
            
            $stmt_check = $pdo->prepare("SELECT sale_price FROM products WHERE id = ? AND is_active = 1");
            foreach ($cart_data as $item) {
                $pid = $item['id'];
                $qty = $item['qty'];
                
                $stmt_check->execute([$pid]);
                $real_price = $stmt_check->fetchColumn();
                
                if ($real_price === false) {
                    throw new Exception("ไม่พบสินค้าบางรายการ หรือสินค้ายกเลิกขายไปแล้ว");
                }
                
                $total_price = $qty * $real_price;
                $total_amount_real += $total_price;
                
                $validated_items[] = [
                    'id' => $pid,
                    'qty' => $qty,
                    'price' => $real_price,
                    'total_price' => $total_price
                ];
            }
            
            // คำนวณเงินทอนจากยอดจริง
            $change_amount_real = $payment_received - $total_amount_real;
            
            if ($change_amount_real < 0) {
                throw new Exception("รับเงินมาไม่พอ (ยอดรวมจริง: ฿" . number_format($total_amount_real, 2) . ")");
            }
            
            // 2. สร้างเลขที่บิล (Order No) อัตโนมัติ
            $order_no = 'ORD' . date('YmdHis') . rand(10,99);
            $user_id = $_SESSION['user_id'];
            
            // 3. บันทึกข้อมูลลงตาราง orders (หัวบิล)
            $stmt = $pdo->prepare("INSERT INTO orders (order_no, total_amount, payment_received, change_amount, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$order_no, $total_amount_real, $payment_received, $change_amount_real, $user_id]);
            $order_id = $pdo->lastInsertId(); // ดึง ID ของบิลที่เพิ่ง Insert ไป
            
            // เตรียมคำสั่ง SQL สำหรับลูปเพิ่มรายการสินค้า และตัดสต็อก
            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
            $stmt_stock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            
            // 4. ลูปรายการสินค้าในตะกร้า (ที่ตรวจสอบความถูกต้องแล้ว)
            foreach ($validated_items as $v_item) {
                // บันทึกรายละเอียดสินค้าลง order_items
                $stmt_item->execute([$order_id, $v_item['id'], $v_item['qty'], $v_item['price'], $v_item['total_price']]);
                // อัปเดตตัดสต็อกใน products
                $stmt_stock->execute([$v_item['qty'], $v_item['id']]);
            }
            
            // ยืนยันการทำงาน (Commit)
            $pdo->commit();
            
            // แจ้งเตือนความสำเร็จและเด้งไปหน้าใบเสร็จ
            $_SESSION['alert'] = ['type' => 'success', 'msg' => "บันทึกการขายสำเร็จ!<br>ยอดเงินทอน: ฿" . number_format($change_amount, 2)];
            header("Location: receipt.php?order_id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack(); // ยกเลิกการบันทึกทั้งหมด
            $_SESSION['alert'] = ['type' => 'error', 'msg' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['alert'] = ['type' => 'error', 'msg' => 'ข้อมูลไม่ถูกต้อง หรือ รับเงินมาไม่พอ'];
    }
}

// --- ดึงข้อมูลสินค้าที่สต็อกมากกว่า 0 และยังไม่ถูกลบ มาแสดงในระบบ POS ---
$stmt = $pdo->query("SELECT * FROM products WHERE stock_quantity > 0 AND is_active = 1 ORDER BY product_name ASC");
$products = $stmt->fetchAll();

// --- ดึงข้อมูลหมวดหมู่มาทำปุ่มตัวกรอง (Category Pills) ---
$stmt_cats = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
$categories = $stmt_cats->fetchAll();

// --- ดึงข้อมูลสรุปยอดขายประจำกะ (My Shift Summary) ---
$stmt_shift = $pdo->prepare("SELECT COUNT(id) as shift_bills, SUM(total_amount) as shift_sales FROM orders WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'");
$stmt_shift->execute([$_SESSION['user_id']]);
$my_shift = $stmt_shift->fetch();
$shift_bills = $my_shift['shift_bills'] ?? 0;
$shift_sales = $my_shift['shift_sales'] ?? 0;

// เรียกใช้ส่วนหัว
require_once 'includes/header.php';
?>

<div class="row">
    <!-- ฝั่งซ้าย: แสดงรายการสินค้า (Product Grid) -->
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-3">
                <h4 class="mb-0 text-secondary"><i class="fa-solid fa-tags"></i> เลือกสินค้า</h4>
                <!-- ปุ่มเรียก Modal สรุปยอดกะ (สำหรับพนักงาน) -->
                <button class="btn btn-sm btn-outline-info rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#shiftModal">
                    <i class="fa-solid fa-wallet me-1"></i> ยอดขายของฉันวันนี้
                </button>
            </div>
            
            <!-- ช่องสำหรับดักจับเครื่องสแกนบาร์โค้ด -->
            <div class="input-group w-50">
                <span class="input-group-text bg-body"><i class="fa-solid fa-barcode"></i></span>
                <input type="text" id="barcodeScanner" class="form-control border-start-0" placeholder="คลิกที่นี่แล้วสแกนบาร์โค้ด..." autofocus autocomplete="off">
            </div>
        </div>
        
        <!-- แถบปุ่มหมวดหมู่ (Category Filter Pills) -->
        <div class="d-flex flex-nowrap overflow-auto mb-3 gap-2 pb-2" style="-webkit-overflow-scrolling: touch;">
            <button class="btn btn-primary rounded-pill px-4 flex-shrink-0 btn-category active" onclick="filterCategory('all', this)">ทั้งหมด</button>
            <?php foreach($categories as $c): ?>
                <button class="btn btn-outline-primary rounded-pill px-3 flex-shrink-0 btn-category" onclick="filterCategory(<?= $c['id'] ?>, this)">
                    <?= htmlspecialchars($c['category_name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body bg-body-tertiary rounded" style="height: 75vh; overflow-y: auto;">
                <div class="row g-3">
                    <?php if(empty($products)): ?>
                        <div class="col-12 text-center text-muted py-5">
                            <h5>ไม่มีสินค้าพร้อมขายในขณะนี้ (สต็อกหมด)</h5>
                        </div>
                    <?php endif; ?>

                    <?php foreach($products as $p): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 product-item" data-category="<?= $p['category_id'] ?>">
                            <!-- เพิ่ม onclick เรียกฟังก์ชัน JavaScript เพื่อส่งข้อมูลสินค้าเข้าตะกร้า -->
                            <div class="card h-100 border-0 shadow-sm product-card" style="cursor:pointer;" onclick='addToCart(<?= json_encode($p) ?>)'>
                                <?php $img = (!empty($p['image']) && file_exists('assets/images/'.$p['image'])) ? 'assets/images/'.$p['image'] : 'https://placehold.co/150x150?text=No+Img'; ?>
                                <img src="<?= $img ?>" class="card-img-top" alt="Product" style="height: 120px; object-fit: cover;">
                                <div class="card-body p-2 text-center d-flex flex-column justify-content-between">
                                    <h6 class="card-title mb-1 fs-6 text-truncate" title="<?= htmlspecialchars($p['product_name']) ?>">
                                        <?= htmlspecialchars($p['product_name']) ?>
                                    </h6>
                                    <div>
                                        <p class="card-text text-success fw-bold mb-0">฿<?= number_format($p['sale_price'], 2) ?></p>
                                        <small class="text-muted" style="font-size: 0.8rem;">คงเหลือ: <?= $p['stock_quantity'] ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ฝั่งขวา: ตะกร้าสินค้า และ ระบบคำนวณเงิน (Cart & Checkout) -->
    <div class="col-md-4">
        <div class="card shadow border-0" style="height: 80vh; display: flex; flex-direction: column;">
            <div class="card-header bg-dark text-white fw-bold py-3">
                <i class="fa-solid fa-cart-shopping me-2"></i> รายการสั่งซื้อ
            </div>
            
            <!-- พื้นที่แสดงรายการในตะกร้า (ใช้ JavaScript แทรก HTML เข้ามา) -->
            <div class="card-body p-0" style="flex: 1; overflow-y: auto;">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-group-divider sticky-top">
                        <tr>
                            <th class="ps-3">สินค้า</th>
                            <th class="text-center">จำนวน</th>
                            <th class="text-end">รวม</th>
                            <th width="40"></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody">
                        <!-- รายการสินค้าจะถูกสร้างด้วย JS -->
                    </tbody>
                </table>
            </div>
            
            <!-- ส่วนสรุปยอดและรับเงิน -->
            <div class="card-footer bg-body border-top-0 pt-3 shadow-lg" style="z-index: 10;">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fs-5 fw-bold text-secondary">ยอดรวมทั้งสิ้น:</span>
                    <span class="fs-3 fw-bold text-danger" id="grandTotal">฿0.00</span>
                </div>
                
                <form action="pos.php" method="POST" id="checkoutForm">
                    <input type="hidden" name="checkout" value="1">
                    <input type="hidden" name="cart_data" id="cart_data_input">
                    <input type="hidden" name="total_amount" id="total_amount_input">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary">รับเงินมา (บาท)</label>
                        <input type="number" class="form-control form-control-lg text-end fs-4 text-primary bg-body-tertiary" name="payment_received" id="paymentReceived" min="0" step="0.01" autocomplete="off" required>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3 bg-body-tertiary p-3 rounded border">
                        <span class="fs-5 fw-bold text-secondary">เงินทอน:</span>
                        <span class="fs-3 fw-bold text-success" id="changeAmount">฿0.00</span>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill shadow-sm" id="btnCheckout" disabled>
                        <i class="fa-solid fa-cash-register me-1"></i> ชำระเงิน / ตัดสต็อก
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* แต่ง Effect เมื่อเอาเมาส์ชี้การ์ดสินค้า */
.product-card { transition: all 0.2s ease-in-out; }
.product-card:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; 
    border: 2px solid #0d6efd !important; 
}
</style>

<!-- สคริปต์ JavaScript สำหรับจัดการตะกร้าสินค้า (Client-side) -->
<script>
let cart = []; // อาร์เรย์เก็บสินค้าที่เลือก
const productsList = <?= json_encode($products) ?>; // ดึงข้อมูลสินค้าทั้งหมดมาไว้ใน JS

// 1. ฟังก์ชันเพิ่มสินค้าลงตะกร้า
function addToCart(product) {
    // เช็คว่ามีสินค้าชิ้นนี้ในตะกร้าหรือยัง
    let existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        // ถ้ามีแล้ว ให้บวกจำนวนเพิ่ม (แต่ห้ามเกินสต็อกที่มี)
        if (existingItem.qty < product.stock_quantity) {
            existingItem.qty++;
        } else {
            Swal.fire({icon: 'warning', title: 'สต็อกไม่พอ', text: 'สินค้ามีสต็อกเพียง ' + product.stock_quantity + ' ชิ้น'});
        }
    } else {
        // ถ้ายังไม่มี ให้ push ลงไปใหม่
        cart.push({
            id: product.id,
            name: product.product_name,
            price: parseFloat(product.sale_price),
            qty: 1,
            max_qty: parseInt(product.stock_quantity)
        });
    }
    renderCart(); // วาดตะกร้าใหม่ทุกครั้งที่มีการเปลี่ยน
}

// 2. ฟังก์ชันเพิ่ม/ลด จำนวนสินค้าในตะกร้า
function updateQty(id, change) {
    let item = cart.find(i => i.id === id);
    if (item) {
        let newQty = item.qty + change;
        if (newQty > 0 && newQty <= item.max_qty) {
            item.qty = newQty;
        } else if (newQty > item.max_qty) {
            Swal.fire({icon: 'warning', title: 'สต็อกไม่พอ', toast:true, position: 'top-end', showConfirmButton: false, timer: 1500});
        }
        renderCart();
    }
}

// 3. ฟังก์ชันลบสินค้าออกจากตะกร้า
function removeItem(id) {
    cart = cart.filter(item => item.id !== id);
    renderCart();
}

// 4. ฟังก์ชันวาดตะกร้าและคำนวณยอดรวม (Render UI)
function renderCart() {
    let tbody = document.getElementById('cartBody');
    tbody.innerHTML = '';
    let grandTotal = 0;
    
    if (cart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-5"><i class="fa-solid fa-basket-shopping fa-3x mb-3 opacity-25"></i><br>ยังไม่มีสินค้าในตะกร้า</td></tr>';
        document.getElementById('btnCheckout').disabled = true;
    } else {
        document.getElementById('btnCheckout').disabled = false;
        
        cart.forEach(item => {
            let itemTotal = item.price * item.qty;
            grandTotal += itemTotal;
            
            tbody.innerHTML += `
                <tr>
                    <td class="ps-3 text-truncate" style="max-width: 130px;" title="${item.name}">${item.name}</td>
                    <td class="text-center" style="white-space: nowrap;">
                        <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0" onclick="updateQty(${item.id}, -1)">-</button>
                        <span class="mx-1 fw-bold">${item.qty}</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0" onclick="updateQty(${item.id}, 1)">+</button>
                    </td>
                    <td class="text-end text-success fw-bold">฿${itemTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger px-2 py-0" onclick="removeItem(${item.id})"><i class="fa-solid fa-xmark"></i></button>
                    </td>
                </tr>
            `;
        });
    }
    
    // อัปเดตตัวเลขบนหน้าจอ
    document.getElementById('grandTotal').innerText = '฿' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // อัปเดตค่าที่จะส่งไปให้ PHP (ซ่อนไว้ใน input hidden)
    document.getElementById('total_amount_input').value = grandTotal;
    document.getElementById('cart_data_input').value = JSON.stringify(cart);
    
    calculateChange(); // คำนวณเงินทอนใหม่ทุกครั้ง
}

// 5. คำนวณเงินทอนอัตโนมัติเมื่อพิมพ์จำนวนเงิน
document.getElementById('paymentReceived').addEventListener('input', calculateChange);

function calculateChange() {
    let total = parseFloat(document.getElementById('total_amount_input').value) || 0;
    let payment = parseFloat(document.getElementById('paymentReceived').value) || 0;
    
    let change = payment - total;
    let changeDisplay = document.getElementById('changeAmount');
    let btnCheckout = document.getElementById('btnCheckout');
    
    if (payment >= total && total > 0) {
        changeDisplay.innerText = '฿' + change.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        changeDisplay.className = 'fs-3 fw-bold text-success';
        btnCheckout.disabled = false;
    } else {
        changeDisplay.innerText = 'ยอดเงินไม่พอ';
        changeDisplay.className = 'fs-5 fw-bold text-danger mt-1';
        if(total > 0) btnCheckout.disabled = true;
    }
}

// ดักจับการ Submit ฟอร์ม ป้องกันตะกร้าว่าง
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    if (cart.length === 0) {
        e.preventDefault();
        Swal.fire({icon: 'error', title: 'ตะกร้าว่างเปล่า', text: 'กรุณาเลือกสินค้าก่อนชำระเงิน'});
    }
});

// ดักจับการยิงเครื่องสแกนบาร์โค้ด
document.getElementById('barcodeScanner').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        let barcode = this.value.trim();
        
        if (barcode !== '') {
            // ค้นหาสินค้าจากอาร์เรย์ productsList
            let product = productsList.find(p => p.barcode === barcode);
            
            if (product) {
                addToCart(product);
                
                // แจ้งเตือนเล็กๆ มุมขวาบนว่าเพิ่มแล้ว
                Swal.fire({
                    icon: 'success',
                    title: 'เพิ่ม ' + product.product_name,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1000
                });
            } else {
                Swal.fire({icon: 'error', title: 'ไม่พบสินค้า', text: 'ไม่มีสินค้ารหัสบาร์โค้ดนี้ในระบบ'});
            }
        }
        
        this.value = ''; // เคลียร์ช่อง input เตรียมรับชิ้นต่อไป
    }
});

// ป้องกันการกด Enter ในช่องค้นหาบาร์โค้ดแล้วเผลอไป Submit ฟอร์มจ่ายเงิน
document.getElementById('barcodeScanner').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
    }
});

// เรียกใช้งานครั้งแรกตอนโหลดหน้า
renderCart();
// -------------------------------------------------------------
// ระบบกรองหมวดหมู่ (Category Filter)
// -------------------------------------------------------------
function filterCategory(categoryId, btnElement) {
    // 1. เปลี่ยนสีปุ่มให้รู้ว่ากดอันไหนอยู่
    let buttons = document.querySelectorAll('.btn-category');
    buttons.forEach(btn => {
        btn.classList.remove('btn-primary', 'active');
        btn.classList.add('btn-outline-primary');
    });
    btnElement.classList.remove('btn-outline-primary');
    btnElement.classList.add('btn-primary', 'active');

    // 2. ซ่อน/แสดง สินค้า
    let items = document.querySelectorAll('.product-item');
    items.forEach(item => {
        if (categoryId === 'all') {
            item.style.display = 'block';
        } else {
            if (item.getAttribute('data-category') == categoryId) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        }
    });
}
</script>

<!-- Modal สรุปยอดขายประจำกะ (My Shift Summary) -->
<div class="modal fade" id="shiftModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-info text-body border-0">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-wallet me-2"></i> สรุปยอดขายของคุณ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
          <p class="text-muted mb-1">ยอดเงินสดที่เก็บได้วันนี้</p>
          <h2 class="text-success fw-bold mb-3">฿<?= number_format($shift_sales, 2) ?></h2>
          
          <div class="d-flex justify-content-between px-3 text-muted">
              <span>จำนวนบิลทั้งหมด:</span>
              <span class="fw-bold text-body"><?= number_format($shift_bills) ?> บิล</span>
          </div>
      </div>
      <div class="modal-footer bg-body-tertiary border-0 justify-content-center">
          <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
      </div>
    </div>
  </div>
</div>

<?php 
// แจ้งเตือนเมื่อบิลสำเร็จ
if(isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '{$alert['type']}',
                title: '{$alert['msg']}',
                html: true,
                confirmButtonText: 'รับทราบ',
                confirmButtonColor: '#0d6efd'
            });
        });
    </script>";
    unset($_SESSION['alert']);
}
require_once 'includes/footer.php'; 
?>
