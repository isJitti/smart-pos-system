</div> <!-- ปิด Container หลักที่เปิดไว้ใน header.php -->
    
    <!-- Bootstrap 5 JS Bundle (รวม Popper.js แล้ว) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 (ไลบรารีสำหรับทำ Popup แจ้งเตือนสวยๆ) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Script ควบคุม Dark Mode -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkbox = document.getElementById('checkboxDarkMode');
            const html = document.documentElement;
            
            // ตั้งค่าสถานะ switch ตอนโหลดหน้า
            if (html.getAttribute('data-bs-theme') === 'dark') {
                checkbox.checked = true;
            }
            
            checkbox.addEventListener('change', function() {
                const newTheme = this.checked ? 'dark' : 'light';
                html.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });
        });
    </script>

</body>
</html>
