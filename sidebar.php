<?php
// Tambahkan session_start jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blankspot Maps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body>
    <!-- Tombol Menu Mobile -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Overlay Sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <h1><i class="fas fa-map-marked-alt"></i> Blankspot Maps</h1>
            <p>Sistem Pemetaan Terintegrasi</p>
        </div>
        
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard Utama</span>
            </a>
            <a href="hasil_pemetaan.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'hasil_pemetaan.php' ? 'active' : '' ?>">
                <i class="fas fa-map-marked-alt"></i>
                <span>Pemetaan Detail</span>
            </a>
            <a href="report.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Laporan Statistik</span>
            </a>
            <a href="riwayat.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'riwayat.php' ? 'active' : '' ?>">
                <i class="fas fa-history"></i>
                <span>Riwayat Aktivitas</span>
            </a>
            <?php if (isset($_SESSION['peran']) && $_SESSION['peran'] === 'Super Admin'): ?>
            <a href="manage_users.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i>
                <span>Kelola User</span>
            </a>
            <?php endif; ?>
            <a href="pengaturan.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'pengaturan.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span>Pengaturan</span>
            </a>
            <div class="nav-item" id="logoutBtn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </div>    
        </nav>
    </div>

    <!-- Modal Konfirmasi Logout -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <h2 class="modal-title">Konfirmasi Logout</h2>
            <p class="modal-message">Apakah Anda yakin ingin keluar dari sistem?</p>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-cancel" id="cancelLogout">Batal</button>
                <button class="modal-btn modal-btn-logout" id="confirmLogout">Ya, Logout</button>
            </div>
        </div>
    </div>
    
    <script>
        // JavaScript untuk toggle sidebar
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        
        menuToggle.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);
        
        // Tutup sidebar ketika link diklik di mobile
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
            });
        });

        // Pastikan sidebar tertutup saat page load di mobile
        window.addEventListener('load', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
            }
        });

        // Modal Logout Functionality
        const logoutBtn = document.getElementById('logoutBtn');
        const logoutModal = document.getElementById('logoutModal');
        const cancelLogout = document.getElementById('cancelLogout');
        const confirmLogout = document.getElementById('confirmLogout');

        // Tampilkan modal saat tombol logout diklik
        logoutBtn.addEventListener('click', function() {
            logoutModal.classList.add('active');
        });

        // Sembunyikan modal saat tombol batal diklik
        cancelLogout.addEventListener('click', function() {
            logoutModal.classList.remove('active');
        });

        // Redirect ke halaman logout saat konfirmasi
        confirmLogout.addEventListener('click', function() {
            window.location.href = 'logout.php';
        });

        // Tutup modal saat klik di luar modal
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                logoutModal.classList.remove('active');
            }
        });

        // Tutup modal dengan tombol ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && logoutModal.classList.contains('active')) {
                logoutModal.classList.remove('active');
            }
        });
    </script>
</body>
</html>