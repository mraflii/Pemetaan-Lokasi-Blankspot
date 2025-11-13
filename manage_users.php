<?php
session_start();
include "config/db.php";

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Cek apakah user memiliki akses Super Admin atau Admin
$current_user_role = $_SESSION['peran'] ?? '';
if ($current_user_role !== 'Super Admin' && $current_user_role !== 'Admin') {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini!";
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

// Hapus old input jika tidak ada error
if (isset($_SESSION['message_type']) && $_SESSION['message_type'] !== 'error' && isset($_SESSION['old_input'])) {
    unset($_SESSION['old_input']);
}

// Pesan sukses/error
$message = '';
$message_type = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Ambil data users dengan error handling
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['peran'];

// Query berdasarkan role
if ($current_user_role === 'Super Admin') {
    // Super Admin bisa lihat semua user
    $users_query = "SELECT * FROM users ORDER BY dibuat_pada DESC";
} else {
    // Admin hanya bisa lihat user biasa dan admin lain (bukan Super Admin)
    $users_query = "SELECT * FROM users WHERE peran != 'Super Admin' ORDER BY dibuat_pada DESC";
}

$users_result = $conn->query($users_query);

// Handle query error
if ($users_result === false) {
    $error_message = "Error dalam mengambil data users: " . $conn->error;
    $users = []; // Set empty array untuk menghindari error
} else {
    $users = [];
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
    $users_result->free(); // Free result set
}

// Hitung statistik dengan error handling
if ($current_user_role === 'Super Admin') {
    $stats_query = "SELECT 
        COUNT(*) as total_users,
        SUM(status_aktif = 1) as active_users,
        SUM(peran = 'Super Admin') as super_admins,
        SUM(peran = 'Admin') as admins,
        SUM(peran = 'User') as regular_users
        FROM users";
} else {
    $stats_query = "SELECT 
        COUNT(*) as total_users,
        SUM(status_aktif = 1) as active_users,
        0 as super_admins,
        SUM(peran = 'Admin') as admins,
        SUM(peran = 'User') as regular_users
        FROM users WHERE peran != 'Super Admin'";
}

$stats_result = $conn->query($stats_query);

if ($stats_result === false) {
    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'super_admins' => 0,
        'admins' => 0,
        'regular_users' => 0
    ];
} else {
    $stats = $stats_result->fetch_assoc();
    $stats_result->free();
}

