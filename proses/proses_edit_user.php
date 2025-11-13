<?php
session_start();
include "../config/db.php";

// Cek apakah user sudah login dan memiliki akses Super Admin atau Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['peran'] !== 'Super Admin' && $_SESSION['peran'] !== 'Admin')) {
    $_SESSION['message'] = "Anda tidak memiliki akses untuk mengedit user!";
    $_SESSION['message_type'] = 'error';
    header('Location: ../manage_users.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $user_id = intval($_POST['id']);
    $username = trim($_POST['username']);
    $nama_user = trim($_POST['nama_user']);
    $email = trim($_POST['email']);
    $telepon = trim($_POST['telepon']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $peran = $_POST['peran'];
    $status_aktif = intval($_POST['status_aktif']);
    
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['peran'];
    
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
    
    // Validasi karakter username (hanya huruf, angka, underscore)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username hanya boleh mengandung huruf, angka, dan underscore!";
    }
    
    // Cek peran valid berdasarkan hak akses user yang login
    $allowed_roles = ['User']; // Default untuk Admin
    
    if ($current_user_role === 'Super Admin') {
        $allowed_roles = ['Super Admin', 'Admin', 'User'];
    } elseif ($current_user_role === 'Admin') {
        $allowed_roles = ['Admin', 'User'];
    }
    
    if (!in_array($peran, $allowed_roles)) {
        $errors[] = "Peran tidak valid untuk level akses Anda!";
    }
    
    // Jika tidak ada error, proses update user
    if (empty($errors)) {
        try {
            // Cek apakah user yang akan diedit ada dan ambil data lama
            $check_stmt = $conn->prepare("SELECT username, peran FROM users WHERE id = ?");
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $errors[] = "User tidak ditemukan!";
            } else {
                $old_user_data = $check_result->fetch_assoc();
                $old_role = $old_user_data['peran'];
                
                // Validasi hak akses berdasarkan role
                if ($current_user_role === 'Admin') {
                    // Admin tidak bisa mengedit Super Admin
                    if ($old_role === 'Super Admin') {
                        $errors[] = "Admin tidak dapat mengedit Super Admin!";
                    }
                    
                    // Admin tidak bisa mengubah role menjadi Super Admin
                    if ($peran === 'Super Admin') {
                        $errors[] = "Admin tidak dapat mengubah role menjadi Super Admin!";
                    }
                    
                    // Admin tidak bisa mengubah role Admin lain (hanya bisa ubah User ke Admin atau sebaliknya)
                    if ($old_role === 'Admin' && $peran === 'User' && $user_id != $current_user_id) {
                        // Admin bisa mengubah Admin lain menjadi User
                    } elseif ($old_role === 'User' && $peran === 'Admin') {
                        // Admin bisa mengubah User menjadi Admin
                    } elseif ($old_role === 'Admin' && $peran === 'Admin' && $user_id != $current_user_id) {
                        $errors[] = "Admin tidak dapat mengubah role Admin lain!";
                    }
                }
                
                // Cek apakah username atau email sudah digunakan oleh user lain
                $check_duplicate_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $check_duplicate_stmt->bind_param("ssi", $username, $email, $user_id);
                $check_duplicate_stmt->execute();
                $check_duplicate_result = $check_duplicate_stmt->get_result();
                
                if ($check_duplicate_result->num_rows > 0) {
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
                
                $check_duplicate_stmt->close();
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
        header("Location: ../manage_users.php?edit_id=$user_id");
        exit();
    }
} else {
    // Jika bukan POST request, redirect ke manage_users
    header('Location: ../manage_users.php');
    exit();
}
?>