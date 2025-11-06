<?php
include "config/db.php";
session_start();

// Redirect ke login jika belum login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Ambil data user dari database
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

if (!$user_data) {
    header('Location: logout.php');
    exit();
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Handle profile update
        $nama_user = $conn->real_escape_string($_POST['nama_user']);
        $email = $conn->real_escape_string($_POST['email']);
        $telepon = $conn->real_escape_string($_POST['telepon']);
        
        $update_query = $conn->prepare("UPDATE users SET nama_user = ?, email = ?, telepon = ? WHERE id = ?");
        $update_query->bind_param("sssi", $nama_user, $email, $telepon, $user_id);
        
        if ($update_query->execute()) {
            $success = "Profil berhasil diperbarui!";
            // Update session data
            $_SESSION['nama_user'] = $nama_user;
            // Refresh user data
            $user_data['nama_user'] = $nama_user;
            $user_data['email'] = $email;
            $user_data['telepon'] = $telepon;
        } else {
            $error = "Gagal memperbarui profil: " . $conn->error;
        }
        $update_query->close();
    }
    
    if (isset($_POST['change_theme'])) {
        // Handle theme change
        $theme = $conn->real_escape_string($_POST['theme']);
        setcookie('theme', $theme, time() + (86400 * 30), "/"); // 30 days
        $_SESSION['current_theme'] = $theme;
        $theme_success = "Tema berhasil diubah!";
    }
    
    if (isset($_POST['change_password'])) {
        // Handle password change
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verifikasi password lama
        if (password_verify($current_password, $user_data['password_hash'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $password_query = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $password_query->bind_param("si", $new_password_hash, $user_id);
                    
                    if ($password_query->execute()) {
                        $success = "Password berhasil diubah!";
                    } else {
                        $error = "Gagal mengubah password: " . $conn->error;
                    }
                    $password_query->close();
                } else {
                    $error = "Password baru minimal 8 karakter!";
                }
            } else {
                $error = "Password baru tidak cocok!";
            }
        } else {
            $error = "Password saat ini salah!";
        }
    }
}

// Get current theme
$current_theme = $_SESSION['current_theme'] ?? $_COOKIE['theme'] ?? 'light';

// Format user data untuk tampilan
$user_profile = [
    'nama_user' => $user_data['nama_user'],
    'email' => $user_data['email'],
    'telepon' => $user_data['telepon'] ?? '-',
    'username' => $user_data['username'],
    'peran' => $user_data['peran'],
    'login_terakhir' => $user_data['login_terakhir'] ?? date('Y-m-d H:i:s'),
    'tanggal_gabung' => $user_data['tanggal_gabung']
];

// Fungsi untuk mendapatkan informasi sistem
function getSystemInfo($conn) {
    $info = [];
    
    // PHP version
    $info['php_version'] = phpversion();
    
    // Database info
    $info['database'] = 'MySQL';
    $info['database_version'] = $conn->server_info;
    
    // Server software
    $info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    
    // Memory usage
    $info['memory_usage'] = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
    $info['memory_limit'] = ini_get('memory_limit');
    
    // Uptime (estimasi)
    $info['server_time'] = date('d/m/Y H:i:s');
    
    return $info;
}

$system_info = getSystemInfo($conn);
?>

<!DOCTYPE html>
<html lang="id" data-theme="<?= $current_theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem - Pemetaan Blankspot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <link rel="stylesheet" href="css/pengaturan.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="page-header fade-in">
                <div class="header-title">
                    <h1><i class="fas fa-cog"></i> Pengaturan Sistem</h1>
                    <p>Kelola pengaturan akun dan preferensi sistem</p>
                </div>
                <div class="header-actions">
                    <button onclick="location.reload()" class="btn btn-light">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </div>
            </div>

            <!-- Settings Grid -->
            <div class="settings-grid fade-in">
                <!-- Profile Settings -->
                <div class="setting-card">
                    <div class="setting-header">
                        <div class="setting-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h2 class="setting-title">Profil Pengguna</h2>
                            <p class="setting-description">Kelola informasi profil akun Anda</p>
                        </div>
                    </div>

                    <?php if ($success && isset($_POST['update_profile'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= $success ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error && isset($_POST['update_profile'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Nama User</label>
                            <input type="text" class="form-control" name="nama_user" 
                                   value="<?= htmlspecialchars($user_profile['nama_user']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?= htmlspecialchars($user_profile['email']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="tel" class="form-control" name="telepon" 
                                   value="<?= htmlspecialchars($user_profile['telepon']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user_profile['username']) ?>" disabled>
                            <div class="form-text">Username tidak dapat diubah</div>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </form>
                </div>

                <!-- User Info Card -->
                <div class="setting-card">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h2 class="user-name"><?= htmlspecialchars($user_profile['nama_user']) ?></h2>
                        <div class="user-role"><?= htmlspecialchars($user_profile['peran']) ?></div>
                        
                        <div class="user-stats">
                            <div class="user-stat">
                                <span class="stat-value"><?= date('d M Y', strtotime($user_profile['tanggal_gabung'])) ?></span>
                                <span class="stat-label">Bergabung</span>
                            </div>
                            <div class="user-stat">
                                <span class="stat-value"><?= $user_profile['login_terakhir'] ? date('H:i', strtotime($user_profile['login_terakhir'])) : 'Belum' ?></span>
                                <span class="stat-label">Login Terakhir</span>
                            </div>
                        </div>
                    </div>

                    <div class="system-info">
                        <h4 style="margin-bottom: 15px; color: var(--primary);">Informasi Sistem</h4>
                        <div class="info-item">
                            <span class="info-label">Versi Aplikasi</span>
                            <span class="info-value">v2.1.0</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">PHP Version</span>
                            <span class="info-value"><?= $system_info['php_version'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Database</span>
                            <span class="info-value"><?= $system_info['database'] ?> (<?= $system_info['database_version'] ?>)</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Server Software</span>
                            <span class="info-value"><?= $system_info['server_software'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Memory Usage</span>
                            <span class="info-value"><?= $system_info['memory_usage'] ?> / <?= $system_info['memory_limit'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Server Time</span>
                            <span class="info-value"><?= $system_info['server_time'] ?></span>
                        </div>
                    </div>
                </div>
                <!-- Security Settings -->
                <div class="setting-card">
                    <div class="setting-header">
                        <div class="setting-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <h2 class="setting-title">Keamanan</h2>
                            <p class="setting-description">Kelola keamanan akun dan kata sandi</p>
                        </div>
                    </div>

                    <?php if ($success && isset($_POST['change_password'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= $success ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error && isset($_POST['change_password'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Password Saat Ini</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Password Baru</label>
                            <input type="password" class="form-control" name="new_password" required>
                            <div class="form-text">Minimal 8 karakter dengan kombinasi huruf dan angka</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme selection
        function selectTheme(theme) {
            // Remove active class from all themes
            document.querySelectorAll('.theme-option').forEach(option => {
                option.classList.remove('active');
            });
            
            // Add active class to selected theme
            event.currentTarget.classList.add('active');
            
            // Check the corresponding radio button
            const radio = event.currentTarget.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Preview theme
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let valid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = '#dc3545';
                        } else {
                            field.style.borderColor = '';
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Harap lengkapi semua field yang wajib diisi!');
                    }
                });
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>