// Ambil data user untuk edit jika ada parameter
$edit_user = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    
    // Cek apakah user boleh mengedit user ini
    $allowed_to_edit = false;
    
    if ($current_user_role === 'Super Admin') {
        // Super Admin bisa edit semua user
        $allowed_to_edit = true;
    } else {
        // Admin hanya bisa edit user biasa dan admin lain (bukan Super Admin)
        $check_stmt = $conn->prepare("SELECT peran FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $edit_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $target_user = $check_result->fetch_assoc();
            if ($target_user['peran'] !== 'Super Admin') {
                $allowed_to_edit = true;
            }
        }
        $check_stmt->close();
    }
    
    if ($allowed_to_edit) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $edit_user = $result->fetch_assoc();
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "Anda tidak memiliki akses untuk mengedit user ini!";
        $_SESSION['message_type'] = 'error';
        header('Location: manage_users.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Blankspot Maps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/kelola_user.css">
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
                <h1><i class="fas fa-users-cog"></i> Kelola Pengguna</h1>
                <p>
                    <?php if ($current_user_role === 'Super Admin'): ?>
                        Manajemen semua akun pengguna sistem Blankspot Maps
                    <?php else: ?>
                        Manajemen akun User dan Admin sistem Blankspot Maps
                    <?php endif; ?>
                </p>
            </div>
            <div class="header-actions">
                <button onclick="location.reload()" class="btn btn-light">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-user-plus"></i> 
                    <?php if ($current_user_role === 'Super Admin'): ?>
                        Tambah User
                    <?php else: ?>
                        Tambah User/Admin
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Notification -->
        <?php if ($message): ?>
            <div class="notification <?= $message_type ?> fade-in">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="content-card fade-in">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Peran</th>
                            <th>Status</th>
                            <th>Login Terakhir</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($user['username']) ?></strong>
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge info"><i class="fas fa-user"></i> Anda</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['nama_user']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge 
                                            <?= $user['peran'] === 'Super Admin' ? 'warning' : 
                                               ($user['peran'] === 'Admin' ? 'info' : 'success') ?>">
                                            <?= $user['peran'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $user['status_aktif'] ? 'success' : 'danger' ?>">
                                            <?= $user['status_aktif'] ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $user['login_terakhir'] ? 
                                            date('d/m/Y H:i', strtotime($user['login_terakhir'])) : 
                                            'Belum pernah' ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($user['dibuat_pada'])) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                <!-- Untuk akun sendiri -->
                                                <button class="btn btn-light btn-sm" onclick="openEditModal(<?= $user['id'] ?>)" title="Edit Akun Sendiri">
                                                    <i class="fas fa-edit icon-edit"></i>
                                                </button>
                                                <span class="badge secondary" title="Tidak dapat menghapus akun sendiri">
                                                    <i class="fas fa-trash"></i>
                                                </span>
                                                <span class="badge secondary" title="Akun sudah aktif">
                                                    <i class="fas fa-check-circle"></i>
                                                </span>
                                            <?php else: ?>
                                                <!-- Untuk user lain -->
                                                <?php if ($current_user_role === 'Super Admin' || ($current_user_role === 'Admin' && $user['peran'] !== 'Super Admin')): ?>
                                                    <!-- Tombol Edit -->
                                                    <button class="btn btn-light btn-sm" onclick="openEditModal(<?= $user['id'] ?>)" title="Edit User">
                                                        <i class="fas fa-edit icon-edit"></i>
                                                    </button>
                                                    
                                                    <!-- Tombol Hapus -->
                                                    <?php if ($current_user_role === 'Super Admin' || ($current_user_role === 'Admin' && $user['peran'] === 'User')): ?>
                                                        <a href="proses/proses_hapus_user.php?id=<?= $user['id'] ?>" 
                                                           class="btn btn-light btn-sm"
                                                           onclick="return confirm('Hapus user <?= htmlspecialchars($user['username']) ?>? Tindakan ini tidak dapat dibatalkan!')"
                                                           title="Hapus User">
                                                            <i class="fas fa-trash icon-delete"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge secondary" title="Tidak dapat menghapus Admin lain">
                                                            <i class="fas fa-trash"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Tombol Aktif/Nonaktif -->
                                                    <?php if ($current_user_role === 'Super Admin' || ($current_user_role === 'Admin' && $user['peran'] === 'User')): ?>
                                                        <?php if ($user['status_aktif']): ?>
                                                            <a href="proses/proses_aktifkan_user.php?id=<?= $user['id'] ?>&action=deactivate" 
                                                               class="btn btn-light btn-sm"
                                                               onclick="return confirm('Nonaktifkan user <?= htmlspecialchars($user['username']) ?>?')"
                                                               title="Nonaktifkan User">
                                                                <i class="fas fa-pause icon-deactivate"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="proses/proses_aktifkan_user.php?id=<?= $user['id'] ?>&action=activate" 
                                                               class="btn btn-light btn-sm"
                                                               onclick="return confirm('Aktifkan user <?= htmlspecialchars($user['username']) ?>?')"
                                                               title="Aktifkan User">
                                                                <i class="fas fa-play icon-activate"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge secondary" title="Tidak dapat mengubah status Admin lain">
                                                            <i class="fas fa-ban"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <!-- Admin tidak bisa akses Super Admin -->
                                                    <span class="badge secondary" title="Tidak memiliki akses">
                                                        <i class="fas fa-lock"></i>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-users" style="font-size: 3rem; color: #6c757d; margin-bottom: 15px;"></i>
                                    <h3 style="color: #6c757d; margin-bottom: 10px;">Belum ada pengguna</h3>
                                    <p style="color: #6c757d;">Klik tombol "Tambah User" untuk menambahkan pengguna baru</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Statistics -->
        <div class="content-card fade-in">
            <h3 style="margin-bottom: 20px; color: var(--dark);">
                <i class="fas fa-chart-bar"></i> Statistik Pengguna
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0; font-size: 2rem;"><?= $stats['total_users'] ?></h4>
                    <p style="margin: 0; opacity: 0.9;">Total Pengguna</p>
                </div>
                <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0; font-size: 2rem;"><?= $stats['active_users'] ?></h4>
                    <p style="margin: 0; opacity: 0.9;">Pengguna Aktif</p>
                </div>
                <?php if ($current_user_role === 'Super Admin'): ?>
                    <div style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                        <h4 style="margin: 0 0 10px 0; font-size: 2rem;"><?= $stats['super_admins'] ?></h4>
                        <p style="margin: 0; opacity: 0.9;">Super Admin</p>
                    </div>
                <?php endif; ?>
                <div style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0; font-size: 2rem;"><?= $stats['admins'] ?></h4>
                    <p style="margin: 0; opacity: 0.9;">Admin</p>
                </div>
                <div style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                    <h4 style="margin: 0 0 10px 0; font-size: 2rem;"><?= $stats['regular_users'] ?></h4>
                    <p style="margin: 0; opacity: 0.9;">User Biasa</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah User -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> 
                <?php if ($current_user_role === 'Super Admin'): ?>
                    Tambah User Baru
                <?php else: ?>
                    Tambah User/Admin Baru
                <?php endif; ?>
            </h2>
            <button class="close" onclick="closeAddModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addUserForm" action="proses/proses_tambah_user.php" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="Masukkan username" pattern="[a-zA-Z0-9_]+"
                               value="<?= $_SESSION['old_input']['username'] ?? '' ?>">
                        <span class="form-help">Hanya huruf, angka, dan underscore</span>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required 
                               placeholder="Masukkan email"
                               value="<?= $_SESSION['old_input']['email'] ?? '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="nama_user">Nama Lengkap *</label>
                    <input type="text" id="nama_user" name="nama_user" required 
                           placeholder="Masukkan nama lengkap"
                           value="<?= $_SESSION['old_input']['nama_user'] ?? '' ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="telepon">Telepon</label>
                        <input type="text" id="telepon" name="telepon" 
                               placeholder="Masukkan nomor telepon"
                               value="<?= $_SESSION['old_input']['telepon'] ?? '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="peran">Peran *</label>
                        <select id="peran" name="peran" required>
                            <option value="">Pilih Peran</option>
                            <option value="User" <?= ($_SESSION['old_input']['peran'] ?? '') === 'User' ? 'selected' : '' ?>>User</option>
                            <?php if ($current_user_role === 'Super Admin'): ?>
                                <option value="Admin" <?= ($_SESSION['old_input']['peran'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="Super Admin" <?= ($_SESSION['old_input']['peran'] ?? '') === 'Super Admin' ? 'selected' : '' ?>>Super Admin</option>
                            <?php else: ?>
                                <option value="Admin" <?= ($_SESSION['old_input']['peran'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Masukkan password (min. 6 karakter)" minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Konfirmasi password" minlength="6">
                    </div>
                </div>

                <div class="form-group">
                    <label for="status_aktif">Status Akun</label>
                    <select id="status_aktif" name="status_aktif">
                        <option value="1" <?= ($_SESSION['old_input']['status_aktif'] ?? 1) == 1 ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= ($_SESSION['old_input']['status_aktif'] ?? 1) == 0 ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-light" onclick="closeAddModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit User</h2>
            <button class="close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editUserForm" action="proses/proses_edit_user.php" method="POST">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_username">Username *</label>
                        <input type="text" id="edit_username" name="username" required 
                               pattern="[a-zA-Z0-9_]+">
                        <span class="form-help">Hanya huruf, angka, dan underscore</span>
                    </div>

                    <div class="form-group">
                        <label for="edit_email">Email *</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_nama_user">Nama Lengkap *</label>
                    <input type="text" id="edit_nama_user" name="nama_user" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_telepon">Telepon</label>
                        <input type="text" id="edit_telepon" name="telepon">
                    </div>

                    <div class="form-group">
                        <label for="edit_peran">Peran *</label>
                        <select id="edit_peran" name="peran" required>
                            <?php if ($current_user_role === 'Super Admin'): ?>
                                <option value="User">User</option>
                                <option value="Admin">Admin</option>
                                <option value="Super Admin">Super Admin</option>
                            <?php else: ?>
                                <option value="User">User</option>
                                <option value="Admin">Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_password">Password</label>
                        <input type="password" id="edit_password" name="password" 
                               placeholder="Kosongkan jika tidak ingin mengubah" minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="edit_confirm_password">Konfirmasi Password</label>
                        <input type="password" id="edit_confirm_password" name="confirm_password" 
                               placeholder="Konfirmasi password" minlength="6">
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_status_aktif">Status</label>
                    <select id="edit_status_aktif" name="status_aktif">
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-light" onclick="closeEditModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal Functions
function openAddModal() {
    document.getElementById('addUserModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeAddModal() {
    document.getElementById('addUserModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function openEditModal(userId) {
    // Redirect dengan parameter edit_id
    window.location.href = 'manage_users.php?edit_id=' + userId;
}

function closeEditModal() {
    // Redirect tanpa parameter edit_id
    window.location.href = 'manage_users.php';
}

// Auto open edit modal jika ada parameter edit_id
<?php if ($edit_user): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Isi form edit dengan data user
    document.getElementById('edit_id').value = '<?= $edit_user['id'] ?>';
    document.getElementById('edit_username').value = '<?= $edit_user['username'] ?>';
    document.getElementById('edit_email').value = '<?= $edit_user['email'] ?>';
    document.getElementById('edit_nama_user').value = '<?= $edit_user['nama_user'] ?>';
    document.getElementById('edit_telepon').value = '<?= $edit_user['telepon'] ?? '' ?>';
    document.getElementById('edit_peran').value = '<?= $edit_user['peran'] ?>';
    document.getElementById('edit_status_aktif').value = '<?= $edit_user['status_aktif'] ?>';
    
    // Tampilkan modal edit
    document.getElementById('editUserModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
});
<?php endif; ?>

// Close modal when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('addUserModal');
    const editModal = document.getElementById('editUserModal');
    
    if (event.target === addModal) {
        closeAddModal();
    }
    if (event.target === editModal) {
        closeEditModal();
    }
}

// Form validation
document.getElementById('addUserForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Password harus minimal 6 karakter!');
        document.getElementById('password').focus();
        return false;
    }
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Password dan konfirmasi password tidak sama!');
        document.getElementById('confirm_password').focus();
        return false;
    }
});

document.getElementById('editUserForm').addEventListener('submit', function(e) {
    const password = document.getElementById('edit_password').value;
    const confirmPassword = document.getElementById('edit_confirm_password').value;
    
    if (password !== '' && password.length < 6) {
        e.preventDefault();
        alert('Password harus minimal 6 karakter!');
        document.getElementById('edit_password').focus();
        return false;
    }
    
    if (password !== '' && password !== confirmPassword) {
        e.preventDefault();
        alert('Password dan konfirmasi password tidak sama!');
        document.getElementById('edit_confirm_password').focus();
        return false;
    }
});

// Auto-hide notification
setTimeout(() => {
    const notification = document.querySelector('.notification');
    if (notification) {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.5s ease';
        setTimeout(() => notification.remove(), 500);
    }
}, 5000);
</script>
</body>
</html>