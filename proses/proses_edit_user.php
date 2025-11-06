<?php
session_start();
include "../config/db.php";

// Cek apakah user sudah login dan memiliki akses Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] !== 'Super Admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $user_id = intval($_POST['id']);
    $username = trim($_POST['username']);
    $nama_user = trim($_POST['nama_user']);
    $email = trim($_POST['email']); // Tambahkan email
    $telepon = trim($_POST['telepon']); // Tambahkan telepon
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $peran = $_POST['peran'];
    $status_aktif = intval($_POST['status_aktif']);
    
    // Validasi input
    $errors = [];
    
    // Cek username tidak kosong
    if (empty($username)) {
        $errors[] = "Username harus diisi!";
    }
    
    // Cek nama lengkap tidak kosong
    if (empty($nama_user)) {
        $errors[] = "Nama lengkap harus diisi!";
    }
    
    // Cek email tidak kosong dan valid
    if (empty($email)) {
        $errors[] = "Email harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid!";
    }
    
    // Cek password jika diisi
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "Password minimal 6 karakter!";
    }
    
    // Cek konfirmasi password jika password diisi
    if (!empty($password) && $password !== $confirm_password) {
        $errors[] = "Password dan konfirmasi password tidak sama!";
    }
    
    // Cek peran valid
    $allowed_roles = ['Super Admin', 'Admin', 'User'];
    if (!in_array($peran, $allowed_roles)) {
        $errors[] = "Peran tidak valid!";
    }
    
    // Validasi karakter username (hanya huruf, angka, underscore)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username hanya boleh mengandung huruf, angka, dan underscore!";
    }
    
    // Cek apakah user mencoba mengedit dirinya sendiri
    if ($user_id == $_SESSION['user_id']) {
        $errors[] = "Tidak dapat mengedit akun sendiri!";
    }
    
    // Jika tidak ada error, proses update user
    if (empty($errors)) {
        try {
            // Cek apakah username atau email sudah digunakan oleh user lain
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check_stmt->bind_param("ssi", $username, $email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $errors[] = "Username atau email sudah digunakan! Silakan gunakan username/email lain.";
            } else {
                // Update user
                if (!empty($password)) {
                    // Jika password diisi, update dengan password baru
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET username = ?, nama_user = ?, email = ?, telepon = ?, password = ?, peran = ?, status_aktif = ?, diperbarui_pada = NOW() WHERE id = ?");
                    $update_stmt->bind_param("ssssssii", $username, $nama_user, $email, $telepon, $hashed_password, $peran, $status_aktif, $user_id);
                } else {
                    // Jika password tidak diisi, update tanpa mengubah password
                    $update_stmt = $conn->prepare("UPDATE users SET username = ?, nama_user = ?, email = ?, telepon = ?, peran = ?, status_aktif = ?, diperbarui_pada = NOW() WHERE id = ?");
                    $update_stmt->bind_param("sssssii", $username, $nama_user, $email, $telepon, $peran, $status_aktif, $user_id);
                }
                
                if ($update_stmt->execute()) {
                    $_SESSION['message'] = "User <strong>$username</strong> berhasil diupdate!";
                    $_SESSION['message_type'] = 'success';
                    header('Location: ../manage_users.php');
                    exit();
                } else {
                    $errors[] = "Gagal mengupdate user! Error: " . $update_stmt->error;
                }
                
                if (isset($update_stmt)) {
                    $update_stmt->close();
                }
            }
            
            $check_stmt->close();
            
        } catch (Exception $e) {
            $errors[] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
    
    // Jika ada error, simpan ke session untuk ditampilkan
    if (!empty($errors)) {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = 'error';
        // Simpan input lama untuk pre-fill form
        $_SESSION['old_input'] = [
            'username' => $username,
            'nama_user' => $nama_user,
            'email' => $email,
            'telepon' => $telepon,
            'peran' => $peran,
            'status_aktif' => $status_aktif
        ];
        header("Location: edit_user.php?id=$user_id");
        exit();
    }
} else {
    // Jika bukan POST request, redirect ke manage_users
    header('Location: ../manage_users.php');
    exit();
}
?>