<?php
session_start();
include "../config/db.php";

// Cek apakah user sudah login dan memiliki akses Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['peran'] !== 'Super Admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $username = trim($_POST['username']);
    $nama_user = trim($_POST['nama_user']);
    $email = trim($_POST['email']);
    $telepon = trim($_POST['telepon']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $peran = $_POST['peran'];
    $status_aktif = intval($_POST['status_aktif']);
    $dibuat_oleh = $_SESSION['user_id'];
    
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
    
    // Cek password tidak kosong dan minimal 6 karakter
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password harus diisi dan minimal 6 karakter!";
    }
    
    // Cek konfirmasi password
    if ($password !== $confirm_password) {
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
    
    // Jika tidak ada error, proses tambah user
    if (empty($errors)) {
        try {
            // Cek apakah username atau email sudah ada
            $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
            $check_stmt = $conn->prepare($check_query);
            
            if (!$check_stmt) {
                throw new Exception("Error preparing check query: " . $conn->error);
            }
            
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $errors[] = "Username atau email sudah digunakan! Silakan gunakan username/email lain.";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // PERBAIKAN: Gunakan kolom 'password' bukan 'password_hash'
                $insert_query = "INSERT INTO users (username, password, nama_user, email, telepon, peran, status_aktif, dibuat_oleh, dibuat_pada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $insert_stmt = $conn->prepare($insert_query);
                
                if (!$insert_stmt) {
                    throw new Exception("Error preparing insert query: " . $conn->error);
                }
                
                $insert_stmt->bind_param("ssssssii", $username, $hashed_password, $nama_user, $email, $telepon, $peran, $status_aktif, $dibuat_oleh);
                
                if ($insert_stmt->execute()) {
                    $_SESSION['message'] = "User <strong>$username</strong> berhasil ditambahkan!";
                    $_SESSION['message_type'] = 'success';
                    header('Location: ../manage_users.php');
                    exit();
                } else {
                    $errors[] = "Gagal menambahkan user! Error: " . $insert_stmt->error;
                }
                
                $insert_stmt->close();
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
        header('Location: ../manage_users.php');
        exit();
    }
} else {
    // Jika bukan POST request, redirect ke manage_users
    header('Location: ../manage_users.php');
    exit();
}
?>