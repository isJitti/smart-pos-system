# Smart POS (Point of Sale System)
ระบบบริหารจัดการสต็อกและจุดขายสินค้าขนาดย่อม (Mini POS System) พัฒนาด้วย PHP, MySQL และ Bootstrap 5

## ฟีเจอร์เด่น (Features)
- 🛒 **ระบบขายสินค้า (POS):** รองรับเครื่องสแกนบาร์โค้ด, พิมพ์ใบเสร็จ (PDF/Slip)
- 📦 **ระบบจัดการสต็อก:** แจ้งเตือนสินค้าใกล้หมดสต็อก
- 👥 **ระบบสมาชิก & สิทธิ์การใช้งาน:** รองรับ Admin และ Staff
- 📊 **รายงานยอดขาย:** กราฟยอดขายรายวัน/เดือน และรายการสินค้าขายดี
- 🌙 **Dark Mode:** รองรับโหมดถนอมสายตา

## การติดตั้ง (Installation)
1. ติดตั้ง XAMPP หรือเครื่องจำลองเซิร์ฟเวอร์อื่นๆ
2. คัดลอกโฟลเดอร์นี้ไปไว้ใน `htdocs`
3. สร้างฐานข้อมูลชื่อ `smart_pos` และ Import ไฟล์ `smart_pos_database.sql`
4. ตั้งค่าการเชื่อมต่อฐานข้อมูลใน `includes/db.php`
5. เข้าใช้งานผ่าน `http://localhost/smart_pos`

## บัญชีทดสอบ (Test Accounts)
- **Admin:** Username: `admin` / Password: `123456`
- **Staff:** Username: `staff` / Password: `123456`
